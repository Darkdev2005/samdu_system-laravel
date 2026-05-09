<?php
// Noldan real E2E test:
// 1) Fakultet, kafedra, yo'nalish, semestr, guruh, fanlarni yaratadi
// 2) chet_tili_talablarga 4 guruh x 3 til (10/10/10) taqsimot kiritadi
// 3) save_chet_tili_guruh_biriktirish endpointini subprocess orqali chaqiradi
// 4) Biriktirish/yuklama/taqsimot natijasini tekshiradi
// 5) Barcha test ma'lumotini tozalaydi

declare(strict_types=1);

include __DIR__ . '/../legacy/dashboard/config.php';

$db = new Database();
$token = 'ZZ_CHT_ZERO_' . date('Ymd_His');
$logs = [];

$created = [
    'fakultet_id' => 0,
    'kafedra_ids' => [],
    'yonalish_ids' => [],
    'semestr_ids' => [],
    'guruh_ids' => [],
    'fan_ids' => [],
];

function log_line(string $line): void
{
    global $logs;
    $logs[] = $line;
}

function fail_and_exit(string $message): void
{
    global $logs;
    foreach ($logs as $line) {
        echo $line . PHP_EOL;
    }
    echo 'FAIL: ' . $message . PHP_EOL;
    exit(1);
}

function cleanup_created(Database $db, array $created, string $token): void
{
    $fanIds = array_values(array_unique(array_map('intval', $created['fan_ids'] ?? [])));
    $guruhIds = array_values(array_unique(array_map('intval', $created['guruh_ids'] ?? [])));
    $semestrIds = array_values(array_unique(array_map('intval', $created['semestr_ids'] ?? [])));
    $yonalishIds = array_values(array_unique(array_map('intval', $created['yonalish_ids'] ?? [])));
    $kafedraIds = array_values(array_unique(array_map('intval', $created['kafedra_ids'] ?? [])));
    $fakultetId = (int)($created['fakultet_id'] ?? 0);

    if (!empty($fanIds)) {
        $fanSql = implode(',', $fanIds);
        $db->query("DELETE FROM chet_tili_biriktirilgan_guruhlar WHERE fan_id IN ($fanSql)");
        $db->query("DELETE FROM chet_tili_talablar WHERE fan_id IN ($fanSql)");
        $db->query("DELETE FROM oquv_rejalar WHERE fan_id IN ($fanSql)");
        $db->query("DELETE FROM fanlar WHERE id IN ($fanSql)");
    }

    if (!empty($guruhIds)) {
        $guruhSql = implode(',', $guruhIds);
        $db->query("DELETE FROM guruhlar WHERE id IN ($guruhSql)");
    }

    if (!empty($semestrIds)) {
        $semestrSql = implode(',', $semestrIds);
        $db->query("DELETE FROM semestrlar WHERE id IN ($semestrSql)");
    }

    if (!empty($yonalishIds)) {
        $yonSql = implode(',', $yonalishIds);
        $db->query("DELETE FROM yonalishlar WHERE id IN ($yonSql)");
    }

    if (!empty($kafedraIds)) {
        $kafSql = implode(',', $kafedraIds);
        $db->query("DELETE FROM kafedralar WHERE id IN ($kafSql)");
    }

    if ($fakultetId > 0) {
        $db->query("DELETE FROM fakultetlar WHERE id = $fakultetId");
    }

    // Safety net: token bo'yicha ortiqcha yozuvlar qolsa ham tozalash.
    $safeToken = addslashes($token);
    $db->query("DELETE FROM fanlar WHERE fan_name LIKE '%$safeToken%' OR fan_code LIKE '%$safeToken%'");
    $db->query("DELETE FROM guruhlar WHERE guruh_nomer LIKE '%$safeToken%'");
    $db->query("DELETE FROM yonalishlar WHERE name LIKE '%$safeToken%' OR code LIKE '%$safeToken%'");
    $db->query("DELETE FROM kafedralar WHERE name LIKE '%$safeToken%'");
    $db->query("DELETE FROM fakultetlar WHERE name LIKE '%$safeToken%'");
}

function find_existing_id(Database $db, string $table, string $name): int
{
    $safeName = addslashes($name);
    $res = $db->query("SELECT id FROM $table WHERE name = '$safeName' ORDER BY id DESC LIMIT 1");
    if (!$res) {
        return 0;
    }
    $row = mysqli_fetch_assoc($res);
    return (int)($row['id'] ?? 0);
}

