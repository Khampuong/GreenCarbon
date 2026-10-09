<?php
// อนุมัติ / ปฏิเสธ / ลบ สินค้ามือสอง
require __DIR__ . '/../includes/init.php';
require_admin();

if (is_post()) {
    $p = row('SELECT * FROM products WHERE id = ?', [(int) post('id')]);
    if ($p && in_array(post('action'), ['approved', 'rejected'], true) && $p['status'] !== 'sold') {
        q('UPDATE products SET status = ? WHERE id = ?', [post('action'), $p['id']]);
        flash(post('action') === 'approved' ? 'อนุมัติสินค้าแล้ว' : 'ไม่อนุมัติสินค้านี้', post('action') === 'approved' ? 'success' : 'warning');
    } elseif ($p && post('action') === 'delete' && $p['status'] !== 'sold') {
        q('DELETE FROM products WHERE id = ?', [$p['id']]);
        flash('ลบสินค้าแล้ว');
    } else {
        flash('ไม่สามารถทำรายการได้', 'danger');
    }
    back('admin/products.php');
}

$where = ['1 = 1'];
$args = [];
if (get('q') !== '') {
    $where[] = '(p.title LIKE ? OR u.name LIKE ?)';
    array_push($args, '%' . get('q') . '%', '%' . get('q') . '%');
}
if (in_array(get('status'), ['pending', 'approved', 'rejected', 'sold', 'hidden'], true)) {
    $where[] = 'p.status = ?';
    $args[] = get('status');
}
$list = rows("SELECT p.*, c.name AS cat_name, c.icon, c.co2_reuse_kg, u.name AS seller FROM products p JOIN categories c ON c.id = p.category_id JOIN users u ON u.id = p.user_id
    WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(p.status, 'pending') DESC, p.id DESC", $args);

$title = 'อนุมัติสินค้า';
require __DIR__ . '/../includes/admin_header.php';
?>
<form class="panel mb-3" method="get">
  <div class="d-flex gap-2 flex-wrap">
    <input type="search" name="q" class="form-control form-control-sm" style="width:240px" value="<?= e(get('q')) ?>" placeholder="ค้นหาสินค้า / ผู้ขาย" aria-label="ค้นหาสินค้า">
    <select name="status" class="form-select form-select-sm" style="width:auto" aria-label="สถานะ"><option value="">ทุกสถานะ</option>
      <?php foreach (['pending', 'approved', 'rejected', 'sold', 'hidden'] as $k): ?><option value="<?= $k ?>" <?= get('status') === $k ? 'selected' : '' ?>><?= e(STATUS_LABELS[$k][0]) ?></option><?php endforeach; ?></select>
    <button class="btn btn-success btn-sm">ค้นหา</button><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/products.php')) ?>">ล้าง</a>
    <span class="small text-muted ms-auto align-self-center">พบ <?= count($list) ?> รายการ</span>
  </div>
</form>
<div class="panel">
  <?php if (!$list): ?><div class="empty">ไม่พบสินค้าตามเงื่อนไข</div><?php else: ?>
  <div class="table-responsive"><table class="table">
    <thead><tr><th data-sort>สินค้า</th><th data-sort>ผู้ขาย</th><th data-sort="num" class="text-end">ราคา</th><th data-sort="num" class="text-end">ลด CO₂e (kg)</th><th data-sort>สถานะ</th><th data-sort>วันที่ลง</th><th></th></tr></thead>
    <tbody><?php foreach ($list as $p): ?>
      <tr><td><a href="<?= e(url('product.php?id=' . $p['id'])) ?>" target="_blank" rel="noopener"><?= e($p['title']) ?></a><div class="small text-muted"><?= e($p['icon'] . ' ' . $p['cat_name']) ?> · สภาพ<?= CONDITIONS[$p['item_condition']] ?></div></td>
        <td><?= e($p['seller']) ?></td><td class="text-end" data-value="<?= (float) $p['price'] ?>"><?= baht($p['price']) ?></td><td class="text-end"><?= num($p['co2_reuse_kg'], 0) ?></td>
        <td><?= badge($p['status']) ?></td><td data-value="<?= e($p['created_at']) ?>" class="text-nowrap"><?= thai_date($p['created_at'], false) ?></td>
        <td class="text-end text-nowrap"><?php if ($p['status'] !== 'sold'): ?>
          <?php foreach (['approved' => ['อนุมัติ', 'btn-success'], 'rejected' => ['ไม่อนุมัติ', 'btn-outline-warning']] as $act => $b): if ($p['status'] !== $act): ?>
            <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="<?= $act ?>"><button class="btn btn-sm <?= $b[1] ?>"><?= $b[0] ?></button></form>
          <?php endif; endforeach; ?>
          <form method="post" class="d-inline" data-confirm="ลบสินค้านี้?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger">ลบ</button></form>
        <?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
