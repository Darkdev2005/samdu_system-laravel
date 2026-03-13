<?php
    include_once '../config.php';
    $db = new Database();
    header('Content-Type: application/json');

    if (!isset($_POST['nomi']) || empty(trim($_POST['nomi']))) {
        echo json_encode([
            'success' => false,
            'message' => 'Fakultet nomi majburiy'
        ]);
        return;
    }

    $nomi = trim($_POST['nomi']);
    $insertsql = $db->insert('fakultetlar', [
        'name' => $nomi
    ]);
    if ($insertsql) {
        echo json_encode([
            'success' => true,
            'message' => 'Fakultet muvaffaqiyatli qo\'shildi'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Fakultet qo\'shishda xatolik yuz berdi'
        ]);
    }
?>
