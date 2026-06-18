<?php
/**
 * get_latest.php — ดึงข้อมูลสถานะล่าสุดและประวัติการเกิดโพรงอากาศสำหรับบอร์ด Dashboard
 * รูปแบบ Output: JSON
 */
require_once __DIR__ . '/../config/db.php';

// ตั้งค่า Header ให้ตอบกลับเป็น JSON และรองรับ CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    // เชื่อมต่อฐานข้อมูล (อ้างอิงฟังก์ชันจากโค้ดเดิมของคุณ)
    $conn = getConnection();

    // สร้างโครงสร้าง JSON เริ่มต้น
    $response = [
        "class_label"   => "UNKNOWN",
        "soil_moisture" => 0.0,
        "inference_ms"  => 0.0,
        "history_fft"   => []
    ];

    // ==========================================
    // 1. ดึงข้อมูลล่าสุด 1 รายการ สำหรับแสดงผลบนการ์ด
    // ==========================================
    // หมายเหตุ: สมมติว่าตารางมีคอลัมน์ id เป็น Primary Key (Auto Increment)
    $sqlLatest = "SELECT class_label, soil_moisture, inference_ms 
                  FROM sensor_log 
                  ORDER BY id DESC LIMIT 1";
                  
    $resultLatest = $conn->query($sqlLatest);

    if ($resultLatest && $resultLatest->num_rows > 0) {
        $row = $resultLatest->fetch_assoc();
        $response["class_label"]   = $row["class_label"];
        $response["soil_moisture"] = (float)$row["soil_moisture"];
        $response["inference_ms"]  = (float)$row["inference_ms"];
    }

    // ==========================================
    // 2. ดึงข้อมูลประวัติพลังงานคลื่นเสียง (FFT Energy)
    // ==========================================
    // ดึงข้อมูล 25 รายการล่าสุดเพื่อส่งไปให้กราฟวิ่ง Animation
    // (หากต้องการค่าเฉลี่ยรายวัน 7 วันเป๊ะๆ สามารถปรับ Query เป็น GROUP BY DATE() ได้)
    $sqlHistory = "SELECT fft_energy 
                   FROM sensor_log 
                   ORDER BY id DESC LIMIT 25";
                   
    $resultHistory = $conn->query($sqlHistory);

    $fft_array = [];
    if ($resultHistory && $resultHistory->num_rows > 0) {
        while($row = $resultHistory->fetch_assoc()) {
            $fft_array[] = (float)$row["fft_energy"];
        }
    }
    
    // กลับด้าน Array เพื่อให้ข้อมูลเรียงจาก "เก่าไปใหม่" (Chronological order) 
    // กราฟจะได้วาดจากอดีตวิ่งมาหาปัจจุบัน
    $response["history_fft"] = array_reverse($fft_array);

    // ปิดการเชื่อมต่อฐานข้อมูล
    $conn->close();

    // ส่งออกเป็น JSON
    http_response_code(200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (\mysqli_sql_exception $e) {
    // จัดการ Error กรณีฐานข้อมูลมีปัญหา
    http_response_code(500);
    echo json_encode([
        'status' => 'error', 
        'detail' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>