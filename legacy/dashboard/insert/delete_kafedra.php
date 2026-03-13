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
    'oqituvchilar' => "SELECT COUNT(*) AS cnt FROM oqituvchilar WHERE kafedra_id = $id",
    'fanlar' => "SELECT COUNT(*) AS cnt FROM fanlar WHERE kafedra_id = $id",
    'qoshimcha_oquv_rejalar' => "SELECT COUNT(*) AS cnt FROM qoshimcha_oquv_rejalar WHERE kafedra_id = $id",
    'umumtalim_fanlar' => "SELECT COUNT(*) AS cnt FROM umumtalim_fanlar WHERE kafedra_id = $id",
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
        'message' => 'Kafedra bog\'langan: ' . implode(', ', $used)
    ]);
    return;
}

$deleted = $db->delete('kafedralar', "id = $id");

if ($deleted) {
    echo json_encode([
        'success' => true,
        'message' => 'Kafedra o\'chirildi'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Kafedrani o\'chirishda xatolik yuz berdi'
    ]);
}
?>
