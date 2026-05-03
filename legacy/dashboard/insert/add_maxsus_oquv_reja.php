<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();

$semestrId = (int)($_POST['semestr_id'] ?? 0);
$yonalishId = (int)($_POST['yonalish_id'] ?? 0);
$guruhId = (int)($_POST['guruh_id'] ?? 0);
$izoh = trim((string)($_POST['izoh'] ?? ''));

$fanCodes = $_POST['fan_code'] ?? [];
$fanNames = $_POST['fan_nomi'] ?? [];
$kafedraIds = $_POST['kafedra_id'] ?? [];
$darsTurlari = $_POST['dars_turi'] ?? [];
$darsSoatlari = $_POST['dars_soati'] ?? [];

if ($semestrId <= 0 || $yonalishId <= 0 || $guruhId <= 0 || !is_array($fanCodes) || count($fanCodes) === 0) {
    echo json_encode(['success' => false, 'message' => "Asosiy ma'lumotlar to'liq emas"]);
    return;
}

$semestrRow = $db->get_data_by_table('semestrlar', ['id' => $semestrId]);
if (!$semestrRow || (int)($semestrRow['yonalish_id'] ?? 0) !== $yonalishId) {
    echo json_encode(['success' => false, 'message' => "Semestr va yo'nalish mos emas"]);
    return;
}

$guruhRow = $db->get_data_by_table('guruhlar', ['id' => $guruhId]);
if (!$guruhRow || (int)($guruhRow['yonalish_id'] ?? 0) !== $yonalishId) {
    echo json_encode(['success' => false, 'message' => "Guruh tanlangan yo'nalishga tegishli emas"]);
    return;
}

if (legacy_is_kafedra_mudiri()) {
    $lockedKafedraId = legacy_user_kafedra_id();
    if ($lockedKafedraId <= 0) {
        echo json_encode(['success' => false, 'message' => "Kafedra aniqlanmadi"]);
        return;
    }
} else {
    $lockedKafedraId = 0;
}

$rows = [];
foreach ($fanCodes as $index => $codeRaw) {
    $code = trim((string)$codeRaw);
    $name = trim((string)($fanNames[$index] ?? ''));
    $kafedraId = $lockedKafedraId > 0 ? $lockedKafedraId : (int)($kafedraIds[$index] ?? 0);

    if ($code === '' && $name === '') {
        continue;
    }
    if ($code === '' || $name === '' || $kafedraId <= 0) {
        echo json_encode(['success' => false, 'message' => "Fan ma'lumotlari to'liq emas (#" . ((int)$index + 1) . ")"]);
        return;
    }

    $turRows = $darsTurlari[$index] ?? [];
    $soatRows = $darsSoatlari[$index] ?? [];
    if (!is_array($turRows)) {
        $turRows = [];
    }
    if (!is_array($soatRows)) {
        $soatRows = [];
    }

    $darsMap = [];
    $total = 0.0;
    foreach ($turRows as $i => $turRaw) {
        $turId = (int)$turRaw;
        $soat = (float)($soatRows[$i] ?? 0);
        if ($turId <= 0 || $soat < 0) {
            continue;
        }
        if (!isset($darsMap[$turId])) {
            $darsMap[$turId] = 0.0;
        }
        $darsMap[$turId] += $soat;
    }

    foreach ($darsMap as $soat) {
        if ($soat > 0) {
            $total += $soat;
        }
    }

    if ($total <= 0) {
        echo json_encode(['success' => false, 'message' => "{$name}: kamida bitta dars soati kiritilishi kerak"]);
        return;
    }
    $mod = fmod((float)$total, 30.0);
    if ($mod < 0) {
        $mod += 30.0;
    }
    if ($mod > 0.0001 && abs($mod - 30.0) > 0.0001) {
        echo json_encode(['success' => false, 'message' => "{$name}: jami soat ({$total}) 30 ga qoldiqsiz bo'linishi shart"]);
        return;
    }

    $rows[] = [
        'code' => $code,
        'name' => $name,
        'kafedra_id' => $kafedraId,
        'dars_map' => $darsMap,
    ];
}

if (count($rows) === 0) {
    echo json_encode(['success' => false, 'message' => "Saqlash uchun fan topilmadi"]);
    return;
}

$db->query("START TRANSACTION");
$ok = true;
$savedCount = 0;

