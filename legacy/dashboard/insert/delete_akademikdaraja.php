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

$res = $db->query("SELECT COUNT(*) AS cnt FROM yonalishlar WHERE akademik_daraja_id = $id");
$usedInYonalish = (int) (mysqli_fetch_assoc($res)['cnt'] ?? 0);

if ($usedInYonalish > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Bu akademik daraja yo\'nalishlarga biriktirilgan'
    ]);
    return;
}

$deleted = $db->delete('akademik_darajalar', "id = $id");

if ($deleted) {
    echo json_encode([
        'success' => true,
        'message' => 'Akademik daraja o\'chirildi'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Akademik darajani o\'chirishda xatolik yuz berdi'
    ]);
}
?>
