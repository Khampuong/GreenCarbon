<?php
// ลงขาย / แก้ไข / ซ่อน / ลบ สินค้ามือสองของฉัน
require __DIR__ . '/includes/init.php';

$u = require_login();
$edit = (int) get('edit') > 0 ? row("SELECT * FROM products WHERE id = ? AND user_id = ? AND status <> 'sold'", [(int) get('edit'), $u['id']]) : null;
$errors = [];

if (is_post() && post('action') === 'save') {
    $edit = (int) post('id') > 0 ? row("SELECT * FROM products WHERE id = ? AND user_id = ? AND status <> 'sold'", [(int) post('id'), $u['id']]) : null;
    if (mb_strlen(post('title')) < 3 || mb_strlen(post('title')) > 150) {
        $errors['title'] = 'ชื่อสินค้าต้องยาว 3-150 ตัวอักษร';
    }
    if (!val('SELECT 1 FROM categories WHERE id = ?', [(int) post('category_id')])) {
        $errors['category_id'] = 'กรุณาเลือกหมวด';
    }
    if (!is_numeric(post('price')) || post('price') <= 0 || post('price') > 9999999) {
        $errors['price'] = 'ราคาต้องเป็นตัวเลขมากกว่า 0';
    }
    if (!isset(CONDITIONS[post('item_condition')])) {
        $errors['item_condition'] = 'กรุณาเลือกสภาพสินค้า';
    }
    if (mb_strlen(post('description')) < 10) {
        $errors['description'] = 'กรุณาอธิบายสินค้าอย่างน้อย 10 ตัวอักษร';
    }
    $image = $errors ? null : upload_image('image', 'products');
    if (!$errors && $image !== false) {
        $data = [(int) post('category_id'), post('title'), post('description'), (float) post('price'), post('item_condition')];
        if ($edit) {
            // แก้ไขแล้วต้องให้ผู้ดูแลตรวจสอบใหม่
            q("UPDATE products SET category_id = ?, title = ?, description = ?, price = ?, item_condition = ?, image = COALESCE(?, image), status = 'pending' WHERE id = ?", array_merge($data, [$image, $edit['id']]));
        } else {
            q('INSERT INTO products (category_id, title, description, price, item_condition, image, user_id) VALUES (?,?,?,?,?,?,?)', array_merge($data, [$image, $u['id']]));
        }
        notify_admin('product', 'สินค้ารออนุมัติ', post('title'), 'admin/products.php?status=pending', $u['id']);
        flash('บันทึกสินค้าแล้ว รอผู้ดูแลระบบอนุมัติก่อนแสดงในตลาด');
        redirect('sell.php');
    }
    flash('กรุณาตรวจสอบข้อมูลสินค้า', 'danger');
} elseif (is_post()) {
    $p = row("SELECT * FROM products WHERE id = ? AND user_id = ? AND status <> 'sold'", [(int) post('id'), $u['id']]);
    if ($p && post('action') === 'delete') {
        q('DELETE FROM products WHERE id = ?', [$p['id']]);
        flash('ลบสินค้าแล้ว');
    } elseif ($p && post('action') === 'toggle' && in_array($p['status'], ['approved', 'hidden'], true)) {
        q('UPDATE products SET status = ? WHERE id = ?', [$p['status'] === 'hidden' ? 'approved' : 'hidden', $p['id']]);
        flash($p['status'] === 'hidden' ? 'กลับมาแสดงสินค้าแล้ว' : 'ซ่อนสินค้าแล้ว');
    }
    redirect('sell.php');
}

$f = is_post() ? $_POST : ($edit ?: ['title' => '', 'category_id' => '', 'price' => '', 'item_condition' => 'good', 'description' => '']);
$cats = rows('SELECT * FROM categories ORDER BY id');
$mine = rows('SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON c.id = p.category_id WHERE p.user_id = ? ORDER BY p.id DESC', [$u['id']]);

