<?php
    include_once __DIR__ . '/../config.php';
    $db = new Database();
    $teacher_id = isset($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : 0;
    if ($teacher_id <= 0 || !legacy_can_access_teacher($db, $teacher_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'O‘qituvchi topilmadi yoki sizga ruxsat berilmagan.'
        ]);
        return;
    }
    $oqtuvchi_soatlari = $db->get_oqtuvchi_total_hours($teacher_id);
    echo json_encode([
        'success' => true,
        'data' => $oqtuvchi_soatlari
    ]);
?>
