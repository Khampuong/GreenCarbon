<?php
// รายการซื้อ-ขายของฉัน: ผู้ซื้อยืนยันรับสินค้าเพื่อปิดรายการและรับ Green Point
require __DIR__ . '/includes/init.php';

$u = require_login();
if (is_post()) {
    $o = row('SELECT * FROM orders WHERE id = ? AND (buyer_id = ? OR seller_id = ?)', [(int) post('id'), $u['id'], $u['id']]);
    if ($o && post('action') === 'complete' && (int) $o['buyer_id'] === (int) $u['id'] && complete_order($o['id'])) {
        flash('ยืนยันรับสินค้าแล้ว ทั้งผู้ซื้อและผู้ขายได้รับ Green Point และบันทึกคาร์บอนที่ลดได้');
    } elseif ($o && post('action') === 'cancel' && cancel_order($o['id'])) {
        flash('ยกเลิกรายการแล้ว สินค้ากลับไปแสดงในตลาด', 'warning');
    } else {
        flash('ไม่สามารถทำรายการได้', 'danger');
    }
    redirect('orders.php');
}
$orders = rows('SELECT o.*, p.title, c.co2_reuse_kg, b.name AS buyer_name, b.phone AS buyer_phone, s.name AS seller_name, s.phone AS seller_phone
    FROM orders o JOIN products p ON p.id = o.product_id JOIN categories c ON c.id = p.category_id JOIN users b ON b.id = o.buyer_id JOIN users s ON s.id = o.seller_id
    WHERE o.buyer_id = ? OR o.seller_id = ? ORDER BY o.id DESC', [$u['id'], $u['id']]);

$title = 'รายการซื้อ-ขาย';
require __DIR__ . '/includes/header.php';
?>
<?= page_header('รายการซื้อ-ขายของฉัน', 'ผู้ซื้อกดยืนยันเมื่อได้รับสินค้า เพื่อให้ทั้งสองฝ่ายได้รับแต้ม') ?>
<div class="panel">
  <input type="search" class="form-control form-control-sm mb-3" style="max-width:280px" placeholder="ค้นหาในรายการ..." data-filter="#orders" aria-label="ค้นหาในรายการ">
  <?php if (!$orders): ?><div class="empty">ยังไม่มีรายการ <a href="<?= e(url('market.php')) ?>">ไปที่ตลาดมือสอง</a></div><?php else: ?>
  <div class="table-responsive"><table class="table" id="orders">
    <thead><tr><th data-sort>วันที่</th><th data-sort>สินค้า</th><th data-sort>บทบาท</th><th>คู่ค้า / การรับสินค้า</th><th data-sort="num" class="text-end">ราคา</th><th data-sort>สถานะ</th><th></th></tr></thead>
    <tbody><?php foreach ($orders as $o): $buyer = (int) $o['buyer_id'] === (int) $u['id']; ?>
      <tr><td data-value="<?= e($o['created_at']) ?>" class="text-nowrap"><?= thai_date($o['created_at'], false) ?></td>
        <td><a href="<?= e(url('product.php?id=' . $o['product_id'])) ?>"><?= e($o['title']) ?></a><div class="small text-success">🌿 ลด <?= kg($o['co2_reuse_kg']) ?></div></td>
        <td><?= $buyer ? '🛒 ซื้อ' : '🏷️ ขาย' ?></td>
        <td class="small"><?= e($buyer ? $o['seller_name'] . ' ' . $o['seller_phone'] : $o['buyer_name'] . ' ' . $o['buyer_phone']) ?><div class="text-muted"><?= e($o['contact_note']) ?></div></td>
        <td class="text-end" data-value="<?= (float) $o['price'] ?>"><?= baht($o['price']) ?></td><td><?= badge($o['status']) ?></td>
        <td class="text-end text-nowrap"><?php if ($o['status'] === 'pending'): ?>
          <?php if ($buyer): ?><form method="post" class="d-inline" data-confirm="ยืนยันว่าได้รับสินค้าแล้ว?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $o['id'] ?>"><input type="hidden" name="action" value="complete"><button class="btn btn-sm btn-success">ได้รับสินค้าแล้ว</button></form><?php endif; ?>
          <form method="post" class="d-inline" data-confirm="ยกเลิกรายการนี้?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $o['id'] ?>"><input type="hidden" name="action" value="cancel"><button class="btn btn-sm btn-outline-danger">ยกเลิก</button></form>
        <?php endif; ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
