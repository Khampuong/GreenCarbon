<?php
// จัดการรีไซเคิล: รับคำขอ ตรวจรับด้วยน้ำหนักจริง (ให้แต้ม + CO2e) หรือปฏิเสธ
require __DIR__ . '/../includes/init.php';
require_admin();

if (is_post()) {
    $id = (int) post('id');
    if (post('action') === 'accept' && q("UPDATE recycle_requests SET status = 'accepted' WHERE id = ? AND status = 'pending'", [$id])->rowCount()) {
        flash('รับคำขอแล้ว รอตรวจรับอุปกรณ์');
    } elseif (post('action') === 'reject' && q("UPDATE recycle_requests SET status = 'rejected' WHERE id = ? AND status IN ('pending', 'accepted')", [$id])->rowCount()) {
        flash('ปฏิเสธคำขอแล้ว', 'warning');
    } elseif (post('action') === 'complete' && is_numeric(post('weight_kg')) && post('weight_kg') <= 999 && complete_recycle($id, round(post('weight_kg'), 2))) {
        flash('ตรวจรับเรียบร้อย ระบบให้ Green Point และบันทึกคาร์บอนที่ลดได้แล้ว');
    } else {
        flash('ไม่สามารถทำรายการได้ กรุณาตรวจสอบน้ำหนักและสถานะ', 'danger');
    }
    back('admin/recycles.php');
}

$where = ['1 = 1'];
$args = [];
if (get('q') !== '') {
    $where[] = '(r.item_name LIKE ? OR u.name LIKE ?)';
    array_push($args, '%' . get('q') . '%', '%' . get('q') . '%');
}
if (in_array(get('status'), ['pending', 'accepted', 'completed', 'rejected'], true)) {
    $where[] = 'r.status = ?';
    $args[] = get('status');
}
$list = rows("SELECT r.*, c.name AS cat_name, c.icon, c.co2_recycle_per_kg, u.name AS user_name, u.phone FROM recycle_requests r JOIN categories c ON c.id = r.category_id JOIN users u ON u.id = r.user_id
    WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(r.status, 'accepted', 'pending') DESC, r.id DESC", $args);
$rate = (float) setting('points_recycle_per_kg', 20);

$title = 'จัดการรีไซเคิล';
require __DIR__ . '/../includes/admin_header.php';
?>
<form class="panel mb-3" method="get">
  <div class="d-flex gap-2 flex-wrap">
    <input type="search" name="q" class="form-control form-control-sm" style="width:240px" value="<?= e(get('q')) ?>" placeholder="ค้นหารายการ / สมาชิก" aria-label="ค้นหาคำขอ">
    <select name="status" class="form-select form-select-sm" style="width:auto" aria-label="สถานะ"><option value="">ทุกสถานะ</option>
      <?php foreach (['pending', 'accepted', 'completed', 'rejected'] as $k): ?><option value="<?= $k ?>" <?= get('status') === $k ? 'selected' : '' ?>><?= e(STATUS_LABELS[$k][0]) ?></option><?php endforeach; ?></select>
    <button class="btn btn-success btn-sm">ค้นหา</button><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/recycles.php')) ?>">ล้าง</a>
    <span class="small text-muted ms-auto align-self-center">พบ <?= count($list) ?> รายการ · <?= num($rate, 0) ?> แต้ม/กก.</span>
  </div>
</form>
<div class="panel">
  <?php if (!$list): ?><div class="empty">ไม่พบคำขอตามเงื่อนไข</div><?php else: ?>
  <div class="table-responsive"><table class="table">
    <thead><tr><th data-sort>วันที่</th><th data-sort>รายการ</th><th data-sort>สมาชิก</th><th data-sort="num" class="text-end">กก.</th><th data-sort>สถานะ</th><th data-sort="num" class="text-end">แต้ม</th><th data-sort="num" class="text-end">CO₂e</th><th>ดำเนินการ</th></tr></thead>
    <tbody><?php foreach ($list as $r): ?>
      <tr><td data-value="<?= e($r['created_at']) ?>" class="text-nowrap"><?= thai_date($r['created_at'], false) ?></td>
        <td><?= e($r['icon'] . ' ' . $r['item_name']) ?><div class="small text-muted"><?= e($r['cat_name']) ?> · <?= (int) $r['quantity'] ?> ชิ้น · <?= e(RECYCLE_METHODS[$r['method']]) ?><?= $r['note'] !== '' ? ' · ' . e($r['note']) : '' ?></div></td>
        <td><?= e($r['user_name']) ?><div class="small text-muted"><?= e($r['phone']) ?></div></td>
        <td class="text-end"><?= num($r['weight_kg']) ?></td><td><?= badge($r['status']) ?></td>
        <td class="text-end"><?= $r['points_awarded'] ? (int) $r['points_awarded'] : '-' ?></td><td class="text-end"><?= $r['co2_saved_kg'] > 0 ? num($r['co2_saved_kg']) : '-' ?></td>
        <td class="text-nowrap"><?php if (in_array($r['status'], ['pending', 'accepted'], true)): ?>
          <?php if ($r['status'] === 'pending'): ?><form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="accept"><button class="btn btn-sm btn-outline-success">รับคำขอ</button></form><?php endif; ?>
          <form method="post" class="d-inline-flex gap-1 align-items-center" data-confirm="ยืนยันตรวจรับด้วยน้ำหนักนี้? ระบบจะให้แต้มทันที"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="complete">
            <input type="number" name="weight_kg" min="0.01" max="999" step="0.01" value="<?= e((float) $r['weight_kg']) ?>" class="form-control form-control-sm" style="width:80px" required aria-label="น้ำหนักจริง (กก.)" title="น้ำหนักที่ชั่งจริง (กก.)">
            <button class="btn btn-sm btn-success">ตรวจรับ</button></form>
          <form method="post" class="d-inline" data-confirm="ปฏิเสธคำขอนี้?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-sm btn-outline-danger">ปฏิเสธ</button></form>
        <?php else: ?><span class="small text-muted"><?= $r['completed_at'] ? thai_date($r['completed_at'], false) : '-' ?></span><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
