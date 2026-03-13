<?php
    include_once '../config.php';
    $db = new Database();
    $teacher_id = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
    $oqtuvchi_soatlari = $db->get_oqtuvchi_total_hours($teacher_id);
    echo json_encode([
        'success' => true,
        'data' => $oqtuvchi_soatlari
    ]);
?>