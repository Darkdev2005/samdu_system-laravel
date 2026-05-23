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
            'message' => 'NotoвЂgвЂri soвЂrov yuborildi.'
        ]);
        return;
    }
    if ($type === 'A' && $soatTuri === '') {
        echo json_encode([
            'success' => false,
            'message' => "Soat turi aniqlanmadi. Sahifani yangilab qayta urinib ko'ring."
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    function legacy_get_taqsimot_rows_for_modal(Database $db, int $yuklamaId, string $type, string $soatTuri): array
    {
        $rows = $db->get_taqsimot_by_teacher($yuklamaId, $type, $soatTuri);
        if (!empty($rows) || !legacy_is_scoped_taqsimot_soat_turi($soatTuri)) {
            return $rows;
        }

        // Nazorat soatlarida auditoriya taqsimoti ko'rinmasligi shart.
        if (in_array($soatTuri, ['oraliq_nazorat', 'yakuniy_nazorat'], true)) {
            return [];
        }

        // Eski auditoriya taqsimotlarda soat_turi bo'sh bo'lgan holatlar uchun fallback.
        // Muhim: bu yerda scoped yozuvlarni (masalan, oraliq/yakuniy) qayta aralashtirmaslik kerak.
        $legacyRows = $db->get_taqsimot_by_teacher($yuklamaId, $type, '');
        if (empty($legacyRows)) {
            return [];
        }

        $filteredLegacyRows = [];
        foreach ($legacyRows as $legacyRow) {
            $legacySoatTuri = trim((string)($legacyRow['soat_turi'] ?? ''));
            if ($legacySoatTuri !== '') {
                continue;
            }
            $filteredLegacyRows[] = $legacyRow;
        }

        return $filteredLegacyRows;
    }

    function legacy_get_basic_oquv_taqsimot_api_row(Database $db, int $rejaId): array
    {
        if ($rejaId <= 0) {
            return [];
        }

        $sql = "
            SELECT
                r.id,
                f.fan_name AS fan_nomi,
                f.fan_name,
                f.fan_code,
                f.semestr_id,
                f.kafedra_id,
                COALESCE(f.tanlov_fan, 0) AS tanlov_fan,
                0 AS variant_fan_id,
                k.name AS kafedra_nomi,
                y.id AS yonalish_id,
                y.name AS talim_yonalishi,
                y.code AS yonalish_code,
                COALESCE(tsh.name, '') AS oquv_shakli,
                CEIL(s.semestr / 2) AS kurs,
                s.semestr,
                COALESCE(ga.guruh_raqami, '') AS guruh_raqami,
                COALESCE(ga.guruhlar_soni, 0) AS guruhlar_soni,
                COALESCE(ga.guruhlar_soni, 0) AS kattaguruh_soni,
                COALESCE(y.kichikguruh_soni, 0) AS kichikguruh_soni,
                COALESCE(ga.talabalar_soni, 0) AS talabalar_soni,
                COALESCE(y.patok_soni, 0) AS patok_soni
            FROM oquv_rejalar r
            JOIN fanlar f ON f.id = r.fan_id
            JOIN semestrlar s ON s.id = f.semestr_id
            JOIN yonalishlar y ON y.id = s.yonalish_id
            LEFT JOIN kafedralar k ON k.id = f.kafedra_id
            LEFT JOIN talim_shakllar tsh ON tsh.id = y.talim_shakli_id
            LEFT JOIN (
                SELECT
                    yonalish_id,
                    GROUP_CONCAT(guruh_nomer ORDER BY guruh_nomer SEPARATOR ' | ') AS guruh_raqami,
                    COUNT(id) AS guruhlar_soni,
                    SUM(soni) AS talabalar_soni
                FROM guruhlar
                GROUP BY yonalish_id
            ) ga ON ga.yonalish_id = y.id
            WHERE r.id = {$rejaId}
            LIMIT 1
        ";
        $result = $db->query($sql);
        if ($result === false) {
            return [];
        }

        $row = mysqli_fetch_assoc($result);
        return $row ? [$row] : [];
    }

    function legacy_is_variant_taqsimot_api_row(int $yuklamaId, array $row): bool
    {
        if (legacy_taqsimot_virtual_fan_id($yuklamaId) > 0) {
            return true;
        }

        $variantFanId = (int)($row['variant_fan_id'] ?? 0);
        $tanlovFan = (int)($row['tanlov_fan'] ?? 0);
        return $variantFanId > 0 || in_array($tanlovFan, [1, 3], true);
    }

    function legacy_filter_control_taqsimot_conflicts(Database $db, array $rows, int $yuklamaId, int $legacyYuklamaId, string $type, string $soatTuri): array
    {
        if ($type !== 'A' || $soatTuri !== 'yakuniy_nazorat' || empty($rows)) {
            return $rows;
        }

        $teacherIds = [];
        foreach ($rows as $row) {
            $teacherId = (int)($row['teacher_id'] ?? 0);
            if ($teacherId > 0) {
                $teacherIds[$teacherId] = true;
            }
        }
        if (empty($teacherIds)) {
            return $rows;
        }

        $rejaIds = [$yuklamaId];
        if ($legacyYuklamaId > 0 && $legacyYuklamaId !== $yuklamaId) {
            $rejaIds[] = $legacyYuklamaId;
        }
        $rejaIdsSql = implode(',', array_map('intval', array_unique($rejaIds)));
        $teacherIdsSql = implode(',', array_map('intval', array_keys($teacherIds)));
        $lessonSoatSql = "'amalda_maruz','amalda_amaliy','amalda_laboratoriya','amalda_seminar'";

        $sql = "
            SELECT DISTINCT teacher_id
            FROM taqsimotlar
            WHERE type = 'A'
              AND oquv_reja_id IN ({$rejaIdsSql})
              AND teacher_id IN ({$teacherIdsSql})
              AND COALESCE(soat_turi, '') IN ({$lessonSoatSql})
        ";
        $result = $db->query($sql);
        if ($result === false) {
            return $rows;
        }

        $blockedTeachers = [];
        while ($r = mysqli_fetch_assoc($result)) {
            $blockedTeachers[(int)($r['teacher_id'] ?? 0)] = true;
        }
        if (empty($blockedTeachers)) {
            return $rows;
        }

        $filtered = [];
        foreach ($rows as $row) {
            $teacherId = (int)($row['teacher_id'] ?? 0);
            if ($teacherId > 0 && isset($blockedTeachers[$teacherId])) {
                continue;
            }
            $filtered[] = $row;
        }

        return $filtered;
    }

    if ($type === 'A') {
        $virtualFanId = legacy_taqsimot_virtual_fan_id($yuklama_id);
        $filters = $virtualFanId > 0
            ? ['taqsimot_variant_fan_id' => $virtualFanId, 'limit' => 1]
            : ['oquv_reja_id' => $yuklama_id, 'strict_oquv_reja_id' => true, 'limit' => 1];
        legacy_apply_kafedra_scope($filters);
        $oquv_reja = $virtualFanId > 0
            ? $db->get_oquv_taqsimotlar($filters)
            : legacy_get_basic_oquv_taqsimot_api_row($db, $yuklama_id);
        if (empty($oquv_reja)) {
            $oquv_reja = $db->get_oquv_taqsimotlar($filters);
        }
        $data = $oquv_reja[0] ?? [];
        $isVariantTaqsimotRow = legacy_is_variant_taqsimot_api_row($yuklama_id, $data);
        $oquv_taqsimotlar = legacy_get_taqsimot_rows_for_modal($db, $yuklama_id, $type, $soatTuri);
        if (empty($oquv_taqsimotlar) && $legacy_yuklama_id > 0 && $legacy_yuklama_id !== $yuklama_id) {
            $oquv_taqsimotlar = legacy_get_taqsimot_rows_for_modal($db, $legacy_yuklama_id, $type, $soatTuri);
            if (!empty($oquv_taqsimotlar)) {
                $db->query('START TRANSACTION');
                try {
                    $migratedTeacherIds = [];
                    $variantKafedraId = 0;
                    if ($isVariantTaqsimotRow && $virtualFanId > 0) {
                        $variantFan = $db->get_data_by_table('fanlar', ['id' => $virtualFanId]);
                        $variantKafedraId = (int)($variantFan['kafedra_id'] ?? 0);
                    }
                    foreach ($oquv_taqsimotlar as $legacyRow) {
                        $teacherId = (int)($legacyRow['teacher_id'] ?? 0);
                        $soat = (float)($legacyRow['soat_soni'] ?? 0);
                        if ($teacherId <= 0 || $soat <= 0) {
                            continue;
                        }
                        if (!legacy_can_access_teacher($db, $teacherId)) {
                            continue;
                        }
                        if ($isVariantTaqsimotRow) {
                            $teacher = $db->get_data_by_table('oqituvchilar', ['id' => $teacherId]);
                            $teacherKafedraId = (int)($teacher['kafedra_id'] ?? 0);
                            if ($variantKafedraId <= 0 || $teacherKafedraId !== $variantKafedraId) {
                                continue;
                            }
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
                    $oquv_taqsimotlar = legacy_get_taqsimot_rows_for_modal($db, $yuklama_id, $type, $soatTuri);
                } catch (Throwable $e) {
                    $db->query('ROLLBACK');
                }
            }
        }
        $oquv_taqsimotlar = legacy_filter_control_taqsimot_conflicts($db, $oquv_taqsimotlar, $yuklama_id, $legacy_yuklama_id, $type, $soatTuri);
        if (!empty($oquv_reja)) {
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
            'message' => "NotoвЂgвЂri soвЂrov yuborildi."
        ]);
    }
?>

