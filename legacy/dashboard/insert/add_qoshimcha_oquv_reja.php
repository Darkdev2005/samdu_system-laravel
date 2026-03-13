<?php
    include_once '../config.php';
    header('Content-Type: application/json');
    $db = new Database();
    
    if (
        empty($_POST['semestr_id']) ||
        empty($_POST['fan_nomi']) ||
        empty($_POST['fan_soat']) ||
        empty($_POST['qoshimcha_dars_id']) ||
        empty($_POST['kafedra_id']) ||
        empty($_POST['dars_soati'])
    ) {
        echo json_encode([
            'success' => false,
            'message' => 'Maʼlumotlar to‘liq emas'
        ]);
        return;
    }
    $semestr_id        = (int) $_POST['semestr_id'];
    $fan_nomlar        = $_POST['fan_nomi'];
    $fan_soatlari      = $_POST['fan_soat'];
    $qoshimcha_dars_ids = $_POST['qoshimcha_dars_id'];
    $kafedra_idlar     = $_POST['kafedra_id']; 
    $dars_soatlari     = $_POST['dars_soati']; 
    $izoh              = trim($_POST['izoh'] ?? '');
    
    $insertCount = 0;
    $errors = [];

    foreach ($fan_nomlar as $fanIndex => $fanNameRaw) {
        $fanNameRaw = trim($fanNameRaw);
        $fanName = $fanNameRaw;
        if ($fanNameRaw !== '' && ctype_digit($fanNameRaw)) {
            $fanRow = $db->get_data_by_table('fanlar', ['id' => (int)$fanNameRaw]);
            if (!empty($fanRow['fan_name'])) {
                $fanName = $fanRow['fan_name'];
            } else {
                $fanName = '';
            }
        }
        $fanSoat = (float) ($fan_soatlari[$fanIndex] ?? 0);
        $qoshimchaDarsId = (int) ($qoshimcha_dars_ids[$fanIndex] ?? 0);
        
        if ($fanName === '' || $fanSoat < 0 || $qoshimchaDarsId <= 0) {
            $errors[] = ($fanIndex + 1) . "-fan uchun ma'lumotlar notog'ri";
            continue;
        }
        
        if (!isset($kafedra_idlar[$fanIndex], $dars_soatlari[$fanIndex])) {
            $errors[] = ($fanIndex + 1) . "-fan uchun kafedra/dars soatlari massivi mavjud emas";
            continue;
        }
        $qoshimcha_fanid = $db->insert('qoshimcha_fanlar', [
            'semestr_id' => $semestr_id,
            'fan_name'   => $fanName,
            'fan_soat'   => $fanSoat,
            'qoshimcha_dars_id' => $qoshimchaDarsId
        ]);
        $kafedralar = is_array($kafedra_idlar[$fanIndex]) ? $kafedra_idlar[$fanIndex] : [$kafedra_idlar[$fanIndex]];
        $darsSoatlari = is_array($dars_soatlari[$fanIndex]) ? $dars_soatlari[$fanIndex] : [$dars_soatlari[$fanIndex]];
        
        foreach ($kafedralar as $i => $kafedraId) {
            $kafedraId = (int) $kafedraId;
            $darsSoat = isset($darsSoatlari[$i]) ? (int) $darsSoatlari[$i] : 0;
            
            if ($kafedraId <= 0 || $darsSoat < 0) {
                continue; 
            }
            
            $insert = $db->insert('qoshimcha_oquv_rejalar', [
                'qoshimcha_fanid'    => $qoshimcha_fanid,
                'kafedra_id'           => $kafedraId,
                'dars_soati'           => $darsSoat,
                'izoh'                 => $izoh,
            ]);
            
            if ($insert) {
                $insertCount++;
            } else {
                $errors[] = ($fanIndex + 1) . "-fan uchun saqlashda xatolik";
            }
        }
    }
    
    if ($insertCount === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Saqlash uchun yaroqli maʼlumot topilmadi' 
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Qoʻshimcha oʻquv reja muvaffaqiyatli saqlandi ({$insertCount} ta)"    
    ]);
?>
