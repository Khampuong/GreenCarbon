<?php
// ของรางวัล Green Point: เพิ่ม แก้ไข ลบ และส่งมอบของที่สมาชิกแลก
require __DIR__ . '/../includes/init.php';
require_admin();

if (is_post()) {
    if (post('action') === 'save') {
        if (mb_strlen(post('name')) < 2 || !ctype_digit(post('points_cost')) || post('points_cost') < 1 || !ctype_digit(post('stock'))) {
            flash('ข้อมูลของรางวัลไม่ถูกต้อง: ต้องมีชื่อ แต้มที่ใช้มากกว่า 0 และจำนวนคงเหลือเป็นเลขจำนวนเต็ม', 'danger');
        } else {
            $data = [mb_substr(post('name'), 0, 120), mb_substr(post('description'), 0, 255), (int) post('points_cost'), (int) post('stock'), post('is_active') === '1' ? 1 : 0];
            if ((int) post('id') > 0) {
                q('UPDATE rewards SET name = ?, description = ?, points_cost = ?, stock = ?, is_active = ? WHERE id = ?', array_merge($data, [(int) post('id')]));
            } else {
                q('INSERT INTO rewards (name, description, points_cost, stock, is_active) VALUES (?,?,?,?,?)', $data);
            }
            flash('บันทึกของรางวัลแล้ว');
        }
    } elseif (post('action') === 'delete') {
        if (val('SELECT 1 FROM redemptions WHERE reward_id = ? LIMIT 1', [(int) post('id')])) {
            q('UPDATE rewards SET is_active = 0 WHERE id = ?', [(int) post('id')]);
            flash('มีสมาชิกแลกของรางวัลนี้แล้ว จึงปิดการใช้งานแทนการลบ', 'warning');
        } else {
            q('DELETE FROM rewards WHERE id = ?', [(int) post('id')]);
            flash('ลบของรางวัลแล้ว');
        }
    } elseif (post('action') === 'deliver') {
        q("UPDATE redemptions SET status = 'delivered' WHERE id = ? AND status = 'pending'", [(int) post('id')]);
        flash('บันทึกการส่งมอบแล้ว');
    }
    redirect('admin/rewards.php');
}
$rewards = rows('SELECT * FROM rewards ORDER BY points_cost');
$edit = null;
foreach ($rewards as $r) {
    if ((int) $r['id'] === (int) get('edit')) {
        $edit = $r;
    }
}
$redemptions = rows("SELECT d.*, r.name AS reward, u.name AS user_name, u.phone FROM redemptions d JOIN rewards r ON r.id = d.reward_id JOIN users u ON u.id = d.user_id ORDER BY FIELD(d.status, 'pending') DESC, d.id DESC LIMIT 100");

$title = 'ของรางวัล';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="row g-3">
  <div class="col-xl-4">
    <form class="panel needs-validation" method="post" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit ? (int) $edit['id'] : 0 ?>">
      <h2><?= $edit ? '✏️ แก้ไขของรางวัล' : '➕ เพิ่มของรางวัล' ?></h2>
      <div class="mb-2"><label class="form-label small mb-1" for="name">ชื่อของรางวัล</label><input id="name" name="name" class="form-control form-control-sm" value="<?= e($edit ? $edit['name'] : '') ?>" required minlength="2" maxlength="120"><div class="invalid-feedback">กรอกชื่อของรางวัล</div></div>
      <div class="mb-2"><label class="form-label small mb-1" for="description">รายละเอียด</label><input id="description" name="description" class="form-control form-control-sm" value="<?= e($edit ? $edit['description'] : '') ?>" maxlength="255"></div>
      <div class="row g-2 mb-2">
        <div class="col-6"><label class="form-label small mb-1" for="points_cost">แต้มที่ใช้</label><input id="points_cost" name="points_cost" type="number" min="1" step="1" class="form-control form-control-sm" value="<?= e($edit ? $edit['points_cost'] : '') ?>" required></div>
        <div class="col-6"><label class="form-label small mb-1" for="stock">คงเหลือ (ชิ้น)</label><input id="stock" name="stock" type="number" min="0" step="1" class="form-control form-control-sm" value="<?= e($edit ? $edit['stock'] : '') ?>" required></div>
      </div>
      <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" <?= !$edit || $edit['is_active'] ? 'checked' : '' ?>><label class="form-check-label small" for="is_active">เปิดให้แลก</label></div>
      <button class="btn btn-success btn-sm">บันทึก</button>
      <?php if ($edit): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/rewards.php')) ?>">ยกเลิก</a><?php endif; ?>
    </form>
  </div>
  <div class="col-xl-8">
    <div class="panel">
      <h2>รายการของรางวัล</h2>
      <div class="table-responsive"><table class="table table-sm">
        <thead><tr><th data-sort>ของรางวัล</th><th data-sort="num" class="text-end">แต้ม</th><th data-sort="num" class="text-end">คงเหลือ</th><th data-sort>สถานะ</th><th></th></tr></thead>
        <tbody><?php foreach ($rewards as $r): ?>
          <tr><td><?= e($r['name']) ?><div class="small text-muted"><?= e($r['description']) ?></div></td><td class="text-end"><?= number_format($r['points_cost']) ?></td><td class="text-end"><?= (int) $r['stock'] ?></td>
            <td><?= badge($r['is_active'] ? 'active' : 'off') ?></td>
            <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-success" href="<?= e(url('admin/rewards.php?edit=' . $r['id'])) ?>">แก้ไข</a>
              <form method="post" class="d-inline" data-confirm="ลบของรางวัลนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-outline-danger">ลบ</button></form></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
    </div>
    <div class="panel">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><h2 class="mb-0">รายการแลกของรางวัล</h2>
        <input type="search" class="form-control form-control-sm" style="width:200px" placeholder="ค้นหารหัส / สมาชิก" data-filter="#redemptions" aria-label="ค้นหารายการแลก"></div>
      <?php if (!$redemptions): ?><div class="empty">ยังไม่มีรายการแลก</div><?php else: ?>
      <div class="table-responsive"><table class="table table-sm" id="redemptions">
        <thead><tr><th data-sort>วันที่</th><th data-sort>รหัส</th><th data-sort>สมาชิก</th><th data-sort>ของรางวัล</th><th data-sort="num" class="text-end">แต้ม</th><th data-sort>สถานะ</th><th></th></tr></thead>
        <tbody><?php foreach ($redemptions as $d): ?>
          <tr><td data-value="<?= e($d['created_at']) ?>" class="text-nowrap"><?= thai_date($d['created_at'], false) ?></td><td><code><?= e($d['code']) ?></code></td>
            <td><?= e($d['user_name']) ?><div class="small text-muted"><?= e($d['phone']) ?></div></td><td><?= e($d['reward']) ?></td><td class="text-end"><?= (int) $d['points_used'] ?></td><td><?= badge($d['status']) ?></td>
            <td class="text-end"><?php if ($d['status'] === 'pending'): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="deliver"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="btn btn-sm btn-success">ส่งมอบแล้ว</button></form><?php endif; ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
