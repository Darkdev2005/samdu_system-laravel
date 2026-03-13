<?php
    include_once '../config.php';
    $db = new Database();
    header('Content-Type: application/json');


    $yonalish_id = trim($_POST['yonalish_id']);
    $guruh_nomi = trim($_POST['guruh_nomi']);
    $talaba_soni = $_POST['talaba_soni'];
    $insertsql = $db->insert('guruhlar', [
        'yonalish_id'=> $yonalish_id,
        'guruh_nomer' => $guruh_nomi,
        'soni' => $talaba_soni
    ]);
    if ($insertsql) {
        echo json_encode([
            'success' => true,
            'message' => 'Guruh muvaffaqiyatli qo\'shildi'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Guruh qo\'shishda xatolik yuz berdi'
        ]);
    }
?>