$title = 'ลงขายสินค้า';
require __DIR__ . '/includes/header.php';
?>
<?= page_header($edit ? 'แก้ไขสินค้า' : 'ลงขายอุปกรณ์มือสอง', 'ส่งต่ออุปกรณ์ที่ไม่ใช้แล้ว รับ ' . (int) setting('points_sell', 20) . ' แต้มเมื่อขายสำเร็จ') ?>
<div class="row g-3">
  <div class="col-lg-5">
    <form class="panel needs-validation" method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
      <div class="mb-3"><label class="form-label" for="title">ชื่อสินค้า</label>
        <input id="title" name="title" class="form-control<?= invalid($errors, 'title') ?>" value="<?= e($f['title']) ?>" required minlength="3" maxlength="150">
        <?= feedback($errors, 'title', 'ชื่อสินค้าต้องยาว 3-150 ตัวอักษร') ?></div>
      <div class="row g-3 mb-3">
        <div class="col-7"><label class="form-label" for="category_id">หมวด</label>
          <select id="category_id" name="category_id" class="form-select<?= invalid($errors, 'category_id') ?>" required><option value="">— เลือก —</option>
            <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= (int) $f['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['icon'] . ' ' . $c['name']) ?></option><?php endforeach; ?></select>
          <?= feedback($errors, 'category_id', 'กรุณาเลือกหมวด') ?></div>
        <div class="col-5"><label class="form-label" for="item_condition">สภาพ</label>
          <select id="item_condition" name="item_condition" class="form-select" required>
            <?php foreach (CONDITIONS as $k => $v): ?><option value="<?= $k ?>" <?= $f['item_condition'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="mb-3"><label class="form-label" for="price">ราคา (บาท)</label>
        <input id="price" name="price" type="number" min="1" max="9999999" step="1" class="form-control<?= invalid($errors, 'price') ?>" value="<?= e($f['price'] === '' ? '' : (float) $f['price']) ?>" required>
        <?= feedback($errors, 'price', 'ราคาต้องเป็นตัวเลขมากกว่า 0') ?></div>
      <div class="mb-3"><label class="form-label" for="description">รายละเอียด</label>
        <textarea id="description" name="description" class="form-control<?= invalid($errors, 'description') ?>" rows="4" required minlength="10"><?= e($f['description']) ?></textarea>
        <?= feedback($errors, 'description', 'กรุณาอธิบายสินค้าอย่างน้อย 10 ตัวอักษร') ?></div>
      <div class="mb-3"><label class="form-label" for="image">รูปสินค้า (ไม่บังคับ)</label>
        <input id="image" name="image" type="file" class="form-control" accept="image/*" data-preview="#preview">
        <img id="preview" class="img-fluid rounded mt-2 <?= $edit && $edit['image'] ? '' : 'd-none' ?>" style="max-height:160px" src="<?= $edit && $edit['image'] ? e(url($edit['image'])) : '' ?>" alt="ตัวอย่างรูปสินค้า"></div>
      <button class="btn btn-success"><?= $edit ? 'บันทึกการแก้ไข' : 'ลงขาย' ?></button>
      <?php if ($edit): ?><a class="btn btn-outline-secondary" href="<?= e(url('sell.php')) ?>">ยกเลิก</a><?php endif; ?>
    </form>
  </div>
  <div class="col-lg-7">
    <div class="panel">
      <h2>สินค้าของฉัน (<?= count($mine) ?>)</h2>
      <?php if (!$mine): ?><div class="empty">ยังไม่มีสินค้า เริ่มลงขายชิ้นแรกได้เลย</div><?php else: ?>
      <div class="table-responsive"><table class="table table-sm">
        <thead><tr><th data-sort>สินค้า</th><th data-sort="num" class="text-end">ราคา</th><th data-sort>สถานะ</th><th></th></tr></thead>
        <tbody><?php foreach ($mine as $p): ?>
          <tr><td><a href="<?= e(url('product.php?id=' . $p['id'])) ?>"><?= e($p['title']) ?></a><div class="small text-muted"><?= e($p['cat_name']) ?></div></td>
            <td class="text-end" data-value="<?= (float) $p['price'] ?>"><?= baht($p['price']) ?></td><td><?= badge($p['status']) ?></td>
            <td class="text-end text-nowrap"><?php if ($p['status'] !== 'sold'): ?>
              <a class="btn btn-sm btn-outline-success" href="<?= e(url('sell.php?edit=' . $p['id'])) ?>">แก้ไข</a>
              <?php if (in_array($p['status'], ['approved', 'hidden'], true)): ?>
                <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn btn-sm btn-outline-secondary"><?= $p['status'] === 'hidden' ? 'แสดง' : 'ซ่อน' ?></button></form>
              <?php endif; ?>
              <form method="post" class="d-inline" data-confirm="ลบสินค้านี้?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger">ลบ</button></form>
            <?php endif; ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
