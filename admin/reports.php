<?php
// ระบบรายงานผล: กรอง / ค้นหา / กราฟ / พิมพ์ / ส่งออก CSV (เปิดใน Excel ได้)
require __DIR__ . '/../includes/init.php';
$admin = require_admin();

const REPORTS = [
    'green' => 'รายงานผลกระทบ Green Digital',
    'footprint' => 'รายงานคาร์บอนฟุตพริ้นท์รายปี',
    'recycle' => 'รายงานการรีไซเคิล E-Waste',
    'members' => 'รายงานข้อมูลสมาชิก',
    'contents' => 'รายงานเนื้อหา',
];
$type = isset(REPORTS[get('type')]) ? get('type') : 'green';
$from = parse_datetime(get('from'));
$to = get('to') !== '' && strtotime(get('to')) ? date('Y-m-d 23:59:59', strtotime(get('to'))) : null;
$kw = get('q');
$f = get('f');

// เงื่อนไขช่วงวันที่ + คำค้น ที่ใช้ร่วมกันทุกรายงาน
$where = ['1 = 1'];
$args = [];
$range = function ($col) use (&$where, &$args, $from, $to) {
    if ($from) {
        $where[] = "$col >= ?";
        $args[] = $from;
    }
    if ($to) {
        $where[] = "$col <= ?";
        $args[] = $to;
    }
};
$search = function ($cols) use (&$where, &$args, $kw) {
    if ($kw !== '') {
        $where[] = '(' . implode(' OR ', array_map(function ($c) { return "$c LIKE ?"; }, $cols)) . ')';
        foreach ($cols as $c) {
            $args[] = '%' . $kw . '%';
        }
    }
};
$monthly = function ($list, $dateKey, $valueKey = null) {
    $out = [];
    foreach ($list as $r) {
        $m = substr($r[$dateKey], 0, 7);
        $out[$m] = (isset($out[$m]) ? $out[$m] : 0) + ($valueKey ? $r[$valueKey] : 1);
    }
    ksort($out);
    return $out;
};

