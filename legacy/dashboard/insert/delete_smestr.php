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
    'fanlar' => "SELECT COUNT(*) AS cnt FROM fanlar WHERE semestr_id = $id",
    'qoshimcha_fanlar' => "SELECT COUNT(*) AS cnt FROM qoshimcha_fanlar WHERE semestr_id = $id",
    'umumtalim_fan_biriktirish' => "SELECT COUNT(*) AS cnt FROM umumtalim_fan_biriktirish WHERE semestr_id = $id",
    'chet_tili_guruhlar' => "SELECT COUNT(*) AS cnt FROM chet_tili_guruhlar WHERE semestr_id = $id",
    'ishchi_oquv_reja' => "SELECT COUNT(*) AS cnt FROM ishchi_oquv_reja WHERE semestr_id = $id",
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
        'message' => 'Semestr bog\'langan: ' . implode(', ', $used)
    ]);
    return;
}

$deleted = $db->delete('semestrlar', "id = $id");

if ($deleted) {
    echo json_encode([
        'success' => true,
        'message' => 'Semestr o\'chirildi'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Semestrni o\'chirishda xatolik yuz berdi'
    ]);
}
?>
