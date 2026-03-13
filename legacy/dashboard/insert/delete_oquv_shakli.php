<?php
include_once '../config.php';
header('Content-Type: application/json');
$db = new Database();

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID noto\'g\'ri'
    ]);
    return;
}

$deleted = $db->delete('oquv_shakllar', "id = $id");

if ($deleted) {
    echo json_encode([
        'success' => true,
        'message' => 'O\'quv shakli o\'chirildi'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'O\'quv shaklini o\'chirishda xatolik yuz berdi'
    ]);
}
?>
