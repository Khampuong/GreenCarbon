<?php
$self = basename($_SERVER['SCRIPT_NAME']);
$unread = (int) val('SELECT COUNT(*) FROM notifications WHERE is_read = 0');
$pending = [
    'products.php' => (int) val("SELECT COUNT(*) FROM products WHERE status = 'pending'"),
    'recycles.php' => (int) val("SELECT COUNT(*) FROM recycle_requests WHERE status IN ('pending', 'accepted')"),
    'rewards.php' => (int) val("SELECT COUNT(*) FROM redemptions WHERE status = 'pending'"),
    'notifications.php' => $unread,
];
$menu = [
    'index.php' => ['📊', 'Dashboard'],
    'notifications.php' => ['🔔', 'การแจ้งเตือน'],
    'users.php' => ['👥', 'จัดการสมาชิก'],
    'contents.php' => ['📰', 'จัดการเนื้อหา'],
    'products.php' => ['📦', 'อนุมัติสินค้า'],
    'recycles.php' => ['♻️', 'จัดการรีไซเคิล'],
    'green.php' => ['🌿', 'ตั้งค่า Green'],
    'rewards.php' => ['🎁', 'ของรางวัล'],
    'reports.php' => ['🖨️', 'รายงาน'],
];
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e((isset($title) ? $title : 'Admin') . ' | ' . SITE_NAME . ' Admin') ?></title>
<link href="<?= e(url('assets/vendor/bootstrap.min.css')) ?>" rel="stylesheet">
<link href="<?= e(url('assets/css/style.css')) ?>?v=1" rel="stylesheet">
</head>
<body class="admin">
<div class="admin-shell">
  <aside class="admin-side">
    <a class="brand" href="<?= e(url('admin/index.php')) ?>">♻ GreenLoop <small>Admin</small></a>
    <nav>
    <?php foreach ($menu as $file => $m): ?>
      <a class="item <?= $self === $file ? 'active' : '' ?>" href="<?= e(url('admin/' . $file)) ?>"><span><?= $m[0] ?></span> <?= $m[1] ?>
        <?php if (!empty($pending[$file])): ?><span class="badge rounded-pill bg-warning text-dark"><?= $pending[$file] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
      <a class="item" href="<?= e(url('index.php')) ?>"><span>🏠</span> กลับหน้าเว็บไซต์</a>
      <form method="post" action="<?= e(url('logout.php')) ?>"><?= csrf_field() ?><button class="item"><span>🚪</span> ออกจากระบบ</button></form>
    </nav>
  </aside>
  <div class="admin-main">
    <div class="admin-top">
      <h1><?= e(isset($title) ? $title : 'Admin') ?></h1>
      <div class="d-flex align-items-center gap-3">
        <a class="bell" href="<?= e(url('admin/notifications.php')) ?>" title="การแจ้งเตือน">🔔<?php if ($unread): ?><span class="dot"><?= $unread ?></span><?php endif; ?></a>
        <span class="small text-muted"><?= e(user()['name']) ?></span>
      </div>
    </div>
    <?php if (!empty($_SESSION['flash'])): foreach ($_SESSION['flash'] as $flashMsg): ?>
      <div class="alert alert-<?= e($flashMsg[0]) ?> alert-dismissible fade show" role="alert"><?= e($flashMsg[1]) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="ปิด"></button></div>
    <?php endforeach; unset($_SESSION['flash']); endif; ?>
