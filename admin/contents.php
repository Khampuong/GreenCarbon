<?php
// จัดการเนื้อหา: เพิ่ม ลบ แก้ไข ค้นหา และตั้งวันเวลาเปิด-ปิดการแสดงผล
require __DIR__ . '/../includes/init.php';
$admin = require_admin();

$edit = (int) get('edit') > 0 ? row('SELECT * FROM contents WHERE id = ?', [(int) get('edit')]) : null;
$errors = [];

if (is_post() && post('action') === 'save') {
    $edit = (int) post('id') > 0 ? row('SELECT * FROM contents WHERE id = ?', [(int) post('id')]) : null;
    $publish = parse_datetime(post('publish_at'));
    $expire = parse_datetime(post('expire_at'));
    $mult = post('type') === 'campaign' ? (float) post('point_multiplier') : 1;
    if (!isset(CONTENT_TYPES[post('type')])) {
        $errors['type'] = 'กรุณาเลือกประเภท';
    }
    if (mb_strlen(post('title')) < 3 || mb_strlen(post('title')) > 150) {
        $errors['title'] = 'หัวข้อต้องยาว 3-150 ตัวอักษร';
    }
    if (mb_strlen(post('body')) < 5) {
        $errors['body'] = 'กรุณากรอกเนื้อหาอย่างน้อย 5 ตัวอักษร';
    }
    if (!$publish) {
        $errors['publish_at'] = 'กรุณาระบุวันเวลาเริ่มแสดงผล';
    }
    if (post('expire_at') !== '' && (!$expire || ($publish && $expire <= $publish))) {
        $errors['expire_at'] = 'วันเวลาสิ้นสุดต้องอยู่หลังวันเวลาเริ่มแสดงผล';
    }
    if (post('type') === 'campaign' && !$expire) {
        $errors['expire_at'] = 'แคมเปญต้องระบุวันเวลาสิ้นสุด';
    }
    if ($mult < 1 || $mult > 5) {
        $errors['point_multiplier'] = 'ตัวคูณแต้มต้องอยู่ระหว่าง 1-5';
    }
    $image = $errors ? null : upload_image('image', 'contents');
    if (!$errors && $image !== false) {
        $data = [post('type'), post('title'), post('body'), $mult, $publish, $expire, post('is_active') === '1' ? 1 : 0];
        if ($edit) {
            q('UPDATE contents SET type = ?, title = ?, body = ?, point_multiplier = ?, publish_at = ?, expire_at = ?, is_active = ?, image = COALESCE(?, image) WHERE id = ?', array_merge($data, [$image, $edit['id']]));
        } else {
            q('INSERT INTO contents (type, title, body, point_multiplier, publish_at, expire_at, is_active, image, created_by) VALUES (?,?,?,?,?,?,?,?,?)', array_merge($data, [$image, $admin['id']]));
        }
        flash($edit ? 'บันทึกการแก้ไขเนื้อหาแล้ว' : 'เพิ่มเนื้อหาแล้ว');
        redirect('admin/contents.php');
    }
    flash('กรุณาตรวจสอบข้อมูลที่กรอก', 'danger');
} elseif (is_post() && post('action') === 'delete') {
    q('DELETE FROM contents WHERE id = ?', [(int) post('id')]);
    flash('ลบเนื้อหาแล้ว');
    redirect('admin/contents.php');
}

$where = ['1 = 1'];
$args = [];
if (get('q') !== '') {
    $where[] = '(c.title LIKE ? OR c.body LIKE ?)';
    array_push($args, '%' . get('q') . '%', '%' . get('q') . '%');
}
if (isset(CONTENT_TYPES[get('type')])) {
    $where[] = 'c.type = ?';
    $args[] = get('type');
}
$all = rows('SELECT c.*, u.name AS author FROM contents c JOIN users u ON u.id = c.created_by WHERE ' . implode(' AND ', $where) . ' ORDER BY c.publish_at DESC', $args);
$statusFilter = in_array(get('status'), ['scheduled', 'live', 'expired', 'off'], true) ? get('status') : '';
$list = $statusFilter ? array_filter($all, function ($c) use ($statusFilter) { return content_status($c) === $statusFilter; }) : $all;

