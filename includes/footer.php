</main>
<footer class="gl-footer">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-5">
        <div class="fw-bold fs-5 mb-1">♻ GreenLoop Net Zero</div>
        <p class="small opacity-75 mb-0">วัดคาร์บอนฟุตพริ้นท์รายปีของคุณ แล้วลดด้วยการส่งต่ออุปกรณ์ดิจิทัลมือสองและรีไซเคิล E-Waste พร้อมสะสม Green Point</p>
      </div>
      <div class="col-6 col-lg-3">
        <h6>บริการ</h6>
        <a href="<?= e(url('footprint.php')) ?>">คำนวณคาร์บอนฟุตพริ้นท์</a>
        <a href="<?= e(url('market.php')) ?>">ตลาดมือสอง</a>
        <a href="<?= e(url('recycle.php')) ?>">แจ้งรีไซเคิล</a>
      </div>
      <div class="col-6 col-lg-4">
        <h6>สำหรับสมาชิก</h6>
        <a href="<?= e(url('points.php')) ?>">Green Point และของรางวัล</a>
        <a href="<?= e(url('news.php')) ?>">ข่าวสารและบทความ</a>
        <a href="<?= e(url('issue.php')) ?>">แจ้งปัญหาการใช้งาน</a>
      </div>
    </div>
    <hr>
    <div class="small text-center opacity-75">&copy; <?= date('Y') + 543 ?> GreenLoop Net Zero — Powered by GreenDigital</div>
  </div>
</footer>
<script src="<?= e(url('assets/vendor/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(url('assets/vendor/chart.umd.js')) ?>"></script>
<script src="<?= e(url('assets/js/app.js')) ?>?v=1"></script>
</body>
</html>
