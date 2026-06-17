<?php
/**
 * ingest.php — รับข้อมูลจากบอร์ด ESP32 (HTTP POST + JSON Body)
 * MySQLi Prepared Statement เท่านั้น (ไม่ใช้ PDO)
 *
 * ตัวอย่าง JSON ที่บอร์ดส่งมา:
 * {
 *   "device":"ESP32-01", "class_id":1, "class_label":"MILD STRESS",
 *   "confidence":0.8973, "soil_moisture":38.20,
 *   "inference_ms":1.52,  "fft_energy":1.183, "uptime_ms":86402340
 * }
 *
 * Response:  {"status":"ok","insert_id":42}
 *            {"status":"error","detail":"..."}
 */
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
header('Access-Control-Allow-Methods: POST, OPTIONS');

// ตอบ preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ===== (ออปชัน) API Key =====
// ตั้งค่าเช่น 'PLANT-2569-SECRET' เพื่อเปิดการตรวจ
// ESP32 ส่ง header:  X-API-Key: PLANT-2569-SECRET
const API_KEY = '';
if (API_KEY !== '') {
    if (!hash_equals(API_KEY, $_SERVER['HTTP_X_API_KEY'] ?? '')) {
        http_response_code(401);
        exit(json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE));
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'POST only'], JSON_UNESCAPED_UNICODE));
}

// อ่าน + ตรวจ JSON
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || !array_key_exists('class_id', $data)) {
    http_response_code(400);
    exit(json_encode(['error' => 'Invalid JSON payload', 'received' => $raw], JSON_UNESCAPED_UNICODE));
}

// เตรียมค่า
$CLASS_EN = [0 => 'NORMAL', 1 => 'MILD STRESS', 2 => 'SEVERE STRESS'];
$device   = substr((string)($data['device']        ?? 'ESP32-01'), 0, 20);
$classId  = max(0, min(2, (int)$data['class_id']));
$classLbl = (string)($data['class_label']          ?? $CLASS_EN[$classId]);
$conf     = (float)($data['confidence']            ?? 0);
$soil     = (float)($data['soil_moisture']         ?? 0);
$infMs    = (float)($data['inference_ms']          ?? 0);
$fft      = (float)($data['fft_energy']            ?? 0);
$uptime   = (int)($data['uptime_ms']               ?? 0);

try {
    $conn = getConnection();
    $stmt = $conn->prepare(
        "INSERT INTO sensor_log
           (device_id,class_id,class_label,confidence,soil_moisture,inference_ms,fft_energy,uptime_ms)
         VALUES (?,?,?,?,?,?,?,?)"
    );
    $stmt->bind_param('sissdddi',
        $device,$classId,$classLbl,$conf,$soil,$infMs,$fft,$uptime);
    $stmt->execute();
    $id = $conn->insert_id;
    $stmt->close();
    $conn->close();
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'insert_id' => $id], JSON_UNESCAPED_UNICODE);
} catch (\mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'detail' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
