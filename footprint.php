<?php
// Net Zero Man: คำนวณคาร์บอนฟุตพริ้นท์รายปี และเทียบกับ CO2e ที่ลดได้จากกิจกรรมใน GreenLoop
require __DIR__ . '/includes/init.php';

$u = require_login();
$thisYear = (int) date('Y');
$years = [$thisYear, $thisYear - 1];
$year = in_array((int) get('year'), $years, true) ? (int) get('year') : $thisYear;
$factors = rows("SELECT * FROM emission_factors WHERE is_active = 1 ORDER BY FIELD(category, 'energy', 'transport', 'food', 'waste', 'device'), id");

if (is_post()) {
    $year = in_array((int) post('year'), $years, true) ? (int) post('year') : $thisYear;
    $items = [];
    $total = 0;
    foreach ($factors as $f) {
        $raw = isset($_POST['amount'][$f['id']]) && is_string($_POST['amount'][$f['id']]) ? trim($_POST['amount'][$f['id']]) : '';
        if ($raw === '') {
            continue;
        }
        if (!is_numeric($raw) || $raw < 0 || $raw > 99999) {
            flash('ค่าที่กรอกในช่อง "' . $f['name'] . '" ต้องเป็นตัวเลข 0-99,999', 'danger');
            redirect('footprint.php?year=' . $year . '&edit=1');
        }
        if ($raw > 0) {
            $co2 = round($raw * $f['factor'] * $f['periods_per_year'], 2);
            $items[] = [$f['id'], $raw, $co2];
            $total += $co2;
        }
    }
    if (!$items) {
        flash('กรุณากรอกข้อมูลอย่างน้อย 1 รายการ', 'danger');
        redirect('footprint.php?year=' . $year . '&edit=1');
    }
    in_tx(function () use ($u, $year, $items, $total) {
        $id = val('SELECT id FROM carbon_footprints WHERE user_id = ? AND year = ?', [$u['id'], $year]);
        if ($id) {
            q('UPDATE carbon_footprints SET total_kg = ? WHERE id = ?', [$total, $id]);
            q('DELETE FROM carbon_footprint_items WHERE footprint_id = ?', [$id]);
        } else {
            q('INSERT INTO carbon_footprints (user_id, year, total_kg) VALUES (?,?,?)', [$u['id'], $year, $total]);
            $id = db()->lastInsertId();
            add_activity($u['id'], 'footprint', $id, 'คำนวณคาร์บอนฟุตพริ้นท์ครั้งแรกของปี ' . ($year + 543), setting('points_footprint', 30));
            flash('รับ Green Point จากการคำนวณครั้งแรกของปีแล้ว');
        }
        foreach ($items as $it) {
            q('INSERT INTO carbon_footprint_items (footprint_id, factor_id, amount, co2e_kg) VALUES (?,?,?,?)', [$id, $it[0], $it[1], $it[2]]);
        }
    });
    flash('บันทึกคาร์บอนฟุตพริ้นท์ปี ' . ($year + 543) . ' แล้ว');
    redirect('footprint.php?year=' . $year);
}

$fp = row('SELECT * FROM carbon_footprints WHERE user_id = ? AND year = ?', [$u['id'], $year]);
$amounts = [];
$byGroup = [];
if ($fp) {
    foreach (rows('SELECT i.*, f.category FROM carbon_footprint_items i JOIN emission_factors f ON f.id = i.factor_id WHERE i.footprint_id = ?', [$fp['id']]) as $it) {
        $amounts[$it['factor_id']] = $it['amount'];
        $byGroup[$it['category']] = (isset($byGroup[$it['category']]) ? $byGroup[$it['category']] : 0) + $it['co2e_kg'];
    }
    arsort($byGroup);
}
$editing = !$fp || get('edit') === '1';
$saved = co2_saved($u['id'], $year);
$history = rows('SELECT year, total_kg FROM carbon_footprints WHERE user_id = ? ORDER BY year', [$u['id']]);
$avg = (float) setting('avg_footprint_kg', 3700);
const TIPS = [
    'energy' => 'ไฟฟ้าและพลังงานเป็นแหล่งปล่อยหลักของคุณ ลองตั้งแอร์ที่ 26°C ถอดปลั๊กเมื่อไม่ใช้ และเปลี่ยนเป็นหลอด LED',
    'transport' => 'การเดินทางเป็นแหล่งปล่อยหลักของคุณ ลองใช้ขนส่งสาธารณะ เดินทางร่วมกัน หรือรวมธุระไว้ในเที่ยวเดียว',
    'food' => 'อาหารเป็นแหล่งปล่อยหลักของคุณ ลองเพิ่มมื้อมังสวิรัติสัปดาห์ละ 2-3 มื้อ และลดอาหารเหลือทิ้ง',
    'waste' => 'ขยะเป็นแหล่งปล่อยหลักของคุณ ลองคัดแยกขยะ ใช้ซ้ำ และพกถุงผ้าหรือแก้วส่วนตัว',
    'device' => 'อุปกรณ์ดิจิทัลเป็นแหล่งปล่อยหลักของคุณ ลองยืดอายุการใช้งาน ซ่อมก่อนเปลี่ยน หรือเลือกซื้อมือสองในตลาดของเรา',
];