// แต่ละรายงานกำหนด: $cols [หัวคอลัมน์, ชนิด], $data, $summary [ป้าย, ค่า], $chart [ชนิด, ป้าย, ค่า, คำอธิบาย], $options ของตัวกรอง $f
$useDates = true;
$filterLabel = 'สถานะ';
if ($type === 'members') {
    $options = ['active' => 'ใช้งานได้', 'suspended' => 'ระงับสิทธิ์'];
    $where[] = "u.role = 'user'";
    $range('u.created_at');
    $search(['u.name', 'u.email', 'u.phone']);
    if (isset($options[$f])) {
        $where[] = 'u.status = ?';
        $args[] = $f;
    }
    $list = rows('SELECT u.*, (SELECT COALESCE(SUM(co2_saved_kg), 0) FROM green_activities a WHERE a.user_id = u.id) AS co2,
        (SELECT total_kg FROM carbon_footprints c WHERE c.user_id = u.id ORDER BY year DESC LIMIT 1) AS footprint
        FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.created_at DESC', $args);
    $cols = [['ชื่อ-นามสกุล', 'text'], ['อีเมล', 'text'], ['เบอร์โทร', 'text'], ['สถานะ', 'status'], ['Green Point', 'num'], ['ฟุตพริ้นท์ล่าสุด (kg)', 'num'], ['ลดได้ (kgCO₂e)', 'num'], ['วันที่สมัคร', 'date']];
    $data = array_map(function ($r) { return [$r['name'], $r['email'], $r['phone'], $r['status'], $r['green_points'], $r['footprint'], $r['co2'], $r['created_at']]; }, $list);
    $summary = [['สมาชิกในรายงาน', count($list) . ' คน'], ['ใช้งานได้', count(array_filter($list, function ($r) { return $r['status'] === 'active'; })) . ' คน'],
        ['คำนวณฟุตพริ้นท์แล้ว', count(array_filter($list, function ($r) { return $r['footprint'] !== null; })) . ' คน'], ['คาร์บอนที่ลดได้รวม', kg(array_sum(array_column($list, 'co2')))]];
    $m = $monthly($list, 'created_at');
    $chart = ['bar', array_map('month_label', array_keys($m)), $m, 'สมาชิกใหม่รายเดือน (คน)'];
} elseif ($type === 'contents') {
    $filterLabel = 'ประเภท';
    $options = CONTENT_TYPES;
    $range('c.publish_at');
    $search(['c.title', 'c.body']);
    if (isset($options[$f])) {
        $where[] = 'c.type = ?';
        $args[] = $f;
    }
    $list = rows('SELECT c.*, u.name AS author FROM contents c JOIN users u ON u.id = c.created_by WHERE ' . implode(' AND ', $where) . ' ORDER BY c.publish_at DESC', $args);
    $cols = [['หัวข้อ', 'text'], ['ประเภท', 'text'], ['เริ่มแสดงผล', 'datetime'], ['สิ้นสุดการแสดงผล', 'datetime'], ['สถานะ', 'status'], ['ผู้สร้าง', 'text']];
    $data = array_map(function ($r) { return [$r['title'], CONTENT_TYPES[$r['type']], $r['publish_at'], $r['expire_at'], content_status($r), $r['author']]; }, $list);
    $byStatus = array_count_values(array_column($data, 4));
    $summary = [['เนื้อหาในรายงาน', count($list) . ' รายการ']];
    foreach (['live', 'scheduled', 'expired', 'off'] as $st) {
        $summary[] = [STATUS_LABELS[$st][0], (isset($byStatus[$st]) ? $byStatus[$st] : 0) . ' รายการ'];
    }
    $chart = ['doughnut', array_map(function ($st) { return STATUS_LABELS[$st][0]; }, array_keys($byStatus)), $byStatus, ''];
} elseif ($type === 'footprint') {
    $useDates = false;
    $filterLabel = 'ปี';
    $options = [];
    foreach (rows('SELECT DISTINCT year FROM carbon_footprints ORDER BY year DESC') as $y) {
        $options[$y['year']] = 'พ.ศ. ' . ($y['year'] + 543);
    }
    $search(['u.name', 'u.email']);
    if (isset($options[$f])) {
        $where[] = 'c.year = ?';
        $args[] = (int) $f;
    }
    $sums = '';
    foreach (FACTOR_GROUPS as $g => $info) {
        $sums .= ", COALESCE(SUM(IF(ef.category = '$g', i.co2e_kg, 0)), 0) AS g_$g";
    }
    $list = rows("SELECT c.id, c.year, c.total_kg, u.name $sums,
        (SELECT COALESCE(SUM(a.co2_saved_kg), 0) FROM green_activities a WHERE a.user_id = c.user_id AND YEAR(a.created_at) = c.year) AS saved
        FROM carbon_footprints c JOIN users u ON u.id = c.user_id LEFT JOIN carbon_footprint_items i ON i.footprint_id = c.id LEFT JOIN emission_factors ef ON ef.id = i.factor_id
        WHERE " . implode(' AND ', $where) . ' GROUP BY c.id, c.year, c.total_kg, u.name, c.user_id ORDER BY c.year DESC, c.total_kg DESC', $args);
    $cols = [['สมาชิก', 'text'], ['ปี (พ.ศ.)', 'text']];
    foreach (FACTOR_GROUPS as $info) {
        $cols[] = [$info[0], 'num'];
    }
    array_push($cols, ['รวม (kgCO₂e)', 'num'], ['ลดได้ในปี', 'num'], ['คงเหลือสุทธิ', 'num'], ['% สู่ Net Zero', 'num']);
    $data = [];
    $avgGroup = [];
    foreach ($list as $r) {
        $line = [$r['name'], $r['year'] + 543];
        foreach (FACTOR_GROUPS as $g => $info) {
            $line[] = $r["g_$g"];
            $avgGroup[$info[0]] = (isset($avgGroup[$info[0]]) ? $avgGroup[$info[0]] : 0) + $r["g_$g"] / count($list);
        }
        array_push($line, $r['total_kg'], $r['saved'], max(0, $r['total_kg'] - $r['saved']), $r['total_kg'] > 0 ? min(100, $r['saved'] / $r['total_kg'] * 100) : 0);
        $data[] = $line;
    }
    $total = array_sum(array_column($list, 'total_kg'));
    $saved = array_sum(array_column($list, 'saved'));
    $summary = [['ผู้คำนวณในรายงาน', count($list) . ' รายการ'], ['ฟุตพริ้นท์เฉลี่ยต่อคน', kg($list ? $total / count($list) : 0)], ['ฟุตพริ้นท์รวม', num($total / 1000) . ' ตัน CO₂e'], ['ลดได้รวมจากกิจกรรม', kg($saved)]];
    $chart = ['doughnut', array_keys($avgGroup), $avgGroup, ''];
} elseif ($type === 'recycle') {
    $options = ['pending' => 'รอตรวจสอบ', 'accepted' => 'รับคำขอแล้ว', 'completed' => 'สำเร็จ', 'rejected' => 'ไม่อนุมัติ'];
    $range('r.created_at');
    $search(['r.item_name', 'u.name', 'c.name']);
    if (isset($options[$f])) {
        $where[] = 'r.status = ?';
        $args[] = $f;
    }
    $list = rows('SELECT r.*, c.name AS cat_name, u.name AS user_name FROM recycle_requests r JOIN categories c ON c.id = r.category_id JOIN users u ON u.id = r.user_id
        WHERE ' . implode(' AND ', $where) . ' ORDER BY r.created_at DESC', $args);
    $cols = [['วันที่แจ้ง', 'date'], ['สมาชิก', 'text'], ['หมวดอุปกรณ์', 'text'], ['รายการ', 'text'], ['จำนวน (ชิ้น)', 'num'], ['น้ำหนัก (กก.)', 'num'], ['สถานะ', 'status'], ['แต้มที่ให้', 'num'], ['ลดได้ (kgCO₂e)', 'num']];
    $data = array_map(function ($r) { return [$r['created_at'], $r['user_name'], $r['cat_name'], $r['item_name'], $r['quantity'], $r['weight_kg'], $r['status'], $r['points_awarded'], $r['co2_saved_kg']]; }, $list);
    $done = array_filter($list, function ($r) { return $r['status'] === 'completed'; });
    $byCat = [];
    foreach ($done as $r) {
        $byCat[$r['cat_name']] = (isset($byCat[$r['cat_name']]) ? $byCat[$r['cat_name']] : 0) + $r['weight_kg'];
    }
    $summary = [['คำขอในรายงาน', count($list) . ' รายการ'], ['รีไซเคิลสำเร็จ', count($done) . ' รายการ'], ['E-Waste ที่รีไซเคิล', num(array_sum(array_column($done, 'weight_kg'))) . ' กก.'], ['คาร์บอนที่ลดได้', kg(array_sum(array_column($done, 'co2_saved_kg')))]];
    $chart = ['bar', array_keys($byCat), $byCat, 'น้ำหนักที่รีไซเคิลสำเร็จตามหมวด (กก.)'];
} else {
    $filterLabel = 'ประเภทกิจกรรม';
    $options = array_map(function ($v) { return $v[0]; }, ACTIVITY_TYPES);
    $range('a.created_at');
    $search(['u.name', 'a.description']);
    if (isset($options[$f])) {
        $where[] = 'a.type = ?';
        $args[] = $f;
    }
    $list = rows('SELECT a.*, u.name AS user_name FROM green_activities a JOIN users u ON u.id = a.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY a.created_at DESC', $args);
    $cols = [['วันที่', 'date'], ['สมาชิก', 'text'], ['ประเภท', 'text'], ['รายละเอียด', 'text'], ['Green Point', 'num'], ['ลดได้ (kgCO₂e)', 'num']];
    $data = array_map(function ($r) { return [$r['created_at'], $r['user_name'], ACTIVITY_TYPES[$r['type']][0], $r['description'], $r['points'], $r['co2_saved_kg']]; }, $list);
    $co2 = array_sum(array_column($list, 'co2_saved_kg'));
    $reused = count(array_filter($list, function ($r) { return $r['type'] === 'buy'; }));
    $summary = [['คาร์บอนที่ลดได้รวม', kg($co2)], ['เทียบเท่าต้นไม้ดูดซับ 1 ปี', num(trees($co2), 1) . ' ต้น'], ['อุปกรณ์ที่ถูกใช้ซ้ำ', $reused . ' ชิ้น'],
        ['กิจกรรมทั้งหมด', count($list) . ' รายการ']];
    $m = $monthly($list, 'created_at', 'co2_saved_kg');
    $chart = ['bar', array_map('month_label', array_keys($m)), $m, 'คาร์บอนที่ลดได้รายเดือน (kgCO₂e)'];
}

// คำอธิบายเงื่อนไขที่ใช้ แสดงบนหัวรายงานที่พิมพ์และไฟล์ส่งออก
$desc = [];
if ($useDates && ($from || $to)) {
    $desc[] = 'ช่วงวันที่ ' . ($from ? thai_date($from, false) : 'เริ่มต้น') . ' ถึง ' . ($to ? thai_date($to, false) : 'ปัจจุบัน');
}
if (isset($options[$f])) {
    $desc[] = $filterLabel . ': ' . $options[$f];
}
if ($kw !== '') {
    $desc[] = 'คำค้น: ' . $kw;
}
$desc = $desc ? implode(' · ', $desc) : 'ข้อมูลทั้งหมด';

// แปลงค่าตามชนิดคอลัมน์ ($plain = สำหรับไฟล์ส่งออก)
$cell = function ($v, $kind, $plain = false) {
    if ($kind === 'num') {
        return $v === null ? '-' : ($plain ? (string) round($v, 2) : num($v));
    }
    if ($kind === 'date' || $kind === 'datetime') {
        return $v ? thai_date($v, $kind === 'datetime') : ($plain ? '' : 'ไม่มีกำหนด');
    }
    if ($kind === 'status') {
        return $plain ? (isset(STATUS_LABELS[$v]) ? STATUS_LABELS[$v][0] : $v) : badge($v);
    }
    return $plain ? (string) $v : e($v);
};

if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report-' . $type . '-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");   // BOM ให้ Excel อ่านภาษาไทยถูกต้อง
    // ระบุตัวคั่นครบทุกตัว เพื่อให้ทำงานเหมือนกันทุกเวอร์ชันของ PHP
    $put = function ($line) use ($out) {
        fputcsv($out, $line, ',', '"', '\\');
    };
    $put([REPORTS[$type] . ' — ' . SITE_NAME]);
    $put(['เงื่อนไข: ' . $desc, 'ส่งออกเมื่อ ' . thai_date(date('Y-m-d H:i:s')), 'โดย ' . $admin['name']]);
    foreach ($summary as $s) {
        $put([$s[0], $s[1]]);
    }
    $put(['']);
    $put(array_column($cols, 0));
    foreach ($data as $line) {
        $put(array_map(function ($v, $c) use ($cell) { return $cell($v, $c[1], true); }, $line, $cols));
    }
    fclose($out);
    exit;
}

$qs = function ($extra = []) use ($type) {
    return e(url('admin/reports.php?' . http_build_query($extra + ['type' => $type, 'from' => get('from'), 'to' => get('to'), 'f' => get('f'), 'q' => get('q')])));
};
$title = 'รายงาน';
require __DIR__ . '/../includes/admin_header.php';
?>
<ul class="nav nav-pills mb-3 no-print flex-nowrap overflow-auto">
  <?php foreach (REPORTS as $k => $v): ?><li class="nav-item"><a class="nav-link text-nowrap <?= $type === $k ? 'active bg-success' : '' ?>" href="<?= e(url('admin/reports.php?type=' . $k)) ?>"><?= e($v) ?></a></li><?php endforeach; ?>
</ul>
<form class="panel mb-3 no-print" method="get">
  <input type="hidden" name="type" value="<?= e($type) ?>">
  <div class="row g-2 align-items-end">
    <?php if ($useDates): ?>
      <div class="col-6 col-md-2"><label class="form-label small mb-1" for="from">ตั้งแต่วันที่</label><input id="from" type="date" name="from" class="form-control form-control-sm" value="<?= e(get('from')) ?>"></div>
      <div class="col-6 col-md-2"><label class="form-label small mb-1" for="to">ถึงวันที่</label><input id="to" type="date" name="to" class="form-control form-control-sm" value="<?= e(get('to')) ?>"></div>
    <?php endif; ?>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="f"><?= e($filterLabel) ?></label>
      <select id="f" name="f" class="form-select form-select-sm"><option value="">ทั้งหมด</option>
        <?php foreach ($options as $k => $v): ?><option value="<?= e($k) ?>" <?= (string) $k === $f ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-1" for="q">ค้นหาในรายงาน</label><input id="q" type="search" name="q" class="form-control form-control-sm" value="<?= e($kw) ?>" placeholder="ชื่อ / รายละเอียด"></div>
    <div class="col-12 col-md-3 d-flex gap-2 flex-wrap">
      <button class="btn btn-success btn-sm">กรองข้อมูล</button>
      <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/reports.php?type=' . $type)) ?>">ล้าง</a>
    </div>
  </div>
</form>

<div class="print-head">
  <h2><?= e(REPORTS[$type]) ?></h2>
  <div><?= e(SITE_NAME) ?> · <?= e($desc) ?></div>
  <div class="small">พิมพ์เมื่อ <?= thai_date(date('Y-m-d H:i:s')) ?> โดย <?= e($admin['name']) ?></div>
</div>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 no-print">
  <div><strong><?= e(REPORTS[$type]) ?></strong> <span class="small text-muted">· <?= e($desc) ?> · <?= count($data) ?> แถว</span></div>
  <div class="d-flex gap-2">
    <button class="btn btn-success btn-sm" onclick="window.print()">🖨️ พิมพ์ / บันทึก PDF</button>
    <a class="btn btn-outline-success btn-sm" href="<?= $qs(['export' => 'csv']) ?>">⬇️ ส่งออก Excel (CSV)</a>
  </div>
</div>
<div class="row g-3 mb-3">
  <?php foreach ($summary as $i => $s): ?><div class="col-6 col-xl-3"><div class="stat <?= $i ? '' : 'green' ?>"><div class="label"><?= e($s[0]) ?></div><div class="value fs-4"><?= e($s[1]) ?></div></div></div><?php endforeach; ?>
</div>
<?php if ($data && array_sum($chart[2]) > 0): ?>
  <div class="panel mb-3"><h2><?= e($chart[3] !== '' ? $chart[3] : 'สัดส่วนข้อมูลในรายงาน') ?></h2><?= chart_canvas($chart[0], $chart[1], $chart[2], $chart[3], 240) ?></div>
<?php endif; ?>
<div class="panel">
  <?php if (!$data): ?><div class="empty">ไม่พบข้อมูลตามเงื่อนไข</div><?php else: ?>
  <div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>#</th><?php foreach ($cols as $c): ?><th data-sort<?= $c[1] === 'num' ? '="num" class="text-end"' : '' ?>><?= e($c[0]) ?></th><?php endforeach; ?></tr></thead>
    <tbody><?php foreach ($data as $n => $line): ?>
      <tr><td><?= $n + 1 ?></td><?php foreach ($line as $i => $v): $k = $cols[$i][1]; ?>
        <td<?= $k === 'num' ? ' class="text-end"' : '' ?><?= $k === 'date' || $k === 'datetime' ? ' class="text-nowrap" data-value="' . e($v) . '"' : '' ?>><?= $cell($v, $k) ?></td>
      <?php endforeach; ?></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
