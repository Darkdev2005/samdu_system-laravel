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

$checks = [
    'kafedralar' => "SELECT COUNT(*) AS cnt FROM kafedralar WHERE fakultet_id = $id",
    'yonalishlar' => "SELECT COUNT(*) AS cnt FROM yonalishlar WHERE fakultet_id = $id",
    'oqituvchilar' => "SELECT COUNT(*) AS cnt FROM oqituvchilar WHERE fakultet_id = $id",
    'semestrlar' => "SELECT COUNT(*) AS cnt FROM semestrlar WHERE fakultet_id = $id",
];

$used = [];
foreach ($checks as $label => $sql) {
    $res = $db->query($sql);
    $count = (int) (mysqli_fetch_assoc($res)['cnt'] ?? 0);
    if ($count > 0) {
        $used[] = "{$label}: {$count}";
    }
}

if (!empty($used)) {
    echo json_encode([
        'success' => false,
        'message' => 'Fakultet bog\'langan: ' . implode(', ', $used)
    ]);
    return;
}

$deleted = $db->delete('fakultetlar', "id = $id");

if ($deleted) {
    echo json_encode([
        'success' => true,
        'message' => 'Fakultet o\'chirildi'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Fakultetni o\'chirishda xatolik yuz berdi'
    ]);
}
?>
