<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>แดชบอร์ดระบบตรวจจับความเครียดของพืชด้วย AI (TinyML)</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- ===== Header ===== -->
<header class="header-bar">
  <div class="header-content">
    <div>
      <h1>แดชบอร์ดระบบตรวจจับความเครียดของพืชด้วย AI (TinyML)</h1>
      <p class="subtitle">สไลด์แสดงการทำงานของระบบ TinyML ในการตรวจจับความเครียดของพืช (Zinnia elegans) จากสัญญาณเสียงและระดับความชื้นในดิน โดยเน้นข้อมูลประสิทธิภาพและสถานะแบบเรียลไทม์</p>
    </div>
    <div class="header-right">
      <div class="online-badge" id="badge-online">
        <span class="dot"></span><span id="badge-txt">กำลังเชื่อมต่อ…</span>
      </div>
      <div class="poll-label">
        อัปเดตทุก 10 วินาที · ดึงล่าสุด <span id="last-fetch">--</span>
        <span class="poll-countdown" id="countdown" style="--prog:0%"></span>
      </div>
    </div>
  </div>
</header>

<main class="grid-main">

  <!-- ===== Device Panel (Sidebar) ===== -->
  <aside class="panel-device">
    <h3>สถานะอุปกรณ์ (DEVICE STATUS)</h3>
    <div class="plant-icon" id="plant-icon">🌿</div>
    <div class="device-name" id="device-name">ESP32-01</div>

    <!-- อุณหภูมิ / ความชื้น -->
    <div class="device-temp" id="d-soil-badge">-- %</div>
    <div class="last-seen" id="last-seen">--</div>

    <div class="stat-row">
      <span class="lbl">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#388E3C" stroke-width="2.2">
          <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2z"/>
          <path d="M12 6v6l4 2"/>
        </svg>
        สภาพแวดล้อม
      </span>
      <span class="val" id="d-uptime">--</span>
    </div>

    <div class="stat-row">
      <span class="lbl">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#388E3C" stroke-width="2.2">
          <rect x="2" y="8" width="4" height="13" rx="1"/>
          <rect x="9" y="4" width="4" height="17" rx="1"/>
          <rect x="16" y="11" width="4" height="10" rx="1"/>
        </svg>
        ระดับเสียงรบกวน
      </span>
      <span class="val" id="d-fft">--</span>
    </div>

    <div class="stat-row">
      <span class="lbl"><span class="dot dot-t"></span> ความชื้นดิน</span>
      <span class="val" id="d-soil">--%</span>
    </div>

    <div class="stat-row">
      <span class="lbl"><span class="dot dot-b"></span> Inference</span>
      <span class="val" id="d-inf">-- ms</span>
    </div>
  </aside>

  <!-- ===== KPI Cards ===== -->
  <section class="metric-cards">

    <!-- Card 1: Accuracy -->
    <div class="kpi-card kpi-card-acc">
      <div class="kpi-top">
        <div class="kpi-icon">🤖</div>
        <div class="kpi-label">ความแม่นยำ<br>โมเดล TinyML</div>
      </div>
      <div class="kpi-val" id="c-acc">--%</div>
      <div class="kpi-note">
        ความแม่นยำเฉลี่ย (Average Accuracy)
        <span class="kpi-mom" id="c-acc-mom" style="margin-left:4px">MoM: --</span>
      </div>
    </div>

    <!-- Card 2: Inference -->
    <div class="kpi-card kpi-card-inf">
      <div class="kpi-top">
        <div class="kpi-icon">⚡</div>
        <div class="kpi-label">เวลาประมวลผล<br>ปลายทาง</div>
      </div>
      <div class="kpi-val" id="c-inf">-- ms</div>
      <div class="kpi-note">เรียลไทม์ · Total Edge Processing
        <span class="kpi-badge">Real-time</span>
      </div>
    </div>

    <!-- Card 3: Status -->
    <div class="kpi-card kpi-card-st" id="c-status-card">
      <div class="kpi-top">
        <div class="kpi-icon">🌱</div>
        <div class="kpi-label">สถานะพืช<br>ปัจจุบัน</div>
      </div>
      <div class="kpi-val" style="font-size:1.3rem" id="c-status">--</div>
      <div class="kpi-note" id="c-conf">
        predicted status
        <span class="kpi-badge" id="c-conf-badge">-- %</span>
      </div>
    </div>

    <!-- Card 4: Records -->
    <div class="kpi-card kpi-card-rec">
      <div class="kpi-top">
        <div class="kpi-icon">☁️</div>
        <div class="kpi-label">จำนวน Records<br>ทั้งหมด</div>
      </div>
      <div class="kpi-val" id="c-total">--</div>
      <div class="kpi-note" id="c-today">
        Total Records
        <span class="kpi-badge" id="c-today-badge">วันนี้ --</span>
      </div>
    </div>

  </section>

  <!-- ===== Section Header ===== -->
  <div class="section-header" style="grid-column:1/-1;grid-row:3">
    สถิติความเครียดพืชจากคลื่นเสียง (Acoustic Stress Statistics)
  </div>

  <!-- ===== Line Chart ===== -->
  <div class="chart-card chart-main">
    <h4>ความแม่นยำรายวัน TinyML vs. RMS Threshold</h4>
    <canvas id="chartAcc"></canvas>
  </div>

  <!-- ===== Donut Chart ===== -->
  <div class="chart-card chart-donut">
    <h4>การจัดเก็บข้อมูลออนไลน์</h4>
    <canvas id="chartDonut"></canvas>
    <div class="donut-center" id="donut-center">--<small>Records</small></div>
    <div class="chart-note" id="donut-note">จำนวน Records ทั้งหมด: -- Records</div>
  </div>

  <!-- ===== Spectral Trend ===== -->
  <div class="chart-card chart-spec">
    <h4>แนวโน้มประสิทธิภาพระบบ <small style="color:var(--text-muted);font-weight:400">7 วันล่าสุด</small></h4>
    <canvas id="chartSpec"></canvas>
  </div>

  <!-- ===== Uptime / Recent Table ===== -->
  <div class="chart-card chart-tbl">
    <h4>ข้อมูลล่าสุดจากบอร์ด ESP32
      <span style="float:right;font-size:.78rem;font-weight:400;color:var(--text-muted)">
        การใช้ประโยชน์อุปกรณ์ (Uptime of ESP32)
        <span class="uptime-pct" id="uptime-pct" style="display:inline;font-size:1rem">--%</span>
      </span>
    </h4>

    <!-- Uptime bars -->
    <div class="uptime-bars" id="uptime-bars" style="margin-bottom:10px"></div>

    <table class="recent-table">
      <thead>
        <tr>
          <th>#</th><th>เวลา</th><th>สถานะ</th>
          <th>มั่นใจ</th><th>ดิน&nbsp;%</th><th>ms</th>
        </tr>
      </thead>
      <tbody id="tbl-body">
        <tr><td colspan="6" class="muted">กำลังโหลด…</td></tr>
      </tbody>
    </table>
  </div>

</main>

<footer class="footer-note">
  กราฟ/แผนภูมินี้เชื่อมโยงกับ MySQL และจะเปลี่ยนแปลงโดยอัตโนมัติตามข้อมูล เพียงคลิกซ้ายที่กันแล้วเลือก "แก้ไขข้อมูล" &nbsp;·&nbsp;
  <a href="../index.php">หน้าหลัก</a> ·
  <a href="../admin/index.php">Admin Panel</a> ·
  <a href="../api/query.php?action=all" target="_blank">JSON API</a>
</footer>

<script src="app.js"></script>
</body>
</html>
