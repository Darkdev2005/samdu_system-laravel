<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();

$qoshimchaFanId = (int)($_POST['qoshimcha_fanid'] ?? 0);
$fanName = trim((string)($_POST['fan_name'] ?? ''));
$fanSoat = (float)($_POST['fan_soat'] ?? 0);
$qoshimchaDarsId = (int)($_POST['qoshimcha_dars_id'] ?? 0);
$semestrId = (int)($_POST['semestr_id'] ?? 0);
$izoh = trim((string)($_POST['izoh'] ?? ''));
$yadakSubtype = trim((string)($_POST['yadak_subtype'] ?? ''));
$yadakTeacher = (int)($_POST['yadak_teacher'] ?? 0);
$yadakFanCount = (int)($_POST['yadak_fan_count'] ?? 0);
$yadakBmiTalabaRaw = trim((string)($_POST['yadak_bmi_talaba'] ?? ''));
$yadakPotokCountRaw = trim((string)($_POST['yadak_potok_count'] ?? ''));
$allocationsJson = (string)($_POST['allocations_json'] ?? '[]');
$allocations = json_decode($allocationsJson, true);

if ($qoshimchaFanId <= 0 || $fanName === '' || $fanSoat < 0 || $qoshimchaDarsId <= 0 || $semestrId <= 0 || !is_array($allocations)) {
    echo json_encode(['success' => false, 'message' => "Ma'lumotlar to'liq emas"]);
    return;
}

$existingFan = $db->get_data_by_table('qoshimcha_fanlar', ['id' => $qoshimchaFanId]);
if (!$existingFan) {
    echo json_encode(['success' => false, 'message' => "Fan topilmadi"]);
    return;
}

$existingSemestr = $db->get_data_by_table('semestrlar', ['id' => $semestrId]);
if (!$existingSemestr) {
    echo json_encode(['success' => false, 'message' => "Semestr topilmadi"]);
    return;
}

$normalizedAllocations = [];
$sumSoat = 0.0;
$hasPositive = false;

foreach ($allocations as $allocation) {
    if (!is_array($allocation)) {
        continue;
    }

    $kafedraId = (int)($allocation['kafedra_id'] ?? 0);
    $darsSoati = (float)($allocation['dars_soati'] ?? 0);

    if ($kafedraId <= 0 || $darsSoati < 0) {
        continue;
    }

    if (!isset($normalizedAllocations[$kafedraId])) {
        $normalizedAllocations[$kafedraId] = 0.0;
    }
    $normalizedAllocations[$kafedraId] += $darsSoati;
}

foreach ($normalizedAllocations as $kafedraId => $darsSoati) {
    $sumSoat += $darsSoati;
    if ($darsSoati > 0) {
        $hasPositive = true;
    }
}

if (count($normalizedAllocations) === 0 || !$hasPositive) {
    echo json_encode(['success' => false, 'message' => "Kamida bitta kafedra soati 0 dan katta bo'lishi kerak"]);
    return;
}

if (abs($sumSoat - $fanSoat) > 0.0001) {
    echo json_encode(['success' => false, 'message' => "Hisoblangan fan soati va kafedralar yig'indisi teng bo'lishi kerak"]);
    return;
}

$db->query("START TRANSACTION");
$ok = true;

$subtypeCode = trim((string)($existingFan['subtype_code'] ?? ''));
$formulaMeta = (string)($existingFan['formula_meta'] ?? '');

