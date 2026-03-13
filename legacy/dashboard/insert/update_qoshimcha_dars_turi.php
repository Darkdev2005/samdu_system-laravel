<?php
include_once '../config.php';
header('Content-Type: application/json');
$db = new Database();

$id = (int) ($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$koifesent = trim($_POST['koifesent'] ?? '');

if ($id <= 0 || $name === '' || $koifesent === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Ma\'lumotlar to\'liq emas'
    ]);
    return;
}

$updated = $db->update('qoshimcha_dars_turlar', [
    'name' => $name,
    'koifesent' => $koifesent
], "id = $id");

if ($updated) {
    echo json_encode([
        'success' => true,
        'message' => 'Qo\'shimcha dars turi tahrirlandi'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Qo\'shimcha dars turini tahrirlashda xatolik yuz berdi'
    ]);
}
?>
