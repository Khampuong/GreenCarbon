<?php
require __DIR__ . '/includes/init.php';

if (user()) {
    redirect('index.php');
}
$errors = [];
if (is_post()) {
    $errors = profile_errors();
    $pw = isset($_POST['password']) ? (string) $_POST['password'] : '';
    if ($msg = password_error($pw, isset($_POST['password2']) ? (string) $_POST['password2'] : '')) {
        $errors['password'] = $msg;
    }
    if (!in_array(post('secret_question'), SECRET_QUESTIONS, true)) {
        $errors['secret_question'] = 'กรุณาเลือกคำถามกันลืม';
    }
    if (mb_strlen(post('secret_answer')) < 2) {
        $errors['secret_answer'] = 'กรุณากรอกคำตอบอย่างน้อย 2 ตัวอักษร';
    }
    if (!$errors) {
        q('INSERT INTO users (name, email, phone, address, password_hash, secret_question, secret_answer_hash) VALUES (?,?,?,?,?,?,?)', [
            post('name'), post('email'), post('phone'), post('address'),
            password_hash($pw, PASSWORD_DEFAULT), post('secret_question'), password_hash(secret_normalize(post('secret_answer')), PASSWORD_DEFAULT),
        ]);
        $id = (int) db()->lastInsertId();
        notify_admin('member', 'สมาชิกใหม่: ' . post('name'), post('email'), 'admin/users.php?q=' . urlencode(post('email')), $id);
        session_regenerate_id(true);
        $_SESSION['uid'] = $id;
        flash('สมัครสมาชิกสำเร็จ เริ่มต้นด้วยการคำนวณคาร์บอนฟุตพริ้นท์ของคุณ');
        redirect('footprint.php');
    }
    flash('กรุณาตรวจสอบข้อมูลที่กรอก', 'danger');
}

$title = 'สมัครสมาชิก';
require __DIR__ . '/includes/header.php';
?>
<div class="auth-wrap" style="max-width:620px">
  <form class="panel needs-validation" method="post" novalidate>
    <?= csrf_field() ?>
    <?= page_header('สมัครสมาชิก', 'สมัครฟรี เพื่อวัดและลดคาร์บอนฟุตพริ้นท์ของคุณ') ?>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label" for="name">ชื่อ-นามสกุล</label>
        <input id="name" name="name" class="form-control<?= invalid($errors, 'name') ?>" value="<?= e(post('name')) ?>" required minlength="2" maxlength="100">
        <?= feedback($errors, 'name', 'กรุณากรอกชื่อ-นามสกุล') ?></div>
      <div class="col-md-6"><label class="form-label" for="phone">เบอร์โทรศัพท์</label>
        <input id="phone" name="phone" class="form-control<?= invalid($errors, 'phone') ?>" value="<?= e(post('phone')) ?>" required pattern="0[0-9]{8,9}" inputmode="numeric" maxlength="10">
        <?= feedback($errors, 'phone', 'เบอร์โทรต้องเป็นตัวเลข 9-10 หลัก ขึ้นต้นด้วย 0') ?></div>
      <div class="col-12"><label class="form-label" for="email">อีเมล</label>
        <input id="email" name="email" type="email" class="form-control<?= invalid($errors, 'email') ?>" value="<?= e(post('email')) ?>" required maxlength="120" autocomplete="email">
        <?= feedback($errors, 'email', 'รูปแบบอีเมลไม่ถูกต้อง') ?></div>
      <div class="col-12"><label class="form-label" for="address">ที่อยู่ (ไม่บังคับ)</label>
        <input id="address" name="address" class="form-control<?= invalid($errors, 'address') ?>" value="<?= e(post('address')) ?>" maxlength="255">
        <?= feedback($errors, 'address', 'ที่อยู่ยาวเกินไป') ?></div>
      <div class="col-md-6"><label class="form-label" for="password">รหัสผ่าน</label>
        <input id="password" name="password" type="password" class="form-control<?= invalid($errors, 'password') ?>" required minlength="8" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,}" autocomplete="new-password">
        <?= feedback($errors, 'password', 'อย่างน้อย 8 ตัวอักษร มีทั้งตัวอักษรภาษาอังกฤษและตัวเลข') ?>
        <div class="form-text">อย่างน้อย 8 ตัวอักษร มีทั้งตัวอักษรและตัวเลข</div></div>
      <div class="col-md-6"><label class="form-label" for="password2">ยืนยันรหัสผ่าน</label>
        <input id="password2" name="password2" type="password" class="form-control" required data-match="#password" autocomplete="new-password">
        <div class="invalid-feedback">ยืนยันรหัสผ่านไม่ตรงกัน</div></div>
      <div class="col-md-6"><label class="form-label" for="secret_question">คำถามกันลืมรหัสผ่าน</label>
        <select id="secret_question" name="secret_question" class="form-select<?= invalid($errors, 'secret_question') ?>" required>
          <option value="">— เลือกคำถาม —</option>
          <?php foreach (SECRET_QUESTIONS as $sq): ?><option <?= post('secret_question') === $sq ? 'selected' : '' ?>><?= e($sq) ?></option><?php endforeach; ?>
        </select><?= feedback($errors, 'secret_question', 'กรุณาเลือกคำถามกันลืม') ?></div>
      <div class="col-md-6"><label class="form-label" for="secret_answer">คำตอบ</label>
        <input id="secret_answer" name="secret_answer" class="form-control<?= invalid($errors, 'secret_answer') ?>" value="<?= e(post('secret_answer')) ?>" required minlength="2" maxlength="100">
        <?= feedback($errors, 'secret_answer', 'กรุณากรอกคำตอบ') ?></div>
    </div>
    <button class="btn btn-success w-100 mt-4">สมัครสมาชิก</button>
    <p class="text-center small mt-3 mb-0">มีบัญชีแล้ว? <a href="<?= e(url('login.php')) ?>">เข้าสู่ระบบ</a></p>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
