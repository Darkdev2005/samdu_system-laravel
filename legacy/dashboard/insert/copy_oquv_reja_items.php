<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();
$allowedTanlovTypes = [0, 1, 2, 3];

function parseSemestrIds($raw): array
{
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $str = trim((string)$raw);
        if ($str === '') {
            return [];
        }

        $decoded = null;
        if ($str !== '' && ($str[0] === '[' || $str[0] === '{')) {
            $decoded = json_decode($str, true);
        }
        if (is_array($decoded)) {
            $items = $decoded;
        } else {
            $items = preg_split('/\s*,\s*/', $str) ?: [];
        }
    }

    $ids = [];
    foreach ($items as $item) {
        $id = (int)$item;
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return array_values(array_unique($ids));
}

function parsePairMap($raw): array
{
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $str = trim((string)$raw);
        if ($str === '') {
            return [];
        }
        $decoded = json_decode($str, true);
        if (!is_array($decoded)) {
            return [];
        }
        $items = $decoded;
    }

    $pairs = [];
    $seen = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $targetId = (int)($item['target_semestr_id'] ?? 0);
        $sourceId = (int)($item['source_semestr_id'] ?? 0);
        if ($targetId <= 0 || $sourceId <= 0) {
            continue;
        }

        $key = $targetId . ':' . $sourceId;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $pairs[] = [
            'target_semestr_id' => $targetId,
            'source_semestr_id' => $sourceId,
        ];
    }
    return $pairs;
}

function copySemestrPair(Database $db, int $sourceSemestrId, int $targetSemestrId, array $allowedTanlovTypes): array
{
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
        return [
            'ok' => false,
            'message' => "Manba semestr fanlarini olishda xatolik (ID: {$sourceSemestrId})"
        ];
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
        return [
            'ok' => true,
            'empty' => true,
            'message' => "Manba semestrda mos fan topilmadi (ID: {$sourceSemestrId})",
            'created_fans' => 0,
            'reused_fans' => 0,
            'created_dars_rows' => 0,
            'updated_dars_rows' => 0,
            'skipped_fans' => 0,
        ];
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
                return [
                    'ok' => false,
                    'message' => "Yangi fan yaratishda xatolik (maqsad semestr ID: {$targetSemestrId})"
                ];
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
                $ok = $db->update('oquv_rejalar', [
                    'dars_soat' => $darsSoat,
                    'izoh' => $izoh
                ], 'id = ' . $existingId);

                if (!$ok) {
                    return [
                        'ok' => false,
                        'message' => "Dars satrini yangilashda xatolik (fan ID: {$targetFanId})"
                    ];
                }

                $db->query("DELETE FROM oquv_rejalar WHERE fan_id = {$targetFanId} AND dars_tur_id = {$darsTurId} AND id <> {$existingId}");
                $updatedDarsRows++;
            } else {
                $insertRejaId = (int)$db->insert('oquv_rejalar', [
                    'fan_id' => $targetFanId,
                    'dars_tur_id' => $darsTurId,
                    'dars_soat' => $darsSoat,
                    'izoh' => $izoh
                ]);

                if ($insertRejaId <= 0) {
                    return [
                        'ok' => false,
                        'message' => "Dars satrini yaratishda xatolik (fan ID: {$targetFanId})"
                    ];
                }

                $db->query("DELETE FROM oquv_rejalar WHERE fan_id = {$targetFanId} AND dars_tur_id = {$darsTurId} AND id <> {$insertRejaId}");
                $createdDarsRows++;
            }
        }
    }

    return [
        'ok' => true,
        'empty' => false,
        'created_fans' => $createdFans,
        'reused_fans' => $reusedFans,
        'created_dars_rows' => $createdDarsRows,
        'updated_dars_rows' => $updatedDarsRows,
        'skipped_fans' => $skippedFans,
    ];
}

$targetSemestrIds = parseSemestrIds($_POST['target_semestr_ids'] ?? '');
$pairMap = parsePairMap($_POST['pair_map_json'] ?? '');
$sourceYonalishId = (int)($_POST['source_yonalish_id'] ?? 0);

$singleTargetSemestrId = (int)($_POST['target_semestr_id'] ?? 0);
$singleSourceSemestrId = (int)($_POST['source_semestr_id'] ?? 0);

