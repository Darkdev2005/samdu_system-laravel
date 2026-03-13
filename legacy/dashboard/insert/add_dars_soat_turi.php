<?php
    include_once '../config.php';
    $db = new Database();
    header('Content-Type: application/json');

    if (!isset($_POST['nomi']) || empty(trim($_POST['nomi']))) {
        echo json_encode([
            'success' => false,
            'message' => 'Dars soat tur nomi majburiy'
        ]);
        return;
    }

    $nomi = trim($_POST['nomi']);
    $insertsql = $db->insert('dars_soat_turlar', [
        'name' => $nomi
    ]);
    if ($insertsql) {
        echo json_encode([
            'success' => true,
            'message' => 'Dars soat tur muvaffaqiyatli qo\'shildi'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Dars soat tur qo\'shishda xatolik yuz berdi'
        ]);
    }
?>
