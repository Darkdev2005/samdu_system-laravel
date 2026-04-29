<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();

$fakultetId = (int)($_GET['fakultet_id'] ?? 0);
$yonalishId = (int)($_GET['yonalish_id'] ?? 0);
$semestrId = (int)($_GET['semestr_id'] ?? 0);

$yonalishFakultetColRes = $db->query("SHOW COLUMNS FROM yonalishlar LIKE 'fakultet_id'");
$hasYonalishFakultetCol = $yonalishFakultetColRes && mysqli_num_rows($yonalishFakultetColRes) > 0;
$fakultetField = $hasYonalishFakultetCol
    ? '(CASE WHEN y.fakultet_id IS NULL OR y.fakultet_id = 0 THEN s.fakultet_id ELSE y.fakultet_id END)'
    : 's.fakultet_id';

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
$qoshimchaFanIds = [];

$res = $db->query("
    SELECT
        qf.id AS qoshimcha_fanid,
        qf.fan_name,
        qf.fan_soat,
        qf.qoshimcha_dars_id,
        qf.subtype_code,
        qf.formula_meta,
        COALESCE(qdt.name, '-') AS qoshimcha_dars_name,
        s.id AS semestr_id,
        s.semestr AS semestr_num,
        y.id AS yonalish_id,
        y.name AS yonalish_name,
        y.kirish_yili,
        COALESCE(MAX(q.izoh), '') AS izoh
    FROM qoshimcha_fanlar qf
    JOIN semestrlar s ON s.id = qf.semestr_id
    JOIN yonalishlar y ON y.id = s.yonalish_id
    LEFT JOIN qoshimcha_dars_turlar qdt ON qdt.id = qf.qoshimcha_dars_id
    LEFT JOIN qoshimcha_oquv_rejalar q ON q.qoshimcha_fanid = qf.id
    $whereSql
    GROUP BY
        qf.id,
        qf.fan_name,
        qf.fan_soat,
        qf.qoshimcha_dars_id,
        qdt.name,
        s.id,
        s.semestr,
        y.id,
        y.name,
        y.kirish_yili
    ORDER BY s.id DESC, qf.id DESC
");

if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $qoshimchaFanId = (int)($row['qoshimcha_fanid'] ?? 0);
        if ($qoshimchaFanId <= 0) {
            continue;
        }

        $subtypeCode = (string)($row['subtype_code'] ?? '');
        $subtypeLabel = legacy_qoshimcha_subtype_label((int)($row['qoshimcha_dars_id'] ?? 0), $subtypeCode);
        $row['subtype_label'] = $subtypeLabel;
        $row['qoshimcha_dars_display_name'] = legacy_qoshimcha_display_name(
            (string)($row['qoshimcha_dars_name'] ?? '-'),
            (int)($row['qoshimcha_dars_id'] ?? 0),
            $subtypeCode
        );

        $row['allocations'] = [];
        $rows[] = $row;
        $qoshimchaFanIds[] = $qoshimchaFanId;
    }
}

$allocationsByFan = [];
if (count($qoshimchaFanIds) > 0) {
    $fanSql = implode(',', array_map('intval', array_unique($qoshimchaFanIds)));
    $allocRes = $db->query("
        SELECT
            q.qoshimcha_fanid,
            q.kafedra_id,
            COALESCE(k.name, '-') AS kafedra_name,
            SUM(q.dars_soati) AS dars_soati
        FROM qoshimcha_oquv_rejalar q
        LEFT JOIN kafedralar k ON k.id = q.kafedra_id
        WHERE q.qoshimcha_fanid IN ($fanSql)
        GROUP BY q.qoshimcha_fanid, q.kafedra_id, k.name
        ORDER BY q.kafedra_id
    ");

    if ($allocRes) {
        while ($allocRow = mysqli_fetch_assoc($allocRes)) {
            $fanId = (int)($allocRow['qoshimcha_fanid'] ?? 0);
            if ($fanId <= 0) {
                continue;
            }
            if (!isset($allocationsByFan[$fanId])) {
                $allocationsByFan[$fanId] = [];
            }
            $allocationsByFan[$fanId][] = [
                'kafedra_id' => (int)($allocRow['kafedra_id'] ?? 0),
                'kafedra_name' => (string)($allocRow['kafedra_name'] ?? '-'),
                'dars_soati' => (int)($allocRow['dars_soati'] ?? 0),
            ];
        }
    }
}

foreach ($rows as $idx => $row) {
    $fanId = (int)($row['qoshimcha_fanid'] ?? 0);
    $rows[$idx]['allocations'] = $allocationsByFan[$fanId] ?? [];
}

echo json_encode([
    'success' => true,
    'rows' => $rows,
], JSON_UNESCAPED_UNICODE);
