<?php
// Green Point: ระดับสมาชิก อันดับ ของรางวัล และประวัติกิจกรรมทั้งหมด
require __DIR__ . '/includes/init.php';

$u = require_login();
if (is_post()) {
    $msg = in_tx(function () use ($u) {
        $r = row('SELECT * FROM rewards WHERE id = ? AND is_active = 1 FOR UPDATE', [(int) post('reward_id')]);
        if (!$r || $r['stock'] < 1) {
            flash('ของรางวัลนี้หมดแล้ว', 'danger');
            return false;
        }
        $code = 'GL' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        q('INSERT INTO redemptions (user_id, reward_id, points_used, code) VALUES (?,?,?,?)', [$u['id'], $r['id'], $r['points_cost'], $code]);
        if (!add_activity($u['id'], 'redeem', db()->lastInsertId(), 'แลกของรางวัล: ' . $r['name'], -$r['points_cost'])) {
            flash('แต้มของคุณไม่พอสำหรับของรางวัลนี้', 'danger');
            return false;
        }
        q('UPDATE rewards SET stock = stock - 1 WHERE id = ?', [$r['id']]);
        notify_admin('redeem', 'แลกของรางวัล: ' . $r['name'], $u['name'] . ' รหัส ' . $code, 'admin/rewards.php', $u['id']);
        return 'แลก "' . $r['name'] . '" สำเร็จ รหัสรับของ: ' . $code;
    });
    if ($msg) {
        flash($msg);
    }
    redirect('points.php');
}

list($level, $next, $earned) = user_level($u['id']);
$saved = co2_saved($u['id']);
$rewards = rows('SELECT * FROM rewards WHERE is_active = 1 ORDER BY points_cost');
$redemptions = rows('SELECT d.*, r.name FROM redemptions d JOIN rewards r ON r.id = d.reward_id WHERE d.user_id = ? ORDER BY d.id DESC LIMIT 10', [$u['id']]);
$type = isset(ACTIVITY_TYPES[get('type')]) ? get('type') : '';
$acts = $type
    ? rows('SELECT * FROM green_activities WHERE user_id = ? AND type = ? ORDER BY id DESC LIMIT 100', [$u['id'], $type])
    : rows('SELECT * FROM green_activities WHERE user_id = ? ORDER BY id DESC LIMIT 100', [$u['id']]);
