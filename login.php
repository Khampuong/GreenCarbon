<?php
require __DIR__ . '/includes/init.php';

if (user()) {
    redirect('index.php');
}
if (is_post()) {
    $found = row('SELECT * FROM users WHERE email = ?', [post('email')]);
    if (!$found || !password_verify(isset($_POST['password']) ? (string) $_POST['password'] : '', $found['password_hash'])) {
        flash('อีเมลหรือรหัสผ่านไม่ถูกต้อง', 'danger');
    } elseif ($found['status'] !== 'active') {
        flash('บัญชีนี้ถูกระงับสิทธิ์การใช้งาน กรุณาติดต่อผู้ดูแลระบบ', 'danger');
    } else {
        $next = isset($_SESSION['after_login']) ? $_SESSION['after_login'] : null;
        session_regenerate_id(true);
        unset($_SESSION['after_login']);
        $_SESSION['uid'] = $found['id'];
        flash('ยินดีต้อนรับ ' . $found['name']);
        if ($next && $next[0] === '/' && substr($next, 0, 2) !== '//') {
            header('Location: ' . $next);
            exit;
        }
        redirect($found['role'] === 'admin' ? 'admin/index.php' : 'index.php');
    }
    redirect('login.php');
}

$title = 'เข้าสู่ระบบ';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <form class="panel needs-validation" method="post" novalidate>
    <?= csrf_field() ?>
    <?= page_header('เข้าสู่ระบบ') ?>
    <div class="mb-3"><label class="form-label" for="email">อีเมล</label>
      <input id="email" class="form-control" type="email" name="email" required autofocus autocomplete="email">
      <div class="invalid-feedback">กรุณากรอกอีเมลให้ถูกต้อง</div></div>
    <div class="mb-3"><label class="form-label" for="password">รหัสผ่าน</label>
      <input id="password" class="form-control" type="password" name="password" required autocomplete="current-password">
      <div class="invalid-feedback">กรุณากรอกรหัสผ่าน</div></div>
    <button class="btn btn-success w-100">เข้าสู่ระบบ</button>
    <div class="d-flex justify-content-between small mt-3">
      <a href="<?= e(url('forgot.php')) ?>">ลืมรหัสผ่าน?</a>
      <a href="<?= e(url('register.php')) ?>">สมัครสมาชิก</a>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
