<?php
// ตลาดมือสอง: ค้นหา กรอง และเรียงลำดับ
require __DIR__ . '/includes/init.php';

const SORTS = [
    'new' => ['มาใหม่ล่าสุด', 'p.id DESC'],
    'price_asc' => ['ราคาต่ำ → สูง', 'p.price ASC'],
    'price_desc' => ['ราคาสูง → ต่ำ', 'p.price DESC'],
    'co2' => ['ลดคาร์บอนมากที่สุด', 'c.co2_reuse_kg DESC, p.id DESC'],
];
$where = ["p.status = 'approved'"];
$args = [];
if (get('q') !== '') {
    $where[] = '(p.title LIKE ? OR p.description LIKE ?)';
    $args[] = '%' . get('q') . '%';
    $args[] = '%' . get('q') . '%';
}
if ((int) get('cat') > 0) {
    $where[] = 'p.category_id = ?';
    $args[] = (int) get('cat');
}
if (isset(CONDITIONS[get('cond')])) {
    $where[] = 'p.item_condition = ?';
    $args[] = get('cond');
}
if (is_numeric(get('max')) && get('max') > 0) {
    $where[] = 'p.price <= ?';
    $args[] = (float) get('max');
}
$sort = isset(SORTS[get('sort')]) ? get('sort') : 'new';
$products = rows('SELECT p.*, c.name AS cat_name, c.icon AS cat_icon, c.co2_reuse_kg FROM products p JOIN categories c ON c.id = p.category_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . SORTS[$sort][1] . ' LIMIT 60', $args);
$cats = rows('SELECT * FROM categories ORDER BY id');

$title = 'ตลาดมือสอง';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
  <?= page_header('ตลาดอุปกรณ์ดิจิทัลมือสอง', 'ทุกชิ้นที่ถูกใช้ซ้ำ ช่วยลดคาร์บอนจากการผลิตเครื่องใหม่') ?>
  <a class="btn btn-success" href="<?= e(url('sell.php')) ?>">🏷️ ลงขายสินค้า</a>
</div>
<form class="panel mb-3" method="get">
  <div class="row g-2 align-items-end">
    <div class="col-md-4"><label class="form-label small mb-1" for="q">ค้นหา</label><input id="q" type="search" name="q" class="form-control" value="<?= e(get('q')) ?>" placeholder="ชื่อสินค้า รุ่น ยี่ห้อ"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="cat">หมวด</label>
      <select id="cat" name="cat" class="form-select"><option value="">ทั้งหมด</option>
        <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= (int) get('cat') === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['icon'] . ' ' . $c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="cond">สภาพ</label>
      <select id="cond" name="cond" class="form-select"><option value="">ทั้งหมด</option>
        <?php foreach (CONDITIONS as $k => $v): ?><option value="<?= $k ?>" <?= get('cond') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="max">ราคาไม่เกิน</label><input id="max" type="number" min="0" name="max" class="form-control" value="<?= e(get('max')) ?>" placeholder="บาท"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1" for="sort">เรียงตาม</label>
      <select id="sort" name="sort" class="form-select"><?php foreach (SORTS as $k => $v): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= e($v[0]) ?></option><?php endforeach; ?></select></div>
    <div class="col-12 d-flex gap-2"><button class="btn btn-success btn-sm">ค้นหา</button><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('market.php')) ?>">ล้างตัวกรอง</a>
      <span class="small text-muted ms-auto align-self-center">พบ <?= count($products) ?> รายการ</span></div>
  </div>
</form>
<?php if (!$products): ?>
  <div class="panel empty">ไม่พบสินค้าตามเงื่อนไข ลองเปลี่ยนคำค้นหรือล้างตัวกรอง</div>
<?php endif; ?>
<div class="row g-3">
  <?php foreach ($products as $p): ?><div class="col-6 col-md-4 col-lg-3"><?= product_card($p) ?></div><?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