try {
    $akademikDarajaId = 4;
    $talimShakliId = 1;

    $adarajaRes = $db->query("SELECT id FROM akademik_darajalar ORDER BY id ASC LIMIT 1");
    $adarajaRow = $adarajaRes ? mysqli_fetch_assoc($adarajaRes) : null;
    if ((int)($adarajaRow['id'] ?? 0) <= 0) {
        fail_and_exit("akademik_darajalar jadvalida yozuv topilmadi");
    }
    if ((int)$akademikDarajaId <= 0) {
        $akademikDarajaId = (int)$adarajaRow['id'];
    }

    $tshRes = $db->query("SELECT id FROM talim_shakllar ORDER BY id ASC LIMIT 1");
    $tshRow = $tshRes ? mysqli_fetch_assoc($tshRes) : null;
    if ((int)($tshRow['id'] ?? 0) <= 0) {
        fail_and_exit("talim_shakllar jadvalida yozuv topilmadi");
    }
    if ((int)$talimShakliId <= 0) {
        $talimShakliId = (int)$tshRow['id'];
    }

    $fakultetName = "Fakultet $token";
    $fakultetId = (int)$db->insert('fakultetlar', ['name' => $fakultetName]);
    if ($fakultetId <= 0) {
        $fakultetId = find_existing_id($db, 'fakultetlar', $fakultetName);
    }
    if ($fakultetId <= 0) {
        fail_and_exit("Fakultet yaratilmadi");
    }
    $created['fakultet_id'] = $fakultetId;
    log_line("OK: Fakultet yaratildi (ID=$fakultetId)");

    $kafedraMap = [];
    $kafedraNames = [
        'en' => "Ingliz kafedrasi $token",
        'de' => "Nemis kafedrasi $token",
        'fr' => "Fransuz kafedrasi $token",
    ];
    foreach ($kafedraNames as $key => $name) {
        $kid = (int)$db->insert('kafedralar', [
            'name' => $name,
            'fakultet_id' => $fakultetId,
        ]);
        if ($kid <= 0) {
            $kid = find_existing_id($db, 'kafedralar', $name);
        }
        if ($kid <= 0) {
            fail_and_exit("Kafedra yaratilmadi: $name");
        }
        $kafedraMap[$key] = $kid;
        $created['kafedra_ids'][] = $kid;
    }
    log_line('OK: 3 ta til kafedrasi yaratildi');

    $yonalishMap = [];
    $yonalishDefs = [
        'di' => ['name' => "Dasturiy injenering $token", 'code' => "DI_$token"],
        'si' => ['name' => "Suniy intellekt $token", 'code' => "SI_$token"],
    ];
    foreach ($yonalishDefs as $key => $def) {
        $yid = (int)$db->insert('yonalishlar', [
            'name' => $def['name'],
            'code' => $def['code'],
            'muddati' => 4,
            'patok_soni' => 1,
            'kattaguruh_soni' => 1,
            'kichikguruh_soni' => 2,
            'kirish_yili' => 2024,
            'kvalifikatsiya' => 'Bakalavr',
            'akademik_daraja_id' => $akademikDarajaId,
            'talim_shakli_id' => $talimShakliId,
            'fakultet_id' => $fakultetId,
        ]);
        if ($yid <= 0) {
            $safeName = addslashes($def['name']);
            $res = $db->query("SELECT id FROM yonalishlar WHERE name = '$safeName' ORDER BY id DESC LIMIT 1");
            $row = $res ? mysqli_fetch_assoc($res) : null;
            $yid = (int)($row['id'] ?? 0);
        }
        if ($yid <= 0) {
            fail_and_exit("Yo'nalish yaratilmadi: " . $def['name']);
        }
        $yonalishMap[$key] = $yid;
        $created['yonalish_ids'][] = $yid;
    }
    log_line('OK: 2 ta yo\'nalish yaratildi');

    $semestrMap = [];
    foreach ($yonalishMap as $key => $yonalishId) {
        $sid = (int)$db->insert('semestrlar', [
            'fakultet_id' => $fakultetId,
            'yonalish_id' => $yonalishId,
            'semestr' => 3,
        ]);
        if ($sid <= 0) {
            $res = $db->query("SELECT id FROM semestrlar WHERE yonalish_id = " . (int)$yonalishId . " AND semestr = 3 LIMIT 1");
            $row = $res ? mysqli_fetch_assoc($res) : null;
            $sid = (int)($row['id'] ?? 0);
        }
        if ($sid <= 0) {
            fail_and_exit("Semestr yaratilmadi (yonalish_id=$yonalishId)");
        }
        $semestrMap[$key] = $sid;
        $created['semestr_ids'][] = $sid;
    }
    log_line("OK: Har yo'nalish uchun 3-semestr yaratildi");

    $groupDefs = [
        ['key' => 'di', 'no' => "304_$token", 'size' => 30],
        ['key' => 'di', 'no' => "305_$token", 'size' => 30],
        ['key' => 'si', 'no' => "302_$token", 'size' => 30],
        ['key' => 'si', 'no' => "303_$token", 'size' => 30],
    ];
    $groups = [];
    foreach ($groupDefs as $gdef) {
        $gId = (int)$db->insert('guruhlar', [
            'guruh_nomer' => $gdef['no'],
            'soni' => $gdef['size'],
            'yonalish_id' => $yonalishMap[$gdef['key']],
        ]);
        if ($gId <= 0) {
            $safeNo = addslashes($gdef['no']);
            $res = $db->query("SELECT id FROM guruhlar WHERE guruh_nomer = '$safeNo' ORDER BY id DESC LIMIT 1");
            $row = $res ? mysqli_fetch_assoc($res) : null;
            $gId = (int)($row['id'] ?? 0);
        }
        if ($gId <= 0) {
            fail_and_exit("Guruh yaratilmadi: " . $gdef['no']);
        }
        $groups[] = [
            'id' => $gId,
            'yon_key' => $gdef['key'],
            'yonalish_id' => $yonalishMap[$gdef['key']],
            'semestr_id' => $semestrMap[$gdef['key']],
            'size' => $gdef['size'],
            'no' => $gdef['no'],
        ];
        $created['guruh_ids'][] = $gId;
    }
    log_line("OK: 4 ta guruh yaratildi (304/305/302/303 tokenli)");

    $fanMap = [];
    $fanDefs = [
        'en' => ['name' => "Ingliz tili $token", 'code' => "EN_$token", 'kafedra' => 'en'],
        'de' => ['name' => "Nemis tili $token", 'code' => "DE_$token", 'kafedra' => 'de'],
        'fr' => ['name' => "Fransuz tili $token", 'code' => "FR_$token", 'kafedra' => 'fr'],
    ];

    foreach ($semestrMap as $yonKey => $semestrId) {
        foreach ($fanDefs as $lang => $fdef) {
            $fid = (int)$db->insert('fanlar', [
                'fan_name' => $fdef['name'],
                'fan_code' => $fdef['code'],
                'semestr_id' => $semestrId,
                'kafedra_id' => $kafedraMap[$fdef['kafedra']],
                'tanlov_fan' => 3,
            ]);
            if ($fid <= 0) {
                fail_and_exit("Fan yaratilmadi: {$fdef['name']}, semestr_id=$semestrId");
            }
            $fanMap[$yonKey][$lang] = $fid;
            $created['fan_ids'][] = $fid;

            // Yuklamada ko'rinishi uchun reja soatlari.
            foreach ([1 => 30, 2 => 30, 3 => 0, 4 => 0] as $darsTurId => $soat) {
                $rid = (int)$db->insert('oquv_rejalar', [
                    'fan_id' => $fid,
                    'dars_tur_id' => $darsTurId,
                    'dars_soat' => $soat,
                    'izoh' => 'zero-e2e',
                ]);
                if ($rid <= 0) {
                    fail_and_exit("oquv_rejalar yozuvi yaratilmadi (fan_id=$fid, tur=$darsTurId)");
                }
            }
        }
    }
    log_line('OK: 6 ta til fani va o\'quv rejalari yaratildi');

    $scopeItems = [];
    foreach ($groups as $group) {
        $yonKey = $group['yon_key'];
        foreach (['en', 'de', 'fr'] as $lang) {
            $fanId = (int)($fanMap[$yonKey][$lang] ?? 0);
            if ($fanId <= 0) {
                fail_and_exit("Fan topilmadi (yon=$yonKey, lang=$lang)");
            }

            $rowId = (int)$db->insert('chet_tili_talablar', [
                'semestr_id' => $group['semestr_id'],
                'yonalish_id' => $group['yonalish_id'],
                'guruh_id' => $group['id'],
                'fan_id' => $fanId,
                'talabalar_soni' => 10,
            ]);
            if ($rowId <= 0) {
                fail_and_exit("chet_tili_talablar yozuvi yaratilmadi (guruh={$group['no']}, lang=$lang)");
            }

            $scopeItems[] = [
                'semestr_id' => $group['semestr_id'],
                'yonalish_id' => $group['yonalish_id'],
                'guruh_id' => $group['id'],
                'fan_id' => $fanId,
                'talabalar_soni' => 10,
                'selected' => 1,
            ];
        }
    }
    log_line('OK: chet_tili_talablarga 12 qator (4 guruh x 3 til) saqlandi');

    $payloadPath = __DIR__ . '/../__tmp_zero_payload.json';
    $payloadJson = json_encode($scopeItems, JSON_UNESCAPED_UNICODE);
    if ($payloadJson === false || file_put_contents($payloadPath, $payloadJson) === false) {
        fail_and_exit("Endpoint payload fayli yozilmadi");
    }

    $invokePath = __DIR__ . '/../__tmp_invoke_save_zero.php';
    if (!is_file($invokePath)) {
        fail_and_exit("__tmp_invoke_save_zero.php topilmadi");
    }
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($invokePath);
    $raw = (string)shell_exec($cmd);
    $raw = trim($raw);
    if ($raw === '') {
        fail_and_exit("save_chet_tili_guruh_biriktirish javobi bo'sh qaytdi");
    }
    $resp = json_decode($raw, true);
    if (!is_array($resp) || empty($resp['success'])) {
        fail_and_exit("Endpoint xato qaytardi: $raw");
    }
    log_line('OK: save_chet_tili_guruh_biriktirish success: ' . ($resp['message'] ?? ''));

    $tokenLike = addslashes('%' . $token . '%');
    $aggRes = $db->query("
        SELECT
            f.fan_name,
            COUNT(*) AS rows_cnt,
            SUM(bg.talabalar_soni) AS total_students
        FROM chet_tili_biriktirilgan_guruhlar bg
        JOIN fanlar f ON f.id = bg.fan_id
        WHERE f.fan_name LIKE '$tokenLike'
        GROUP BY f.fan_name
        ORDER BY f.fan_name
    ");

    $agg = [];
    if ($aggRes) {
        while ($row = mysqli_fetch_assoc($aggRes)) {
            $agg[] = $row;
        }
    }

    if (count($agg) < 3) {
        fail_and_exit("Biriktirilgan guruhlar agregati yetarli emas (kutilgan >=3, topildi=" . count($agg) . ")");
    }

    foreach ($agg as $row) {
        $rowsCnt = (int)($row['rows_cnt'] ?? 0);
        $total = (int)($row['total_students'] ?? 0);
        if ($rowsCnt !== 4 || $total !== 40) {
            fail_and_exit("Agregat mos emas: " . json_encode($row, JSON_UNESCAPED_UNICODE));
        }
    }
    log_line("OK: Har til bo'yicha 4 ta guruh, 40 ta talaba tasdiqlandi");

    $yRows = $db->get_oquv_yuklamalar(['limit' => 5000]);
    $yMatched = [];
    foreach ($yRows as $row) {
        $fanName = (string)($row['fan_name'] ?? '');
        if (strpos($fanName, $token) !== false) {
            $yMatched[] = $row;
        }
    }
    if (count($yMatched) < 3) {
        fail_and_exit("Yuklamada tokenli satrlar topilmadi (>=3 kerak, topildi=" . count($yMatched) . ")");
    }
    log_line("OK: Yuklamada tokenli satrlar ko'rindi (" . count($yMatched) . " ta)");

    $tRows = $db->get_oquv_taqsimotlar(['limit' => 5000]);
    $tMatched = [];
    foreach ($tRows as $row) {
        $fanName = (string)($row['fan_nomi'] ?? '');
        if (strpos($fanName, $token) !== false) {
            $tMatched[] = $row;
        }
    }
    if (count($tMatched) < 3) {
        fail_and_exit("Taqsimotda tokenli satrlar topilmadi (>=3 kerak, topildi=" . count($tMatched) . ")");
    }
    log_line("OK: Taqsimotda tokenli satrlar ko'rindi (" . count($tMatched) . " ta)");

    foreach ($logs as $line) {
        echo $line . PHP_EOL;
    }
    echo "TEST PASS: $token" . PHP_EOL;
} catch (Throwable $e) {
    fail_and_exit("Exception: " . $e->getMessage());
} finally {
    cleanup_created($db, $created, $token);
    @unlink(__DIR__ . '/../__tmp_zero_payload.json');
}

