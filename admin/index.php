<?php
/**
 * admin/index.php — Admin Panel (หลังบ้าน)
 * ดู / กรอง / ลบ / Export CSV ข้อมูล sensor_log
 * PHP + MySQLi Prepared Statement (ไม่ใช้ PDO)
 */
require_once __DIR__ . '/../config/db.php';

/* ===== helper: สร้าง WHERE clause ===== */
function buildWhere(string $cls, string $dev, string $df, string $dt): string
{
    $parts = [];
    if ($cls !== '')   $parts[] = "class_id = ".(int)$cls;
    if ($dev !== '')   $parts[] = "device_id = '".addslashes($dev)."'";
    
    // เปรียบเทียบกับ created_at (Datetime) โดยตรง รองรับรูปแบบจาก Flatpickr (Y-m-d H:i:s)
    if ($df  !== '')   $parts[] = "created_at >= '".addslashes($df)."'";
    if ($dt  !== '')   $parts[] = "created_at <= '".addslashes($dt)."'";
    
    return $parts ? 'WHERE '.implode(' AND ', $parts) : '';
}
function q(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

/* ===== กรอง params ===== */
$fClass = $_GET['f_class']     ?? ($_POST['f_class']    ?? '');
$fDev   = $_GET['f_device']    ?? ($_POST['f_device']   ?? '');
$fFrom  = $_GET['f_date_from'] ?? '';
$fTo    = $_GET['f_date_to']   ?? '';

$conn = getConnection();
$msg  = ''; $msgType = 'ok';

/* ===== Actions (POST) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ลบ record เดี่ยว
    if (!empty($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $st = $conn->prepare("DELETE FROM sensor_log WHERE id=?");
        $st->bind_param('i', $id); $st->execute(); $st->close();
        $msg = "ลบ record #$id เรียบร้อย";
    }

    // ลบทั้งหมดตาม filter ที่เลือก
    if (!empty($_POST['delete_filtered'])) {
        $where = buildWhere($fClass, $fDev, $fFrom, $fTo);
        $conn->query("DELETE FROM sensor_log $where");
        $aff = $conn->affected_rows;
        $msg = "ลบ $aff แถว (ตามตัวกรองที่เลือก)";
        $msgType = $aff > 0 ? 'ok' : 'warn';
    }
}

/* ===== Export CSV ===== */
if (isset($_GET['export'])) {
    $where = buildWhere($fClass, $fDev, $fFrom, $fTo);
    $rows  = $conn->query("SELECT * FROM sensor_log $where ORDER BY id DESC")
                  ->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="plant_stress_'.date('Ymd_His').'.csv"');
    echo "\xEF\xBB\xBF";  // BOM for Excel
    if ($rows) echo implode(',', array_keys($rows[0]))."\n";
    foreach ($rows as $row) {
        echo implode(',', array_map(fn($v) => '"'.str_replace('"','""',(string)$v).'"', $row))."\n";
    }
    exit;
}

/* ===== Pagination + data ===== */
$where  = buildWhere($fClass, $fDev, $fFrom, $fTo);
$perPg  = 20;
$page   = max(1, (int)($_GET['page'] ?? 1));
$total  = (int)$conn->query("SELECT COUNT(*) c FROM sensor_log $where")->fetch_assoc()['c'];
$pages  = max(1, (int)ceil($total / $perPg));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPg;

$rows = $conn->query(
    "SELECT id,device_id,class_id,class_label,confidence,
            soil_moisture,inference_ms,fft_energy,created_at
     FROM sensor_log $where ORDER BY id DESC LIMIT $perPg OFFSET $offset"
)->fetch_all(MYSQLI_ASSOC);

/* ===== Stats strip ===== */
$stats = $conn->query("
    SELECT COUNT(*) total,
           AVG(confidence)*100 acc,
           AVG(soil_moisture)  soil,
           SUM(class_id=0) n0, SUM(class_id=1) n1, SUM(class_id=2) n2,
           MIN(created_at) dt_min, MAX(created_at) dt_max
    FROM sensor_log $where
")->fetch_assoc();

/* ===== device list สำหรับ filter ===== */
$devList = $conn->query("SELECT DISTINCT device_id FROM sensor_log ORDER BY device_id")
               ->fetch_all(MYSQLI_ASSOC);

$conn->close();

/* ===== สร้าง query string สำหรับ link ต่าง ๆ ===== */
$qs = http_build_query(array_filter([
    'f_class'     => $fClass,
    'f_device'    => $fDev,
    'f_date_from' => $fFrom,
    'f_date_to'   => $fTo,
], fn($v) => $v !== ''));

$CLS = [
    0 => ['ปกติ',            '#2E7D32'],
    1 => ['เครียดเล็กน้อย', '#F57F17'],
    2 => ['เครียดรุนแรง',   '#C62828'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Panel — Plant Stress</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>

<header class="ah">
  <div class="ah-inner">
    <div>
      <h1>🛠 Admin Panel — Plant Stress TinyML</h1>
      <p class="asub">จัดการข้อมูล sensor_log · PHP + MySQLi</p>
    </div>
    <nav class="anav">
      <a href="../dashboard/index.php">📊 Dashboard</a>
      <a href="../index.php">🏠 หน้าหลัก</a>
      <a href="../install/setup.php">⚙️ Setup</a>
      <a href="../install/test_ingest.php">🧪 ทดสอบ ESP32</a>
    </nav>
  </div>
</header>

<div class="wrap">

<?php if ($msg): ?>
  <div class="alert alert-<?=$msgType?>"><?=q($msg)?></div>
<?php endif; ?>

<div class="stat-strip">
  <div class="ss-item">
    <span class="ss-n"><?=number_format($stats['total'] ?? 0)?></span>
    <span class="ss-l">รวมทั้งหมด</span>
  </div>
  <div class="ss-item">
    <span class="ss-n" style="color:#3fb950"><?=number_format($stats['n0'] ?? 0)?></span>
    <span class="ss-l">ปกติ</span>
  </div>
  <div class="ss-item">
    <span class="ss-n" style="color:#F57F17"><?=number_format($stats['n1'] ?? 0)?></span>
    <span class="ss-l">เครียดเล็กน้อย</span>
  </div>
  <div class="ss-item">
    <span class="ss-n" style="color:#f85149"><?=number_format($stats['n2'] ?? 0)?></span>
    <span class="ss-l">เครียดรุนแรง</span>
  </div>
  <div class="ss-item">
    <span class="ss-n"><?=$stats['acc'] !== null ? number_format($stats['acc'],1).'%' : '--'?></span>
    <span class="ss-l">ความมั่นใจเฉลี่ย</span>
  </div>
  <div class="ss-item">
    <span class="ss-n"><?=$stats['soil'] !== null ? number_format($stats['soil'],1).'%' : '--'?></span>
    <span class="ss-l">ความชื้นเฉลี่ย</span>
  </div>
  <div class="ss-item" style="flex:2;min-width:180px;">
    <span class="ss-n" style="font-size:1rem"><?=q($stats['dt_min'] ?? '--')?></span>
    <span class="ss-l">ข้อมูลเริ่มต้น</span>
  </div>
  <div class="ss-item" style="flex:2;min-width:180px;">
    <span class="ss-n" style="font-size:1rem"><?=q($stats['dt_max'] ?? '--')?></span>
    <span class="ss-l">ข้อมูลล่าสุด</span>
  </div>
</div>

<form class="filter-bar" method="get" action="index.php">
  <select name="f_class">
    <option value="">— คลาสทั้งหมด —</option>
    <?php foreach ([0=>'ปกติ',1=>'เครียดเล็กน้อย',2=>'เครียดรุนแรง'] as $k=>$v): ?>
      <option value="<?=$k?>" <?=$fClass===(string)$k?'selected':''?>><?=$v?></option>
    <?php endforeach; ?>
  </select>

  <select name="f_device">
    <option value="">— อุปกรณ์ทั้งหมด —</option>
    <?php foreach ($devList as $d): ?>
      <option value="<?=q($d['device_id'])?>" <?=$fDev===$d['device_id']?'selected':''?>><?=q($d['device_id'])?></option>
    <?php endforeach; ?>
  </select>

  <input type="text" class="datetime-picker" name="f_date_from" value="<?=q($fFrom)?>" placeholder="ตั้งแต่วันและเวลา">
  <input type="text" class="datetime-picker" name="f_date_to"   value="<?=q($fTo)?>"   placeholder="ถึงวันและเวลา">
  
  <button type="submit" class="btn-filter">🔍 กรอง</button>
  <a href="index.php" class="btn-clear">ล้าง</a>
  <a href="?<?=$qs?>&export=1" class="btn-export">⬇ Export CSV (<?=number_format($total)?> แถว)</a>
</form>

<div class="tbl-wrap">
  <table class="data-tbl">
    <thead>
      <tr>
        <th>ID</th>
        <th>อุปกรณ์</th>
        <th>สถานะ</th>
        <th>ความมั่นใจ</th>
        <th>ความชื้นดิน</th>
        <th>Inference</th>
        <th>FFT Energy</th>
        <th>เวลาบันทึก</th>
        <th>ลบ</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="9" class="muted-c">ไม่พบข้อมูลที่ตรงกับเงื่อนไข</td></tr>
    <?php else: ?>
      <?php foreach ($rows as $r):
          [$cTh, $cCol] = $CLS[(int)$r['class_id']] ?? ['?', '#555'];
          $soilPct = min(100, max(0, (float)$r['soil_moisture']));
          $soilColor = $soilPct > 40 ? '#2dd4bf' : ($soilPct > 25 ? '#e3b341' : '#f85149');
      ?>
      <tr>
        <td class="id-c"><?=q($r['id'])?></td>
        <td><?=q($r['device_id'])?></td>
        <td><span class="badge" style="background:<?=$cCol?>"><?=$cTh?></span></td>
        <td><?=number_format((float)$r['confidence'] * 100, 1)?>%</td>
        <td>
          <div class="mini-bar-wrap">
            <div class="mini-bar" style="width:<?=$soilPct?>%;background:<?=$soilColor?>"></div>
          </div>
          <?=number_format((float)$r['soil_moisture'], 1)?>%
        </td>
        <td><?=number_format((float)$r['inference_ms'], 2)?> ms</td>
        <td><?=number_format((float)$r['fft_energy'], 3)?></td>
        <td class="dt-c"><?=q($r['created_at'])?></td>
        <td>
          <form method="post" style="margin:0"
                onsubmit="return confirm('ยืนยันลบ record #<?=q($r['id'])?> ?')">
            <input type="hidden" name="delete_id"  value="<?=q($r['id'])?>">
            <input type="hidden" name="f_class"    value="<?=q($fClass)?>">
            <input type="hidden" name="f_device"   value="<?=q($fDev)?>">
            <button type="submit" class="btn-del">✕</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?>
    <a href="?page=<?=$page-1?>&<?=$qs?>">‹ ก่อนหน้า</a>
  <?php endif; ?>
  <?php
  $start = max(1, $page-3); $end = min($pages, $page+3);
  if ($start > 1) echo '<a href="?page=1&'.$qs.'">1</a><span class="pg-gap">…</span>';
  for ($p = $start; $p <= $end; $p++):
  ?>
    <a href="?page=<?=$p?>&<?=$qs?>" class="<?=$p===$page?'active':''?>"><?=$p?></a>
  <?php endfor;
  if ($end < $pages) echo '<span class="pg-gap">…</span><a href="?page='.$pages.'&'.$qs.'">'.$pages.'</a>';
  ?>
  <?php if ($page < $pages): ?>
    <a href="?page=<?=$page+1?>&<?=$qs?>">ถัดไป ›</a>
  <?php endif; ?>
</div>
<p class="pg-info">หน้า <?=$page?>/<?=$pages?> · <?=number_format($total)?> แถว</p>
<?php endif; ?>

<?php if ($total > 0): ?>
<div class="danger-zone">
  <h4>⚠ Danger Zone — ลบข้อมูล</h4>
  <p style="color:#7d8590;font-size:.82rem;margin:0 0 12px">
    ลบ <b><?=number_format($total)?></b> แถวที่กรองอยู่ขณะนี้ทั้งหมด (ไม่สามารถกู้คืนได้)
  </p>
  <form method="post" onsubmit="return confirm('ยืนยันลบ <?=number_format($total)?> แถว? ไม่สามารถกู้คืนได้!')">
    <input type="hidden" name="f_class"       value="<?=q($fClass)?>">
    <input type="hidden" name="f_device"      value="<?=q($fDev)?>">
    <input type="hidden" name="f_date_from"   value="<?=q($fFrom)?>">
    <input type="hidden" name="f_date_to"     value="<?=q($fTo)?>">
    <input type="hidden" name="delete_filtered" value="1">
    <button type="submit" class="btn-danger">
      🗑 ลบ <?=number_format($total)?> แถวที่กรองนี้
    </button>
  </form>
</div>
<?php endif; ?>

</div><footer class="a-footer">
  Plant Stress TinyML Admin Panel · PHP + MySQLi ·
  <a href="../dashboard/index.php">Dashboard</a> ·
  <a href="../index.php">หน้าหลัก</a>
</footer>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>
<script>
  flatpickr(".datetime-picker", {
      enableTime: true,          // เปิดใช้งานการเลือกเวลา
      enableSeconds: true,       // เปิดใช้งานระดับวินาที
      time_24hr: true,           // แสดงผลรูปแบบ 24 ชั่วโมง (ไม่เอา AM/PM)
      dateFormat: "Y-m-d H:i:s", // รูปแบบข้อมูลดิบที่ส่งไปหา SQL Query ด้านบน
      altInput: true,
      altFormat: "d/m/Y H:i:S",  // 🚀 บังคับฟอร์แมตการแสดงผลบนช่อง Input เป็น วัน/เดือน/ปี ช:น:ว
      locale: "th"               // แปลง UI ปฏิทินเป็นภาษาไทย
  });
</script>
</body>
</html>