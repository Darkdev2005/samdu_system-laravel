<?php
include_once '../config.php';
header('Content-Type: application/json');

$db = new Database();

$turi = trim((string)($_POST['turi'] ?? ''));
$kurs = (int)($_POST['kurs'] ?? 0);
$kirishYili = (int)($_POST['kirish_yili'] ?? 0);
$kod = trim((string)($_POST['kod'] ?? ''));
$ismFamiliya = trim((string)($_POST['ism_familiya'] ?? ''));
$kafedraId = (int)($_POST['kafedra_id'] ?? 0);

if (!in_array($turi, ['magistr', 'doktorant'], true) || $kurs < 1 || $kurs > 3 || $kirishYili < 2000 || $kirishYili > 2100 || $kod === '' || $ismFamiliya === '' || $kafedraId <= 0) {
    echo json_encode(['success' => false, 'message' => "Ma'lumotlarni to'liq kiriting"]);
    return;
}

$kafedra = $db->get_data_by_table('kafedralar', ['id' => $kafedraId]);
if (!$kafedra) {
    echo json_encode(['success' => false, 'message' => 'Kafedra topilmadi']);
    return;
}

$insertId = $db->insert('magistr_doktorant_yuklamalar', [
    'semestr_id' => 0,
    'turi' => $turi,
    'kurs' => $kurs,
    'kirish_yili' => $kirishYili,
    'kod' => $kod,
    'ism_familiya' => $ismFamiliya,
    'kafedra_id' => $kafedraId,
]);

echo json_encode([
    'success' => (bool)$insertId,
    'message' => $insertId ? 'Magistr/doktorant yozuvi saqlandi' : 'Saqlashda xatolik'
]);
