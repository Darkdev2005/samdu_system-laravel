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

$res = $db->query("SELECT COUNT(*) AS cnt FROM oquv_haftaliklar WHERE haftalik_turi = $id");
$usedInHaftaliklar = (int) (mysqli_fetch_assoc($res)['cnt'] ?? 0);

if ($usedInHaftaliklar > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Bu tur o\'quv haftaliklariga biriktirilgan'
    ]);
    return;
}

$deleted = $db->delete('oquv_haftalik_turlar', "id = $id");

if ($deleted) {
    echo json_encode([
        'success' => true,
        'message' => 'O\'quv haftalik turi o\'chirildi'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'O\'quv haftalik turini o\'chirishda xatolik yuz berdi'
    ]);
}
?>
