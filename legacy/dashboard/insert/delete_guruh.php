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

try {
    $db->query("START TRANSACTION");

    $old = $db->get_data_by_table('guruhlar', ['id' => $id]);
    if (!$old) {
        $db->query("ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => "Guruh topilmadi"
        ]);
        return;
    }

    $historySaved = $db->insert('guruhlar_history', [
        'guruh_id' => $old['id'],
        'yonalish_id' => $old['yonalish_id'],
        'guruh_nomer' => $old['guruh_nomer'],
        'soni' => $old['soni'],
        'sync_status' => 'nosync',
        'change_type' => 'delete'
    ]);

    if (!$historySaved) {
        $db->query("ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => "Tarixni saqlab bo'lmadi"
        ]);
        return;
    }

    $deleted = $db->delete('guruhlar', "id = $id");
    if (!$deleted) {
        $db->query("ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => "Guruhni o'chirishda xatolik yuz berdi"
        ]);
        return;
    }

    $db->query("COMMIT");
    echo json_encode([
        'success' => true,
        'message' => "Guruh o'chirildi"
    ]);
} catch (Throwable $e) {
    $db->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => "O'chirishda texnik xatolik yuz berdi"
    ]);
}
?>
