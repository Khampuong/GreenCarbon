<?php
// การแจ้งเตือนสำหรับผู้ดูแลระบบ: สมาชิกใหม่ คำขอรีไซเคิล สินค้ารออนุมัติ คำสั่งซื้อ แลกของรางวัล รายงานปัญหา
require __DIR__ . '/../includes/init.php';
require_admin();

// เปิดจาก Dashboard หรือกระดิ่ง: ทำเครื่องหมายอ่านแล้ว แล้วพาไปยังหน้าที่เกี่ยวข้อง
if ((int) get('open') > 0) {
    $n = row('SELECT * FROM notifications WHERE id = ?', [(int) get('open')]);
    if ($n) {
        q('UPDATE notifications SET is_read = 1 WHERE id = ?', [$n['id']]);
        redirect($n['link'] !== '' && $n['type'] !== 'issue' ? $n['link'] : 'admin/notifications.php?type=' . $n['type']);
    }
}
if (is_post()) {
    if (post('action') === 'read_all') {
        q('UPDATE notifications SET is_read = 1');
        flash('ทำเครื่องหมายอ่านแล้วทั้งหมด');
    } elseif (post('action') === 'toggle') {
        q('UPDATE notifications SET is_read = 1 - is_read WHERE id = ?', [(int) post('id')]);
    } elseif (post('action') === 'delete') {
        q('DELETE FROM notifications WHERE id = ?', [(int) post('id')]);
        flash('ลบการแจ้งเตือนแล้ว');
    }
    back('admin/notifications.php');
}

$where = ['1 = 1'];
$args = [];
if (isset(NOTI_TYPES[get('type')])) {
    $where[] = 'n.type = ?';
    $args[] = get('type');
}
if (get('unread') === '1') {
    $where[] = 'n.is_read = 0';
}
if (get('q') !== '') {
    $where[] = '(n.title LIKE ? OR n.message LIKE ? OR u.name LIKE ?)';
    array_push($args, '%' . get('q') . '%', '%' . get('q') . '%', '%' . get('q') . '%');
}
$list = rows('SELECT n.*, u.name AS user_name FROM notifications n LEFT JOIN users u ON u.id = n.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY n.id DESC LIMIT 200', $args);

$title = 'การแจ้งเตือน';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="panel mb-3">
  <div class="d-flex justify-content-between flex-wrap gap-2">
    <form method="get" class="d-flex gap-2 flex-wrap">
      <input type="search" name="q" class="form-control form-control-sm" style="width:200px" value="<?= e(get('q')) ?>" placeholder="ค้นหา..." aria-label="ค้นหา">
      <select name="type" class="form-select form-select-sm" style="width:auto" aria-label="ประเภท"><option value="">ทุกประเภท</option>
        <?php foreach (NOTI_TYPES as $k => $v): ?><option value="<?= $k ?>" <?= get('type') === $k ? 'selected' : '' ?>><?= e($v[1] . ' ' . $v[0]) ?></option><?php endforeach; ?></select>
      <div class="form-check align-self-center"><input class="form-check-input" type="checkbox" name="unread" value="1" id="unread" <?= get('unread') === '1' ? 'checked' : '' ?>><label class="form-check-label small" for="unread">เฉพาะที่ยังไม่อ่าน</label></div>
      <button class="btn btn-success btn-sm">กรอง</button>
    </form>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all"><button class="btn btn-outline-success btn-sm" <?= $unread ? '' : 'disabled' ?>>✓ อ่านแล้วทั้งหมด (<?= $unread ?>)</button></form>
  </div>
</div>
<?php if (!$list): ?><div class="panel empty">ไม่มีการแจ้งเตือนตามเงื่อนไข</div><?php endif; ?>
<?php foreach ($list as $n): ?>
  <div class="panel noti <?= $n['is_read'] ? '' : 'unread' ?> py-2 mb-2 mt-0">
    <div class="d-flex justify-content-between gap-2 flex-wrap">
      <div>
        <span class="badge bg-light text-dark border"><?= NOTI_TYPES[$n['type']][1] ?> <?= e(NOTI_TYPES[$n['type']][0]) ?></span>
        <strong class="ms-1"><?= e($n['title']) ?></strong>
        <div class="small"><?= e($n['message']) ?></div>
        <div class="small text-muted"><?= thai_date($n['created_at']) ?><?= $n['user_name'] ? ' · ' . e($n['user_name']) : '' ?></div>
      </div>
      <div class="d-flex gap-1 align-items-start">
        <?php if ($n['link'] !== '' && $n['type'] !== 'issue'): ?><a class="btn btn-sm btn-success" href="<?= e(url('admin/notifications.php?open=' . $n['id'])) ?>">เปิด</a><?php endif; ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $n['id'] ?>"><button class="btn btn-sm btn-outline-secondary text-nowrap"><?= $n['is_read'] ? 'ยังไม่อ่าน' : 'อ่านแล้ว' ?></button></form>
        <form method="post" data-confirm="ลบการแจ้งเตือนนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $n['id'] ?>"><button class="btn btn-sm btn-outline-danger">ลบ</button></form>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
