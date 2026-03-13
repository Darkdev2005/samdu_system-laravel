<?php
include_once '../config.php';
header('Content-Type: application/json');
$db = new Database();

$id = (int) ($_POST['id'] ?? 0);
$nomi = trim($_POST['nomi'] ?? '');
$fakultetId = (int) ($_POST['fakultet_id'] ?? 0);

if ($id <= 0 || $nomi === '' || $fakultetId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Ma\'lumotlar to\'liq emas'
    ]);
    return;
}

$updated = $db->update('kafedralar', [
    'name' => $nomi,
    'fakultet_id' => $fakultetId
], "id = $id");

if ($updated) {
    echo json_encode([
        'success' => true,
        'message' => 'Kafedra tahrirlandi'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Kafedrani tahrirlashda xatolik yuz berdi'
    ]);
}
?>
