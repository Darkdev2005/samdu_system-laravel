<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database();

    $fakultetId = (int)($_GET['fakultet_id'] ?? 0);
    $yonalishId = (int)($_GET['yonalish_id'] ?? 0);
    $semestrId = (int)($_GET['semestr_id'] ?? 0);

    $yonalishFakultetColRes = $db->query("SHOW COLUMNS FROM yonalishlar LIKE 'fakultet_id'");
    $hasYonalishFakultetCol = $yonalishFakultetColRes && mysqli_num_rows($yonalishFakultetColRes) > 0;
    $fakultetField = $hasYonalishFakultetCol
        ? '(CASE WHEN y.fakultet_id IS NULL OR y.fakultet_id = 0 THEN s.fakultet_id ELSE y.fakultet_id END)'
        : 's.fakultet_id';

    $where = [
        'f.tanlov_fan = 1',
        'f.kafedra_id > 0',
    ];

    if ($fakultetId > 0) {
        $where[] = "$fakultetField = $fakultetId";
    }
    if ($yonalishId > 0) {
        $where[] = "y.id = $yonalishId";
    }
    if ($semestrId > 0) {
        $where[] = "s.id = $semestrId";
    }

    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $rows = [];
    $res = $db->query("
        SELECT
            f.id AS fan_id,
            f.fan_code,
            f.fan_name,
            f.kafedra_id,
            COALESCE(k.name, '-') AS kafedra_name,
            s.id AS semestr_id,
            s.semestr AS semestr_num,
            y.id AS yonalish_id,
            y.name AS yonalish_name,
            y.kirish_yili,
            (
                SELECT b.fan_name
                FROM fanlar b
                WHERE b.semestr_id = f.semestr_id
                  AND b.fan_code = f.fan_code
                  AND b.tanlov_fan = 1
                  AND (b.kafedra_id = 0 OR b.kafedra_id IS NULL OR b.kafedra_id = '')
                ORDER BY b.id DESC
                LIMIT 1
            ) AS base_fan_name
        FROM fanlar f
        JOIN semestrlar s ON s.id = f.semestr_id
        JOIN yonalishlar y ON y.id = s.yonalish_id
        LEFT JOIN kafedralar k ON k.id = f.kafedra_id
        $whereSql
        ORDER BY s.id DESC, f.id DESC
    ");

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = [
                'fan_id' => (int)($row['fan_id'] ?? 0),
                'fan_code' => (string)($row['fan_code'] ?? ''),
                'fan_name' => (string)($row['fan_name'] ?? ''),
                'kafedra_id' => (int)($row['kafedra_id'] ?? 0),
                'kafedra_name' => (string)($row['kafedra_name'] ?? '-'),
                'semestr_id' => (int)($row['semestr_id'] ?? 0),
                'semestr_num' => (string)($row['semestr_num'] ?? ''),
                'yonalish_id' => (int)($row['yonalish_id'] ?? 0),
                'yonalish_name' => (string)($row['yonalish_name'] ?? ''),
                'kirish_yili' => (string)($row['kirish_yili'] ?? ''),
                'base_fan_name' => (string)($row['base_fan_name'] ?? ''),
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'rows' => $rows,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'rows' => [],
    ], JSON_UNESCAPED_UNICODE);
}
