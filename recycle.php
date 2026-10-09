<?php
// แจ้งรีไซเคิล E-Waste และติดตามสถานะ
require __DIR__ . '/includes/init.php';

$u = require_login();
$cats = rows('SELECT * FROM categories ORDER BY id');
$errors = [];

if (is_post() && post('action') === 'cancel') {
    $done = q("UPDATE recycle_requests SET status = 'rejected' WHERE id = ? AND user_id = ? AND status = 'pending'", [(int) post('id'), $u['id']])->rowCount();
    flash($done ? 'ยกเลิกคำขอแล้ว' : 'ไม่สามารถยกเลิกคำขอนี้ได้', $done ? 'warning' : 'danger');
    redirect('recycle.php');
} elseif (is_post()) {
    if (!val('SELECT 1 FROM categories WHERE id = ?', [(int) post('category_id')])) {
        $errors['category_id'] = 'กรุณาเลือกประเภทอุปกรณ์';
    }
    if (mb_strlen(post('item_name')) < 3 || mb_strlen(post('item_name')) > 150) {
        $errors['item_name'] = 'กรุณาระบุรายการ 3-150 ตัวอักษร';
    }
    if (!ctype_digit(post('quantity')) || post('quantity') < 1 || post('quantity') > 999) {
        $errors['quantity'] = 'จำนวนต้องเป็นเลข 1-999';
    }
    if (!is_numeric(post('weight_kg')) || post('weight_kg') <= 0 || post('weight_kg') > 999) {
        $errors['weight_kg'] = 'น้ำหนักต้องมากกว่า 0 และไม่เกิน 999 กก.';
    }
    if (!isset(RECYCLE_METHODS[post('method')])) {
        $errors['method'] = 'กรุณาเลือกวิธีส่ง';
    }
    if (!$errors) {
        q('INSERT INTO recycle_requests (user_id, category_id, item_name, quantity, weight_kg, method, note) VALUES (?,?,?,?,?,?,?)', [
            $u['id'], (int) post('category_id'), post('item_name'), (int) post('quantity'), round(post('weight_kg'), 2), post('method'), mb_substr(post('note'), 0, 255),
        ]);
        notify_admin('recycle', 'คำขอรีไซเคิลใหม่', post('item_name') . ' (' . num(post('weight_kg')) . ' กก.) โดย ' . $u['name'], 'admin/recycles.php?status=pending', $u['id']);
        flash('ส่งคำขอรีไซเคิลแล้ว เมื่อผู้ดูแลตรวจรับอุปกรณ์ คุณจะได้รับ Green Point');
        redirect('recycle.php');
    }
    flash('กรุณาตรวจสอบข้อมูลที่กรอก', 'danger');
}
$list = rows('SELECT r.*, c.name AS cat_name, c.icon FROM recycle_requests r JOIN categories c ON c.id = r.category_id WHERE r.user_id = ? ORDER BY r.id DESC', [$u['id']]);
$sum = row("SELECT COALESCE(SUM(weight_kg), 0) AS kg, COALESCE(SUM(co2_saved_kg), 0) AS co2, COUNT(*) AS n FROM recycle_requests WHERE user_id = ? AND status = 'completed'", [$u['id']]);
$rate = (int) setting('points_recycle_per_kg', 20);