// ค่าในฟอร์ม: จากที่กรอกค้างไว้ > รายการที่กำลังแก้ไข > ค่าเริ่มต้น (เผยแพร่ทันที)
$dt = function ($v) { return $v ? date('Y-m-d\TH:i', strtotime($v)) : ''; };
if ($errors) {
    $f = ['type' => post('type'), 'title' => post('title'), 'body' => post('body'), 'point_multiplier' => post('point_multiplier'), 'publish_at' => post('publish_at'), 'expire_at' => post('expire_at'), 'is_active' => post('is_active') === '1'];
} elseif ($edit) {
    $f = ['publish_at' => $dt($edit['publish_at']), 'expire_at' => $dt($edit['expire_at'])] + $edit;
} else {
    $f = ['type' => 'news', 'title' => '', 'body' => '', 'point_multiplier' => 2, 'publish_at' => date('Y-m-d\TH:i'), 'expire_at' => '', 'is_active' => 1];
}

$title = 'จัดการเนื้อหา';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="row g-3">
  <div class="col-xl-4">
    <form class="panel needs-validation" method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
      <h2><?= $edit ? '✏️ แก้ไขเนื้อหา' : '➕ เพิ่มเนื้อหา' ?></h2>
      <div class="mb-3"><label class="form-label" for="type">ประเภท</label>
        <select id="type" name="type" class="form-select" required onchange="document.getElementById('mult-row').classList.toggle('d-none', this.value !== 'campaign')">
          <?php foreach (CONTENT_TYPES as $k => $v): ?><option value="<?= $k ?>" <?= $f['type'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="mb-3"><label class="form-label" for="title">หัวข้อ</label>
        <input id="title" name="title" class="form-control<?= invalid($errors, 'title') ?>" value="<?= e($f['title']) ?>" required minlength="3" maxlength="150">
        <?= feedback($errors, 'title', 'หัวข้อต้องยาว 3-150 ตัวอักษร') ?></div>
      <div class="mb-3"><label class="form-label" for="body">เนื้อหา</label>
        <textarea id="body" name="body" class="form-control<?= invalid($errors, 'body') ?>" rows="5" required minlength="5"><?= e($f['body']) ?></textarea>
        <?= feedback($errors, 'body', 'กรุณากรอกเนื้อหา') ?></div>
      <div class="mb-3 <?= $f['type'] === 'campaign' ? '' : 'd-none' ?>" id="mult-row"><label class="form-label" for="point_multiplier">ตัวคูณ Green Point ช่วงแคมเปญ</label>
        <input id="point_multiplier" name="point_multiplier" type="number" min="1" max="5" step="0.5" class="form-control<?= invalid($errors, 'point_multiplier') ?>" value="<?= e(num($f['point_multiplier'], 1)) ?>">
        <?= feedback($errors, 'point_multiplier', 'ตัวคูณแต้มต้องอยู่ระหว่าง 1-5') ?></div>
      <div class="row g-2 mb-3">
        <div class="col-sm-6 col-xl-12"><label class="form-label" for="publish_at">🕒 เริ่มแสดงผล (วันเวลาโพสต์)</label>
          <input id="publish_at" name="publish_at" type="datetime-local" class="form-control<?= invalid($errors, 'publish_at') ?>" value="<?= e($f['publish_at']) ?>" required>
          <?= feedback($errors, 'publish_at', 'กรุณาระบุวันเวลาเริ่มแสดงผล') ?></div>
        <div class="col-sm-6 col-xl-12"><label class="form-label" for="expire_at">🕒 สิ้นสุดการแสดงผล</label>
          <input id="expire_at" name="expire_at" type="datetime-local" class="form-control<?= invalid($errors, 'expire_at') ?>" value="<?= e($f['expire_at']) ?>">
          <?= feedback($errors, 'expire_at', 'วันเวลาสิ้นสุดต้องอยู่หลังวันเวลาเริ่ม') ?>
          <div class="form-text">เว้นว่าง = แสดงตลอดไป</div></div>
      </div>
      <div class="mb-3"><label class="form-label" for="image">รูปประกอบ (ไม่บังคับ)</label><input id="image" name="image" type="file" class="form-control" accept="image/*"></div>
      <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" <?= $f['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="is_active">เปิดใช้งาน (ปิดเพื่อซ่อนทันทีโดยไม่สนเวลา)</label></div>
      <button class="btn btn-success"><?= $edit ? 'บันทึกการแก้ไข' : 'เพิ่มเนื้อหา' ?></button>
      <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= e(url('admin/contents.php')) ?>">ยกเลิก</a><?php endif; ?>
    </form>
  </div>
  <div class="col-xl-8">
    <form class="panel mb-3" method="get">
      <div class="d-flex gap-2 flex-wrap">
        <input type="search" name="q" class="form-control form-control-sm" style="width:220px" value="<?= e(get('q')) ?>" placeholder="ค้นหาหัวข้อ / เนื้อหา" aria-label="ค้นหาเนื้อหา">
        <select name="type" class="form-select form-select-sm" style="width:auto" aria-label="ประเภท"><option value="">ทุกประเภท</option>
          <?php foreach (CONTENT_TYPES as $k => $v): ?><option value="<?= $k ?>" <?= get('type') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
        <select name="status" class="form-select form-select-sm" style="width:auto" aria-label="สถานะ"><option value="">ทุกสถานะ</option>
          <?php foreach (['scheduled', 'live', 'expired', 'off'] as $k): ?><option value="<?= $k ?>" <?= $statusFilter === $k ? 'selected' : '' ?>><?= e(STATUS_LABELS[$k][0]) ?></option><?php endforeach; ?></select>
        <button class="btn btn-success btn-sm">ค้นหา</button><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/contents.php')) ?>">ล้าง</a>
        <span class="small text-muted ms-auto align-self-center">พบ <?= count($list) ?> รายการ · เวลาเซิร์ฟเวอร์ <?= thai_date(date('Y-m-d H:i:s')) ?></span>
      </div>
    </form>
    <div class="panel">
      <?php if (!$list): ?><div class="empty">ไม่พบเนื้อหาตามเงื่อนไข</div><?php else: ?>
      <div class="table-responsive"><table class="table">
        <thead><tr><th data-sort>หัวข้อ</th><th data-sort>ประเภท</th><th data-sort>เริ่มแสดง</th><th data-sort>สิ้นสุด</th><th data-sort>สถานะ</th><th></th></tr></thead>
        <tbody><?php foreach ($list as $c): ?>
          <tr><td><?= e($c['title']) ?><?= $c['type'] === 'campaign' ? ' <span class="badge bg-warning text-dark">x' . num($c['point_multiplier'], 1) . '</span>' : '' ?><div class="small text-muted">โดย <?= e($c['author']) ?></div></td>
            <td class="text-nowrap"><?= e(CONTENT_TYPES[$c['type']]) ?></td>
            <td data-value="<?= e($c['publish_at']) ?>" class="text-nowrap"><?= thai_date($c['publish_at']) ?></td>
            <td data-value="<?= e($c['expire_at'] ?: '9999') ?>" class="text-nowrap"><?= $c['expire_at'] ? thai_date($c['expire_at']) : 'ไม่มีกำหนด' ?></td>
            <td><?= badge(content_status($c)) ?></td>
            <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-success" href="<?= e(url('admin/contents.php?edit=' . $c['id'])) ?>">แก้ไข</a>
              <form method="post" class="d-inline" data-confirm="ลบเนื้อหานี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-sm btn-outline-danger">ลบ</button></form></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
