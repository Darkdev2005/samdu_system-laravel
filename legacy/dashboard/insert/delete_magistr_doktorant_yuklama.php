<?php
include_once '../config.php';
header('Content-Type: application/json');

$db = new Database();
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => "Noto'g'ri ID"]);
    return;
}

$qRows = $db->get_data_by_table_all('magistr_doktorant_qoshimcha_rejalar', 'WHERE magistr_doktorant_id = ' . $id);
$qIds = [];
foreach ($qRows as $qRow) {
    $qid = (int)($qRow['id'] ?? 0);
    if ($qid > 0) {
        $qIds[] = $qid;
    }
}

$deleted = $db->delete('magistr_doktorant_yuklamalar', 'id = ' . $id);
if ($deleted && !empty($qIds)) {
    $idsSql = implode(',', array_map('intval', $qIds));
    $db->delete('magistr_doktorant_qoshimcha_rejalar', "id IN ({$idsSql})");
    $db->delete('taqsimotlar', "type = 'D' AND oquv_reja_id IN ({$idsSql})");
}
echo json_encode([
    'success' => (bool)$deleted,
    'message' => $deleted ? "Yozuv o'chirildi" : "O'chirishda xatolik"
]);
