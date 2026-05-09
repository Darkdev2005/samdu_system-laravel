<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();

$maxsusRejaId = (int)($_POST['maxsus_reja_id'] ?? 0);
if ($maxsusRejaId <= 0) {
    echo json_encode(['success' => false, 'message' => "Maxsus reja ID noto'g'ri"]);
    return;
}

$reja = $db->get_data_by_table('maxsus_oquv_rejalar', ['id' => $maxsusRejaId]);
if (!$reja) {
    echo json_encode(['success' => false, 'message' => "Maxsus reja topilmadi"]);
    return;
}

if (legacy_is_kafedra_mudiri()) {
    $lockedKafedraId = legacy_user_kafedra_id();
    if ($lockedKafedraId <= 0) {
        echo json_encode(['success' => false, 'message' => "Kafedra aniqlanmadi"]);
        return;
    }
    if ((int)($reja['kafedra_id'] ?? 0) !== $lockedKafedraId) {
        echo json_encode(['success' => false, 'message' => "Bu yozuv sizning kafedrangizga tegishli emas"]);
        return;
    }
}

$db->query("START TRANSACTION");
$ok = true;

$soatIds = [];
$res = $db->query("SELECT id FROM maxsus_oquv_reja_soatlar WHERE maxsus_reja_id = {$maxsusRejaId}");
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $id = (int)($row['id'] ?? 0);
        if ($id > 0) {
            $soatIds[] = $id;
        }
    }
}

if ($ok && !empty($soatIds)) {
    $soatIdsSql = implode(',', array_map('intval', array_unique($soatIds)));
    $ok = $ok && $db->query("DELETE FROM taqsimotlar WHERE type = 'M' AND oquv_reja_id IN ({$soatIdsSql})");
}

if ($ok) {
    $ok = $ok && $db->query("DELETE FROM maxsus_oquv_reja_soatlar WHERE maxsus_reja_id = {$maxsusRejaId}");
}

if ($ok) {
    $ok = $ok && $db->delete('maxsus_oquv_rejalar', 'id = ' . $maxsusRejaId);
}

if ($ok) {
    $pendingExists = $db->get_data_by_table('taqsimot_resync_events', [
        'entity_type' => 'maxsus_guruh',
        'entity_id' => (int)($reja['guruh_id'] ?? 0),
        'yonalish_id' => (int)($reja['yonalish_id'] ?? 0),
        'status' => 'pending',
    ]);
    if (!$pendingExists) {
        $db->insert('taqsimot_resync_events', [
            'entity_type' => 'maxsus_guruh',
            'entity_id' => (int)($reja['guruh_id'] ?? 0),
            'yonalish_id' => (int)($reja['yonalish_id'] ?? 0),
            'reason' => "Maxsus guruh fani o'chirildi",
            'archived_rows' => 0,
            'status' => 'pending',
        ]);
    }
}

if ($ok) {
    $db->query("COMMIT");
    echo json_encode(['success' => true, 'message' => "Maxsus reja fani o'chirildi"]);
} else {
    $db->query("ROLLBACK");
    echo json_encode(['success' => false, 'message' => "O'chirishda xatolik yuz berdi"]);
}

