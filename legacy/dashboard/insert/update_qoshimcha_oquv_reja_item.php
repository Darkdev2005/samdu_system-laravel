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

$ok = $ok && $db->update('qoshimcha_fanlar', [
    'fan_name' => $fanName,
    'fan_soat' => $fanSoat,
    'qoshimcha_dars_id' => $qoshimchaDarsId,
    'semestr_id' => $semestrId,
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
