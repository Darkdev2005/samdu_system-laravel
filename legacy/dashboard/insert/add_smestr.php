<?php
    include_once '../config.php';
    $db = new Database();
    $insertsql = $db->insert_semestrlar();
    if ($insertsql) {
        echo json_encode([
            'success' => true,
            'message' => 'Semestrlar muvaffaqiyatli qo\'shildi'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Semestrlar qo\'shishda xatolik yuz berdi'
        ]);
    }
?>