$stats = [
    'created_fans' => 0,
    'reused_fans' => 0,
    'created_dars_rows' => 0,
    'updated_dars_rows' => 0,
    'skipped_fans' => 0,
    'processed_pairs' => 0,
    'missing_pairs' => 0,
    'empty_pairs' => 0,
];
$pairDetails = [];

if (!empty($pairMap)) {
    $targetSeen = [];
    foreach ($pairMap as $pair) {
        $targetSemestrId = (int)($pair['target_semestr_id'] ?? 0);
        if ($targetSemestrId <= 0) {
            continue;
        }
        if (isset($targetSeen[$targetSemestrId])) {
            echo json_encode([
                'success' => false,
                'message' => "Har bir maqsad semestr faqat bitta juftlikda qatnashishi kerak"
            ]);
            return;
        }
        $targetSeen[$targetSemestrId] = true;
    }

    $allSemestrIds = [];
    foreach ($pairMap as $pair) {
        $targetSemestrId = (int)($pair['target_semestr_id'] ?? 0);
        $sourceSemestrId = (int)($pair['source_semestr_id'] ?? 0);
        if ($targetSemestrId > 0) {
            $allSemestrIds[] = $targetSemestrId;
        }
        if ($sourceSemestrId > 0) {
            $allSemestrIds[] = $sourceSemestrId;
        }
    }
    $allSemestrIds = array_values(array_unique(array_map('intval', $allSemestrIds)));
    if (empty($allSemestrIds)) {
        echo json_encode([
            'success' => false,
            'message' => "Kamida bitta juftlikni to'g'ri tanlang"
        ]);
        return;
    }

    $allIdsSql = implode(',', $allSemestrIds);
    $semestrRowsResult = $db->query("SELECT id, yonalish_id, semestr FROM semestrlar WHERE id IN ({$allIdsSql})");
    if (!$semestrRowsResult) {
        echo json_encode([
            'success' => false,
            'message' => "Semestr ma'lumotlarini olishda xatolik"
        ]);
        return;
    }

    $semestrRows = [];
    while ($row = mysqli_fetch_assoc($semestrRowsResult)) {
        $sid = (int)($row['id'] ?? 0);
        if ($sid <= 0) {
            continue;
        }
        $semestrRows[$sid] = [
            'id' => $sid,
            'yonalish_id' => (int)($row['yonalish_id'] ?? 0),
            'semestr' => (int)($row['semestr'] ?? 0),
        ];
    }

    $db->query('START TRANSACTION');
    $ok = true;
    $errorMessage = "Nusxalash jarayonida xatolik yuz berdi";

    foreach ($pairMap as $idx => $pair) {
        $targetSemestrId = (int)($pair['target_semestr_id'] ?? 0);
        $sourceSemestrId = (int)($pair['source_semestr_id'] ?? 0);
        $pairLabel = ($idx + 1) . "-juftlik";

        if ($targetSemestrId <= 0 || $sourceSemestrId <= 0) {
            $stats['missing_pairs']++;
            $pairDetails[] = "{$pairLabel}: semestr tanlovi noto'g'ri";
            continue;
        }

        if ($targetSemestrId === $sourceSemestrId) {
            $stats['missing_pairs']++;
            $pairDetails[] = "{$pairLabel}: manba va maqsad bir xil";
            continue;
        }

        $targetRow = $semestrRows[$targetSemestrId] ?? null;
        $sourceRow = $semestrRows[$sourceSemestrId] ?? null;
        if (!$targetRow || !$sourceRow) {
            $stats['missing_pairs']++;
            $pairDetails[] = "{$pairLabel}: semestr topilmadi";
            continue;
        }

        if ($sourceYonalishId > 0 && (int)($sourceRow['yonalish_id'] ?? 0) !== $sourceYonalishId) {
            $stats['missing_pairs']++;
            $pairDetails[] = "{$pairLabel}: manba semestr tanlangan yo'nalishga tegishli emas";
            continue;
        }

        $pairResult = copySemestrPair($db, $sourceSemestrId, $targetSemestrId, $allowedTanlovTypes);
        if (!$pairResult['ok']) {
            $ok = false;
            $errorMessage = (string)($pairResult['message'] ?? $errorMessage);
            break;
        }

        if (!empty($pairResult['empty'])) {
            $stats['empty_pairs']++;
            $pairDetails[] = "{$pairLabel}: manbada nusxalanadigan fan yo'q";
            continue;
        }

        $stats['processed_pairs']++;
        $stats['created_fans'] += (int)($pairResult['created_fans'] ?? 0);
        $stats['reused_fans'] += (int)($pairResult['reused_fans'] ?? 0);
        $stats['created_dars_rows'] += (int)($pairResult['created_dars_rows'] ?? 0);
        $stats['updated_dars_rows'] += (int)($pairResult['updated_dars_rows'] ?? 0);
        $stats['skipped_fans'] += (int)($pairResult['skipped_fans'] ?? 0);
    }

    if (!$ok) {
        $db->query('ROLLBACK');
        echo json_encode([
            'success' => false,
            'message' => $errorMessage
        ]);
        return;
    }

    $db->query('COMMIT');

    $parts = [];
    $parts[] = "{$stats['processed_pairs']} ta semestrga nusxalandi";
    $parts[] = "{$stats['created_fans']} ta yangi fan";
    $parts[] = "{$stats['reused_fans']} ta mavjud fan yangilandi";
    $parts[] = "{$stats['created_dars_rows']} ta yangi dars satri";
    $parts[] = "{$stats['updated_dars_rows']} ta dars satri yangilandi";
    if ($stats['skipped_fans'] > 0) {
        $parts[] = "{$stats['skipped_fans']} ta fan o'tkazib yuborildi";
    }
    if ($stats['missing_pairs'] > 0) {
        $parts[] = "{$stats['missing_pairs']} ta juftlik mos kelmadi";
    }
    if ($stats['empty_pairs'] > 0) {
        $parts[] = "{$stats['empty_pairs']} ta juftlikda fan topilmadi";
    }

    echo json_encode([
        'success' => true,
        'message' => "Nusxa olish yakunlandi: " . implode(', ', $parts),
        'stats' => $stats,
        'details' => $pairDetails,
    ]);
    return;
}

