<?php
include_once '../config.php';
header('Content-Type: application/json');

$db = new Database();

$where = [];
if (!empty($_GET['kurs'])) {
    $where[] = 'mdy.kurs = ' . (int)$_GET['kurs'];
}
if (!empty($_GET['kafedra_id'])) {
    $where[] = 'mdy.kafedra_id = ' . (int)$_GET['kafedra_id'];
}

$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
$rows = [];
$result = $db->query("
    SELECT
        mdy.id,
        mdy.turi,
        mdy.kurs,
        mdy.kirish_yili,
        mdy.kod,
        mdy.ism_familiya,
        mdy.kafedra_id,
        k.name AS kafedra_name
    FROM magistr_doktorant_yuklamalar mdy
    JOIN kafedralar k ON k.id = mdy.kafedra_id
    $whereSQL
    ORDER BY mdy.kurs, mdy.turi, mdy.ism_familiya
");

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
}

echo json_encode(['success' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
