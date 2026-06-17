<?php
/**
 * query.php — REST API ส่งข้อมูลให้ Dashboard (MySQLi, ไม่ใช้ PDO)
 *
 * GET ?action=all              → รวมทุกอย่าง (Dashboard ใช้อันนี้)
 * GET ?action=latest           → เรคคอร์ดล่าสุด 1 แถว
 * GET ?action=kpi              → ตัวเลข KPI รวม
 * GET ?action=daily_accuracy&days=14  → รายวัน
 * GET ?action=class_dist       → การกระจายคลาส
 * GET ?action=spectral_trend   → FFT+ดิน 7 วัน
 * GET ?action=recent&limit=10  → รายการล่าสุด N แถว
 */
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? 'all';
$conn   = getConnection();

function q_latest(mysqli $c): array {
    $r = $c->query("SELECT * FROM sensor_log ORDER BY id DESC LIMIT 1");
    return $r->fetch_assoc() ?: [];
}

function q_kpi(mysqli $c): array {
    $r = $c->query("
        SELECT COUNT(*)             AS total_records,
               AVG(confidence)*100  AS avg_acc,
               AVG(inference_ms)    AS avg_inf,
               AVG(soil_moisture)   AS avg_soil,
               MAX(created_at)      AS last_update,
               SUM(class_id = 2)    AS severe_count
        FROM sensor_log");
    $row = $r->fetch_assoc() ?: [];

    $r2 = $c->query("SELECT COUNT(*) c FROM sensor_log WHERE DATE(created_at)=CURDATE()");
    $row['samples_today'] = (int)($r2->fetch_assoc()['c'] ?? 0);

    $r3 = $c->query("SELECT uptime_ms FROM sensor_log ORDER BY id DESC LIMIT 1");
    $up = (int)($r3->fetch_assoc()['uptime_ms'] ?? 0);
    $row['uptime_hours'] = round($up / 3600000, 1);
    return $row;
}

function q_daily_accuracy(mysqli $c, int $days): array {
    $stmt = $c->prepare("
        SELECT DATE(created_at)     AS day,
               AVG(confidence)*100  AS acc_pct,
               COUNT(*)             AS total,
               AVG(soil_moisture)   AS avg_soil
        FROM sensor_log
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY day ORDER BY day ASC");
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function q_class_dist(mysqli $c): array {
    $r = $c->query("
        SELECT class_id, class_label, COUNT(*) AS cnt
        FROM sensor_log
        GROUP BY class_id, class_label
        ORDER BY class_id ASC");
    return $r->fetch_all(MYSQLI_ASSOC);
}

function q_spectral_trend(mysqli $c): array {
    $r = $c->query("
        SELECT DATE(created_at)    AS day,
               AVG(fft_energy)     AS fft_avg,
               AVG(inference_ms)   AS avg_inf,
               AVG(soil_moisture)  AS soil_avg,
               COUNT(*)            AS cnt
        FROM sensor_log
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY day ORDER BY day ASC");
    return $r->fetch_all(MYSQLI_ASSOC);
}

function q_recent(mysqli $c, int $limit): array {
    $stmt = $c->prepare(
        "SELECT id,device_id,class_id,class_label,confidence,
                soil_moisture,inference_ms,fft_energy,created_at
         FROM sensor_log ORDER BY id DESC LIMIT ?");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

try {
    $days  = max(1, min(60, (int)($_GET['days']  ?? 14)));
    $limit = max(1, min(100,(int)($_GET['limit'] ?? 10)));

    switch ($action) {
        case 'latest':         echo json_encode(q_latest($conn), JSON_UNESCAPED_UNICODE); break;
        case 'kpi':            echo json_encode(q_kpi($conn),    JSON_UNESCAPED_UNICODE); break;
        case 'daily_accuracy': echo json_encode(q_daily_accuracy($conn,$days),  JSON_UNESCAPED_UNICODE); break;
        case 'class_dist':     echo json_encode(q_class_dist($conn),  JSON_UNESCAPED_UNICODE); break;
        case 'spectral_trend': echo json_encode(q_spectral_trend($conn), JSON_UNESCAPED_UNICODE); break;
        case 'recent':         echo json_encode(q_recent($conn,$limit), JSON_UNESCAPED_UNICODE); break;
        case 'all':
            echo json_encode([
                'server_time'    => date('Y-m-d H:i:s'),
                'latest'         => q_latest($conn),
                'kpi'            => q_kpi($conn),
                'daily_accuracy' => q_daily_accuracy($conn, $days),
                'class_dist'     => q_class_dist($conn),
                'spectral_trend' => q_spectral_trend($conn),
                'recent'         => q_recent($conn, 8),
            ], JSON_UNESCAPED_UNICODE);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action'], JSON_UNESCAPED_UNICODE);
    }
} catch (\mysqli_sql_exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
$conn->close();
