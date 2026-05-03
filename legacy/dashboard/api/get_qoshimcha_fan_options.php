<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();
$semestrId = (int)($_GET['semestr_id'] ?? 0);

if ($semestrId <= 0) {
    echo json_encode([
        'success' => true,
        'rows' => [],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rows = [];
$res = $db->query("
    SELECT
        f.id,
        f.semestr_id,
        f.fan_code,
        f.fan_name,
        COALESCE(f.tanlov_fan, 0) AS tanlov_fan,
        COALESCE(SUM(CASE WHEN dst.id IN (1,2,3,4) THEN o.dars_soat ELSE 0 END), 0) AS auditoriya_soat
    FROM fanlar f
    LEFT JOIN oquv_rejalar o ON o.fan_id = f.id
    LEFT JOIN dars_soat_turlar dst ON dst.id = o.dars_tur_id
    WHERE f.semestr_id = {$semestrId}
    GROUP BY f.id, f.semestr_id, f.fan_code, f.fan_name, f.tanlov_fan
    ORDER BY f.fan_name, f.id
");

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $fanId = (int)($row['id'] ?? 0);
        $fanName = trim((string)($row['fan_name'] ?? ''));
        if ($fanId <= 0 || $fanName === '') {
            continue;
        }

        $fanCode = trim((string)($row['fan_code'] ?? ''));
        $tanlovFanType = (int)($row['tanlov_fan'] ?? 0);
        $auditoriyaSoat = $tanlovFanType === 0 ? (float)($row['auditoriya_soat'] ?? 0) : 0.0;
        $rows[] = [
            'semestr_id' => (int)($row['semestr_id'] ?? $semestrId),
            'value' => $fanId,
            'fan_name' => $fanName,
            'tanlov_fan' => $tanlovFanType,
            'label' => $fanCode !== '' ? ($fanCode . ' - ' . $fanName) : $fanName,
            'auditoriya_soat' => $auditoriyaSoat,
        ];
    }
}

echo json_encode([
    'success' => true,
    'rows' => $rows,
], JSON_UNESCAPED_UNICODE);
