<?php
$u = user();
$self = basename($_SERVER['SCRIPT_NAME']);
$nav = [
    'footprint.php' => '🧮 คำนวณคาร์บอน',
    'market.php' => '🛒 ตลาดมือสอง',
    'recycle.php' => '♻️ รีไซเคิล',
    'points.php' => '🏆 Green Point',
    'news.php' => '📰 ข่าวสาร',
];
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(isset($title) ? $title . ' | ' . SITE_NAME : SITE_NAME) ?></title>
<link href="<?= e(url('assets/vendor/bootstrap.min.css')) ?>" rel="stylesheet">
<link href="<?= e(url('assets/css/style.css')) ?>?v=1" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light sticky-top gl-nav">
  <div class="container">
    <a class="navbar-brand" href="<?= e(url('index.php')) ?>"><span class="logo-mark">♻</span> GreenLoop <span class="logo-sub">Net Zero</span></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="เมนู"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav me-auto">
        <?php foreach ($nav as $file => $label): ?>
          <li class="nav-item"><a class="nav-link <?= $self === $file ? 'active' : '' ?>" href="<?= e(url($file)) ?>"><?= $label ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="d-flex align-items-center gap-2 flex-wrap py-2 py-lg-0">
      <?php if ($u): ?>
        <a class="chip" href="<?= e(url('points.php')) ?>" title="Green Point">🌿 <?= number_format($u['green_points']) ?> แต้ม</a>
        <div class="dropdown">
          <a class="d-flex align-items-center gap-2 text-decoration-none dropdown-toggle text-dark" href="#" data-bs-toggle="dropdown"><?= avatar_html($u, 34) ?><span class="d-lg-none"><?= e($u['name']) ?></span></a>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li class="px-3 py-2"><div class="fw-semibold"><?= e($u['name']) ?></div><div class="small text-muted"><?= e($u['email']) ?></div></li>
            <li><hr class="dropdown-divider"></li>
            <?php if ($u['role'] === 'admin'): ?><li><a class="dropdown-item fw-semibold text-success" href="<?= e(url('admin/index.php')) ?>">🛠️ ระบบผู้ดูแล</a></li><?php endif; ?>
            <li><a class="dropdown-item" href="<?= e(url('profile.php')) ?>">👤 ข้อมูลส่วนตัว</a></li>
            <li><a class="dropdown-item" href="<?= e(url('footprint.php')) ?>">🧮 คาร์บอนฟุตพริ้นท์ของฉัน</a></li>
            <li><a class="dropdown-item" href="<?= e(url('sell.php')) ?>">🏷️ ลงขาย / สินค้าของฉัน</a></li>
            <li><a class="dropdown-item" href="<?= e(url('orders.php')) ?>">🧾 รายการซื้อ-ขาย</a></li>
            <li><a class="dropdown-item" href="<?= e(url('points.php')) ?>">🏆 Green Point และกิจกรรม</a></li>
            <li><a class="dropdown-item" href="<?= e(url('issue.php')) ?>">⚠️ แจ้งปัญหา</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="dropdown-item text-danger">🚪 ออกจากระบบ</button></form></li>
          </ul>
        </div>
      <?php else: ?>
        <a class="btn btn-outline-success btn-sm" href="<?= e(url('login.php')) ?>">เข้าสู่ระบบ</a>
        <a class="btn btn-success btn-sm" href="<?= e(url('register.php')) ?>">สมัครสมาชิก</a>
      <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
<main class="<?= isset($mainClass) ? e($mainClass) : 'container py-4' ?>">
<?php if (!empty($_SESSION['flash'])): ?>
  <div class="<?= isset($mainClass) ? 'container pt-3' : '' ?>">
  <?php foreach ($_SESSION['flash'] as $flashMsg): ?>
    <div class="alert alert-<?= e($flashMsg[0]) ?> alert-dismissible fade show" role="alert"><?= e($flashMsg[1]) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ปิด"></button></div>
  <?php endforeach; unset($_SESSION['flash']); ?>
  </div>
<?php endif; ?>
