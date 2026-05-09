<?php
include_once '../config.php';
header('Content-Type: application/json');

$db = new Database();

$id = (int)($_POST['id'] ?? 0);
$personId = (int)($_POST['person_id'] ?? 0);
$qoshimchaDarsId = (int)($_POST['qoshimcha_dars_id'] ?? 0);
$darsSoati = (float)($_POST['dars_soati'] ?? 0);
$izoh = trim((string)($_POST['izoh'] ?? ''));

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => "Noto'g'ri ID"]);
    return;
}

if ($personId <= 0 || !in_array($qoshimchaDarsId, [9, 10, 11, 12, 13, 14], true) || $darsSoati <= 0) {
    echo json_encode(['success' => false, 'message' => "Ma'lumotlarni to'liq kiriting"]);
    return;
}

$row = $db->get_data_by_table('magistr_doktorant_qoshimcha_rejalar', ['id' => $id]);
if (!$row) {
    echo json_encode(['success' => false, 'message' => "Yozuv topilmadi"]);
    return;
}

$person = $db->get_data_by_table('magistr_doktorant_yuklamalar', ['id' => $personId]);
if (!$person) {
    echo json_encode(['success' => false, 'message' => 'Magistr/Doktorant topilmadi']);
    return;
}

$personType = strtolower(trim((string)($person['turi'] ?? '')));
if (strpos($personType, 'doktor') !== false) {
    $personType = 'doktorant';
} else {
    $personType = 'magistr';
}

$allowedIds = ($personType === 'doktorant') ? [12, 13, 14] : [9, 10, 11];
if (!in_array($qoshimchaDarsId, $allowedIds, true)) {
    echo json_encode(['success' => false, 'message' => "Tanlangan dars turi magistr/doktorant turiga mos emas"]);
    return;
}

$qoshimchaDars = $db->get_data_by_table('qoshimcha_dars_turlar', ['id' => $qoshimchaDarsId]);
if (!$qoshimchaDars) {
    echo json_encode(['success' => false, 'message' => "Dars turi topilmadi"]);
    return;
}

$updated = $db->update('magistr_doktorant_qoshimcha_rejalar', [
    'magistr_doktorant_id' => $personId,
    'qoshimcha_dars_id' => $qoshimchaDarsId,
    'dars_soati' => $darsSoati,
    'izoh' => $izoh,
], 'id = ' . $id);

echo json_encode([
    'success' => (bool)$updated,
    'message' => $updated ? "Magistr/Doktorant qo'shimcha rejasi yangilandi" : 'Yangilashda xatolik'
], JSON_UNESCAPED_UNICODE);
