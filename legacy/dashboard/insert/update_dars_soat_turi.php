<?php
include_once '../config.php';
header('Content-Type: application/json');
$db = new Database();

$id = (int) ($_POST['id'] ?? 0);
$nomi = trim($_POST['nomi'] ?? '');

if ($id <= 0 || $nomi === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Ma\'lumotlar to\'liq emas'
    ]);
    return;
}

$updated = $db->update('dars_soat_turlar', [
    'name' => $nomi
], "id = $id");

if ($updated) {
    echo json_encode([
        'success' => true,
        'message' => 'Dars soat turi tahrirlandi'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Dars soat turini tahrirlashda xatolik yuz berdi'
    ]);
}
?>
