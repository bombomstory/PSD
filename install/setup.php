<?php
/**
 * setup.php — ติดตั้ง DB + สร้างข้อมูลจำลอง 8 วัน (วันนี้ + ย้อนหลัง 7 วัน)
 * URL: http://localhost/plant_stress_dashboard/install/setup.php
 * ?keep=1  → เพิ่มข้อมูลโดยไม่ล้างของเดิม
 * ?reset=1 → ล้างแล้วสร้างใหม่ (ค่าเริ่มต้น)
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: text/html; charset=utf-8');

$keep = isset($_GET['keep']);
$log  = [];

try {
    // 1) สร้าง Database
    $srv = getServerConnection();
    $srv->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`
                 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $log[] = ['ok', 'ตรวจ/สร้างฐานข้อมูล `' . DB_NAME . '` สำเร็จ'];
    $srv->close();

    // 2) สร้างตาราง
    $conn = getConnection();
    $conn->query("
        CREATE TABLE IF NOT EXISTS `sensor_log` (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            device_id     VARCHAR(20)  NOT NULL DEFAULT 'ESP32-01',
            class_id      TINYINT      NOT NULL COMMENT '0=Normal 1=Mild 2=Severe',
            class_label   VARCHAR(20)  NOT NULL,
            confidence    FLOAT        NOT NULL,
            soil_moisture FLOAT        NOT NULL,
            inference_ms  FLOAT        NOT NULL,
            fft_energy    FLOAT        DEFAULT 0,
            uptime_ms     BIGINT       DEFAULT 0,
            created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_device (device_id),
            INDEX idx_time   (created_at),
            INDEX idx_class  (class_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $log[] = ['ok', 'ตรวจ/สร้างตาราง `sensor_log` สำเร็จ'];

    // 3) ล้างข้อมูลเก่า (ถ้าไม่ได้ส่ง ?keep)
    if (!$keep) {
        $conn->query("TRUNCATE TABLE sensor_log");
        $log[] = ['ok', 'ล้างข้อมูลเก่าทั้งหมด (TRUNCATE)'];
    }

    // 4) สร้างข้อมูลจำลอง
    // สถานการณ์จำลอง: ต้นไม้เริ่มปกติ → ขาดน้ำเพิ่มขึ้น → เครียดรุนแรงช่วงท้าย
    $CLASSES  = [0 => 'NORMAL', 1 => 'MILD STRESS', 2 => 'SEVERE STRESS'];
    $device   = 'ESP32-01';
    $stepMin  = 15;          // บันทึกทุก 15 นาที

    $now   = new DateTime();
    $start = (clone $now)->modify('-7 days')->setTime(0, 0, 0);
    $boot  = $start->getTimestamp();
    $span  = max(1, $now->getTimestamp() - $boot);

    $stmt = $conn->prepare(
        "INSERT INTO sensor_log
           (device_id,class_id,class_label,confidence,
            soil_moisture,inference_ms,fft_energy,uptime_ms,created_at)
         VALUES (?,?,?,?,?,?,?,?,?)"
    );

    $count  = 0;
    $cursor = clone $start;
    $conn->begin_transaction();

    while ($cursor <= $now) {
        $ts  = $cursor->getTimestamp();
        $p   = ($ts - $boot) / $span;          // 0 → 1 (แห้งขึ้นเรื่อย ๆ)
        $hr  = (int)$cursor->format('G');

        // ความชื้นดิน: 62% → 18% พร้อม pattern กลางวัน/กลางคืน
        $soil = (62 - $p * 44) - 2.5 * sin(($hr - 6) / 24 * 2 * M_PI) + rnd(-2, 2);
        $soil = clamp($soil, 8, 95);

        // คลาสจากความชื้น + สุ่มเล็กน้อย (~8%) ให้สมจริง
        if ($soil >= 42)     $cls = 0;
        elseif ($soil >= 26) $cls = 1;
        else                 $cls = 2;
        if (mt_rand(1, 100) <= 8) $cls = max(0, min(2, $cls + (mt_rand(0,1)?1:-1)));

        // ค่าอื่น ๆ
        $conf  = rnd(0.82, 0.99);
        $infMs = rnd(1.25, 1.85);
        $fft   = [0=>rnd(0.5,1.1), 1=>rnd(1.0,1.9), 2=>rnd(1.7,3.4)][$cls];
        $up    = ($ts - $boot) * 1000;
        $lbl   = $CLASSES[$cls];
        $dt    = $cursor->format('Y-m-d H:i:s');

        $stmt->bind_param('sissdddis',
            $device,$cls,$lbl,$conf,$soil,$infMs,$fft,$up,$dt);
        $stmt->execute();
        $count++;
        $cursor->modify("+{$stepMin} minutes");
    }
    $conn->commit();
    $stmt->close();
    $log[] = ['ok', "สร้างข้อมูลจำลอง {$count} แถว ({$start->format('d/m/Y')} → {$now->format('d/m/Y H:i')})"];

    // สรุปการกระจายคลาส
    $r = $conn->query("SELECT class_label,COUNT(*) c FROM sensor_log GROUP BY class_label ORDER BY class_id");
    $d = []; while($row=$r->fetch_assoc()) $d[]=$row['class_label'].'='.$row['c'];
    $log[] = ['ok', 'การกระจายคลาส: '.implode(' | ', $d)];
    $conn->close();
    $success = true;
} catch (\Throwable $e) {
    $success = false;
    $log[] = ['err', 'ผิดพลาด: '.$e->getMessage()];
}

function rnd(float $a, float $b): float { return $a + mt_rand()/mt_getrandmax()*($b-$a); }
function clamp(float $v, float $lo, float $hi): float { return max($lo, min($hi, $v)); }
?>
<!DOCTYPE html><html lang="th"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ติดตั้งระบบ — Plant Stress</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;}
  body{font-family:'Noto Sans Thai',sans-serif;background:#0f1419;color:#e6edf3;
       display:flex;justify-content:center;padding:40px 16px;margin:0;}
  .box{background:#161b22;border:1px solid #30363d;border-radius:14px;max-width:660px;width:100%;padding:28px 32px;}
  h1{margin:0 0 4px;font-size:1.35rem;}
  .sub{color:#7d8590;font-size:.85rem;margin-bottom:20px;}
  .chip{display:inline-block;padding:4px 14px;border-radius:20px;font-size:.83rem;font-weight:700;margin-bottom:18px;}
  .ok{background:#1a3a1f;color:#3fb950;}.err{background:#3a1a1a;color:#f85149;}
  ul{list-style:none;padding:0;margin:0 0 20px;}
  li{padding:9px 0;border-bottom:1px solid #21262d;font-size:.88rem;display:flex;gap:10px;align-items:flex-start;}
  li .ico{flex-shrink:0;}
  .btns{display:flex;gap:12px;flex-wrap:wrap;}
  a.btn{text-decoration:none;padding:10px 20px;border-radius:8px;font-weight:600;font-size:.88rem;}
  .g{background:#238636;color:#fff;}.s{background:#21262d;color:#e6edf3;border:1px solid #30363d;}
  code{background:#21262d;padding:2px 7px;border-radius:4px;font-size:.8rem;color:#79c0ff;}
</style>
</head><body>
<div class="box">
  <h1>⚙️ ติดตั้งระบบ Plant Stress TinyML</h1>
  <p class="sub">สร้างฐานข้อมูล ตาราง และข้อมูลจำลอง 8 วัน (ทุก 15 นาที)</p>
  <span class="chip <?=$success?'ok':'err'?>"><?=$success?'✓ ติดตั้งสำเร็จ':'✗ ติดตั้งล้มเหลว'?></span>
  <ul>
    <?php foreach($log as [$t,$m]): ?>
    <li><span class="ico"><?=$t==='ok'?'✓':'✗'?></span><span><?=htmlspecialchars($m)?></span></li>
    <?php endforeach; ?>
  </ul>
  <p style="color:#7d8590;font-size:.8rem;">เพิ่มข้อมูลโดยไม่ล้างของเดิม → <code>setup.php?keep=1</code></p>
  <div class="btns">
    <a class="btn g" href="../dashboard/index.php">เปิด Dashboard →</a>
    <a class="btn s" href="../admin/index.php">Admin Panel</a>
    <a class="btn s" href="../index.php">หน้าหลัก</a>
    <a class="btn s" href="setup.php">ติดตั้งใหม่</a>
  </div>
</div>
</body></html>
