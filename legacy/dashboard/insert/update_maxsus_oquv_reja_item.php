<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();

$maxsusRejaId = (int)($_POST['maxsus_reja_id'] ?? 0);
$fanCode = trim((string)($_POST['fan_code'] ?? ''));
$fanName = trim((string)($_POST['fan_name'] ?? ''));
$kafedraId = (int)($_POST['kafedra_id'] ?? 0);
$izoh = trim((string)($_POST['izoh'] ?? ''));
$darsJson = (string)($_POST['dars_json'] ?? '{}');
$darsMap = json_decode($darsJson, true);

if ($maxsusRejaId <= 0 || $fanCode === '' || $fanName === '' || !is_array($darsMap)) {
    echo json_encode(['success' => false, 'message' => "Ma'lumotlar to'liq emas"]);
    return;
}

$reja = $db->get_data_by_table('maxsus_oquv_rejalar', ['id' => $maxsusRejaId]);
if (!$reja) {
    echo json_encode(['success' => false, 'message' => "Maxsus reja topilmadi"]);
    return;
}

$targetKafedraId = $kafedraId;
if (legacy_is_kafedra_mudiri()) {
    $lockedKafedraId = legacy_user_kafedra_id();
    if ($lockedKafedraId <= 0) {
        echo json_encode(['success' => false, 'message' => "Kafedra aniqlanmadi"]);
        return;
    }

    if ((int)($reja['kafedra_id'] ?? 0) !== $lockedKafedraId) {
        echo json_encode(['success' => false, 'message' => "Bu yozuv sizning kafedrangizga tegishli emas"]);
        return;
    }
    $targetKafedraId = $lockedKafedraId;
}

if ($targetKafedraId <= 0) {
    echo json_encode(['success' => false, 'message' => "Kafedra tanlanmagan"]);
    return;
}

$normalizedDars = [];
foreach ($darsMap as $darsTurIdRaw => $soatRaw) {
    $darsTurId = (int)$darsTurIdRaw;
    $soat = (float)$soatRaw;
    if ($darsTurId <= 0 || $soat < 0) {
        continue;
    }
    if (!isset($normalizedDars[$darsTurId])) {
        $normalizedDars[$darsTurId] = 0.0;
    }
    $normalizedDars[$darsTurId] += $soat;
}

$hasPositive = false;
foreach ($normalizedDars as $soat) {
    if ($soat > 0) {
        $hasPositive = true;
        break;
    }
}

if (count($normalizedDars) === 0 || !$hasPositive) {
    echo json_encode(['success' => false, 'message' => "Kamida bitta dars soati 0 dan katta bo'lishi kerak"]);
    return;
}

$db->query("START TRANSACTION");
$ok = true;

$ok = $ok && $db->update('maxsus_oquv_rejalar', [
    'fan_code' => $fanCode,
    'fan_name' => $fanName,
    'kafedra_id' => $targetKafedraId,
    'izoh' => $izoh,
], 'id = ' . $maxsusRejaId);

$existingMap = [];
$existingRes = $db->query("
    SELECT id, dars_tur_id
    FROM maxsus_oquv_reja_soatlar
    WHERE maxsus_reja_id = {$maxsusRejaId}
");
if ($existingRes) {
    while ($row = mysqli_fetch_assoc($existingRes)) {
        $turId = (int)($row['dars_tur_id'] ?? 0);
        $id = (int)($row['id'] ?? 0);
        if ($turId > 0 && $id > 0 && !isset($existingMap[$turId])) {
            $existingMap[$turId] = $id;
        }
    }
}

$processedTurIds = [];
foreach ($normalizedDars as $darsTurId => $soat) {
    $processedTurIds[] = (int)$darsTurId;

    if (isset($existingMap[$darsTurId])) {
        $ok = $ok && $db->update('maxsus_oquv_reja_soatlar', [
            'dars_soat' => (float)$soat
        ], 'id = ' . (int)$existingMap[$darsTurId]);
    } else {
        $insertId = (int)$db->insert('maxsus_oquv_reja_soatlar', [
            'maxsus_reja_id' => $maxsusRejaId,
            'dars_tur_id' => (int)$darsTurId,
            'dars_soat' => (float)$soat,
        ]);
        $ok = $ok && ($insertId > 0);
    }

    if (!$ok) {
        break;
    }
}

if ($ok && !empty($processedTurIds)) {
    $processedSql = implode(',', array_map('intval', array_unique($processedTurIds)));
    $ok = $ok && $db->query("
        DELETE FROM maxsus_oquv_reja_soatlar
        WHERE maxsus_reja_id = {$maxsusRejaId}
          AND dars_tur_id NOT IN ({$processedSql})
    ");
}

if ($ok) {
    $pendingExists = $db->get_data_by_table('taqsimot_resync_events', [
        'entity_type' => 'maxsus_guruh',
        'entity_id' => (int)($reja['guruh_id'] ?? 0),
        'yonalish_id' => (int)($reja['yonalish_id'] ?? 0),
        'status' => 'pending',
    ]);
    if (!$pendingExists) {
        $db->insert('taqsimot_resync_events', [
            'entity_type' => 'maxsus_guruh',
            'entity_id' => (int)($reja['guruh_id'] ?? 0),
            'yonalish_id' => (int)($reja['yonalish_id'] ?? 0),
            'reason' => "Maxsus guruh fani tahrirlandi",
            'archived_rows' => 0,
            'status' => 'pending',
        ]);
    }
}

if ($ok) {
    $db->query("COMMIT");
    echo json_encode(['success' => true, 'message' => "Maxsus reja yangilandi"]);
} else {
    $db->query("ROLLBACK");
    echo json_encode(['success' => false, 'message' => "Yangilashda xatolik yuz berdi"]);
}