$leaders = rows("SELECT u.id, u.name, u.avatar, COALESCE(SUM(a.co2_saved_kg), 0) AS co2, COALESCE(SUM(IF(a.points > 0, a.points, 0)), 0) AS pts
    FROM users u LEFT JOIN green_activities a ON a.user_id = u.id WHERE u.role = 'user' AND u.status = 'active' GROUP BY u.id ORDER BY co2 DESC, pts DESC LIMIT 5");
$campaign = active_campaign();

$title = 'Green Point';
require __DIR__ . '/includes/header.php';
?>
<?= page_header('Green Point และกิจกรรมของฉัน', 'ทุกกิจกรรมรักษ์โลกได้ทั้งแต้มและคาร์บอนที่ลดได้') ?>
<?php if ($campaign): ?><div class="campaign-bar mb-3">🎉 <strong><?= e($campaign['title']) ?></strong> — รับแต้ม x<?= num($campaign['point_multiplier'], 1) ?> ถึง <?= thai_date($campaign['expire_at']) ?></div><?php endif; ?>
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="stat green"><div class="label">แต้มที่ใช้ได้</div><div class="value"><?= number_format($u['green_points']) ?></div><div class="unit">Green Point</div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="label">คาร์บอนที่ลดได้ทั้งหมด</div><div class="value text-success"><?= num($saved, 1) ?></div><div class="unit">kgCO₂e</div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="label">เทียบเท่าต้นไม้</div><div class="value">🌳 <?= num(trees($saved), 1) ?></div><div class="unit">ต้นดูดซับ 1 ปี</div></div></div>
  <div class="col-6 col-lg-3"><div class="stat"><div class="label">ระดับสมาชิก</div><div class="value"><?= $level[2] ?> <span class="fs-6"><?= e($level[1]) ?></span></div>
    <?php if ($next): ?>
      <div class="progress mt-1" style="height:.5rem"><div class="progress-bar bg-success" style="width:<?= round(($earned - $level[0]) / ($next[0] - $level[0]) * 100) ?>%"></div></div>
      <div class="unit">อีก <?= number_format($next[0] - $earned) ?> แต้ม เป็น<?= e($next[1]) ?></div>
    <?php else: ?><div class="unit">ระดับสูงสุดแล้ว</div><?php endif; ?></div></div>
</div>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="panel">
      <h2>แลกของรางวัล</h2>
      <div class="row g-2">
      <?php foreach ($rewards as $r): $can = $u['green_points'] >= $r['points_cost'] && $r['stock'] > 0; ?>
        <div class="col-sm-6"><div class="border rounded-3 p-3 h-100 d-flex flex-column">
          <strong>🎁 <?= e($r['name']) ?></strong><div class="small text-muted flex-grow-1"><?= e($r['description']) ?></div>
          <div class="d-flex justify-content-between align-items-center mt-2"><span class="chip"><?= number_format($r['points_cost']) ?> แต้ม</span>
            <form method="post" data-confirm="ใช้ <?= (int) $r['points_cost'] ?> แต้มแลกของรางวัลนี้?"><?= csrf_field() ?><input type="hidden" name="reward_id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-success" <?= $can ? '' : 'disabled' ?>><?= $r['stock'] < 1 ? 'หมด' : ($can ? 'แลก' : 'แต้มไม่พอ') ?></button></form></div>
          <div class="small text-muted mt-1">คงเหลือ <?= (int) $r['stock'] ?> ชิ้น</div>
        </div></div>
      <?php endforeach; ?>
      </div>
      <?php if ($redemptions): ?>
        <h2 class="mt-4">ของรางวัลที่แลกแล้ว</h2>
        <table class="table table-sm mb-0"><tbody><?php foreach ($redemptions as $d): ?>
          <tr><td><?= e($d['name']) ?><div class="small text-muted"><?= thai_date($d['created_at']) ?></div></td><td><code><?= e($d['code']) ?></code></td><td class="text-end"><?= badge($d['status']) ?></td></tr>
        <?php endforeach; ?></tbody></table>
      <?php endif; ?>
    </div>
    <div class="panel">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h2 class="mb-0">ประวัติกิจกรรม</h2>
        <form method="get" class="d-flex gap-2">
          <select name="type" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="กรองประเภทกิจกรรม"><option value="">ทุกประเภท</option>
            <?php foreach (ACTIVITY_TYPES as $k => $v): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($v[0]) ?></option><?php endforeach; ?></select>
          <input type="search" class="form-control form-control-sm" placeholder="ค้นหา..." data-filter="#acts" aria-label="ค้นหากิจกรรม">
        </form>
      </div>
      <?php if (!$acts): ?><div class="empty">ยังไม่มีกิจกรรม เริ่มจาก<a href="<?= e(url('footprint.php')) ?>">คำนวณคาร์บอนฟุตพริ้นท์</a></div><?php else: ?>
      <div class="table-responsive"><table class="table table-sm" id="acts">
        <thead><tr><th data-sort>วันที่</th><th data-sort>กิจกรรม</th><th data-sort="num" class="text-end">แต้ม</th><th data-sort="num" class="text-end">CO₂e ที่ลด</th></tr></thead>
        <tbody><?php foreach ($acts as $a): ?>
          <tr><td data-value="<?= e($a['created_at']) ?>" class="text-nowrap"><?= thai_date($a['created_at'], false) ?></td>
            <td><?= ACTIVITY_TYPES[$a['type']][1] ?> <?= e($a['description']) ?></td>
            <td class="text-end fw-semibold <?= $a['points'] < 0 ? 'text-danger' : 'text-success' ?>"><?= ($a['points'] > 0 ? '+' : '') . (int) $a['points'] ?></td>
            <td class="text-end"><?= $a['co2_saved_kg'] > 0 ? num($a['co2_saved_kg']) : '-' ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="panel">
      <h2>🏅 อันดับผู้ลดคาร์บอนสูงสุด</h2>
      <?php foreach ($leaders as $i => $l): ?>
        <div class="d-flex align-items-center gap-2 py-2 <?= $i ? 'border-top' : '' ?> <?= (int) $l['id'] === (int) $u['id'] ? 'fw-bold' : '' ?>">
          <span class="rank"><?= $i + 1 ?></span><?= avatar_html($l, 32) ?><span class="flex-grow-1"><?= e($l['name']) ?><?= (int) $l['id'] === (int) $u['id'] ? ' (คุณ)' : '' ?></span>
          <span class="text-success"><?= num($l['co2'], 1) ?> kg</span>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="panel">
      <h2>วิธีรับแต้ม</h2>
      <table class="table table-sm mb-0"><tbody>
        <tr><td>🧮 คำนวณคาร์บอนฟุตพริ้นท์ครั้งแรกของปี</td><td class="text-end text-success">+<?= (int) setting('points_footprint', 30) ?></td></tr>
        <tr><td>🏷️ ขายสินค้ามือสองสำเร็จ</td><td class="text-end text-success">+<?= (int) setting('points_sell', 20) ?></td></tr>
        <tr><td>🛒 ซื้อสินค้ามือสองสำเร็จ</td><td class="text-end text-success">+<?= (int) setting('points_buy', 10) ?></td></tr>
        <tr><td>♻️ รีไซเคิล E-Waste (ต่อ 1 กก.)</td><td class="text-end text-success">+<?= (int) setting('points_recycle_per_kg', 20) ?></td></tr>
      </tbody></table>
      <div class="small text-muted mt-2">ระดับ: <?php foreach (LEVELS as $lv): ?><span class="me-2 text-nowrap"><?= $lv[2] ?> <?= e($lv[1]) ?> (<?= number_format($lv[0]) ?>+)</span><?php endforeach; ?></div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
