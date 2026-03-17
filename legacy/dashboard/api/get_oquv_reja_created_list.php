<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();

$fakultetId = (int)($_GET['fakultet_id'] ?? 0);
$yonalishId = (int)($_GET['yonalish_id'] ?? 0);
$semestrId = (int)($_GET['semestr_id'] ?? 0);

$yonalishFakultetColRes = $db->query("SHOW COLUMNS FROM yonalishlar LIKE 'fakultet_id'");
$hasYonalishFakultetCol = $yonalishFakultetColRes && mysqli_num_rows($yonalishFakultetColRes) > 0;
$fakultetField = $hasYonalishFakultetCol ? 'y.fakultet_id' : 's.fakultet_id';

$where = [];
if ($fakultetId > 0) {
    $where[] = "$fakultetField = $fakultetId";
}
if ($yonalishId > 0) {
    $where[] = "y.id = $yonalishId";
}
if ($semestrId > 0) {
    $where[] = "s.id = $semestrId";
}
$whereSql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$rows = [];
$fanIds = [];

$res = $db->query("
    SELECT
        f.id AS fan_id,
        f.fan_code,
        f.fan_name,
        f.tanlov_fan,
        f.kafedra_id,
        COALESCE(k.name, '-') AS kafedra_name,
        s.id AS semestr_id,
        s.semestr AS semestr_num,
        y.name AS yonalish_name,
        y.kirish_yili,
        COALESCE(MAX(o.izoh), '') AS izoh
    FROM fanlar f
    JOIN oquv_rejalar o ON o.fan_id = f.id
    JOIN semestrlar s ON s.id = f.semestr_id
    JOIN yonalishlar y ON y.id = s.yonalish_id
    LEFT JOIN kafedralar k ON k.id = f.kafedra_id
    $whereSql
    GROUP BY
        f.id, f.fan_code, f.fan_name, f.tanlov_fan, f.kafedra_id,
        k.name, s.id, s.semestr, y.name, y.kirish_yili
    ORDER BY s.id DESC, f.id DESC
");

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $fanId = (int)($row['fan_id'] ?? 0);
        if ($fanId <= 0) {
            continue;
        }

        $tanlovFan = (int)($row['tanlov_fan'] ?? 0);
        $kafedraId = (int)($row['kafedra_id'] ?? 0);
        $row['kafedra_lock'] = ($kafedraId === 0 && ($tanlovFan === 1 || $tanlovFan === 3)) ? 1 : 0;
        $row['dars'] = [];

        $rows[] = $row;
        $fanIds[] = $fanId;
    }
}

$darsByFan = [];
if (count($fanIds) > 0) {
    $fanSql = implode(',', array_map('intval', array_unique($fanIds)));
    $darsRes = $db->query("
        SELECT
            fan_id,
            dars_tur_id,
            SUM(dars_soat) AS soat
        FROM oquv_rejalar
        WHERE fan_id IN ($fanSql)
        GROUP BY fan_id, dars_tur_id
    ");
    if ($darsRes) {
        while ($darsRow = mysqli_fetch_assoc($darsRes)) {
            $fanId = (int)($darsRow['fan_id'] ?? 0);
            $darsTurId = (int)($darsRow['dars_tur_id'] ?? 0);
            if ($fanId <= 0 || $darsTurId <= 0) {
                continue;
            }
            if (!isset($darsByFan[$fanId])) {
                $darsByFan[$fanId] = [];
            }
            $darsByFan[$fanId][(string)$darsTurId] = (int)($darsRow['soat'] ?? 0);
        }
    }
}

foreach ($rows as $idx => $row) {
    $fanId = (int)($row['fan_id'] ?? 0);
    $rows[$idx]['dars'] = $darsByFan[$fanId] ?? [];
}

$darsTurlari = $db->get_data_by_table_all('dars_soat_turlar');

echo json_encode([
    'success' => true,
    'rows' => $rows,
    'dars_turlari' => $darsTurlari,
], JSON_UNESCAPED_UNICODE);
