<?php
    include_once '../config.php';
    $db = new Database();
    header('Content-Type: application/json');

    $name = trim($_POST['name']);
    $short_name = trim($_POST['short_name']);
    $insertsql = $db->insert('oquv_haftalik_turlar', [
        'name' => $name,
        'short_name' => $short_name
    ]);
    if ($insertsql) {
        echo json_encode([
            'success' => true,
            'message' => 'O\'quv haftalik turi muvaffaqiyatli qo\'shildi'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'O\'quv haftalik turini qo\'shishda xatolik yuz berdi'
        ]);
    }
?>
