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

$deleted = $db->delete('qoshimcha_dars_turlar', "id = $id");

if ($deleted) {
    echo json_encode([
        'success' => true,
        'message' => 'Qo\'shimcha dars turi o\'chirildi'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Qo\'shimcha dars turini o\'chirishda xatolik yuz berdi'
    ]);
}
?>
