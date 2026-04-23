<?php
include_once '../config.php';
header('Content-Type: application/json');

$db = new Database();
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => "Noto'g'ri ID"]);
    return;
}

$deleted = $db->delete('magistr_doktorant_yuklamalar', 'id = ' . $id);
echo json_encode([
    'success' => (bool)$deleted,
    'message' => $deleted ? "Yozuv o'chirildi" : "O'chirishda xatolik"
]);
