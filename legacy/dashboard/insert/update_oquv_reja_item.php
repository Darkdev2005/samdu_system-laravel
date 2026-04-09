<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();

$fanId = (int)($_POST['fan_id'] ?? 0);
$fanCode = trim((string)($_POST['fan_code'] ?? ''));
$fanName = trim((string)($_POST['fan_name'] ?? ''));
$kafedraId = (int)($_POST['kafedra_id'] ?? 0);
$semestrIdRaw = (int)($_POST['semestr_id'] ?? 0);
$izoh = trim((string)($_POST['izoh'] ?? ''));
$darsJson = (string)($_POST['dars_json'] ?? '{}');
$darsMap = json_decode($darsJson, true);

if ($fanId <= 0 || $fanCode === '' || $fanName === '' || !is_array($darsMap)) {
    echo json_encode(['success' => false, 'message' => "Ma'lumotlar to'liq emas"]);
    return;
}

$fan = $db->get_data_by_table('fanlar', ['id' => $fanId]);
if (!$fan) {
    echo json_encode(['success' => false, 'message' => "Fan topilmadi"]);
    return;
}

$tanlovFan = (int)($fan['tanlov_fan'] ?? 0);
$currentKafedraId = (int)($fan['kafedra_id'] ?? 0);
$currentSemestrId = (int)($fan['semestr_id'] ?? 0);
$targetSemestrId = $semestrIdRaw > 0 ? $semestrIdRaw : $currentSemestrId;

$lockKafedra = ($currentKafedraId === 0 && ($tanlovFan === 1 || $tanlovFan === 3));
$targetKafedraId = $lockKafedra ? 0 : $kafedraId;
if (!$lockKafedra && $targetKafedraId <= 0) {
    echo json_encode(['success' => false, 'message' => "Kafedra tanlanmagan"]);
    return;
}

if ($targetSemestrId <= 0) {
    echo json_encode(['success' => false, 'message' => "Semestr tanlanmagan"]);
    return;
}

$semestr = $db->get_data_by_table('semestrlar', ['id' => $targetSemestrId]);
if (!$semestr) {
    echo json_encode(['success' => false, 'message' => "Semestr topilmadi"]);
    return;
}

$normalizedDars = [];
foreach ($darsMap as $darsTurIdRaw => $soatRaw) {
    $darsTurId = (int)$darsTurIdRaw;
    $soat = (int)$soatRaw;
    if ($darsTurId <= 0 || $soat < 0) {
        continue;
    }
    $normalizedDars[$darsTurId] = $soat;
}

if (count($normalizedDars) === 0) {
    echo json_encode(['success' => false, 'message' => "Kamida bitta dars soati kiritilishi kerak"]);
    return;
}

$totalHours = 0;
foreach ($normalizedDars as $value) {
    if ((int)$value > 0) {
        $totalHours += (int)$value;
    }
}
if ($totalHours % 30 !== 0) {
    echo json_encode(['success' => false, 'message' => "Jami soat ({$totalHours}) 30 ga qoldiqsiz bo'linishi shart"]);
    return;
}

$db->query("START TRANSACTION");
$ok = true;

$ok = $ok && $db->update('fanlar', [
    'fan_code' => $fanCode,
    'fan_name' => $fanName,
    'kafedra_id' => $targetKafedraId,
    'semestr_id' => $targetSemestrId,
], 'id = ' . $fanId);

$existingMap = [];
$existingRes = $db->query("
    SELECT id, dars_tur_id
    FROM oquv_rejalar
    WHERE fan_id = $fanId
");
if ($existingRes) {
    while ($row = mysqli_fetch_assoc($existingRes)) {
        $darsTurId = (int)($row['dars_tur_id'] ?? 0);
        $id = (int)($row['id'] ?? 0);
        if ($darsTurId > 0 && $id > 0 && !isset($existingMap[$darsTurId])) {
            $existingMap[$darsTurId] = $id;
        }
    }
}

$processedTurIds = [];
foreach ($normalizedDars as $darsTurId => $soat) {
    $processedTurIds[] = (int)$darsTurId;
    if (isset($existingMap[$darsTurId])) {
        $ok = $ok && $db->update('oquv_rejalar', [
            'dars_soat' => (int)$soat,
            'izoh' => $izoh,
        ], 'id = ' . (int)$existingMap[$darsTurId]);
    } else {
        $insertId = $db->insert('oquv_rejalar', [
            'fan_id' => $fanId,
            'dars_tur_id' => (int)$darsTurId,
            'dars_soat' => (int)$soat,
            'izoh' => $izoh,
        ]);
        $ok = $ok && ((int)$insertId > 0);
    }

    if (!$ok) {
        break;
    }
}

if ($ok) {
    $processedSql = implode(',', array_map('intval', array_unique($processedTurIds)));
    if ($processedSql !== '') {
        $ok = $ok && $db->query("
            DELETE FROM oquv_rejalar
            WHERE fan_id = $fanId
              AND dars_tur_id NOT IN ($processedSql)
        ");
    }
}

if ($ok) {
    $db->query("COMMIT");
    echo json_encode(['success' => true, 'message' => "O'quv reja yangilandi"]);
} else {
    $db->query("ROLLBACK");
    echo json_encode(['success' => false, 'message' => "Yangilashda xatolik yuz berdi"]);
}
