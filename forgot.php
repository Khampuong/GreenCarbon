<?php
// ลืมรหัสผ่าน: ยืนยันตัวตนด้วยคำถามกันลืม (ระบบทำงานแบบออฟไลน์ จึงไม่ส่งอีเมล)
require __DIR__ . '/includes/init.php';

if (user()) {
    redirect('profile.php');
}
const FORGOT_MAX_TRIES = 5;

$target = empty($_SESSION['forgot_uid']) ? null : row("SELECT * FROM users WHERE id = ? AND status = 'active'", [$_SESSION['forgot_uid']]);
if (is_post()) {
    if (post('step') === 'restart') {
        unset($_SESSION['forgot_uid'], $_SESSION['forgot_tries']);
    } elseif (post('step') === 'email') {
        $found = row("SELECT id FROM users WHERE email = ? AND status = 'active'", [post('email')]);
        if ($found) {
            $_SESSION['forgot_uid'] = $found['id'];
            $_SESSION['forgot_tries'] = 0;
        } else {
            flash('ไม่พบบัญชีที่ใช้อีเมลนี้ หรือบัญชีถูกระงับสิทธิ์', 'danger');
        }
    } elseif (post('step') === 'reset' && $target) {
        $pw = isset($_POST['password']) ? (string) $_POST['password'] : '';
        if (!password_verify(secret_normalize(post('secret_answer')), $target['secret_answer_hash'])) {
            if (++$_SESSION['forgot_tries'] >= FORGOT_MAX_TRIES) {
                unset($_SESSION['forgot_uid'], $_SESSION['forgot_tries']);
                flash('ตอบผิดเกินจำนวนครั้งที่กำหนด กรุณาเริ่มใหม่หรือติดต่อผู้ดูแลระบบ', 'danger');
            } else {
                flash('คำตอบไม่ถูกต้อง (เหลืออีก ' . (FORGOT_MAX_TRIES - $_SESSION['forgot_tries']) . ' ครั้ง)', 'danger');
            }
        } elseif ($msg = password_error($pw, isset($_POST['password2']) ? (string) $_POST['password2'] : '')) {
            flash($msg, 'danger');
        } else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $target['id']]);
            unset($_SESSION['forgot_uid'], $_SESSION['forgot_tries']);
            flash('ตั้งรหัสผ่านใหม่เรียบร้อย กรุณาเข้าสู่ระบบ');
            redirect('login.php');
        }
    }
    redirect('forgot.php');
}

$title = 'ลืมรหัสผ่าน';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap">
  <div class="panel">
    <?= page_header('ลืมรหัสผ่าน', 'ยืนยันตัวตนด้วยคำถามกันลืมที่ตั้งไว้ตอนสมัครสมาชิก') ?>
    <?php if (!$target): ?>
      <form method="post" class="needs-validation" novalidate>
        <?= csrf_field() ?><input type="hidden" name="step" value="email">
        <div class="mb-3"><label class="form-label" for="email">อีเมลที่ใช้สมัครสมาชิก</label>
          <input id="email" class="form-control" type="email" name="email" required autofocus>
          <div class="invalid-feedback">กรุณากรอกอีเมลให้ถูกต้อง</div></div>
        <button class="btn btn-success w-100">ถัดไป</button>
      </form>
    <?php else: ?>
      <form method="post" class="needs-validation" novalidate>
        <?= csrf_field() ?><input type="hidden" name="step" value="reset">
        <div class="mb-3"><label class="form-label" for="secret_answer">คำถาม: <?= e($target['secret_question']) ?></label>
          <input id="secret_answer" class="form-control" name="secret_answer" required autofocus autocomplete="off">
          <div class="invalid-feedback">กรุณากรอกคำตอบ</div></div>
        <div class="mb-3"><label class="form-label" for="password">รหัสผ่านใหม่</label>
          <input id="password" class="form-control" type="password" name="password" required minlength="8" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,}" autocomplete="new-password">
          <div class="invalid-feedback">อย่างน้อย 8 ตัวอักษร มีทั้งตัวอักษรภาษาอังกฤษและตัวเลข</div></div>
        <div class="mb-3"><label class="form-label" for="password2">ยืนยันรหัสผ่านใหม่</label>
          <input id="password2" class="form-control" type="password" name="password2" required data-match="#password" autocomplete="new-password">
          <div class="invalid-feedback">ยืนยันรหัสผ่านไม่ตรงกัน</div></div>
        <button class="btn btn-success w-100">ตั้งรหัสผ่านใหม่</button>
      </form>
      <form method="post" class="text-center mt-3"><?= csrf_field() ?><input type="hidden" name="step" value="restart"><button class="btn btn-link btn-sm">ใช้อีเมลอื่น</button></form>
    <?php endif; ?>
    <p class="text-center small mt-2 mb-0"><a href="<?= e(url('login.php')) ?>">กลับไปหน้าเข้าสู่ระบบ</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
