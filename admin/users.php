<?php
// จัดการสมาชิก: ค้นหา ระงับ / ยกเลิกระงับสิทธิ์ เปลี่ยนบทบาท
require __DIR__ . '/../includes/init.php';
$admin = require_admin();

if (is_post()) {
    $t = row('SELECT * FROM users WHERE id = ?', [(int) post('id')]);
    if (!$t || (int) $t['id'] === (int) $admin['id']) {
        flash('ไม่สามารถทำรายการกับบัญชีนี้ได้', 'danger');
    } elseif (post('action') === 'status') {
        $new = $t['status'] === 'active' ? 'suspended' : 'active';
        q('UPDATE users SET status = ? WHERE id = ?', [$new, $t['id']]);
        flash($new === 'suspended' ? 'ระงับสิทธิ์ ' . $t['name'] . ' แล้ว' : 'ยกเลิกการระงับสิทธิ์ ' . $t['name'] . ' แล้ว', $new === 'suspended' ? 'warning' : 'success');
    } elseif (post('action') === 'role') {
        q('UPDATE users SET role = ? WHERE id = ?', [$t['role'] === 'admin' ? 'user' : 'admin', $t['id']]);
        flash('เปลี่ยนบทบาทของ ' . $t['name'] . ' แล้ว');
    }
    back('admin/users.php');
}

$where = ['1 = 1'];
$args = [];
if (get('q') !== '') {
    $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    array_push($args, '%' . get('q') . '%', '%' . get('q') . '%', '%' . get('q') . '%');
}
if (in_array(get('status'), ['active', 'suspended'], true)) {
    $where[] = 'u.status = ?';
    $args[] = get('status');
}
if (in_array(get('role'), ['user', 'admin'], true)) {
    $where[] = 'u.role = ?';
    $args[] = get('role');
}
$users = rows('SELECT u.*, (SELECT COALESCE(SUM(co2_saved_kg), 0) FROM green_activities a WHERE a.user_id = u.id) AS co2,
    (SELECT total_kg FROM carbon_footprints f WHERE f.user_id = u.id ORDER BY year DESC LIMIT 1) AS footprint
    FROM users u WHERE ' . implode(' AND ', $where) . ' ORDER BY u.id DESC', $args);

$title = 'จัดการสมาชิก';
require __DIR__ . '/../includes/admin_header.php';
?>
<form class="panel mb-3" method="get">
  <div class="d-flex gap-2 flex-wrap">
    <input type="search" name="q" class="form-control form-control-sm" style="width:240px" value="<?= e(get('q')) ?>" placeholder="ค้นหาชื่อ อีเมล เบอร์โทร" aria-label="ค้นหาสมาชิก">
    <select name="status" class="form-select form-select-sm" style="width:auto" aria-label="สถานะ"><option value="">ทุกสถานะ</option>
      <option value="active" <?= get('status') === 'active' ? 'selected' : '' ?>>ใช้งานได้</option><option value="suspended" <?= get('status') === 'suspended' ? 'selected' : '' ?>>ระงับสิทธิ์</option></select>
    <select name="role" class="form-select form-select-sm" style="width:auto" aria-label="บทบาท"><option value="">ทุกบทบาท</option>
      <option value="user" <?= get('role') === 'user' ? 'selected' : '' ?>>สมาชิก</option><option value="admin" <?= get('role') === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ</option></select>
    <button class="btn btn-success btn-sm">ค้นหา</button><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/users.php')) ?>">ล้าง</a>
    <span class="small text-muted ms-auto align-self-center">พบ <?= count($users) ?> คน</span>
  </div>
</form>
<div class="panel">
  <div class="table-responsive"><table class="table">
    <thead><tr><th data-sort>สมาชิก</th><th data-sort>บทบาท</th><th data-sort>สถานะ</th><th data-sort="num" class="text-end">แต้ม</th><th data-sort="num" class="text-end">ฟุตพริ้นท์ (kg)</th><th data-sort="num" class="text-end">ลดได้ (kg)</th><th data-sort>วันที่สมัคร</th><th></th></tr></thead>
    <tbody><?php foreach ($users as $t): ?>
      <tr><td><div class="d-flex align-items-center gap-2"><?= avatar_html($t, 32) ?><div><?= e($t['name']) ?><div class="small text-muted"><?= e($t['email']) ?> · <?= e($t['phone']) ?></div></div></div></td>
        <td><?= $t['role'] === 'admin' ? '<span class="badge bg-dark">ผู้ดูแลระบบ</span>' : 'สมาชิก' ?></td><td><?= badge($t['status']) ?></td>
        <td class="text-end"><?= number_format($t['green_points']) ?></td><td class="text-end"><?= $t['footprint'] !== null ? num($t['footprint'], 0) : '-' ?></td><td class="text-end"><?= num($t['co2'], 1) ?></td>
        <td data-value="<?= e($t['created_at']) ?>" class="text-nowrap"><?= thai_date($t['created_at'], false) ?></td>
        <td class="text-end text-nowrap"><?php if ((int) $t['id'] !== (int) $admin['id']): ?>
          <form method="post" class="d-inline" data-confirm="<?= $t['status'] === 'active' ? 'ระงับสิทธิ์สมาชิกคนนี้? สมาชิกจะเข้าสู่ระบบไม่ได้' : 'ยกเลิกการระงับสิทธิ์สมาชิกคนนี้?' ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="action" value="status">
            <button class="btn btn-sm <?= $t['status'] === 'active' ? 'btn-outline-danger' : 'btn-success' ?>"><?= $t['status'] === 'active' ? 'ระงับสิทธิ์' : 'ยกเลิกระงับ' ?></button></form>
          <form method="post" class="d-inline" data-confirm="เปลี่ยนบทบาทของสมาชิกคนนี้?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="action" value="role">
            <button class="btn btn-sm btn-outline-secondary"><?= $t['role'] === 'admin' ? 'ลดเป็นสมาชิก' : 'ตั้งเป็นผู้ดูแล' ?></button></form>
        <?php else: ?><span class="small text-muted">บัญชีของคุณ</span><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
