<?php
require __DIR__ . '/../includes/init.php';
require_admin();

$s = [
    'members' => (int) val("SELECT COUNT(*) FROM users WHERE role = 'user'"),
    'new_members' => (int) val("SELECT COUNT(*) FROM users WHERE role = 'user' AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"),
    'suspended' => (int) val("SELECT COUNT(*) FROM users WHERE status = 'suspended'"),
    'co2' => (float) val('SELECT COALESCE(SUM(co2_saved_kg), 0) FROM green_activities'),
    'ewaste' => (float) val("SELECT COALESCE(SUM(weight_kg), 0) FROM recycle_requests WHERE status = 'completed'"),
    'reused' => (int) val("SELECT COUNT(*) FROM orders WHERE status = 'completed'"),
    'footprints' => (int) val('SELECT COUNT(*) FROM carbon_footprints WHERE year = YEAR(NOW())'),
    'avg_fp' => (float) val('SELECT COALESCE(AVG(total_kg), 0) FROM carbon_footprints WHERE year = YEAR(NOW())'),
    'points' => (int) val('SELECT COALESCE(SUM(points), 0) FROM green_activities WHERE points > 0'),
    'activities' => (int) val('SELECT COUNT(*) FROM green_activities WHERE created_at >= NOW() - INTERVAL 30 DAY'),
];
$months = month_keys(6);
$co2ByMonth = array_fill_keys(array_keys($months), 0);
$actByMonth = $co2ByMonth;
foreach (rows("SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, SUM(co2_saved_kg) AS co2, COUNT(*) AS n FROM green_activities WHERE created_at >= ? GROUP BY m", [key($months) . '-01']) as $r) {
    $co2ByMonth[$r['m']] = $r['co2'];
    $actByMonth[$r['m']] = $r['n'];
}
$byType = rows("SELECT type, COUNT(*) AS n FROM green_activities WHERE type <> 'redeem' GROUP BY type ORDER BY n DESC");
$fpByGroup = rows('SELECT f.category, SUM(i.co2e_kg) / COUNT(DISTINCT i.footprint_id) AS kg FROM carbon_footprint_items i JOIN emission_factors f ON f.id = i.factor_id
    JOIN carbon_footprints c ON c.id = i.footprint_id WHERE c.year = YEAR(NOW()) GROUP BY f.category ORDER BY kg DESC');
$notis = rows('SELECT * FROM notifications ORDER BY is_read, id DESC LIMIT 6');
$contents = rows('SELECT * FROM contents ORDER BY publish_at DESC LIMIT 6');

$title = 'Dashboard';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3"><div class="stat green"><div class="label">คาร์บอนที่ลดได้รวม</div><div class="value"><?= num($s['co2'], 1) ?></div><div class="unit">kgCO₂e ≈ ต้นไม้ <?= num(trees($s['co2']), 0) ?> ต้น</div></div></div>
  <div class="col-6 col-xl-3"><div class="stat"><div class="label">สมาชิกทั้งหมด</div><div class="value"><?= $s['members'] ?></div><div class="unit">ใหม่เดือนนี้ <?= $s['new_members'] ?> · ระงับ <?= $s['suspended'] ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="stat"><div class="label">E-Waste ที่รีไซเคิล</div><div class="value"><?= num($s['ewaste']) ?></div><div class="unit">กก. · อุปกรณ์ใช้ซ้ำ <?= $s['reused'] ?> ชิ้น</div></div></div>
  <div class="col-6 col-xl-3"><div class="stat"><div class="label">ฟุตพริ้นท์เฉลี่ยปีนี้</div><div class="value"><?= num($s['avg_fp'], 0) ?></div><div class="unit">kgCO₂e/คน · คำนวณแล้ว <?= $s['footprints'] ?> คน</div></div></div>
  <div class="col-6 col-xl-3"><div class="stat"><div class="label">กิจกรรม 30 วันล่าสุด</div><div class="value"><?= $s['activities'] ?></div><div class="unit">รายการ (สถิติการใช้งาน)</div></div></div>
  <div class="col-6 col-xl-3"><div class="stat"><div class="label">Green Point ที่แจก</div><div class="value"><?= number_format($s['points']) ?></div><div class="unit">แต้ม</div></div></div>
  <div class="col-6 col-xl-3"><div class="stat"><div class="label">รออนุมัติ</div><div class="value"><?= $pending['products.php'] + $pending['recycles.php'] ?></div><div class="unit">สินค้า <?= $pending['products.php'] ?> · รีไซเคิล <?= $pending['recycles.php'] ?></div></div></div>
  <div class="col-6 col-xl-3"><div class="stat"><div class="label">แจ้งเตือนยังไม่อ่าน</div><div class="value"><?= $unread ?></div><div class="unit"><a href="<?= e(url('admin/notifications.php')) ?>">เปิดดูทั้งหมด</a></div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-8"><div class="panel h-100"><h2>คาร์บอนที่ลดได้รายเดือน (kgCO₂e)</h2><?= chart_canvas('bar', $months, $co2ByMonth, 'kgCO₂e ที่ลดได้') ?></div></div>
  <div class="col-lg-4"><div class="panel h-100"><h2>กิจกรรมตามประเภท</h2><?= chart_canvas('doughnut', array_map(function ($r) { return ACTIVITY_TYPES[$r['type']][0]; }, $byType), array_column($byType, 'n')) ?></div></div>
  <div class="col-lg-8"><div class="panel h-100"><h2>จำนวนกิจกรรมรายเดือน (สถิติการใช้งาน)</h2><?= chart_canvas('line', $months, $actByMonth, 'จำนวนกิจกรรม', 220) ?></div></div>
  <div class="col-lg-4"><div class="panel h-100"><h2>ฟุตพริ้นท์เฉลี่ยต่อคนตามหมวด</h2>
    <?= $fpByGroup ? chart_canvas('doughnut', array_map(function ($r) { return FACTOR_GROUPS[$r['category']][0]; }, $fpByGroup), array_column($fpByGroup, 'kg'), '', 220) : '<div class="empty">ยังไม่มีข้อมูลปีนี้</div>' ?></div></div>
  <div class="col-lg-6"><div class="panel h-100">
    <div class="d-flex justify-content-between"><h2>🔔 แจ้งเตือนล่าสุด</h2><a class="small" href="<?= e(url('admin/notifications.php')) ?>">ดูทั้งหมด</a></div>
    <?php foreach ($notis as $n): ?>
      <a class="noti <?= $n['is_read'] ? '' : 'unread' ?> d-block px-2 py-1 mb-1 text-decoration-none text-reset" href="<?= e(url('admin/notifications.php?open=' . $n['id'])) ?>">
        <div class="d-flex justify-content-between gap-2"><span><?= NOTI_TYPES[$n['type']][1] ?> <?= e($n['title']) ?></span><span class="small text-muted text-nowrap"><?= thai_date($n['created_at']) ?></span></div>
      </a>
    <?php endforeach; ?>
  </div></div>
  <div class="col-lg-6"><div class="panel h-100">
    <div class="d-flex justify-content-between"><h2>📰 สถานะเนื้อหาตามเวลาที่ตั้งไว้</h2><a class="small" href="<?= e(url('admin/contents.php')) ?>">จัดการ</a></div>
    <table class="table table-sm mb-0"><tbody><?php foreach ($contents as $c): ?>
      <tr><td><?= e($c['title']) ?><div class="small text-muted"><?= thai_date($c['publish_at']) ?> → <?= $c['expire_at'] ? thai_date($c['expire_at']) : 'ไม่มีกำหนด' ?></div></td><td class="text-end"><?= badge(content_status($c)) ?></td></tr>
    <?php endforeach; ?></tbody></table>
  </div></div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
