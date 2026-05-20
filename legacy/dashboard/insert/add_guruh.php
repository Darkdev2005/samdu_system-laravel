<?php
include_once '../config.php';
$db = new Database();
header('Content-Type: application/json');

$yonalish_id = (int)($_POST['yonalish_id'] ?? 0);
$guruh_nomi = trim((string)($_POST['guruh_nomi'] ?? ''));
$talaba_soni = (int)($_POST['talaba_soni'] ?? 0);

if ($yonalish_id <= 0 || $guruh_nomi === '' || $talaba_soni < 0) {
    echo json_encode([
        'success' => false,
        'message' => "Ma'lumotlar noto'g'ri"
    ]);
    return;
}

try {
    $db->query("START TRANSACTION");

    $newGroupId = (int)$db->insert('guruhlar', [
        'yonalish_id' => $yonalish_id,
        'guruh_nomer' => $guruh_nomi,
        'soni' => $talaba_soni
    ]);

    if ($newGroupId <= 0) {
        $db->query("ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => "Guruh qo'shishda xatolik yuz berdi"
        ]);
        return;
    }

    $historyId = (int)$db->insert('guruhlar_history', [
        'guruh_id' => $newGroupId,
        'yonalish_id' => $yonalish_id,
        'guruh_nomer' => $guruh_nomi,
        'soni' => $talaba_soni,
        'sync_status' => 'nosync',
        'change_type' => 'create'
    ]);
    if ($historyId <= 0) {
        $db->query("ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => "Guruh tarixi saqlanmadi"
        ]);
        return;
    }

    $db->query("COMMIT");
    echo json_encode([
        'success' => true,
        'message' => "Guruh muvaffaqiyatli qo'shildi"
    ]);
} catch (Throwable $e) {
    $db->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => "Guruh qo'shishda texnik xatolik yuz berdi"
    ]);
}
?>