$title = 'แจ้งรีไซเคิล';
require __DIR__ . '/includes/header.php';
?>
<?= page_header('แจ้งรีไซเคิล E-Waste', 'ส่งอุปกรณ์ที่ใช้ไม่ได้แล้วเข้าสู่กระบวนการรีไซเคิลที่ถูกวิธี รับ ' . $rate . ' แต้มต่อ 1 กก.') ?>
<div class="row g-3 mb-3">
  <div class="col-4"><div class="stat"><div class="label">รีไซเคิลสำเร็จ</div><div class="value"><?= (int) $sum['n'] ?></div><div class="unit">ครั้ง</div></div></div>
  <div class="col-4"><div class="stat"><div class="label">E-Waste ที่ส่ง</div><div class="value"><?= num($sum['kg']) ?></div><div class="unit">กก.</div></div></div>
  <div class="col-4"><div class="stat green"><div class="label">คาร์บอนที่ลดได้</div><div class="value"><?= num($sum['co2']) ?></div><div class="unit">kgCO₂e</div></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-5">
    <form class="panel needs-validation" method="post" novalidate>
      <?= csrf_field() ?>
      <h2>แจ้งความประสงค์</h2>
      <div class="mb-3"><label class="form-label" for="category_id">ประเภทอุปกรณ์</label>
        <select id="category_id" name="category_id" class="form-select<?= invalid($errors, 'category_id') ?>" required><option value="">— เลือก —</option>
          <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= (int) post('category_id') === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['icon'] . ' ' . $c['name']) ?> (ลด <?= num($c['co2_recycle_per_kg']) ?> kg/กก.)</option><?php endforeach; ?></select>
        <?= feedback($errors, 'category_id', 'กรุณาเลือกประเภทอุปกรณ์') ?></div>
      <div class="mb-3"><label class="form-label" for="item_name">รายการ</label>
        <input id="item_name" name="item_name" class="form-control<?= invalid($errors, 'item_name') ?>" value="<?= e(post('item_name')) ?>" required minlength="3" maxlength="150" placeholder="เช่น โทรศัพท์เก่าเปิดไม่ติด">
        <?= feedback($errors, 'item_name', 'กรุณาระบุรายการ 3-150 ตัวอักษร') ?></div>
      <div class="row g-3 mb-3">
        <div class="col-6"><label class="form-label" for="quantity">จำนวน (ชิ้น)</label>
          <input id="quantity" name="quantity" type="number" min="1" max="999" step="1" class="form-control<?= invalid($errors, 'quantity') ?>" value="<?= e(post('quantity', '1')) ?>" required>
          <?= feedback($errors, 'quantity', 'จำนวนต้องเป็นเลข 1-999') ?></div>
        <div class="col-6"><label class="form-label" for="weight_kg">น้ำหนักโดยประมาณ (กก.)</label>
          <input id="weight_kg" name="weight_kg" type="number" min="0.01" max="999" step="0.01" class="form-control<?= invalid($errors, 'weight_kg') ?>" value="<?= e(post('weight_kg')) ?>" required>
          <?= feedback($errors, 'weight_kg', 'น้ำหนักต้องมากกว่า 0') ?></div>
      </div>
      <div class="mb-3"><label class="form-label" for="method">วิธีส่งอุปกรณ์</label>
        <select id="method" name="method" class="form-select" required>
          <?php foreach (RECYCLE_METHODS as $k => $v): ?><option value="<?= $k ?>" <?= post('method') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      <div class="mb-3"><label class="form-label" for="note">หมายเหตุ (ไม่บังคับ)</label>
        <input id="note" name="note" class="form-control" value="<?= e(post('note')) ?>" maxlength="255" placeholder="เช่น วันเวลาที่สะดวก"></div>
      <button class="btn btn-success">♻️ ส่งคำขอ</button>
    </form>
  </div>
  <div class="col-lg-7">
    <div class="panel">
      <h2>ติดตามสถานะ</h2>
      <?php if (!$list): ?><div class="empty">ยังไม่มีคำขอรีไซเคิล</div><?php else: ?>
      <div class="table-responsive"><table class="table table-sm">
        <thead><tr><th data-sort>วันที่</th><th data-sort>รายการ</th><th data-sort="num" class="text-end">กก.</th><th data-sort>สถานะ</th><th data-sort="num" class="text-end">แต้ม</th><th data-sort="num" class="text-end">CO₂e</th><th></th></tr></thead>
        <tbody><?php foreach ($list as $r): ?>
          <tr><td data-value="<?= e($r['created_at']) ?>" class="text-nowrap"><?= thai_date($r['created_at'], false) ?></td>
            <td><?= e($r['icon'] . ' ' . $r['item_name']) ?><div class="small text-muted"><?= e($r['cat_name']) ?> · <?= (int) $r['quantity'] ?> ชิ้น · <?= e(RECYCLE_METHODS[$r['method']]) ?></div></td>
            <td class="text-end"><?= num($r['weight_kg']) ?></td><td><?= badge($r['status']) ?></td>
            <td class="text-end"><?= $r['points_awarded'] ? '+' . (int) $r['points_awarded'] : '-' ?></td><td class="text-end"><?= $r['co2_saved_kg'] > 0 ? num($r['co2_saved_kg']) : '-' ?></td>
            <td class="text-end"><?php if ($r['status'] === 'pending'): ?><form method="post" data-confirm="ยกเลิกคำขอนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger">ยกเลิก</button></form><?php endif; ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
