<?php
    include_once '../config.php';
    $db = new Database();
    $yuklama_id = isset($_POST['yuklama_id']) ? (int)$_POST['yuklama_id'] : 0;
    $type = isset($_POST['type']) ? trim($_POST['type']) : '';
    
    if ($yuklama_id == 0 || empty($type)) {
        echo json_encode([
            'success' => false,
            'message' => 'Noto‘g‘ri so‘rov yuborildi.'
        ]);
        return;
    }
    
    if ($type === 'A') {
        $filters = ['oquv_reja_id' => $yuklama_id];
        $oquv_reja = $db->get_oquv_taqsimotlar($filters);
        $oquv_taqsimotlar = $db->get_taqsimot_by_teacher($yuklama_id, $type);
        if (!empty($oquv_reja)) {
            $data = $oquv_reja[0]; 
            echo json_encode([
                'success' => true,
                'data' => $data,
                'taqsimotlar' => $oquv_taqsimotlar
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Ma\'lumot topilmadi'
            ]);
        }
        
    } else if ($type === 'Q') {
        $filters = ['qoshimcha_oquv_reja_id' => $yuklama_id];
        $oquv_reja = $db->get_qoshimcha_oquv_taqsimotlar($filters);
        $oquv_taqsimotlar = $db->get_taqsimot_by_teacher($yuklama_id, $type);
        if (!empty($oquv_reja)) {
            $data = $oquv_reja[0]; 
            echo json_encode([
                'success' => true,
                'data' => $data,
                'taqsimotlar' => $oquv_taqsimotlar
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Ma\'lumot topilmadi'
            ]);
        }
        
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Noto‘g‘ri so‘rov yuborildi.'
        ]);
    }
?>