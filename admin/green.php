<?php
// ตั้งค่า Green: ค่าสัมประสิทธิ์การปล่อย (Emission Factor) ค่าคาร์บอนของหมวดอุปกรณ์ และอัตราแต้ม
require __DIR__ . '/../includes/init.php';
require_admin();

if (is_post()) {
    $num = function ($key, $max) {
        return is_numeric(post($key)) && post($key) >= 0 && post($key) <= $max;
    };
    if (post('action') === 'factor_save') {
        if (!isset(FACTOR_GROUPS[post('category')]) || mb_strlen(post('name')) < 3 || post('unit') === '' || !isset(PERIODS[(int) post('periods_per_year')]) || !$num('factor', 100000) || post('factor') <= 0) {
            flash('ข้อมูลค่าสัมประสิทธิ์ไม่ถูกต้อง: ต้องมีชื่อ หน่วย รอบการกรอก และค่ามากกว่า 0', 'danger');
        } else {
            $data = [post('category'), mb_substr(post('name'), 0, 120), mb_substr(post('unit'), 0, 30), (int) post('periods_per_year'), (float) post('factor'), post('is_active') === '1' ? 1 : 0];
            if ((int) post('id') > 0) {
                q('UPDATE emission_factors SET category = ?, name = ?, unit = ?, periods_per_year = ?, factor = ?, is_active = ? WHERE id = ?', array_merge($data, [(int) post('id')]));
            } else {
                q('INSERT INTO emission_factors (category, name, unit, periods_per_year, factor, is_active) VALUES (?,?,?,?,?,?)', $data);
            }
            flash('บันทึกค่าสัมประสิทธิ์แล้ว (มีผลกับการคำนวณครั้งถัดไป)');
        }
    } elseif (post('action') === 'factor_delete') {
        // รายการที่สมาชิกเคยใช้คำนวณแล้วให้ปิดการใช้งานแทนการลบ เพื่อรักษาประวัติ
        if (val('SELECT 1 FROM carbon_footprint_items WHERE factor_id = ? LIMIT 1', [(int) post('id')])) {
            q('UPDATE emission_factors SET is_active = 0 WHERE id = ?', [(int) post('id')]);
            flash('รายการนี้ถูกใช้คำนวณแล้ว จึงปิดการใช้งานแทนการลบ', 'warning');
        } else {
            q('DELETE FROM emission_factors WHERE id = ?', [(int) post('id')]);
            flash('ลบค่าสัมประสิทธิ์แล้ว');
        }
    } elseif (post('action') === 'category_save') {
        if (mb_strlen(post('name')) < 2 || !$num('co2_reuse_kg', 99999) || !$num('co2_recycle_per_kg', 99999)) {
            flash('ข้อมูลหมวดอุปกรณ์ไม่ถูกต้อง', 'danger');
        } else {
            $data = [mb_substr(post('name'), 0, 80), mb_substr(post('icon'), 0, 4) ?: '📦', (float) post('co2_reuse_kg'), (float) post('co2_recycle_per_kg')];
            if ((int) post('id') > 0) {
                q('UPDATE categories SET name = ?, icon = ?, co2_reuse_kg = ?, co2_recycle_per_kg = ? WHERE id = ?', array_merge($data, [(int) post('id')]));
            } else {
                q('INSERT INTO categories (name, icon, co2_reuse_kg, co2_recycle_per_kg) VALUES (?,?,?,?)', $data);
            }
            flash('บันทึกหมวดอุปกรณ์แล้ว');
        }
    } elseif (post('action') === 'category_delete') {
        if (val('SELECT 1 FROM products WHERE category_id = ? LIMIT 1', [(int) post('id')]) || val('SELECT 1 FROM recycle_requests WHERE category_id = ? LIMIT 1', [(int) post('id')])) {
            flash('ลบไม่ได้: มีสินค้าหรือคำขอรีไซเคิลใช้หมวดนี้อยู่', 'danger');
        } else {
            q('DELETE FROM categories WHERE id = ?', [(int) post('id')]);
            flash('ลบหมวดอุปกรณ์แล้ว');
        }
    } elseif (post('action') === 'settings') {
        $ok = true;
        foreach (rows('SELECT name FROM settings') as $s) {
            $v = isset($_POST['s'][$s['name']]) && is_string($_POST['s'][$s['name']]) ? trim($_POST['s'][$s['name']]) : '';
            if (!is_numeric($v) || $v < 0 || $v > 1000000) {
                $ok = false;
                continue;
            }
            q('UPDATE settings SET value = ? WHERE name = ?', [$v, $s['name']]);
        }
        flash($ok ? 'บันทึกการตั้งค่าแล้ว' : 'บางค่าไม่ถูกต้อง (ต้องเป็นตัวเลขไม่ติดลบ) จึงไม่ถูกบันทึก', $ok ? 'success' : 'warning');
    }
    redirect('admin/green.php');
}

