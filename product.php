<?php
require __DIR__ . '/includes/init.php';

$p = row('SELECT p.*, c.name AS cat_name, c.icon AS cat_icon, c.co2_reuse_kg, u.name AS seller_name, u.phone AS seller_phone
    FROM products p JOIN categories c ON c.id = p.category_id JOIN users u ON u.id = p.user_id WHERE p.id = ?', [(int) get('id')]);
$me = user();
$mine = $p && $me && (int) $p['user_id'] === (int) $me['id'];
if (!$p || ($p['status'] !== 'approved' && !$mine && !($me && $me['role'] === 'admin'))) {
    flash('ไม่พบสินค้านี้ หรือสินค้าถูกขายไปแล้ว', 'warning');
    redirect('market.php');
}

if (is_post()) {
    $me = require_login();
    if ($mine) {
        flash('ไม่สามารถสั่งซื้อสินค้าของตนเองได้', 'danger');
    } elseif (mb_strlen(post('contact_note')) < 5 || mb_strlen(post('contact_note')) > 255) {
        flash('กรุณาระบุวิธีรับสินค้า / ที่อยู่จัดส่ง 5-255 ตัวอักษร', 'danger');
    } else {
        $ok = in_tx(function () use ($p, $me) {
            // จองสินค้าด้วยการเปลี่ยนสถานะ ป้องกันการสั่งซื้อซ้อนกัน
            if (!q("UPDATE products SET status = 'sold' WHERE id = ? AND status = 'approved'", [$p['id']])->rowCount()) {
                return false;
            }
            q('INSERT INTO orders (product_id, buyer_id, seller_id, price, contact_note) VALUES (?,?,?,?,?)', [$p['id'], $me['id'], $p['user_id'], $p['price'], post('contact_note')]);
            return true;
        });
        if ($ok) {
            notify_admin('order', 'คำสั่งซื้อใหม่: ' . $p['title'], $me['name'] . ' สั่งซื้อจาก ' . $p['seller_name'], 'admin/reports.php?type=green', $me['id']);
            flash('สั่งซื้อแล้ว ผู้ขายจะติดต่อกลับ เมื่อได้รับสินค้ากรุณากดยืนยันเพื่อรับ Green Point');
            redirect('orders.php');
        }
        flash('สินค้านี้มีผู้สั่งซื้อไปแล้ว', 'warning');
    }
    redirect('product.php?id=' . $p['id']);
}

$title = $p['title'];
require __DIR__ . '/includes/header.php';
?>
<nav class="small mb-3"><a href="<?= e(url('market.php')) ?>">ตลาดมือสอง</a> › <a href="<?= e(url('market.php?cat=' . $p['category_id'])) ?>"><?= e($p['cat_name']) ?></a></nav>
<div class="row g-4">
  <div class="col-md-6"><div class="product-detail-img"><?= product_thumb($p) ?></div></div>
  <div class="col-md-6">
    <h1 class="h3 fw-bold"><?= e($p['title']) ?> <?= $p['status'] !== 'approved' ? badge($p['status']) : '' ?></h1>
    <div class="price fs-2"><?= baht($p['price']) ?></div>
    <p class="text-muted"><?= e($p['cat_icon'] . ' ' . $p['cat_name']) ?> · สภาพ<?= CONDITIONS[$p['item_condition']] ?> · ลงขายเมื่อ <?= thai_date($p['created_at'], false) ?></p>
    <div class="tip mb-3">🌿 ซื้อชิ้นนี้แทนเครื่องใหม่ ช่วยลดได้ประมาณ <strong><?= kg($p['co2_reuse_kg']) ?></strong> (เท่ากับต้นไม้ <?= num(trees($p['co2_reuse_kg']), 1) ?> ต้นดูดซับ 1 ปี)
      ผู้ซื้อรับ <?= (int) setting('points_buy', 10) ?> แต้ม ผู้ขายรับ <?= (int) setting('points_sell', 20) ?> แต้ม</div>
    <p style="white-space:pre-line"><?= e($p['description']) ?></p>
    <p class="small text-muted">ผู้ขาย: <?= e($p['seller_name']) ?></p>
    <?php if ($mine): ?>
      <a class="btn btn-outline-success" href="<?= e(url('sell.php?edit=' . $p['id'])) ?>">✏️ แก้ไขสินค้าของฉัน</a>
    <?php elseif ($p['status'] === 'approved'): ?>
      <form class="panel needs-validation" method="post" novalidate>
        <?= csrf_field() ?>
        <label class="form-label" for="contact_note">วิธีรับสินค้า / ที่อยู่จัดส่ง</label>
        <textarea id="contact_note" name="contact_note" class="form-control" rows="2" required minlength="5" maxlength="255" placeholder="เช่น นัดรับที่วิทยาลัย หรือที่อยู่สำหรับส่งพัสดุ"><?= $me ? e($me['address']) : '' ?></textarea>
        <div class="invalid-feedback">กรุณาระบุ 5-255 ตัวอักษร</div>
        <button class="btn btn-success w-100 mt-3" data-confirm="ยืนยันสั่งซื้อสินค้านี้? (ชำระเงินกับผู้ขายโดยตรงเมื่อรับสินค้า)">🛒 สั่งซื้อ</button>
        <div class="form-text">ชำระเงินกับผู้ขายโดยตรงเมื่อรับสินค้า</div>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
