<?php
include_once '../config.php';
header('Content-Type: application/json');

$db = new Database();

$id = (int)($_POST['id'] ?? 0);
$turi = trim((string)($_POST['turi'] ?? ''));
$kurs = (int)($_POST['kurs'] ?? 0);
$kirishYili = (int)($_POST['kirish_yili'] ?? 0);
$kod = trim((string)($_POST['kod'] ?? ''));
$ismFamiliya = trim((string)($_POST['ism_familiya'] ?? ''));
$kafedraId = (int)($_POST['kafedra_id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => "Noto'g'ri ID"]);
    return;
}

if (!in_array($turi, ['magistr', 'doktorant'], true) || $kurs < 1 || $kurs > 3 || $kirishYili < 2000 || $kirishYili > 2100 || $kod === '' || $ismFamiliya === '' || $kafedraId <= 0) {
    echo json_encode(['success' => false, 'message' => "Ma'lumotlarni to'liq kiriting"]);
    return;
}

$row = $db->get_data_by_table('magistr_doktorant_yuklamalar', ['id' => $id]);
if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Magistr/Doktorant topilmadi']);
    return;
}

$kafedra = $db->get_data_by_table('kafedralar', ['id' => $kafedraId]);
if (!$kafedra) {
    echo json_encode(['success' => false, 'message' => 'Kafedra topilmadi']);
    return;
}

if ((string)($row['turi'] ?? '') !== $turi) {
    $allowedIds = $turi === 'doktorant' ? [12, 13, 14] : [9, 10, 11];
    $allowedSql = implode(',', array_map('intval', $allowedIds));
    $conflict = $db->query("
        SELECT id
        FROM magistr_doktorant_qoshimcha_rejalar
        WHERE magistr_doktorant_id = $id
          AND qoshimcha_dars_id NOT IN ($allowedSql)
        LIMIT 1
    ");

    if ($conflict && mysqli_fetch_assoc($conflict)) {
        echo json_encode([
            'success' => false,
            'message' => "Bu yozuvda turiga mos bo'lmagan qo'shimcha reja bor. Avval qo'shimcha rejani o'chiring yoki alohida qayta kiriting."
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
}

$updated = $db->update('magistr_doktorant_yuklamalar', [
    'semestr_id' => 0,
    'turi' => $turi,
    'kurs' => $kurs,
    'kirish_yili' => $kirishYili,
    'kod' => $kod,
    'ism_familiya' => $ismFamiliya,
    'kafedra_id' => $kafedraId,
], 'id = ' . $id);

echo json_encode([
    'success' => (bool)$updated,
    'message' => $updated ? 'Magistr/Doktorant yozuvi yangilandi' : 'Yangilashda xatolik'
], JSON_UNESCAPED_UNICODE);
