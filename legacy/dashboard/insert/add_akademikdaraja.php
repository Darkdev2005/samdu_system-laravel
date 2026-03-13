<?php
    include_once '../config.php';
    $db = new Database();
    header('Content-Type: application/json');

    if (!isset($_POST['nomi']) || empty(trim($_POST['nomi']))) {
        echo json_encode([
            'success' => false,
            'message' => 'Akademik daraja nomi majburiy'
        ]);
        return;
    }

    $nomi = trim($_POST['nomi']);
    $insertsql = $db->insert('akademik_darajalar', [
        'name' => $nomi
    ]);
    if ($insertsql) {
        echo json_encode([
            'success' => true,
            'message' => 'Akademik daraja muvaffaqiyatli qo\'shildi'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Akademik daraja qo\'shishda xatolik yuz berdi'
        ]);
    }

