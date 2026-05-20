<?php
    include_once __DIR__ . '/../config.php';
    $db = new Database();
    $yuklama_id = isset($_POST['yuklama_id']) ? (int)$_POST['yuklama_id'] : 0;
    $legacy_yuklama_id = isset($_POST['legacy_yuklama_id']) ? (int)$_POST['legacy_yuklama_id'] : 0;
    $type = isset($_POST['type']) ? trim($_POST['type']) : '';
    
    if ($yuklama_id == 0 || empty($type)) {
        echo json_encode([
            'success' => false,
            'message' => 'Noto‘g‘ri so‘rov yuborildi.'
        ]);
        return;
    }
    
    if ($type === 'A') {
        $filters = ['oquv_reja_id' => $yuklama_id, 'limit' => 1];
        legacy_apply_kafedra_scope($filters);
        $oquv_reja = $db->get_oquv_taqsimotlar($filters);
        $oquv_taqsimotlar = $db->get_taqsimot_by_teacher($yuklama_id, $type);
        if (empty($oquv_taqsimotlar) && $legacy_yuklama_id > 0 && $legacy_yuklama_id !== $yuklama_id) {
            $oquv_taqsimotlar = $db->get_taqsimot_by_teacher($legacy_yuklama_id, $type);
            if (!empty($oquv_taqsimotlar)) {
                $db->query('START TRANSACTION');
                try {
                    foreach ($oquv_taqsimotlar as $legacyRow) {
                        $teacherId = (int)($legacyRow['teacher_id'] ?? 0);
                        $soat = (float)($legacyRow['soat_soni'] ?? 0);
                        if ($teacherId <= 0 || $soat <= 0) {
                            continue;
                        }

                        $exists = $db->get_data_by_table('taqsimotlar', [
                            'oquv_reja_id' => $yuklama_id,
                            'teacher_id' => $teacherId,
                            'type' => $type,
                        ]);
                        if (!$exists) {
                            $db->insert('taqsimotlar', [
                                'oquv_reja_id' => $yuklama_id,
                                'teacher_id' => $teacherId,
                                'soat' => $soat,
                                'type' => $type,
                            ]);
                        }
                    }
                    $db->delete('taqsimotlar', "oquv_reja_id = {$legacy_yuklama_id} AND type = '" . addslashes($type) . "'");
                    $db->query('COMMIT');
                    $oquv_taqsimotlar = $db->get_taqsimot_by_teacher($yuklama_id, $type);
                } catch (Throwable $e) {
                    $db->query('ROLLBACK');
                }
            }
        }
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
                'message' => legacy_is_kafedra_mudiri() ? 'Bu yuklama sizning kafedrangizga tegishli emas.' : 'Ma\'lumot topilmadi'
            ]);
        }
        
    } else if ($type === 'Q') {
        $filters = ['qoshimcha_oquv_reja_id' => $yuklama_id, 'limit' => 1];
        legacy_apply_kafedra_scope($filters);
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
                'message' => legacy_is_kafedra_mudiri() ? 'Bu yuklama sizning kafedrangizga tegishli emas.' : 'Ma\'lumot topilmadi'
            ]);
        }
    } else if ($type === 'M') {
        $filters = ['maxsus_oquv_reja_id' => $yuklama_id, 'limit' => 1];
        legacy_apply_kafedra_scope($filters);
        $oquv_reja = $db->get_maxsus_oquv_taqsimotlar($filters);
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
                'message' => legacy_is_kafedra_mudiri() ? 'Bu yuklama sizning kafedrangizga tegishli emas.' : 'Ma\'lumot topilmadi'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Noto‘g‘ri so‘rov yuborildi.'
        ]);
    }
?>