$title = 'คำนวณคาร์บอนฟุตพริ้นท์';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
  <?= page_header('คาร์บอนฟุตพริ้นท์รายปีของคุณ', 'Net Zero Man — วัดก่อน แล้วลดด้วยการใช้ซ้ำและรีไซเคิล') ?>
  <div class="d-flex gap-2 no-print">
    <form method="get" class="d-flex gap-2">
      <select name="year" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="เลือกปี">
        <?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>>ปี <?= $y + 543 ?></option><?php endforeach; ?>
      </select>
    </form>
    <?php if ($fp && !$editing): ?>
      <a class="btn btn-outline-success btn-sm" href="<?= e(url('footprint.php?year=' . $year . '&edit=1')) ?>">✏️ แก้ไขข้อมูล</a>
      <button class="btn btn-success btn-sm" onclick="window.print()">🖨️ พิมพ์ผล</button>
    <?php endif; ?>
  </div>
</div>

<?php if ($fp && !$editing):
    $pct = $fp['total_kg'] > 0 ? min(100, $saved / $fp['total_kg'] * 100) : 0;
    $remain = max(0, $fp['total_kg'] - $saved); ?>
  <div class="print-head"><h2>ผลคำนวณคาร์บอนฟุตพริ้นท์ ปี <?= $year + 543 ?></h2><div><?= e($u['name']) ?> · พิมพ์เมื่อ <?= thai_date(date('Y-m-d H:i:s')) ?></div></div>
  <div class="row g-3">
    <div class="col-6 col-lg-3"><div class="stat green"><div class="label">ปล่อยทั้งปี <?= $year + 543 ?></div><div class="value"><?= num($fp['total_kg'] / 1000) ?></div><div class="unit">ตัน CO₂e (<?= num($fp['total_kg'], 0) ?> kg)</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat"><div class="label">ลดได้จากกิจกรรม GreenLoop</div><div class="value text-success"><?= num($saved, 1) ?></div><div class="unit">kgCO₂e ในปีนี้</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat"><div class="label">คงเหลือสุทธิ</div><div class="value"><?= num($remain, 0) ?></div><div class="unit">kgCO₂e</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat"><div class="label">ต้องปลูกต้นไม้ชดเชย</div><div class="value">🌳 <?= num(ceil(trees($remain)), 0) ?></div><div class="unit">ต้น (ดูดซับ 1 ปี)</div></div></div>
  </div>

  <div class="panel netzero mt-3">
    <h2>เส้นทางสู่ Net Zero</h2>
    <div class="progress"><div class="progress-bar bg-success" style="width:<?= max(2, round($pct, 1)) ?>%" role="progressbar" aria-valuenow="<?= round($pct, 1) ?>" aria-valuemin="0" aria-valuemax="100"><?= num($pct, 1) ?>%</div></div>
    <p class="small text-muted mt-2 mb-0">คุณชดเชยได้ <?= num($pct, 1) ?>% ของฟุตพริ้นท์ปีนี้ ทุกครั้งที่ซื้อ-ขายมือสองหรือรีไซเคิล E-Waste แถบนี้จะเพิ่มขึ้น
      <?php if ($fp['total_kg'] <= $avg): ?> · ฟุตพริ้นท์ของคุณ<strong class="text-success">ต่ำกว่า</strong>ค่าเฉลี่ย (<?= num($avg, 0) ?> kg)<?php else: ?> · ฟุตพริ้นท์ของคุณ<strong class="text-danger">สูงกว่า</strong>ค่าเฉลี่ย (<?= num($avg, 0) ?> kg)<?php endif; ?></p>
    <div class="d-flex gap-2 flex-wrap mt-3 no-print">
      <a class="btn btn-success btn-sm" href="<?= e(url('recycle.php')) ?>">♻️ แจ้งรีไซเคิล</a>
      <a class="btn btn-outline-success btn-sm" href="<?= e(url('market.php')) ?>">🛒 เลือกซื้อมือสอง</a>
      <a class="btn btn-outline-success btn-sm" href="<?= e(url('sell.php')) ?>">🏷️ ส่งต่ออุปกรณ์</a>
    </div>
  </div>

  <div class="row g-3 mt-0">
    <div class="col-lg-5"><div class="panel h-100"><h2>สัดส่วนตามหมวด</h2>
      <?php
      $labels = [];
      foreach ($byGroup as $g => $v) {
          $labels[] = FACTOR_GROUPS[$g][0];
      }
      echo chart_canvas('doughnut', $labels, $byGroup, '', 260);
      ?></div></div>
    <div class="col-lg-7"><div class="panel h-100"><h2>รายละเอียดและคำแนะนำ</h2>
      <table class="table table-sm">
        <thead><tr><th>หมวด</th><th class="text-end">kgCO₂e / ปี</th><th class="text-end">สัดส่วน</th></tr></thead>
        <tbody><?php foreach ($byGroup as $g => $v): ?>
          <tr><td><?= FACTOR_GROUPS[$g][1] ?> <?= e(FACTOR_GROUPS[$g][0]) ?></td><td class="text-end"><?= num($v) ?></td><td class="text-end"><?= num($v / $fp['total_kg'] * 100, 1) ?>%</td></tr>
        <?php endforeach; ?></tbody>
      </table>
      <div class="tip">💡 <?= e(TIPS[array_keys($byGroup)[0]]) ?></div>
      <div class="tip">📱 ซื้อโน้ตบุ๊กมือสองแทนเครื่องใหม่ 1 เครื่อง ลดได้ราว <?= num(val('SELECT MAX(co2_reuse_kg) FROM categories'), 0) ?> kgCO₂e</div>
    </div></div>
    <?php if (count($history) > 1): ?>
    <div class="col-12"><div class="panel"><h2>เปรียบเทียบรายปี</h2>
      <?= chart_canvas('bar', array_map(function ($h) { return 'ปี ' . ($h['year'] + 543); }, $history), array_column($history, 'total_kg'), 'kgCO₂e ต่อปี', 220) ?></div></div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <form id="footprint-form" method="post" class="needs-validation" novalidate>
    <?= csrf_field() ?><input type="hidden" name="year" value="<?= $year ?>">
    <div class="tip mb-3">กรอกเฉพาะรายการที่เกี่ยวข้องกับคุณ ปล่อยว่างได้ ระบบคำนวณเป็นรายปีให้อัตโนมัติ (ปริมาณ × ค่าสัมประสิทธิ์ × จำนวนรอบต่อปี)</div>
    <div class="row g-3">
    <?php foreach (FACTOR_GROUPS as $g => $info):
        $list = array_filter($factors, function ($f) use ($g) { return $f['category'] === $g; });
        if (!$list) { continue; } ?>
      <div class="col-lg-6"><div class="fp-group h-100">
        <h3><?= $info[1] ?> <?= e($info[0]) ?></h3>
        <?php foreach ($list as $f): ?>
          <label class="form-label small mb-1 mt-2" for="f<?= $f['id'] ?>"><?= e($f['name']) ?></label>
          <div class="input-group input-group-sm">
            <input id="f<?= $f['id'] ?>" name="amount[<?= $f['id'] ?>]" type="number" min="0" max="99999" step="0.01" class="form-control" inputmode="decimal"
                   value="<?= isset($amounts[$f['id']]) ? e(num($amounts[$f['id']])) : '' ?>" data-factor="<?= e($f['factor']) ?>" data-periods="<?= (int) $f['periods_per_year'] ?>" placeholder="0">
            <span class="input-group-text"><?= e($f['unit']) ?> <?= PERIODS[$f['periods_per_year']] ?></span>
            <div class="invalid-feedback">กรอกตัวเลข 0-99,999</div>
          </div>
        <?php endforeach; ?>
      </div></div>
    <?php endforeach; ?>
    </div>
    <div class="fp-total d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
      <div>ประมาณการปี <?= $year + 543 ?>: <strong class="fs-4 text-success" id="fp-live-total">0</strong> kgCO₂e <span class="text-muted">(<span id="fp-live-ton">0</span> ตัน)</span></div>
      <div class="d-flex gap-2">
        <?php if ($fp): ?><a class="btn btn-outline-secondary" href="<?= e(url('footprint.php?year=' . $year)) ?>">ยกเลิก</a><?php endif; ?>
        <button class="btn btn-success">บันทึกและดูผล</button>
      </div>
    </div>
  </form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