if (!empty($targetSemestrIds) && $sourceYonalishId > 0) {
    $targetIdsSql = implode(',', array_map('intval', $targetSemestrIds));
    $targetRowsResult = $db->query("SELECT id, yonalish_id, semestr FROM semestrlar WHERE id IN ({$targetIdsSql})");
    if (!$targetRowsResult) {
        echo json_encode([
            'success' => false,
            'message' => "Maqsad semestrlar ma'lumotini olishda xatolik"
        ]);
        return;
    }

    $targetRows = [];
    while ($row = mysqli_fetch_assoc($targetRowsResult)) {
        $targetRows[(int)($row['id'] ?? 0)] = [
            'id' => (int)($row['id'] ?? 0),
            'yonalish_id' => (int)($row['yonalish_id'] ?? 0),
            'semestr' => (int)($row['semestr'] ?? 0),
        ];
    }

    $db->query('START TRANSACTION');
    $ok = true;
    $errorMessage = "Nusxalash jarayonida xatolik yuz berdi";

    foreach ($targetSemestrIds as $targetSemestrId) {
        $targetSemestrId = (int)$targetSemestrId;
        $targetRow = $targetRows[$targetSemestrId] ?? null;
        if (!$targetRow) {
            $stats['missing_pairs']++;
            $pairDetails[] = "Maqsad semestr topilmadi (ID: {$targetSemestrId})";
            continue;
        }

        $semestrNum = (int)($targetRow['semestr'] ?? 0);
        if ($semestrNum <= 0) {
            $stats['missing_pairs']++;
            $pairDetails[] = "Maqsad semestr raqami noto'g'ri (ID: {$targetSemestrId})";
            continue;
        }

        $sourceSemestr = $db->get_data_by_table('semestrlar', [
            'yonalish_id' => $sourceYonalishId,
            'semestr' => $semestrNum
        ]);
        $sourceSemestrId = (int)($sourceSemestr['id'] ?? 0);

        if ($sourceSemestrId <= 0) {
            $stats['missing_pairs']++;
            $pairDetails[] = "{$semestrNum}-semestr uchun manba topilmadi";
            continue;
        }
        if ($sourceSemestrId === $targetSemestrId) {
            $stats['missing_pairs']++;
            $pairDetails[] = "{$semestrNum}-semestr: manba va maqsad bir xil";
            continue;
        }

        $pairResult = copySemestrPair($db, $sourceSemestrId, $targetSemestrId, $allowedTanlovTypes);
        if (!$pairResult['ok']) {
            $ok = false;
            $errorMessage = (string)($pairResult['message'] ?? $errorMessage);
            break;
        }

        if (!empty($pairResult['empty'])) {
            $stats['empty_pairs']++;
            $pairDetails[] = "{$semestrNum}-semestr: manbada nusxalanadigan fan yo'q";
            continue;
        }

        $stats['processed_pairs']++;
        $stats['created_fans'] += (int)($pairResult['created_fans'] ?? 0);
        $stats['reused_fans'] += (int)($pairResult['reused_fans'] ?? 0);
        $stats['created_dars_rows'] += (int)($pairResult['created_dars_rows'] ?? 0);
        $stats['updated_dars_rows'] += (int)($pairResult['updated_dars_rows'] ?? 0);
        $stats['skipped_fans'] += (int)($pairResult['skipped_fans'] ?? 0);
    }

    if (!$ok) {
        $db->query('ROLLBACK');
        echo json_encode([
            'success' => false,
            'message' => $errorMessage
        ]);
        return;
    }

    $db->query('COMMIT');

    $parts = [];
    $parts[] = "{$stats['processed_pairs']} ta semestrga nusxalandi";
    $parts[] = "{$stats['created_fans']} ta yangi fan";
    $parts[] = "{$stats['reused_fans']} ta mavjud fan yangilandi";
    $parts[] = "{$stats['created_dars_rows']} ta yangi dars satri";
    $parts[] = "{$stats['updated_dars_rows']} ta dars satri yangilandi";
    if ($stats['skipped_fans'] > 0) {
        $parts[] = "{$stats['skipped_fans']} ta fan o'tkazib yuborildi";
    }
    if ($stats['missing_pairs'] > 0) {
        $parts[] = "{$stats['missing_pairs']} ta semestr mos kelmadi";
    }
    if ($stats['empty_pairs'] > 0) {
        $parts[] = "{$stats['empty_pairs']} ta semestrda fan topilmadi";
    }

    echo json_encode([
        'success' => true,
        'message' => "Nusxa olish yakunlandi: " . implode(', ', $parts),
        'stats' => $stats,
        'details' => $pairDetails,
    ]);
    return;
}

