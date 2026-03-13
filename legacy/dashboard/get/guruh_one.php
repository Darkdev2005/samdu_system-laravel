<?php
include_once '../config.php';
$db = new Database();
header('Content-Type: application/json');

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => "Noto'g'ri ID"
    ]);
    return;
}

$row = $db->get_data_by_table('guruhlar', ['id' => $id]);
if (!$row) {
    echo json_encode([
        'success' => false,
        'message' => "Guruh topilmadi"
    ]);
    return;
}

echo json_encode([
    'success' => true,
    'data' => $row
]);

