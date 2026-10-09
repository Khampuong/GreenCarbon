<?php
require __DIR__ . '/includes/init.php';

$u = user();
$banner = row("SELECT * FROM contents WHERE type = 'banner' AND " . CONTENT_LIVE . ' ORDER BY publish_at DESC LIMIT 1');
$campaign = active_campaign();
$news = rows("SELECT * FROM contents WHERE type = 'news' AND " . CONTENT_LIVE . ' ORDER BY publish_at DESC LIMIT 3');
$products = rows("SELECT p.*, c.name AS cat_name, c.icon AS cat_icon, c.co2_reuse_kg FROM products p JOIN categories c ON c.id = p.category_id WHERE p.status = 'approved' ORDER BY p.id DESC LIMIT 4");
$impact = [
    'co2' => (float) val('SELECT COALESCE(SUM(co2_saved_kg), 0) FROM green_activities'),
    'ewaste' => (float) val("SELECT COALESCE(SUM(weight_kg), 0) FROM recycle_requests WHERE status = 'completed'"),
    'reused' => (int) val("SELECT COUNT(*) FROM orders WHERE status = 'completed'"),
    'members' => (int) val("SELECT COUNT(*) FROM users WHERE role = 'user'"),
];
$myFp = $u ? row('SELECT * FROM carbon_footprints WHERE user_id = ? ORDER BY year DESC LIMIT 1', [$u['id']]) : null;

$mainClass = '';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="container">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <h1><?= e($banner ? $banner['title'] : 'ส่งต่อเทคโนโลยี เพื่อโลกที่ยั่งยืน') ?></h1>
        <p class="lead mb-4"><?= e($banner ? $banner['body'] : 'วัดคาร์บอนฟุตพริ้นท์ของคุณ แล้วลดด้วยการใช้ซ้ำและรีไซเคิล') ?></p>
        <div class="d-flex gap-2 flex-wrap">
          <a class="btn btn-light btn-lg" href="<?= e(url('footprint.php')) ?>">🧮 คำนวณคาร์บอนฟุตพริ้นท์</a>
          <a class="btn btn-outline-light btn-lg" href="<?= e(url('recycle.php')) ?>">♻️ แจ้งรีไซเคิล</a>
        </div>
      </div>
      <div class="col-lg-5">
        <?php if ($myFp): $saved = co2_saved($u['id'], $myFp['year']); $pct = $myFp['total_kg'] > 0 ? min(100, $saved / $myFp['total_kg'] * 100) : 0; ?>
          <div class="panel text-dark netzero">
            <div class="small text-muted">เส้นทางสู่ Net Zero ของคุณ ปี <?= $myFp['year'] + 543 ?></div>
            <div class="fs-3 fw-bold"><?= num($myFp['total_kg'] / 1000) ?> ตัน CO₂e</div>
            <div class="progress my-2"><div class="progress-bar bg-success" style="width:<?= max(2, round($pct, 1)) ?>%"><?= num($pct, 1) ?>%</div></div>
            <div class="small">ลดได้แล้ว <?= kg($saved) ?> · <a href="<?= e(url('footprint.php?year=' . $myFp['year'])) ?>">ดูรายละเอียด</a></div>
          </div>
        <?php else: ?>
          <div class="panel text-dark">
            <div class="fw-bold mb-1">คุณปล่อยคาร์บอนปีละเท่าไร?</div>
            <p class="small text-muted mb-2">ตอบคำถามเรื่องไฟฟ้า การเดินทาง อาหาร และขยะ ใช้เวลาไม่ถึง 3 นาที รับ <?= (int) setting('points_footprint', 30) ?> แต้มทันที</p>
            <a class="btn btn-success btn-sm" href="<?= e(url($u ? 'footprint.php' : 'register.php')) ?>"><?= $u ? 'เริ่มคำนวณ' : 'สมัครสมาชิกเพื่อเริ่มคำนวณ' ?></a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<div class="container py-4">
  <?php if ($campaign): ?>
    <a class="campaign-bar d-block text-decoration-none text-dark mb-4" href="<?= e(url('news.php?id=' . $campaign['id'])) ?>">🎉 <strong><?= e($campaign['title']) ?></strong> — <?= e($campaign['body']) ?> (ถึง <?= thai_date($campaign['expire_at']) ?>)</a>
  <?php endif; ?>

  <h2 class="h5 fw-bold mb-3">ผลลัพธ์ต่อสิ่งแวดล้อมของชุมชนเรา</h2>
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="stat green"><div class="label">คาร์บอนที่ลดได้รวม</div><div class="value"><?= num($impact['co2'], 1) ?></div><div class="unit">kgCO₂e ≈ ต้นไม้ <?= num(trees($impact['co2']), 0) ?> ต้น</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat"><div class="label">E-Waste ที่รีไซเคิล</div><div class="value"><?= num($impact['ewaste']) ?></div><div class="unit">กิโลกรัม</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat"><div class="label">อุปกรณ์ที่ถูกใช้ซ้ำ</div><div class="value"><?= $impact['reused'] ?></div><div class="unit">ชิ้น</div></div></div>
    <div class="col-6 col-lg-3"><div class="stat"><div class="label">สมาชิก</div><div class="value"><?= $impact['members'] ?></div><div class="unit">คน</div></div></div>
  </div>

  <div class="panel mb-4">
    <div class="row text-center">
      <div class="col-6 col-md-3 loop-step"><div class="ico">🧮</div><strong>1. วัด</strong><div class="small text-muted">คำนวณคาร์บอนฟุตพริ้นท์รายปี</div></div>
      <div class="col-6 col-md-3 loop-step"><div class="ico">♻️</div><strong>2. ลด</strong><div class="small text-muted">ซื้อ-ขายมือสอง และรีไซเคิล</div></div>
      <div class="col-6 col-md-3 loop-step"><div class="ico">🏆</div><strong>3. รับแต้ม</strong><div class="small text-muted">สะสม Green Point แลกของรางวัล</div></div>
      <div class="col-6 col-md-3 loop-step"><div class="ico">🌍</div><strong>4. สู่ Net Zero</strong><div class="small text-muted">ติดตามความคืบหน้าของตัวเอง</div></div>
    </div>
  </div>

  <?php if ($products): ?>
    <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-bold mb-0">อุปกรณ์มือสองมาใหม่</h2><a href="<?= e(url('market.php')) ?>">ดูทั้งหมด →</a></div>
    <div class="row g-3 mb-4"><?php foreach ($products as $p): ?><div class="col-6 col-lg-3"><?= product_card($p) ?></div><?php endforeach; ?></div>
  <?php endif; ?>

  <?php if ($news): ?>
    <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 fw-bold mb-0">ข่าวสารและบทความ</h2><a href="<?= e(url('news.php')) ?>">ดูทั้งหมด →</a></div>
    <div class="row g-3"><?php foreach ($news as $c): ?>
      <div class="col-md-4"><a class="panel d-block h-100 text-decoration-none text-reset" href="<?= e(url('news.php?id=' . $c['id'])) ?>">
        <strong><?= e($c['title']) ?></strong><p class="small text-muted mb-1 mt-1"><?= e(mb_strimwidth($c['body'], 0, 110, '…')) ?></p><span class="small text-muted"><?= thai_date($c['publish_at'], false) ?></span></a></div>
    <?php endforeach; ?></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
