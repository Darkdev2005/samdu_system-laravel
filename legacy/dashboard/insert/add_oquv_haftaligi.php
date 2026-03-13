<?php
    include_once '../config.php';
    $db = new Database();
    header('Content-Type: application/json');

    $yonalish_id = trim($_POST['yonalish_id']);
    $oquv_data = $_POST['oquv_data'];

    $decoded_data = json_decode($oquv_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false,
            'message' => 'JSON ma\'lumotlar noto\'g\'ri formatda'
        ]);
        exit();
    }

    $insertsql = $db->insert('oquv_haftaliklar', [
        'yonalish_id' => $yonalish_id,
        'oquv_data' => $oquv_data
    ]);
    
    if ($insertsql) {
        echo json_encode([
            'success' => true,
            'message' => 'O\'quv haftaligi muvaffaqiyatli saqlandi'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'O\'quv haftaligini saqlashda xatolik yuz berdi'
        ]);
    }
?>