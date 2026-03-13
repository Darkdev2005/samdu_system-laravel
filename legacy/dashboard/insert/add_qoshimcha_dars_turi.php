<?php
    include_once '../config.php';
    $db = new Database();
    header('Content-Type: application/json');

    $name = trim($_POST['name']);
    $koifesent = $_POST['koifesent'];
    $insertsql = $db->insert('qoshimcha_dars_turlar', [
        'name' => $name,
        'koifesent' => $koifesent
    ]);
    if ($insertsql) {
        echo json_encode([
            'success' => true,
            'message' => 'Qo\'shimcha dars tur muvaffaqiyatli qo\'shildi'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Qo\'shimcha dars tur qo\'shishda xatolik yuz berdi'
        ]);
    }
?>
