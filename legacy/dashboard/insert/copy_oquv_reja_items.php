<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();

$targetSemestrId = (int)($_POST['target_semestr_id'] ?? 0);
$sourceSemestrId = (int)($_POST['source_semestr_id'] ?? 0);
$scopeMode = trim((string)($_POST['scope_mode'] ?? 'required_merged'));

if ($targetSemestrId <= 0 || $sourceSemestrId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => "Manba va maqsad semestrni tanlang"
    ]);
    return;
}

if ($targetSemestrId === $sourceSemestrId) {
    echo json_encode([
        'success' => false,
        'message' => "Manba va maqsad semestr bir xil bo'lmasligi kerak"
    ]);
    return;
}

$scopeMap = [
    'required_merged' => [0, 2],
    'all' => [0, 1, 2, 3],
];
$allowedTanlovTypes = $scopeMap[$scopeMode] ?? null;
if ($allowedTanlovTypes === null) {
    echo json_encode([
        'success' => false,
        'message' => "Nusxa olish rejimi noto'g'ri"
    ]);
    return;
}

$sourceSemestr = $db->get_data_by_table('semestrlar', ['id' => $sourceSemestrId]);
$targetSemestr = $db->get_data_by_table('semestrlar', ['id' => $targetSemestrId]);
if (!$sourceSemestr || !$targetSemestr) {
    echo json_encode([
        'success' => false,
        'message' => "Semestr topilmadi"
    ]);
    return;
}

