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
    'taqsimotlar' => "SELECT COUNT(*) AS cnt FROM taqsimotlar WHERE teacher_id = $id",
    'taqsimotlar_archive' => "SELECT COUNT(*) AS cnt FROM taqsimotlar_archive WHERE teacher_id = $id",
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
        'message' => 'O\'qituvchi taqsimotga bog\'langan: ' . implode(', ', $used)
    ]);
    return;
}

$deleted = $db->delete('oqituvchilar', "id = $id");

if ($deleted) {
    echo json_encode([
        'success' => true,
        'message' => 'O\'qituvchi o\'chirildi'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'O\'qituvchini o\'chirishda xatolik yuz berdi'
    ]);
}
?>
