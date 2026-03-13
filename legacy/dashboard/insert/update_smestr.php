<?php
include_once '../config.php';
header('Content-Type: application/json');
$db = new Database();

$id = (int) ($_POST['id'] ?? 0);
$semestr = (int) ($_POST['semestr'] ?? 0);

if ($id <= 0 || $semestr <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Ma\'lumotlar noto\'g\'ri'
    ]);
    return;
}

$current = $db->get_data_by_table('semestrlar', ['id' => $id]);
if (!$current) {
    echo json_encode([
        'success' => false,
        'message' => 'Semestr topilmadi'
    ]);
    return;
}

$yonalishId = (int) $current['yonalish_id'];
$checkUnique = $db->query("
    SELECT COUNT(*) AS cnt
    FROM semestrlar
    WHERE yonalish_id = $yonalishId
      AND semestr = $semestr
      AND id <> $id
");
$alreadyExists = (int) (mysqli_fetch_assoc($checkUnique)['cnt'] ?? 0);
if ($alreadyExists > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Bu yo\'nalishda bu semestr raqami allaqachon mavjud'
    ]);
    return;
}

$yonalish = $db->get_data_by_table('yonalishlar', ['id' => $yonalishId]);
if ($yonalish) {
    $maxSemestr = max(0, ((int) $yonalish['muddati']) * 2);
    if ($maxSemestr > 0 && $semestr > $maxSemestr) {
        echo json_encode([
            'success' => false,
            'message' => "Bu yo'nalish uchun maksimal semestr: {$maxSemestr}"
        ]);
        return;
    }
}

$updated = $db->update('semestrlar', [
    'semestr' => $semestr
], "id = $id");

if ($updated) {
    echo json_encode([
        'success' => true,
        'message' => 'Semestr tahrirlandi'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Semestrni tahrirlashda xatolik yuz berdi'
    ]);
}
?>
