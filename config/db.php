<?php
// ===== ตั้งค่าการเชื่อมต่อ MySQL =====
// XAMPP ปกติ: user=root, pass="" (ว่างเปล่า)
define('DB_HOST', 'db');
define('DB_PORT', 3306);
define('DB_USER', 'iot');
define('DB_PASS', 'iot123');
define('DB_NAME', 'plant_stress_db');

// บังคับให้ MySQLi throw Exception เมื่อเกิด error
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/** เชื่อมต่อพร้อมเลือก Database */
function getConnection(): mysqli
{
    try {
        $c = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $c->set_charset('utf8mb4');
        return $c;
    } catch (\mysqli_sql_exception $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error'  => 'DB connection failed',
            'detail' => $e->getMessage(),
            'fix'    => 'แก้ config/db.php แล้วรัน install/setup.php',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/** เชื่อมต่อโดยยังไม่เลือก Database (ใช้ตอน setup สร้าง DB) */
function getServerConnection(): mysqli
{
    $c = new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
    $c->set_charset('utf8mb4');
    return $c;
}
