<?php
/**
 * test_ingest.php — จำลองบอร์ด ESP32 ส่ง HTTP POST เข้า api/ingest.php
 * ใช้ทดสอบว่า API รับข้อมูลทำงานครบวงจรจริง
 */
require_once __DIR__ . '/../config/db.php';
header('Content-Type: text/html; charset=utf-8');

// สร้าง payload สมจริง
$classId = (int)($_GET['class'] ?? mt_rand(0, 2));
$classId = max(0, min(2, $classId));
$CLASSES  = [0 => 'NORMAL', 1 => 'MILD STRESS', 2 => 'SEVERE STRESS'];

$soil    = [0 => mt_rand(45, 70), 1 => mt_rand(26, 44), 2 => mt_rand(10, 25)][$classId];
$soil   += mt_rand(-15, 15) / 10;

$payload = [
    'device'        => 'ESP32-01',
    'class_id'      => $classId,
    'class_label'   => $CLASSES[$classId],
    'confidence'    => round(mt_rand(82, 98) / 100, 4),
    'soil_moisture' => round($soil, 2),
    'inference_ms'  => round(mt_rand(125, 185) / 100, 3),
    'fft_energy'    => round([0=>0.8,1=>1.4,2=>2.5][$classId] + mt_rand(-20,20)/100, 4),
    'uptime_ms'     => time() * 1000,
];

// สร้าง URL สำหรับ ingest.php
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir    = dirname(dirname($_SERVER['PHP_SELF'] ?? '/plant_stress_dashboard/install/test_ingest.php'));
$url    = "$scheme://$host$dir/api/ingest.php";
$json   = json_encode($payload, JSON_UNESCAPED_UNICODE);

// ส่ง POST (ใช้ cURL ถ้ามี ไม่งั้น stream)
$response = ''; $httpCode = 0; $curlErr = '';
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) { $curlErr = curl_error($ch); $response = ''; }
    curl_close($ch);
} else {
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\nContent-Length: ".strlen($json)."\r\n",
        'content'       => $json,
        'timeout'       => 8,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents($url, false, $ctx) ?: '';
    if (!empty($http_response_header[0]) &&
        preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $httpCode = (int)$m[1];
    }
}

$ok = ($httpCode === 200);
$parsed = json_decode($response, true);

// กำหนด URL สำหรับแต่ละ class เพื่อทดสอบ
$clsLinks = [
    0 => '?class=0', 1 => '?class=1', 2 => '?class=2'
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ทดสอบ API — ESP32 จำลอง</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box;}
  body{font-family:'Noto Sans Thai',sans-serif;background:#0f1419;color:#e6edf3;display:flex;justify-content:center;padding:40px 16px;margin:0;}
  .box{background:#161b22;border:1px solid #30363d;border-radius:14px;max-width:660px;width:100%;padding:28px 32px;}
  h1{font-size:1.25rem;margin:0 0 4px;}
  .sub{color:#7d8590;font-size:.82rem;margin-bottom:18px;}
  .chip{display:inline-block;padding:5px 15px;border-radius:20px;font-weight:700;font-size:.83rem;margin-bottom:18px;}
  .ok {background:#1a3a1f;color:#3fb950;} .err{background:#3a1a1a;color:#f85149;}
  .lbl{color:#7d8590;font-size:.8rem;margin:14px 0 4px;}
  pre{background:#0d1117;border:1px solid #21262d;border-radius:8px;padding:14px;
      overflow:auto;font-size:.8rem;color:#9cdcfe;margin:0;}
  .class-btns{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;}
  .class-btn{padding:7px 16px;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none;}
  .c0{background:#1a3a1f;color:#3fb950;border:1px solid #238636;}
  .c1{background:#2a1f0a;color:#F57F17;border:1px solid #735000;}
  .c2{background:#3a1a1a;color:#f85149;border:1px solid #a8331f;}
  .actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px;}
  a.btn{text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600;font-size:.88rem;}
  .b1{background:#238636;color:#fff;} .b2{background:#21262d;color:#e6edf3;border:1px solid #30363d;}
  .kv-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:0;}
  .kv{background:#0d1117;border:1px solid #21262d;border-radius:8px;padding:10px 14px;font-size:.82rem;}
  .kv .k{color:#7d8590;font-size:.72rem;margin-bottom:2px;}
  .kv .v{font-weight:600;}
</style>
</head>
<body>
<div class="box">
  <h1>🧪 จำลองบอร์ด ESP32 ส่ง HTTP POST</h1>
  <p class="sub">ทดสอบ API รับข้อมูล — ครบวงจร POST → MySQLi INSERT → Response</p>

  <!-- เลือกคลาสที่จะทดสอบ -->
  <div class="lbl">เลือกคลาสที่ต้องการทดสอบ:</div>
  <div class="class-btns">
    <a href="?class=0" class="class-btn c0">🌿 ปกติ (NORMAL)</a>
    <a href="?class=1" class="class-btn c1">🥀 เครียดเล็กน้อย (MILD)</a>
    <a href="?class=2" class="class-btn c2">🍂 เครียดรุนแรง (SEVERE)</a>
    <a href="?" class="class-btn" style="background:#21262d;color:#e6edf3;border:1px solid #30363d">🔀 สุ่ม</a>
  </div>

  <span class="chip <?=$ok?'ok':'err'?>">
    <?=$ok ? "✓ ส่งสำเร็จ (HTTP {$httpCode})" : "✗ ล้มเหลว (HTTP {$httpCode}) {$curlErr}"?>
  </span>

  <div class="lbl">ปลายทาง (Endpoint):</div>
  <pre><?=htmlspecialchars($url)?></pre>

  <div class="lbl">ข้อมูลที่ส่ง (JSON Payload):</div>
  <div class="kv-grid">
    <?php foreach ($payload as $k => $v): ?>
      <div class="kv"><div class="k"><?=htmlspecialchars($k)?></div><div class="v"><?=htmlspecialchars((string)$v)?></div></div>
    <?php endforeach; ?>
  </div>

  <div class="lbl">Response จากเซิร์ฟเวอร์:</div>
  <pre><?=htmlspecialchars($parsed ? json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ($response ?: '(ไม่มีข้อมูลตอบกลับ)'))?></pre>

  <div class="actions">
    <a class="btn b1" href="?class=<?=$classId?>">ส่งซ้ำ (คลาสเดิม) →</a>
    <a class="btn b2" href="../dashboard/index.php">Dashboard</a>
    <a class="btn b2" href="../admin/index.php">Admin Panel</a>
    <a class="btn b2" href="../index.php">หน้าหลัก</a>
  </div>
</div>
</body>
</html>