$factors = rows("SELECT f.*, (SELECT COUNT(*) FROM carbon_footprint_items i WHERE i.factor_id = f.id) AS used FROM emission_factors f ORDER BY FIELD(f.category, 'energy', 'transport', 'food', 'waste', 'device'), f.id");
$cats = rows('SELECT * FROM categories ORDER BY id');
$settings = rows('SELECT * FROM settings ORDER BY name');
$editF = null;
foreach ($factors as $f) {
    if ((int) $f['id'] === (int) get('factor')) {
        $editF = $f;
    }
}
$editC = null;
foreach ($cats as $c) {
    if ((int) $c['id'] === (int) get('cat')) {
        $editC = $c;
    }
}

$title = 'ตั้งค่า Green';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="tip mb-3">ค่าทั้งหมดในหน้านี้ควบคุมการคำนวณคาร์บอนฟุตพริ้นท์ (Net Zero Man) คาร์บอนที่ลดได้จากการใช้ซ้ำ/รีไซเคิล และ Green Point ของทั้งระบบ ควรปรับให้ตรงกับค่าอ้างอิงล่าสุดของหน่วยงานที่เชื่อถือได้</div>
<div class="row g-3">
  <div class="col-xl-8">
    <div class="panel">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><h2 class="mb-0">ค่าสัมประสิทธิ์การปล่อย (Emission Factor)</h2>
        <input type="search" class="form-control form-control-sm" style="width:200px" placeholder="ค้นหา..." data-filter="#factors" aria-label="ค้นหาค่าสัมประสิทธิ์"></div>
      <div class="table-responsive"><table class="table table-sm" id="factors">
        <thead><tr><th data-sort>หมวด</th><th data-sort>รายการ</th><th>หน่วย / รอบ</th><th data-sort="num" class="text-end">kgCO₂e ต่อหน่วย</th><th data-sort>สถานะ</th><th></th></tr></thead>
        <tbody><?php foreach ($factors as $f): ?>
          <tr><td class="text-nowrap"><?= FACTOR_GROUPS[$f['category']][1] ?> <?= e(FACTOR_GROUPS[$f['category']][0]) ?></td><td><?= e($f['name']) ?></td>
            <td class="text-nowrap"><?= e($f['unit']) ?> <?= PERIODS[$f['periods_per_year']] ?></td><td class="text-end"><?= num($f['factor'], 4) ?></td>
            <td><?= $f['is_active'] ? badge('active') : badge('off') ?></td>
            <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-success" href="<?= e(url('admin/green.php?factor=' . $f['id'])) ?>#factor-form">แก้ไข</a>
              <form method="post" class="d-inline" data-confirm="<?= $f['used'] ? 'รายการนี้ถูกใช้คำนวณแล้ว ระบบจะปิดการใช้งานแทนการลบ ดำเนินการต่อ?' : 'ลบรายการนี้?' ?>"><?= csrf_field() ?><input type="hidden" name="action" value="factor_delete"><input type="hidden" name="id" value="<?= $f['id'] ?>"><button class="btn btn-sm btn-outline-danger">ลบ</button></form></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
    </div>
    <div class="panel">
      <h2>หมวดอุปกรณ์ และคาร์บอนที่ลดได้</h2>
      <div class="table-responsive"><table class="table table-sm">
        <thead><tr><th data-sort>หมวด</th><th data-sort="num" class="text-end">ใช้ซ้ำ 1 ชิ้น (kgCO₂e)</th><th data-sort="num" class="text-end">รีไซเคิล 1 กก. (kgCO₂e)</th><th></th></tr></thead>
        <tbody><?php foreach ($cats as $c): ?>
          <tr><td><?= e($c['icon'] . ' ' . $c['name']) ?></td><td class="text-end"><?= num($c['co2_reuse_kg']) ?></td><td class="text-end"><?= num($c['co2_recycle_per_kg']) ?></td>
            <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-success" href="<?= e(url('admin/green.php?cat=' . $c['id'])) ?>#category-form">แก้ไข</a>
              <form method="post" class="d-inline" data-confirm="ลบหมวดนี้?"><?= csrf_field() ?><input type="hidden" name="action" value="category_delete"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn btn-sm btn-outline-danger">ลบ</button></form></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
    </div>
  </div>
  <div class="col-xl-4">
    <form class="panel needs-validation" method="post" id="factor-form" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="factor_save"><input type="hidden" name="id" value="<?= $editF ? (int) $editF['id'] : 0 ?>">
      <h2><?= $editF ? '✏️ แก้ไขค่าสัมประสิทธิ์' : '➕ เพิ่มค่าสัมประสิทธิ์' ?></h2>
      <div class="mb-2"><label class="form-label small mb-1" for="f-category">หมวด</label>
        <select id="f-category" name="category" class="form-select form-select-sm"><?php foreach (FACTOR_GROUPS as $k => $v): ?><option value="<?= $k ?>" <?= $editF && $editF['category'] === $k ? 'selected' : '' ?>><?= e($v[1] . ' ' . $v[0]) ?></option><?php endforeach; ?></select></div>
      <div class="mb-2"><label class="form-label small mb-1" for="f-name">รายการ</label><input id="f-name" name="name" class="form-control form-control-sm" value="<?= e($editF ? $editF['name'] : '') ?>" required minlength="3" maxlength="120"><div class="invalid-feedback">กรอกชื่อรายการ</div></div>
      <div class="row g-2 mb-2">
        <div class="col-6"><label class="form-label small mb-1" for="f-unit">หน่วย</label><input id="f-unit" name="unit" class="form-control form-control-sm" value="<?= e($editF ? $editF['unit'] : '') ?>" required maxlength="30" placeholder="กม."><div class="invalid-feedback">กรอกหน่วย</div></div>
        <div class="col-6"><label class="form-label small mb-1" for="f-period">รอบการกรอก</label>
          <select id="f-period" name="periods_per_year" class="form-select form-select-sm"><?php foreach (PERIODS as $k => $v): ?><option value="<?= $k ?>" <?= $editF && (int) $editF['periods_per_year'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="mb-2"><label class="form-label small mb-1" for="f-factor">kgCO₂e ต่อ 1 หน่วย</label><input id="f-factor" name="factor" type="number" min="0.0001" step="0.0001" class="form-control form-control-sm" value="<?= e($editF ? (float) $editF['factor'] : '') ?>" required><div class="invalid-feedback">กรอกค่ามากกว่า 0</div></div>
      <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="f-active" <?= !$editF || $editF['is_active'] ? 'checked' : '' ?>><label class="form-check-label small" for="f-active">ใช้ในแบบคำนวณ</label></div>
      <button class="btn btn-success btn-sm">บันทึก</button>
      <?php if ($editF): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/green.php')) ?>">ยกเลิก</a><?php endif; ?>
    </form>
    <form class="panel needs-validation" method="post" id="category-form" novalidate>
      <?= csrf_field() ?><input type="hidden" name="action" value="category_save"><input type="hidden" name="id" value="<?= $editC ? (int) $editC['id'] : 0 ?>">
      <h2><?= $editC ? '✏️ แก้ไขหมวดอุปกรณ์' : '➕ เพิ่มหมวดอุปกรณ์' ?></h2>
      <div class="row g-2 mb-2">
        <div class="col-3"><label class="form-label small mb-1" for="c-icon">ไอคอน</label><input id="c-icon" name="icon" class="form-control form-control-sm" value="<?= e($editC ? $editC['icon'] : '📦') ?>" maxlength="4"></div>
        <div class="col-9"><label class="form-label small mb-1" for="c-name">ชื่อหมวด</label><input id="c-name" name="name" class="form-control form-control-sm" value="<?= e($editC ? $editC['name'] : '') ?>" required minlength="2" maxlength="80"><div class="invalid-feedback">กรอกชื่อหมวด</div></div>
        <div class="col-6"><label class="form-label small mb-1" for="c-reuse">ใช้ซ้ำ 1 ชิ้น (kg)</label><input id="c-reuse" name="co2_reuse_kg" type="number" min="0" step="0.01" class="form-control form-control-sm" value="<?= e($editC ? (float) $editC['co2_reuse_kg'] : '') ?>" required></div>
        <div class="col-6"><label class="form-label small mb-1" for="c-recycle">รีไซเคิล 1 กก. (kg)</label><input id="c-recycle" name="co2_recycle_per_kg" type="number" min="0" step="0.01" class="form-control form-control-sm" value="<?= e($editC ? (float) $editC['co2_recycle_per_kg'] : '') ?>" required></div>
      </div>
      <button class="btn btn-success btn-sm">บันทึก</button>
      <?php if ($editC): ?><a class="btn btn-outline-secondary btn-sm" href="<?= e(url('admin/green.php')) ?>">ยกเลิก</a><?php endif; ?>
    </form>
    <form class="panel" method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="settings">
      <h2>อัตราแต้มและค่าอ้างอิง</h2>
      <?php foreach ($settings as $s): ?>
        <div class="mb-2"><label class="form-label small mb-1" for="s-<?= e($s['name']) ?>"><?= e($s['label']) ?></label>
          <input id="s-<?= e($s['name']) ?>" name="s[<?= e($s['name']) ?>]" type="number" min="0" step="0.1" class="form-control form-control-sm" value="<?= e($s['value']) ?>" required></div>
      <?php endforeach; ?>
      <button class="btn btn-success btn-sm mt-2">บันทึกการตั้งค่า</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
