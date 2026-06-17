<?php
/**
 * index.php — หน้าหลัก (Landing Page)
 * แสดงสถานะ DB + ลิงก์เข้าทุกส่วน
 */
require_once __DIR__ . '/config/db.php';

$dbReady  = false;
$records  = 0;
$lastDt   = '--';
$dbErr    = '';
$todayCnt = 0;
$severe   = 0;

try {
    $c = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $c->set_charset('utf8mb4');
    $r = $c->query("SELECT COUNT(*) tot, MAX(created_at) mx,
                           SUM(class_id=2) sev,
                           SUM(DATE(created_at)=CURDATE()) td
                    FROM sensor_log");
    $row     = $r->fetch_assoc();
    $records = (int)$row['tot'];
    $lastDt  = $row['mx'] ?? '--';
    $severe  = (int)$row['sev'];
    $todayCnt= (int)$row['td'];
    $dbReady = true;
    $c->close();
} catch (\Throwable $e) {
    $dbErr = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Plant Stress TinyML — หน้าหลัก</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;}
body{font-family:'Noto Sans Thai',sans-serif;background:#0f1419;color:#e6edf3;margin:0;padding:40px 16px;}
.wrap{max-width:900px;margin:0 auto;}
.hero{text-align:center;padding:10px 0 28px;}
.hero h1{font-size:1.9rem;margin:0 0 6px;}
.hero p{color:#7d8590;font-size:.9rem;margin:0;}

/* DB status card */
.db-card{border-radius:12px;padding:16px 20px;margin:0 0 28px;display:flex;align-items:center;gap:14px;border:1px solid;}
.db-ok{background:#11241a;border-color:#238636;}
.db-err{background:#241414;border-color:#a8331f;}
.db-icon{font-size:1.8rem;flex-shrink:0;}
.db-info h3{margin:0 0 3px;font-size:.95rem;}
.db-info p{margin:0;color:#7d8590;font-size:.8rem;}
.db-stat{display:flex;gap:20px;margin-top:8px;flex-wrap:wrap;}
.db-stat span{font-size:.82rem;}
.db-stat b{color:#e6edf3;}

/* Tiles grid */
.tiles{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:28px;}
@media(max-width:680px){.tiles{grid-template-columns:1fr;}}
a.tile{display:block;text-decoration:none;color:#e6edf3;background:#161b22;border:1px solid #30363d;border-radius:14px;padding:22px 20px;transition:.18s;}
a.tile:hover{border-color:#3fb950;transform:translateY(-3px);background:#1a212b;}
a.tile.main{border-color:#238636;background:#11241a;}
a.tile.admin{border-color:#1f6feb;background:#0d1f33;}
.tile-ic{font-size:2rem;}
.tile h3{margin:10px 0 5px;font-size:1rem;}
.tile p{margin:0;color:#7d8590;font-size:.8rem;line-height:1.5;}
.tile code{color:#79c0ff;font-size:.76rem;}

/* API quick ref */
.api-ref{background:#161b22;border:1px solid #30363d;border-radius:12px;padding:18px 22px;}
.api-ref h4{margin:0 0 12px;font-size:.9rem;color:#7d8590;}
.api-ref table{width:100%;border-collapse:collapse;font-size:.8rem;}
.api-ref td{padding:6px 10px;border-bottom:1px solid #21262d;}
.api-ref tr:last-child td{border:none;}
.api-ref td:first-child{width:45%;}
.api-ref a{color:#58a6ff;}
.api-ref code{background:#21262d;padding:2px 6px;border-radius:4px;font-size:.76rem;}

.footer{text-align:center;color:#7d8590;font-size:.75rem;margin-top:28px;}
</style>
</head>
<body>
<div class="wrap">

  <div class="hero">
    <h1>🌱 Plant Stress TinyML</h1>
    <p>Full Stack · ESP32 → API (PHP + MySQLi) → MySQL → Dashboard</p>
  </div>

  <!-- DB Status -->
  <?php if ($dbReady): ?>
  <div class="db-card db-ok">
    <div class="db-icon">✅</div>
    <div class="db-info">
      <h3>ฐานข้อมูลพร้อมใช้งาน — <?= htmlspecialchars(DB_NAME) ?></h3>
      <p>อัปเดตล่าสุด: <b><?= htmlspecialchars($lastDt) ?></b></p>
      <div class="db-stat">
        <span>รวม: <b><?= number_format($records) ?></b> แถว</span>
        <span>วันนี้: <b><?= number_format($todayCnt) ?></b></span>
        <span>Severe: <b><?= number_format($severe) ?></b></span>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="db-card db-err">
    <div class="db-icon">❌</div>
    <div class="db-info">
      <h3>ยังเชื่อมต่อฐานข้อมูลไม่ได้ — รัน Setup ก่อน</h3>
      <p><?= htmlspecialchars($dbErr) ?></p>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tiles -->
  <div class="tiles">

    <a class="tile main" href="install/setup.php">
      <div class="tile-ic">⚙️</div>
      <h3>1) ติดตั้ง + สร้างข้อมูล</h3>
      <p>สร้าง DB / ตาราง / ข้อมูลจำลอง 8 วัน<br><code>install/setup.php</code></p>
    </a>

    <a class="tile main" href="dashboard/index.php">
      <div class="tile-ic">📊</div>
      <h3>2) Dashboard (หน้าบ้าน)</h3>
      <p>แสดงผล Realtime ดึงข้อมูลทุก 10 วิ<br><code>dashboard/index.php</code></p>
    </a>

    <a class="tile admin" href="admin/index.php">
      <div class="tile-ic">🛠</div>
      <h3>3) Admin Panel (หลังบ้าน)</h3>
      <p>ดู / กรอง / ลบ / Export CSV ข้อมูล<br><code>admin/index.php</code></p>
    </a>

    <a class="tile" href="api/ingest.php" target="_blank">
      <div class="tile-ic">📡</div>
      <h3>4) API รับข้อมูลจาก ESP32</h3>
      <p>POST + JSON Body<br><code>api/ingest.php</code></p>
    </a>

    <a class="tile" href="api/query.php?action=all" target="_blank">
      <div class="tile-ic">🔌</div>
      <h3>5) API ส่งข้อมูลให้ Dashboard</h3>
      <p>GET ?action=all → JSON<br><code>api/query.php</code></p>
    </a>

    <a class="tile" href="install/test_ingest.php">
      <div class="tile-ic">🧪</div>
      <h3>6) ทดสอบ ESP32 POST</h3>
      <p>จำลองบอร์ดส่งข้อมูล HTTP POST<br><code>install/test_ingest.php</code></p>
    </a>

  </div>

  <!-- API Quick Ref -->
  <div class="api-ref">
    <h4>📋 API Quick Reference</h4>
    <table>
      <tr>
        <td><code>POST /api/ingest.php</code></td>
        <td>รับข้อมูลจาก ESP32 (JSON body) → INSERT ลงฐานข้อมูล</td>
      </tr>
      <tr>
        <td><a href="api/query.php?action=all" target="_blank"><code>GET ?action=all</code></a></td>
        <td>รวมทุกอย่าง — latest, kpi, daily, dist, trend, recent</td>
      </tr>
      <tr>
        <td><a href="api/query.php?action=latest" target="_blank"><code>GET ?action=latest</code></a></td>
        <td>เรคคอร์ดล่าสุด 1 แถว</td>
      </tr>
      <tr>
        <td><a href="api/query.php?action=kpi" target="_blank"><code>GET ?action=kpi</code></a></td>
        <td>ตัวเลข KPI รวม (ความแม่นยำ, inference, ดิน, uptime)</td>
      </tr>
      <tr>
        <td><a href="api/query.php?action=class_dist" target="_blank"><code>GET ?action=class_dist</code></a></td>
        <td>การกระจายคลาส (Normal / Mild / Severe)</td>
      </tr>
      <tr>
        <td><a href="api/query.php?action=spectral_trend" target="_blank"><code>GET ?action=spectral_trend</code></a></td>
        <td>แนวโน้ม FFT + ดิน 7 วัน</td>
      </tr>
      <tr>
        <td><a href="api/query.php?action=recent&limit=10" target="_blank"><code>GET ?action=recent&limit=10</code></a></td>
        <td>รายการล่าสุด N แถว</td>
      </tr>
    </table>
  </div>

  <div class="footer">
    ESP32 Endpoint: <code>POST http://&lt;IP&gt;/plant_stress_dashboard/api/ingest.php</code>
  </div>
</div>
</body>
</html>
