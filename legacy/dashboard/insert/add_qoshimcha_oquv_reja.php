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
    $yadak_subtypes    = $_POST['yadak_subtype'] ?? [];
    $yadak_teachers    = $_POST['yadak_teacher'] ?? [];
    $yadak_fan_counts  = $_POST['yadak_fan_count'] ?? [];
    $yadak_bmi_talabalar = $_POST['yadak_bmi_talaba'] ?? [];
    $yadak_potok_counts = $_POST['yadak_potok_count'] ?? [];
    $bmi_talaba_sonlari = $_POST['bmi_talaba_soni'] ?? [];
    $bmi_is_tech_flags = $_POST['bmi_is_tech'] ?? [];
    $izoh              = trim($_POST['izoh'] ?? '');
    
    $insertCount = 0;
    $errors = [];
    $yadakBmiByFanKey = [];

    foreach ($fan_nomlar as $fanIndex => $fanNameRawForMap) {
        $qoshimchaDarsIdForMap = (int)($qoshimcha_dars_ids[$fanIndex] ?? 0);
        if ($qoshimchaDarsIdForMap !== 16) {
            continue;
        }

        $subtypeForMap = trim((string)($yadak_subtypes[$fanIndex] ?? ''));
        if (!in_array($subtypeForMap, ['bmi_himoyasi', 'bmi_rahbarligi'], true)) {
            continue;
        }

        $fanKey = trim((string)$fanNameRawForMap);
        if ($fanKey === '') {
            continue;
        }

        $bmiTalabaForMap = max(0, (int)($yadak_bmi_talabalar[$fanIndex] ?? 0));
        $yadakBmiByFanKey[$fanKey] = $bmiTalabaForMap;
    }

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
        $subtypeCode = '';
        $formulaMeta = '';

        if ($fanName === '' || $fanSoat < 0 || $qoshimchaDarsId <= 0) {
            $errors[] = ($fanIndex + 1) . "-fan uchun ma'lumotlar notog'ri";
            continue;
        }

        if ($qoshimchaDarsId === 16) {
            $subtypeCode = trim((string)($yadak_subtypes[$fanIndex] ?? ''));
            $teacherCount = (int)($yadak_teachers[$fanIndex] ?? 0);
            $fanCount = (int)($yadak_fan_counts[$fanIndex] ?? 0);
            $bmiTalabaCount = (int)($yadak_bmi_talabalar[$fanIndex] ?? 0);
            $potokCount = (int)($yadak_potok_counts[$fanIndex] ?? 0);
            $fanKey = trim((string)($fan_nomlar[$fanIndex] ?? ''));

            if ($subtypeCode === 'yozma_ish' && $bmiTalabaCount <= 0 && isset($yadakBmiByFanKey[$fanKey])) {
                $bmiTalabaCount = (int)$yadakBmiByFanKey[$fanKey];
            }

            if (!in_array($subtypeCode, ['konsultatsiya', 'yozma_ish', 'bmi_himoyasi', 'bmi_rahbarligi'], true)) {
                $errors[] = ($fanIndex + 1) . "-fan uchun YADAK turi noto'g'ri";
                continue;
            }

            if (in_array($subtypeCode, ['bmi_himoyasi', 'bmi_rahbarligi'], true) && $teacherCount <= 0) {
                $errors[] = ($fanIndex + 1) . "-fan uchun BMI o'qituvchi soni noto'g'ri";
                continue;
            }

            if (in_array($subtypeCode, ['bmi_himoyasi', 'bmi_rahbarligi', 'yozma_ish'], true) && $bmiTalabaCount < 0) {
                $errors[] = ($fanIndex + 1) . "-fan uchun BMI talaba soni noto'g'ri";
                continue;
            }

            if (in_array($subtypeCode, ['konsultatsiya', 'yozma_ish'], true) && $fanCount <= 0) {
                $errors[] = ($fanIndex + 1) . "-fan uchun YADAK fan soni noto'g'ri";
                continue;
            }

            if ($subtypeCode === 'konsultatsiya' && $potokCount <= 0) {
                $errors[] = ($fanIndex + 1) . "-fan uchun konsultatsiya potok soni noto'g'ri";
                continue;
            }

            $formulaMeta = json_encode([
                'subtype_code' => $subtypeCode,
                'teacher_count' => $teacherCount,
                'fan_count' => $fanCount,
                'bmi_talaba_count' => $bmiTalabaCount,
                'potok_count' => $potokCount,
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($formulaMeta === false) {
                $formulaMeta = '';
            }
        } elseif ($qoshimchaDarsId === 8) {
            $bmiTalabaCount = (int)($bmi_talaba_sonlari[$fanIndex] ?? 0);
            $bmiIsTech = (int)($bmi_is_tech_flags[$fanIndex] ?? 0) === 1 ? 1 : 0;

            if ($bmiTalabaCount < 0) {
                $errors[] = ($fanIndex + 1) . "-fan uchun BMI rahbarligi talaba soni noto'g'ri";
                continue;
            }

            $formulaMeta = json_encode([
                'bmi_talaba_count' => $bmiTalabaCount,
                'bmi_is_tech' => $bmiIsTech,
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            if ($formulaMeta === false) {
                $formulaMeta = '';
            }
        }
        
        if (!isset($kafedra_idlar[$fanIndex], $dars_soatlari[$fanIndex])) {
            $errors[] = ($fanIndex + 1) . "-fan uchun kafedra/dars soatlari massivi mavjud emas";
            continue;
        }
        $qoshimcha_fanid = $db->insert('qoshimcha_fanlar', [
            'semestr_id' => $semestr_id,
            'fan_name'   => $fanName,
            'fan_soat'   => $fanSoat,
            'qoshimcha_dars_id' => $qoshimchaDarsId,
            'subtype_code' => $subtypeCode,
            'formula_meta' => $formulaMeta,
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
            'message' => $errors[0] ?? 'Saqlash uchun yaroqli maʼlumot topilmadi'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Qoʻshimcha oʻquv reja muvaffaqiyatli saqlandi ({$insertCount} ta)",
        'warnings' => $errors
    ]);
?>
