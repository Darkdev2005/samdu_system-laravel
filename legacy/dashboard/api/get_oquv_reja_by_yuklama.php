<?php
    include_once __DIR__ . '/../config.php';
    $db = new Database();
    $yuklama_id = isset($_POST['yuklama_id']) ? (int)$_POST['yuklama_id'] : 0;
    $legacy_yuklama_id = isset($_POST['legacy_yuklama_id']) ? (int)$_POST['legacy_yuklama_id'] : 0;
    $type = isset($_POST['type']) ? trim($_POST['type']) : '';
    $soatTuri = isset($_POST['soat_turi']) ? trim((string)$_POST['soat_turi']) : '';
    
    if ($yuklama_id == 0 || empty($type)) {
        echo json_encode([
            'success' => false,
            'message' => 'Noto‘g‘ri so‘rov yuborildi.'
        ]);
        return;
    }
    
    if ($type === 'A') {
        $virtualFanId = legacy_taqsimot_virtual_fan_id($yuklama_id);
        $filters = $virtualFanId > 0
            ? ['taqsimot_variant_fan_id' => $virtualFanId, 'limit' => 1]
            : ['oquv_reja_id' => $yuklama_id, 'strict_oquv_reja_id' => true, 'limit' => 1];
        legacy_apply_kafedra_scope($filters);
        $oquv_reja = $db->get_oquv_taqsimotlar($filters);
        $oquv_taqsimotlar = $db->get_taqsimot_by_teacher($yuklama_id, $type, $soatTuri);
        if (empty($oquv_taqsimotlar) && $legacy_yuklama_id > 0 && $legacy_yuklama_id !== $yuklama_id) {
            $oquv_taqsimotlar = $db->get_taqsimot_by_teacher($legacy_yuklama_id, $type, $soatTuri);
            if (!empty($oquv_taqsimotlar)) {
                $db->query('START TRANSACTION');
                try {
                    $migratedTeacherIds = [];
                    foreach ($oquv_taqsimotlar as $legacyRow) {
                        $teacherId = (int)($legacyRow['teacher_id'] ?? 0);
                        $soat = (float)($legacyRow['soat_soni'] ?? 0);
                        if ($teacherId <= 0 || $soat <= 0) {
                            continue;
                        }
                        if (!legacy_can_access_teacher($db, $teacherId)) {
                            continue;
                        }

                        $exists = $db->get_data_by_table('taqsimotlar', [
                            'oquv_reja_id' => $yuklama_id,
                            'teacher_id' => $teacherId,
                            'type' => $type,
                        ]);
                        if (!$exists) {
                            $insertPayload = [
                                'oquv_reja_id' => $yuklama_id,
                                'teacher_id' => $teacherId,
                                'soat' => $soat,
                                'type' => $type,
                            ];
                            if (legacy_is_scoped_taqsimot_soat_turi($soatTuri)) {
                                $insertPayload['soat_turi'] = $soatTuri;
                            }
                            if (!empty($legacyRow['guruhlar_json'])) {
                                $insertPayload['guruhlar_json'] = (string)$legacyRow['guruhlar_json'];
                            }
                            $db->insert('taqsimotlar', $insertPayload);
                        }
                        $migratedTeacherIds[$teacherId] = true;
                    }
                    if (!empty($migratedTeacherIds)) {
                        $teacherIdsSql = implode(',', array_map('intval', array_keys($migratedTeacherIds)));
                        $deleteCondition = "oquv_reja_id = {$legacy_yuklama_id} AND type = '" . addslashes($type) . "' AND teacher_id IN ({$teacherIdsSql})";
                        if (legacy_is_scoped_taqsimot_soat_turi($soatTuri)) {
                            $deleteCondition .= " AND COALESCE(soat_turi, '') = '" . addslashes($soatTuri) . "'";
                        }
                        $db->delete('taqsimotlar', $deleteCondition);
                    }
                    $db->query('COMMIT');
                    $oquv_taqsimotlar = $db->get_taqsimot_by_teacher($yuklama_id, $type, $soatTuri);
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
        $oquv_taqsimotlar = $db->get_taqsimot_by_teacher($yuklama_id, $type, $soatTuri);
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
        $oquv_taqsimotlar = $db->get_taqsimot_by_teacher($yuklama_id, $type, $soatTuri);
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
    } else if ($type === 'D') {
        $filters = ['qoshimcha_oquv_reja_id' => $yuklama_id, 'limit' => 1];
        legacy_apply_kafedra_scope($filters);
        $oquv_reja = $db->get_magistr_doktorant_taqsimotlar($filters);
        $oquv_taqsimotlar = $db->get_taqsimot_by_teacher($yuklama_id, $type, $soatTuri);
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