$targetSemestrId = $singleTargetSemestrId;
$sourceSemestrId = $singleSourceSemestrId;

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

$sourceSemestr = $db->get_data_by_table('semestrlar', ['id' => $sourceSemestrId]);
$targetSemestr = $db->get_data_by_table('semestrlar', ['id' => $targetSemestrId]);
if (!$sourceSemestr || !$targetSemestr) {
    echo json_encode([
        'success' => false,
        'message' => "Semestr topilmadi"
    ]);
    return;
}

$db->query('START TRANSACTION');
$pairResult = copySemestrPair($db, $sourceSemestrId, $targetSemestrId, $allowedTanlovTypes);
if (!$pairResult['ok']) {
    $db->query('ROLLBACK');
    echo json_encode([
        'success' => false,
        'message' => (string)($pairResult['message'] ?? "Nusxalash jarayonida xatolik yuz berdi")
    ]);
    return;
}

$db->query('COMMIT');
$parts = [];
$parts[] = "{$pairResult['created_fans']} ta yangi fan";
$parts[] = "{$pairResult['reused_fans']} ta mavjud fan yangilandi";
$parts[] = "{$pairResult['created_dars_rows']} ta yangi dars satri";
$parts[] = "{$pairResult['updated_dars_rows']} ta dars satri yangilandi";
if ((int)($pairResult['skipped_fans'] ?? 0) > 0) {
    $parts[] = "{$pairResult['skipped_fans']} ta fan o'tkazib yuborildi";
}
if (!empty($pairResult['empty'])) {
    $parts[] = "manba semestrda mos fan topilmadi";
}

echo json_encode([
    'success' => true,
    'message' => "Nusxa olish yakunlandi: " . implode(', ', $parts),
    'created_fans' => (int)($pairResult['created_fans'] ?? 0),
    'reused_fans' => (int)($pairResult['reused_fans'] ?? 0),
    'created_dars_rows' => (int)($pairResult['created_dars_rows'] ?? 0),
    'updated_dars_rows' => (int)($pairResult['updated_dars_rows'] ?? 0),
    'skipped_fans' => (int)($pairResult['skipped_fans'] ?? 0),
]);
