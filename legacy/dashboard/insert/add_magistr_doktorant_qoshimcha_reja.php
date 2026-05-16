<?php
include_once '../config.php';
header('Content-Type: application/json');

$db = new Database();

$personId = (int)($_POST['person_id'] ?? 0);
$qoshimchaDarsId = (int)($_POST['qoshimcha_dars_id'] ?? 0);
$darsSoati = (float)($_POST['dars_soati'] ?? 0);
$izoh = trim((string)($_POST['izoh'] ?? ''));
$bulkMode = trim((string)($_POST['bulk_mode'] ?? ''));
$tripletHours = $_POST['triplet_hours'] ?? [];

$defaultMagistrHours = [
    9 => 50,
    10 => 30,
    11 => 20,
];
$tripletByType = [
    'magistr' => [9, 10, 11],
    'doktorant' => [12, 13, 14],
];

$normalizePersonType = static function (string $value): string {
    $value = strtolower(trim($value));
    if (strpos($value, 'doktor') !== false) {
        return 'doktorant';
    }
    return 'magistr';
};

if ($personId <= 0) {
    echo json_encode(['success' => false, 'message' => "Ma'lumotlarni to'liq kiriting"]);
    return;
}

$person = $db->get_data_by_table('magistr_doktorant_yuklamalar', ['id' => $personId]);
if (!$person) {
    echo json_encode(['success' => false, 'message' => 'Magistr/Doktorant topilmadi']);
    return;
}

$personType = $normalizePersonType((string)($person['turi'] ?? ''));

if ($bulkMode === 'triplet_all' || $bulkMode === 'magistr_all') {
    $tripletIds = $tripletByType[$personType] ?? [];
    if (empty($tripletIds)) {
        echo json_encode([
            'success' => false,
            'message' => "Talaba turi noto'g'ri"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $hoursByType = [];
    foreach ($tripletIds as $darsId) {
        $rawHour = null;
        if (is_array($tripletHours)) {
            $rawHour = $tripletHours[(string)$darsId] ?? ($tripletHours[$darsId] ?? null);
        }
        if ($rawHour === null || $rawHour === '') {
            if ($personType === 'doktorant') {
                continue;
            }
            if ($bulkMode === 'magistr_all' && $personType === 'magistr' && isset($defaultMagistrHours[$darsId])) {
                $hoursByType[$darsId] = (float)$defaultMagistrHours[$darsId];
                continue;
            }
            echo json_encode([
                'success' => false,
                'message' => "Har bir dars turi uchun soat kiriting"
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        if (!is_numeric($rawHour) || (float)$rawHour <= 0) {
            echo json_encode([
                'success' => false,
                'message' => "Soatlar 0 dan katta bo'lishi kerak"
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
        $hoursByType[$darsId] = (float)$rawHour;
    }

    if ($personType === 'doktorant' && empty($hoursByType)) {
        echo json_encode([
            'success' => false,
            'message' => "Kamida bitta dars turi uchun soat kiriting"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $inserted = 0;
    $updated = 0;
    foreach ($hoursByType as $darsId => $hourValue) {
        $qoshimchaDars = $db->get_data_by_table('qoshimcha_dars_turlar', ['id' => $darsId]);
        if (!$qoshimchaDars) {
            echo json_encode([
                'success' => false,
                'message' => "Dars turlaridan biri topilmadi"
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $exists = $db->get_data_by_table('magistr_doktorant_qoshimcha_rejalar', [
            'magistr_doktorant_id' => $personId,
            'qoshimcha_dars_id' => $darsId,
        ]);

        if ($exists) {
            $ok = $db->update('magistr_doktorant_qoshimcha_rejalar', [
                'dars_soati' => $hourValue,
                'izoh' => $izoh,
            ], 'id = ' . (int)$exists['id']);
            if ($ok) {
                $updated++;
            }
            continue;
        }

        $newId = $db->insert('magistr_doktorant_qoshimcha_rejalar', [
            'magistr_doktorant_id' => $personId,
            'qoshimcha_dars_id' => $darsId,
            'dars_soati' => $hourValue,
            'izoh' => $izoh,
        ]);
        if ($newId) {
            $inserted++;
        }
    }

    if ($inserted === 0 && $updated === 0) {
        echo json_encode([
            'success' => false,
            'message' => "Saqlashda xatolik"
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => "{$inserted} ta qo'shildi, {$updated} ta yangilandi",
    ], JSON_UNESCAPED_UNICODE);
    return;
}

if (!in_array($qoshimchaDarsId, [9, 10, 11, 12, 13, 14], true)) {
    echo json_encode(['success' => false, 'message' => "Dars turini tanlang"]);
    return;
}

$darsSoati = $darsSoati > 0 ? $darsSoati : (float)($defaultMagistrHours[$qoshimchaDarsId] ?? 0);
if ($darsSoati <= 0) {
    echo json_encode(['success' => false, 'message' => "Dars soatini kiriting"]);
    return;
}

$allowedIds = ($personType === 'doktorant') ? [12, 13, 14] : [9, 10, 11];
if (!in_array($qoshimchaDarsId, $allowedIds, true)) {
    echo json_encode(['success' => false, 'message' => "Tanlangan dars turi magistr/doktorant turiga mos emas"]);
    return;
}

$qoshimchaDars = $db->get_data_by_table('qoshimcha_dars_turlar', ['id' => $qoshimchaDarsId]);
if (!$qoshimchaDars) {
    echo json_encode(['success' => false, 'message' => "Dars turi topilmadi"]);
    return;
}

$exists = $db->get_data_by_table('magistr_doktorant_qoshimcha_rejalar', [
    'magistr_doktorant_id' => $personId,
    'qoshimcha_dars_id' => $qoshimchaDarsId,
]);
if ($exists) {
    echo json_encode([
        'success' => false,
        'message' => "Bu dars turi ushbu talaba uchun allaqachon kiritilgan"
    ], JSON_UNESCAPED_UNICODE);
    return;
}

$insertId = $db->insert('magistr_doktorant_qoshimcha_rejalar', [
    'magistr_doktorant_id' => $personId,
    'qoshimcha_dars_id' => $qoshimchaDarsId,
    'dars_soati' => $darsSoati,
    'izoh' => $izoh,
]);

echo json_encode([
    'success' => (bool)$insertId,
    'message' => $insertId ? 'Magistr/Doktorant qo\'shimcha rejasi saqlandi' : 'Saqlashda xatolik'
], JSON_UNESCAPED_UNICODE);
