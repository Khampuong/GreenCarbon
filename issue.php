<?php
// แจ้งปัญหา: ส่งเข้าระบบแจ้งเตือนของผู้ดูแลระบบ
require __DIR__ . '/includes/init.php';

$u = require_login();
const ISSUE_TOPICS = ['การใช้งานเว็บไซต์', 'ตลาดมือสอง / ผู้ขาย', 'จุดรับรีไซเคิล', 'Green Point / ของรางวัล', 'ข้อเสนอแนะอื่นๆ'];

if (is_post()) {
    if (!in_array(post('topic'), ISSUE_TOPICS, true) || mb_strlen(post('message')) < 10 || mb_strlen(post('message')) > 500) {
        flash('กรุณาเลือกหัวข้อ และอธิบายรายละเอียด 10-500 ตัวอักษร', 'danger');
    } else {
        notify_admin('issue', 'รายงานปัญหา: ' . post('topic'), post('message'), 'admin/notifications.php?type=issue', $u['id']);
        flash('ส่งเรื่องถึงผู้ดูแลระบบแล้ว ขอบคุณที่ช่วยปรับปรุงระบบ');
    }
    redirect('issue.php');
}
$mine = rows("SELECT * FROM notifications WHERE type = 'issue' AND user_id = ? ORDER BY id DESC LIMIT 10", [$u['id']]);

$title = 'แจ้งปัญหา';
require __DIR__ . '/includes/header.php';
?>
<?= page_header('แจ้งปัญหา / ข้อเสนอแนะ', 'ผู้ดูแลระบบจะได้รับการแจ้งเตือนทันที') ?>
<div class="row g-3">
  <div class="col-lg-6">
    <form class="panel needs-validation" method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="topic">หัวข้อ</label>
        <select id="topic" name="topic" class="form-select" required>
          <option value="">— เลือกหัวข้อ —</option>
          <?php foreach (ISSUE_TOPICS as $t): ?><option><?= e($t) ?></option><?php endforeach; ?>
        </select><div class="invalid-feedback">กรุณาเลือกหัวข้อ</div></div>
      <div class="mb-3"><label class="form-label" for="message">รายละเอียด</label>
        <textarea id="message" name="message" class="form-control" rows="5" required minlength="10" maxlength="500"></textarea>
        <div class="invalid-feedback">กรุณาอธิบายรายละเอียด 10-500 ตัวอักษร</div></div>
      <button class="btn btn-success">ส่งเรื่อง</button>
    </form>
  </div>
  <div class="col-lg-6">
    <div class="panel">
      <h2>เรื่องที่เคยแจ้ง</h2>
      <?php if (!$mine): ?><div class="empty">ยังไม่มีเรื่องที่แจ้ง</div><?php endif; ?>
      <?php foreach ($mine as $n): ?>
        <div class="border-bottom py-2">
          <div class="d-flex justify-content-between gap-2"><strong><?= e($n['title']) ?></strong>
            <span class="badge rounded-pill bg-<?= $n['is_read'] ? 'success' : 'warning text-dark' ?>"><?= $n['is_read'] ? 'ผู้ดูแลรับทราบแล้ว' : 'รอผู้ดูแลอ่าน' ?></span></div>
          <div class="small"><?= e($n['message']) ?></div>
          <div class="small text-muted"><?= thai_date($n['created_at']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