if ($qoshimchaDarsId === 16) {
    $effectiveSubtype = $yadakSubtype !== '' ? $yadakSubtype : $subtypeCode;

    if ($effectiveSubtype === '') {
        $effectiveSubtype = '';
    }

    if ($effectiveSubtype !== '' && !in_array($effectiveSubtype, ['konsultatsiya', 'yozma_ish', 'bmi_himoyasi', 'bmi_rahbarligi'], true)) {
        echo json_encode(['success' => false, 'message' => "YADAK turi noto'g'ri"]);
        return;
    }

    $meta = legacy_decode_formula_meta($formulaMeta);
    $effectiveTeacherCount = $yadakTeacher > 0 ? $yadakTeacher : (int)($meta['teacher_count'] ?? 0);
    $effectiveFanCount = $yadakFanCount > 0 ? $yadakFanCount : (int)($meta['fan_count'] ?? 0);
    $effectiveBmiTalabaCount = $yadakBmiTalabaRaw !== '' ? (int)$yadakBmiTalabaRaw : (int)($meta['bmi_talaba_count'] ?? 0);
    $effectivePotokCount = $yadakPotokCountRaw !== '' ? (int)$yadakPotokCountRaw : (int)($meta['potok_count'] ?? 0);

    if ($effectiveSubtype !== '') {
        if (in_array($effectiveSubtype, ['bmi_himoyasi', 'bmi_rahbarligi'], true) && $effectiveTeacherCount <= 0) {
            echo json_encode(['success' => false, 'message' => "BMI uchun o'qituvchi soni noto'g'ri"]);
            return;
        }

        if (in_array($effectiveSubtype, ['bmi_himoyasi', 'bmi_rahbarligi', 'yozma_ish'], true) && $effectiveBmiTalabaCount < 0) {
            echo json_encode(['success' => false, 'message' => "BMI talaba soni noto'g'ri"]);
            return;
        }

        if (in_array($effectiveSubtype, ['konsultatsiya', 'yozma_ish'], true) && $effectiveFanCount <= 0) {
            echo json_encode(['success' => false, 'message' => "YADAK fan soni noto'g'ri"]);
            return;
        }

        if ($effectiveSubtype === 'konsultatsiya' && $effectivePotokCount <= 0) {
            echo json_encode(['success' => false, 'message' => "Konsultatsiya potok soni noto'g'ri"]);
            return;
        }

        $subtypeCode = $effectiveSubtype;
        $formulaMeta = json_encode([
            'subtype_code' => $subtypeCode,
            'teacher_count' => $effectiveTeacherCount,
            'fan_count' => $effectiveFanCount,
            'bmi_talaba_count' => $effectiveBmiTalabaCount,
            'potok_count' => $effectivePotokCount,
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($formulaMeta === false) {
            $formulaMeta = '';
        }
    }
} elseif ($qoshimchaDarsId === 8) {
    $subtypeCode = '';
    if (trim((string)$formulaMeta) === '') {
        $formulaMeta = json_encode([
            'bmi_talaba_count' => 0,
            'bmi_is_tech' => 0,
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
    }
} else {
    $subtypeCode = '';
    $formulaMeta = '';
}

$ok = $ok && $db->update('qoshimcha_fanlar', [
    'fan_name' => $fanName,
    'fan_soat' => $fanSoat,
    'qoshimcha_dars_id' => $qoshimchaDarsId,
    'semestr_id' => $semestrId,
    'subtype_code' => $subtypeCode,
    'formula_meta' => $formulaMeta,
], 'id = ' . $qoshimchaFanId);

if ($ok) {
    $ok = $ok && $db->query("DELETE FROM qoshimcha_oquv_rejalar WHERE qoshimcha_fanid = $qoshimchaFanId");
}

if ($ok) {
    foreach ($normalizedAllocations as $kafedraId => $darsSoati) {
        $insertId = $db->insert('qoshimcha_oquv_rejalar', [
            'qoshimcha_fanid' => $qoshimchaFanId,
            'kafedra_id' => (int)$kafedraId,
            'dars_soati' => (int)round($darsSoati),
            'izoh' => $izoh,
        ]);

        if ((int)$insertId <= 0) {
            $ok = false;
            break;
        }
    }
}

if ($ok) {
    $db->query("COMMIT");
    echo json_encode(['success' => true, 'message' => "Qo'shimcha fan yangilandi"]);
} else {
    $db->query("ROLLBACK");
    echo json_encode(['success' => false, 'message' => "Yangilashda xatolik yuz berdi"]);
}
