<?php
    include_once '../config.php';
    $db = new Database();
    header('Content-Type: application/json');

    if (!isset($_POST['nomi']) || empty(trim($_POST['nomi']))) {
        echo json_encode([
            'success' => false,
            'message' => 'Ta\'lim shakli nomi majburiy'
        ]);
        return;
    }

    $nomi = trim($_POST['nomi']);
    $insertsql = $db->insert('talim_shakllar', [
        'name' => $nomi
    ]);
    if ($insertsql) {
        echo json_encode([
            'success' => true,
            'message' => 'Ta\'lim shakli muvaffaqiyatli qo\'shildi'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Ta\'lim shakli qo\'shishda xatolik yuz berdi'
        ]);
    }

