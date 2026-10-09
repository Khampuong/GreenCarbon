<?php
// ข่าวสารและบทความ: แสดงเฉพาะเนื้อหาที่อยู่ในช่วงวันเวลาที่ผู้ดูแลตั้งไว้
require __DIR__ . '/includes/init.php';

$one = (int) get('id') > 0 ? row("SELECT c.*, u.name AS author FROM contents c JOIN users u ON u.id = c.created_by WHERE c.id = ? AND c.type <> 'banner' AND " . CONTENT_LIVE, [(int) get('id')]) : null;
if ((int) get('id') > 0 && !$one) {
    flash('ไม่พบเนื้อหานี้ หรืออยู่นอกช่วงเวลาแสดงผล', 'warning');
    redirect('news.php');
}
$args = [];
$kw = '';
if (get('q') !== '') {
    $kw = ' AND (title LIKE ? OR body LIKE ?)';
    $args = ['%' . get('q') . '%', '%' . get('q') . '%'];
}
$list = $one ? [] : rows("SELECT * FROM contents WHERE type <> 'banner' AND " . CONTENT_LIVE . $kw . ' ORDER BY publish_at DESC', $args);

$title = $one ? $one['title'] : 'ข่าวสาร';
require __DIR__ . '/includes/header.php';
?>
<?php if ($one): ?>
  <nav class="small mb-3"><a href="<?= e(url('news.php')) ?>">ข่าวสาร</a> › <?= e(CONTENT_TYPES[$one['type']]) ?></nav>
  <article class="panel">
    <h1 class="h3 fw-bold"><?= e($one['title']) ?></h1>
    <p class="small text-muted">เผยแพร่ <?= thai_date($one['publish_at']) ?> · โดย <?= e($one['author']) ?><?= $one['expire_at'] ? ' · แสดงถึง ' . thai_date($one['expire_at']) : '' ?></p>
    <?php if ($one['image']): ?><img class="img-fluid rounded mb-3" src="<?= e(url($one['image'])) ?>" alt=""><?php endif; ?>
    <div style="white-space:pre-line"><?= e($one['body']) ?></div>
  </article>
<?php else: ?>
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
    <?= page_header('ข่าวสารและบทความ', 'ความรู้และกิจกรรมด้านสิ่งแวดล้อม') ?>
    <form method="get" class="d-flex gap-2"><input type="search" name="q" class="form-control form-control-sm" value="<?= e(get('q')) ?>" placeholder="ค้นหาข่าว..." aria-label="ค้นหาข่าว"><button class="btn btn-success btn-sm">ค้นหา</button></form>
  </div>
  <?php if (!$list): ?><div class="panel empty">ยังไม่มีข่าวสารในขณะนี้</div><?php endif; ?>
  <div class="row g-3">
  <?php foreach ($list as $c): ?>
    <div class="col-md-6"><a class="panel d-block h-100 text-decoration-none text-reset" href="<?= e(url('news.php?id=' . $c['id'])) ?>">
      <span class="badge bg-<?= $c['type'] === 'campaign' ? 'warning text-dark' : 'success' ?>"><?= e(CONTENT_TYPES[$c['type']]) ?></span>
      <h2 class="mt-2 mb-1"><?= e($c['title']) ?></h2>
      <p class="small text-muted mb-1"><?= e(mb_strimwidth($c['body'], 0, 140, '…')) ?></p>
      <span class="small text-muted"><?= thai_date($c['publish_at'], false) ?></span>
    </a></div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