$typesSql = implode(',', array_map('intval', $allowedTanlovTypes));
$sourceFansResult = $db->query("
    SELECT
        f.id,
        f.fan_code,
        f.fan_name,
        IFNULL(f.kafedra_id, 0) AS kafedra_id,
        IFNULL(f.tanlov_fan, 0) AS tanlov_fan
    FROM fanlar f
    WHERE f.semestr_id = {$sourceSemestrId}
      AND IFNULL(f.tanlov_fan, 0) IN ({$typesSql})
    ORDER BY f.id ASC
");

if (!$sourceFansResult) {
    echo json_encode([
        'success' => false,
        'message' => "Manba fanlar ro'yxatini olishda xatolik"
    ]);
    return;
}

$sourceFans = [];
$sourceFanIds = [];
while ($row = mysqli_fetch_assoc($sourceFansResult)) {
    $fanId = (int)($row['id'] ?? 0);
    if ($fanId <= 0) {
        continue;
    }
    $sourceFans[] = [
        'id' => $fanId,
        'fan_code' => trim((string)($row['fan_code'] ?? '')),
        'fan_name' => trim((string)($row['fan_name'] ?? '')),
        'kafedra_id' => (int)($row['kafedra_id'] ?? 0),
        'tanlov_fan' => (int)($row['tanlov_fan'] ?? 0),
    ];
    $sourceFanIds[] = $fanId;
}

if (empty($sourceFans)) {
    echo json_encode([
        'success' => false,
        'message' => "Manba semestrda nusxalashga mos fan topilmadi"
    ]);
    return;
}

$darsByFan = [];
$fanIdsSql = implode(',', array_map('intval', array_unique($sourceFanIds)));
$sourceDarsResult = $db->query("
    SELECT fan_id, dars_tur_id, dars_soat, izoh
    FROM oquv_rejalar
    WHERE fan_id IN ({$fanIdsSql})
");
if ($sourceDarsResult) {
    while ($row = mysqli_fetch_assoc($sourceDarsResult)) {
        $fanId = (int)($row['fan_id'] ?? 0);
        $darsTurId = (int)($row['dars_tur_id'] ?? 0);
        $darsSoat = (int)($row['dars_soat'] ?? 0);
        if ($fanId <= 0 || $darsTurId <= 0 || $darsSoat <= 0) {
            continue;
        }

        if (!isset($darsByFan[$fanId])) {
            $darsByFan[$fanId] = [];
        }
        $darsByFan[$fanId][] = [
            'dars_tur_id' => $darsTurId,
            'dars_soat' => $darsSoat,
            'izoh' => (string)($row['izoh'] ?? ''),
        ];
    }
}

$createdFans = 0;
$reusedFans = 0;
$skippedFans = 0;
$createdDarsRows = 0;
$updatedDarsRows = 0;

$db->query('START TRANSACTION');
$ok = true;

foreach ($sourceFans as $sourceFan) {
    $fanCode = $sourceFan['fan_code'];
    $fanName = $sourceFan['fan_name'];
    $kafedraId = (int)$sourceFan['kafedra_id'];
    $tanlovFan = (int)$sourceFan['tanlov_fan'];

    if ($fanCode === '' || $fanName === '') {
        $skippedFans++;
        continue;
    }

    $targetFan = $db->get_data_by_table('fanlar', [
        'fan_code' => $fanCode,
        'fan_name' => $fanName,
        'kafedra_id' => $kafedraId,
        'semestr_id' => $targetSemestrId,
        'tanlov_fan' => $tanlovFan
    ]);

    $targetFanId = 0;
    if ($targetFan) {
        $targetFanId = (int)($targetFan['id'] ?? 0);
        $reusedFans++;
    } else {
        $targetFanId = (int)$db->insert('fanlar', [
            'fan_code' => $fanCode,
            'fan_name' => $fanName,
            'kafedra_id' => $kafedraId,
            'semestr_id' => $targetSemestrId,
            'tanlov_fan' => $tanlovFan,
        ]);

        if ($targetFanId <= 0) {
            $ok = false;
            break;
        }
        $createdFans++;
    }

    $darsRows = $darsByFan[(int)$sourceFan['id']] ?? [];
    foreach ($darsRows as $darsRow) {
        $darsTurId = (int)($darsRow['dars_tur_id'] ?? 0);
        $darsSoat = (int)($darsRow['dars_soat'] ?? 0);
        $izoh = (string)($darsRow['izoh'] ?? '');

        if ($darsTurId <= 0 || $darsSoat <= 0) {
            continue;
        }

        $existing = $db->get_data_by_table('oquv_rejalar', [
            'fan_id' => $targetFanId,
            'dars_tur_id' => $darsTurId
        ]);

        if ($existing) {
            $existingId = (int)($existing['id'] ?? 0);
            $ok = $ok && $db->update('oquv_rejalar', [
                'dars_soat' => $darsSoat,
                'izoh' => $izoh
            ], 'id = ' . $existingId);

            if ($ok) {
                $db->query("DELETE FROM oquv_rejalar WHERE fan_id = {$targetFanId} AND dars_tur_id = {$darsTurId} AND id <> {$existingId}");
                $updatedDarsRows++;
            }
        } else {
            $insertRejaId = (int)$db->insert('oquv_rejalar', [
                'fan_id' => $targetFanId,
                'dars_tur_id' => $darsTurId,
                'dars_soat' => $darsSoat,
                'izoh' => $izoh
            ]);

            if ($insertRejaId <= 0) {
                $ok = false;
            } else {
                $db->query("DELETE FROM oquv_rejalar WHERE fan_id = {$targetFanId} AND dars_tur_id = {$darsTurId} AND id <> {$insertRejaId}");
                $createdDarsRows++;
            }
        }

        if (!$ok) {
            break;
        }
    }

    if (!$ok) {
        break;
    }
}

if ($ok) {
    $db->query('COMMIT');
    $parts = [];
    $parts[] = "{$createdFans} ta yangi fan";
    $parts[] = "{$reusedFans} ta mavjud fan yangilandi";
    $parts[] = "{$createdDarsRows} ta yangi dars satri";
    $parts[] = "{$updatedDarsRows} ta dars satri yangilandi";
    if ($skippedFans > 0) {
        $parts[] = "{$skippedFans} ta fan o'tkazib yuborildi";
    }

    echo json_encode([
        'success' => true,
        'message' => "Nusxa olish yakunlandi: " . implode(', ', $parts),
        'created_fans' => $createdFans,
        'reused_fans' => $reusedFans,
        'created_dars_rows' => $createdDarsRows,
        'updated_dars_rows' => $updatedDarsRows,
        'skipped_fans' => $skippedFans,
    ]);
    return;
}

$db->query('ROLLBACK');
echo json_encode([
    'success' => false,
    'message' => "Nusxalash jarayonida xatolik yuz berdi"
]);

