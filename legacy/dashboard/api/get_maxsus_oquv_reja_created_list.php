<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();

$filters = [];
if (!empty($_GET['kafedra_id'])) {
    $filters['kafedra_id'] = (int)$_GET['kafedra_id'];
}
if (!empty($_GET['semestr_id'])) {
    $filters['semestr_id'] = (int)$_GET['semestr_id'];
}
if (!empty($_GET['yonalish_id'])) {
    $filters['yonalish_id'] = (int)$_GET['yonalish_id'];
}
if (!empty($_GET['guruh_id'])) {
    $filters['guruh_id'] = (int)$_GET['guruh_id'];
}
legacy_apply_kafedra_scope($filters);

$rows = $db->get_maxsus_oquv_reja_created_list($filters);
$darsTurlari = $db->get_data_by_table_all('dars_soat_turlar');

echo json_encode([
    'success' => true,
    'rows' => $rows,
    'dars_turlari' => $darsTurlari,
], JSON_UNESCAPED_UNICODE);