// Izoh: Tanlangan guruh maxsus guruh sifatida ro'yxatga olinadi.
$existingMaxsusGuruh = $db->get_data_by_table('maxsus_guruhlar', [
    'yonalish_id' => $yonalishId,
    'guruh_id' => $guruhId
]);
if ($existingMaxsusGuruh) {
    $ok = $ok && $db->update('maxsus_guruhlar', [
        'is_active' => 1,
        'izoh' => $izoh,
    ], 'id = ' . (int)$existingMaxsusGuruh['id']);
} else {
    $insertMaxsusGuruh = $db->insert('maxsus_guruhlar', [
        'yonalish_id' => $yonalishId,
        'guruh_id' => $guruhId,
        'izoh' => $izoh,
        'is_active' => 1,
    ]);
    $ok = $ok && ((int)$insertMaxsusGuruh > 0);
}

foreach ($rows as $row) {
    if (!$ok) {
        break;
    }

    $existingReja = $db->get_data_by_table('maxsus_oquv_rejalar', [
        'semestr_id' => $semestrId,
        'yonalish_id' => $yonalishId,
        'guruh_id' => $guruhId,
        'fan_code' => $row['code'],
        'fan_name' => $row['name'],
        'kafedra_id' => $row['kafedra_id'],
    ]);

    if ($existingReja) {
        $maxsusRejaId = (int)$existingReja['id'];
        $ok = $ok && $db->update('maxsus_oquv_rejalar', [
            'izoh' => $izoh,
        ], 'id = ' . $maxsusRejaId);
    } else {
        $maxsusRejaId = (int)$db->insert('maxsus_oquv_rejalar', [
            'fan_code' => $row['code'],
            'fan_name' => $row['name'],
            'kafedra_id' => $row['kafedra_id'],
            'semestr_id' => $semestrId,
            'yonalish_id' => $yonalishId,
            'guruh_id' => $guruhId,
            'izoh' => $izoh,
        ]);
        $ok = $ok && ($maxsusRejaId > 0);
    }

    if (!$ok || $maxsusRejaId <= 0) {
        break;
    }

    $processedTurIds = [];
    foreach ($row['dars_map'] as $darsTurId => $darsSoat) {
        $darsTurId = (int)$darsTurId;
        if ($darsTurId <= 0) {
            continue;
        }
        $processedTurIds[] = $darsTurId;
        $existsSoat = $db->get_data_by_table('maxsus_oquv_reja_soatlar', [
            'maxsus_reja_id' => $maxsusRejaId,
            'dars_tur_id' => $darsTurId
        ]);
        if ($existsSoat) {
            $ok = $ok && $db->update('maxsus_oquv_reja_soatlar', [
                'dars_soat' => (float)$darsSoat
            ], 'id = ' . (int)$existsSoat['id']);
        } else {
            $insertSoatId = (int)$db->insert('maxsus_oquv_reja_soatlar', [
                'maxsus_reja_id' => $maxsusRejaId,
                'dars_tur_id' => $darsTurId,
                'dars_soat' => (float)$darsSoat
            ]);
            $ok = $ok && ($insertSoatId > 0);
        }
        if (!$ok) {
            break;
        }
    }

    if ($ok && !empty($processedTurIds)) {
        $db->query("
            DELETE FROM maxsus_oquv_reja_soatlar
            WHERE maxsus_reja_id = {$maxsusRejaId}
              AND dars_tur_id NOT IN (" . implode(',', array_map('intval', array_unique($processedTurIds))) . ")
        ");
    }

    $savedCount++;
}

if ($ok) {
    $pendingExists = $db->get_data_by_table('taqsimot_resync_events', [
        'entity_type' => 'maxsus_guruh',
        'entity_id' => $guruhId,
        'yonalish_id' => $yonalishId,
        'status' => 'pending',
    ]);
    if (!$pendingExists) {
        $db->insert('taqsimot_resync_events', [
            'entity_type' => 'maxsus_guruh',
            'entity_id' => $guruhId,
            'yonalish_id' => $yonalishId,
            'reason' => 'Maxsus guruh uchun o‘quv reja yangilandi',
            'archived_rows' => 0,
            'status' => 'pending',
        ]);
    }
}

if ($ok) {
    $db->query("COMMIT");
    echo json_encode([
        'success' => true,
        'message' => "Saqlandi ({$savedCount} ta fan)"
    ]);
} else {
    $db->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => "Saqlashda xatolik yuz berdi"
    ]);
}
