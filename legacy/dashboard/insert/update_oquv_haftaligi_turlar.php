<?php
include_once '../config.php';
header('Content-Type: application/json');
$db = new Database();

$id = (int) ($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$shortName = trim($_POST['short_name'] ?? '');

if ($id <= 0 || $name === '' || $shortName === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Ma\'lumotlar to\'liq emas'
    ]);
    return;
}

$updated = $db->update('oquv_haftalik_turlar', [
    'name' => $name,
    'short_name' => $shortName
], "id = $id");

if ($updated) {
    echo json_encode([
        'success' => true,
        'message' => 'O\'quv haftalik turi tahrirlandi'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'O\'quv haftalik turini tahrirlashda xatolik yuz berdi'
    ]);
}
?>
