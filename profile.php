<?php
require __DIR__ . '/includes/init.php';

$u = require_login();
$errors = [];
if (is_post() && post('action') === 'profile') {
    $errors = profile_errors($u['id']);
    $avatar = upload_image('avatar', 'avatars');
    if (!$errors && $avatar !== false) {
        q('UPDATE users SET name = ?, email = ?, phone = ?, address = ?, avatar = COALESCE(?, avatar) WHERE id = ?', [post('name'), post('email'), post('phone'), post('address'), $avatar, $u['id']]);
        flash('บันทึกข้อมูลส่วนตัวแล้ว');
        redirect('profile.php');
    }
    flash('กรุณาตรวจสอบข้อมูลที่กรอก', 'danger');
} elseif (is_post() && post('action') === 'password') {
    $pw = isset($_POST['password']) ? (string) $_POST['password'] : '';
    if (!password_verify(isset($_POST['current']) ? (string) $_POST['current'] : '', $u['password_hash'])) {
        flash('รหัสผ่านปัจจุบันไม่ถูกต้อง', 'danger');
    } elseif ($msg = password_error($pw, isset($_POST['password2']) ? (string) $_POST['password2'] : '')) {
        flash($msg, 'danger');
    } else {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
        session_regenerate_id(true);
        flash('เปลี่ยนรหัสผ่านแล้ว');
    }
    redirect('profile.php');
}
$f = $errors ? $_POST : $u;
list($level) = user_level($u['id']);

$title = 'ข้อมูลส่วนตัว';
require __DIR__ . '/includes/header.php';
?>
<?= page_header('ข้อมูลส่วนตัว', 'สมาชิกตั้งแต่ ' . thai_date($u['created_at'], false)) ?>
<div class="row g-3">
  <div class="col-lg-7">
    <form class="panel needs-validation" method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <h2>แก้ไขโปรไฟล์</h2>
      <div class="d-flex align-items-center gap-3 mb-3">
        <?= avatar_html($u, 64) ?>
        <div><span class="level-badge"><?= $level[2] ?> ระดับ<?= e($level[1]) ?></span>
          <div class="small text-muted mt-1">🌿 <?= number_format($u['green_points']) ?> แต้ม · ลดได้ <?= kg(co2_saved($u['id'])) ?></div></div>
      </div>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label" for="name">ชื่อ-นามสกุล</label>
          <input id="name" name="name" class="form-control<?= invalid($errors, 'name') ?>" value="<?= e($f['name']) ?>" required minlength="2" maxlength="100">
          <?= feedback($errors, 'name', 'กรุณากรอกชื่อ-นามสกุล') ?></div>
        <div class="col-md-6"><label class="form-label" for="phone">เบอร์โทรศัพท์</label>
          <input id="phone" name="phone" class="form-control<?= invalid($errors, 'phone') ?>" value="<?= e($f['phone']) ?>" required pattern="0[0-9]{8,9}" inputmode="numeric" maxlength="10">
          <?= feedback($errors, 'phone', 'เบอร์โทรต้องเป็นตัวเลข 9-10 หลัก ขึ้นต้นด้วย 0') ?></div>
        <div class="col-12"><label class="form-label" for="email">อีเมล</label>
          <input id="email" name="email" type="email" class="form-control<?= invalid($errors, 'email') ?>" value="<?= e($f['email']) ?>" required maxlength="120">
          <?= feedback($errors, 'email', 'รูปแบบอีเมลไม่ถูกต้อง') ?></div>
        <div class="col-12"><label class="form-label" for="address">ที่อยู่</label>
          <input id="address" name="address" class="form-control<?= invalid($errors, 'address') ?>" value="<?= e($f['address']) ?>" maxlength="255">
          <?= feedback($errors, 'address', 'ที่อยู่ยาวเกินไป') ?></div>
        <div class="col-12"><label class="form-label" for="avatar">รูปโปรไฟล์ (ไม่บังคับ)</label>
          <input id="avatar" name="avatar" type="file" class="form-control" accept="image/*"></div>
      </div>
      <button class="btn btn-success mt-3">บันทึกข้อมูล</button>
    </form>
  </div>
  <div class="col-lg-5">
    <form class="panel needs-validation" method="post" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <h2>เปลี่ยนรหัสผ่าน</h2>
      <div class="mb-3"><label class="form-label" for="current">รหัสผ่านปัจจุบัน</label>
        <input id="current" name="current" type="password" class="form-control" required autocomplete="current-password">
        <div class="invalid-feedback">กรุณากรอกรหัสผ่านปัจจุบัน</div></div>
      <div class="mb-3"><label class="form-label" for="password">รหัสผ่านใหม่</label>
        <input id="password" name="password" type="password" class="form-control" required minlength="8" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,}" autocomplete="new-password">
        <div class="invalid-feedback">อย่างน้อย 8 ตัวอักษร มีทั้งตัวอักษรภาษาอังกฤษและตัวเลข</div></div>
      <div class="mb-3"><label class="form-label" for="password2">ยืนยันรหัสผ่านใหม่</label>
        <input id="password2" name="password2" type="password" class="form-control" required data-match="#password" autocomplete="new-password">
        <div class="invalid-feedback">ยืนยันรหัสผ่านไม่ตรงกัน</div></div>
      <button class="btn btn-outline-success">เปลี่ยนรหัสผ่าน</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
