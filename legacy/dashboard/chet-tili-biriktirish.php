<?php
// Izoh: Chet tili fanlarini yo'nalish + semestr bo'yicha biriktirish sahifasi.
include_once 'config.php';
$db = new Database();
$forcedTabId = '';
if (isset($forceChetTiliTab) && is_string($forceChetTiliTab)) {
    $candidateTab = trim($forceChetTiliTab);
    $allowedTabs = ['chet-tab-yaratish', 'chet-tab-biriktirish', 'chet-tab-guruh-birlashtirish'];
    if (in_array($candidateTab, $allowedTabs, true)) {
        $forcedTabId = $candidateTab;
    }
}

$fanOptionsBySemestr = [];
// Izoh: Chet tili fanlari (tanlov_fan = 3) semestr_id bo'yicha ajratib olinadi.
// Izoh: Biriktirishda faqat kafedra biriktirilgan fanlar ko'rsatiladi.
$fanResult = $db->query("
        SELECT f.id, f.fan_name, f.fan_code, f.semestr_id, f.kafedra_id,
               k.name AS kafedra_name,
               s.semestr AS semestr_num,
               y.name AS yonalish_name,
               y.kirish_yili AS yonalish_yili
        FROM fanlar f
        LEFT JOIN kafedralar k ON k.id = f.kafedra_id
        LEFT JOIN semestrlar s ON s.id = f.semestr_id
        LEFT JOIN yonalishlar y ON y.id = s.yonalish_id
        WHERE f.tanlov_fan = 3 AND f.kafedra_id > 0
        ORDER BY f.fan_name, y.name, y.kirish_yili, f.id DESC
    ");
if ($fanResult) {
    $seenFanIds = [];
    while ($row = mysqli_fetch_assoc($fanResult)) {
        $semestrId = (int) ($row['semestr_id'] ?? 0);
        if ($semestrId <= 0) {
            continue;
        }
        $fanId = (int) ($row['id'] ?? 0);
        if ($fanId <= 0 || isset($seenFanIds[$fanId])) {
            continue;
        }
        $seenFanIds[$fanId] = true;

        $label = trim($row['fan_name']);
        if (!empty($row['kafedra_name'])) {
            $label .= ' (' . $row['kafedra_name'] . ')';
        } else {
            $label .= ' (Kafedra belgilanmagan)';
        }

        $yonalishLabel = trim($row['yonalish_name'] ?? '');
        $yonalishYili = trim($row['yonalish_yili'] ?? '');
        if ($yonalishLabel !== '') {
            $label .= ' - ' . $yonalishLabel;
            if ($yonalishYili !== '') {
                $label .= ' - ' . $yonalishYili;
            }
        }

        if (!isset($fanOptionsBySemestr[$semestrId])) {
            $fanOptionsBySemestr[$semestrId] = '';
        }
        $fanOptionsBySemestr[$semestrId] .= '<option value="' . $fanId . '">' . htmlspecialchars($label) . '</option>';
    }
}

$semestrlar = $db->get_semestrlar();
$fakultetlar = $db->get_data_by_table_all('fakultetlar');
$yonalishlar = $db->get_data_by_table_all('yonalishlar');
$kafedralar = $db->get_data_by_table_all('kafedralar');
$fakultetlarMap = [];
foreach ($fakultetlar as $fakultetRow) {
    $fakultetId = (int)($fakultetRow['id'] ?? 0);
    if ($fakultetId <= 0 || isset($fakultetlarMap[$fakultetId])) {
        continue;
    }

    $fakultetlarMap[$fakultetId] = [
        'id' => $fakultetId,
        'name' => (string)($fakultetRow['name'] ?? ''),
    ];
}
$kafedralarSimple = array_map(static function ($row): array {
    return [
        'id' => (int)($row['id'] ?? 0),
        'name' => (string)($row['name'] ?? ''),
    ];
}, $kafedralar);
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$kafedralarJson = json_encode($kafedralarSimple, $jsonFlags);
if ($kafedralarJson === false) {
    $kafedralarJson = '[]';
}
$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$normalizeLanguageName = static function (string $value): string {
    $normalized = trim($value);
    if ($normalized === '') {
        return '';
    }

    if (function_exists('mb_strtolower')) {
        $normalized = (string)@mb_strtolower($normalized, 'UTF-8');
    } else {
        $normalized = strtolower($normalized);
    }

    $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    $normalized = trim($normalized);
    $normalized = preg_replace('/\b(chet|xorijiy|foreign)\b/u', ' ', $normalized) ?? $normalized;
    $normalized = preg_replace('/\btili?\b/u', ' ', $normalized) ?? $normalized;
    $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    $normalized = trim($normalized);

    if ($normalized === '') {
        return 'xorijiy_til';
    }

    return $normalized;
};
$academicYearLabel = static function (string $value): string {
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }

    if (preg_match('/(\d{4})/', $trimmed, $matches)) {
        $startYear = (int)$matches[1];
        if ($startYear > 0) {
            return $startYear . '-' . ($startYear + 1);
        }
    }

    return $trimmed;
};
$makeShortCode = static function (string $name): string {
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $short = '';
    foreach ($words as $word) {
        $word = trim((string)$word);
        if ($word === '') {
            continue;
        }
        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            $first = @mb_substr($word, 0, 1, 'UTF-8');
            if ($first !== false && $first !== '') {
                $short .= (string)@mb_strtoupper($first, 'UTF-8');
                continue;
            }
        }
        $short .= strtoupper((string)substr($word, 0, 1));
    }
    return $short;
};
$yonalishFakultetMap = [];
foreach ($yonalishlar as $yRow) {
    $yId = (int)($yRow['id'] ?? 0);
    if ($yId <= 0) {
        continue;
    }
    $yonalishFakultetMap[$yId] = (int)($yRow['fakultet_id'] ?? 0);
}

$filterYonalishlarMap = [];
foreach ($semestrlar as $s) {
    $yonalishId = (int)($s['yonalish_id'] ?? 0);
    if ($yonalishId <= 0 || isset($filterYonalishlarMap[$yonalishId])) {
        continue;
    }

    $resolvedFakultetId = (int)($yonalishFakultetMap[$yonalishId] ?? 0);
    if ($resolvedFakultetId <= 0) {
        $resolvedFakultetId = (int)($s['yonalish_fakultet_id'] ?? ($s['fakultet_id'] ?? 0));
    }

    $filterYonalishlarMap[$yonalishId] = [
        'id' => $yonalishId,
        'name' => (string)($s['yonalish_name'] ?? ''),
        'kirish_yili' => (string)($s['kirish_yili'] ?? ''),
        'fakultet_id' => $resolvedFakultetId,
    ];
}
$filterYonalishlar = array_values($filterYonalishlarMap);
usort($filterYonalishlar, static function (array $a, array $b): int {
    $aName = (string)($a['name'] ?? '');
    $bName = (string)($b['name'] ?? '');
    $nameCmp = strcmp($aName, $bName);
    if ($nameCmp !== 0) {
        return $nameCmp;
    }
    return strcmp((string)($a['kirish_yili'] ?? ''), (string)($b['kirish_yili'] ?? ''));
});
// Izoh: Chet tili fan select uchun faqat o'quv rejada yaratilgan fanlar olinadi (semestr bo'yicha).
$chet_tili_fanlar = $db->get_data_by_table_all('fanlar', 'WHERE tanlov_fan = 3 AND (kafedra_id = 0 OR kafedra_id IS NULL OR kafedra_id = "")');
$chetTiliOptionsBySemestr = [];
$chetSeen = [];
foreach ($chet_tili_fanlar as $fan) {
    $semestrId = (int) ($fan['semestr_id'] ?? 0);
    $code = trim($fan['fan_code'] ?? '');
    $name = trim($fan['fan_name'] ?? '');
    if ($semestrId <= 0 || $code === '' || $name === '') {
        continue;
    }
    $key = $semestrId . '|' . $code . '|' . $name;
    if (isset($chetSeen[$key])) {
        continue;
    }
    $chetSeen[$key] = true;
    $safeCode = htmlspecialchars($code);
    $safeName = htmlspecialchars($name);
    if (!isset($chetTiliOptionsBySemestr[$semestrId])) {
        $chetTiliOptionsBySemestr[$semestrId] = '';
    }
    $chetTiliOptionsBySemestr[$semestrId] .= "<option value=\"{$safeCode}\" data-name=\"{$safeName}\">{$safeCode} - {$safeName}</option>";
}
$semestrOptions = '';
foreach ($semestrlar as $s) {
    $yonalishName = trim($s['yonalish_name'] ?? '');
    $kirishYili = trim($s['kirish_yili'] ?? '');
    $semestrNum = trim($s['semestr'] ?? '');
    $darajaRaw = trim((string)($s['akademik_daraja_name'] ?? ''));
    $daraja = function_exists('mb_strtolower')
        ? (string)@mb_strtolower($darajaRaw, 'UTF-8')
        : strtolower($darajaRaw);
    $darajaPrefix = '';
    if (strpos($daraja, 'magistr') !== false) {
        $darajaPrefix = 'M ';
    } elseif (strpos($daraja, 'bakalavr') !== false) {
        $darajaPrefix = 'B ';
    }

    $labelParts = [];
    if ($yonalishName !== '') {
        $labelParts[] = $yonalishName;
    }
    if ($kirishYili !== '') {
        $labelParts[] = $kirishYili;
    }
    $label = implode(' - ', $labelParts);
    if ($semestrNum !== '') {
        $label = ($label !== '' ? $label . ' - ' : '') . $semestrNum . '-semestr';
    }
    if ($label === '') {
        $label = 'Semestr: ' . (int)$s['id'];
    }
    $label = $darajaPrefix . $label;
    $yonalishId = (int)($s['yonalish_id'] ?? 0);
    $fakultetId = (int)($yonalishFakultetMap[$yonalishId] ?? 0);
    if ($fakultetId <= 0) {
        $fakultetId = (int)($s['yonalish_fakultet_id'] ?? ($s['fakultet_id'] ?? 0));
    }
    $semestrOptions .= '<option value="' . (int)$s['id'] . '" data-fakultet-id="' . $fakultetId . '" data-yonalish-id="' . $yonalishId . '">' . $h($label) . '</option>';
}
if ($semestrOptions === '') {
    $semestrOptions = '<option value="" disabled>Semestr topilmadi</option>';
}

// Izoh: Yo'nalishlar ro'yxatini map qilib olamiz.
$yonalishlarMap = [];
foreach ($yonalishlar as $y) {
    $label = trim($y['name'] ?? '');
    $yil = trim($y['kirish_yili'] ?? '');
    if ($yil !== '') {
        $label .= ' - ' . $yil;
    }
    $yonalishlarMap[(int)$y['id']] = $label;
}

// Izoh: Biriktirilgan chet tili fanlari ro'yxati (UI uchun birlashtiriladi: fan_code + semestr raqami).
$guruhRows = [];
$guruhResult = $db->query("
        SELECT
            MAX(ct.fan_id) AS fan_id,
            f.fan_code,
            MAX(f.fan_name) AS fan_name,
            MAX(km.name) AS kafedra_name,
            s.semestr AS semestr_num,
            GROUP_CONCAT(DISTINCT ct.yonalish_id ORDER BY ct.yonalish_id SEPARATOR ',') AS yonalish_ids,
            GROUP_CONCAT(DISTINCT ct.semestr_id ORDER BY ct.semestr_id SEPARATOR ',') AS semestr_ids,
            GROUP_CONCAT(DISTINCT ct.source_fan_ids ORDER BY ct.source_fan_ids SEPARATOR ',') AS source_fan_ids,
            MAX(ct.create_at) AS create_at
        FROM chet_tili_guruhlar ct
        JOIN fanlar f ON f.id = ct.fan_id
        LEFT JOIN kafedralar km ON km.id = f.kafedra_id
        JOIN semestrlar s ON s.id = ct.semestr_id
        GROUP BY f.fan_code, s.semestr
        ORDER BY create_at DESC
    ");
if ($guruhResult) {
    while ($row = mysqli_fetch_assoc($guruhResult)) {
        $yonalishList = [];
        $idsRaw = trim($row['yonalish_ids'] ?? '');
        if ($idsRaw !== '') {
            $ids = array_filter(array_map('intval', explode(',', $idsRaw)));
            foreach ($ids as $id) {
                if (isset($yonalishlarMap[$id])) {
                    $yonalishList[] = $yonalishlarMap[$id];
                }
            }
        }
        $row['yonalishlar'] = $yonalishList;
        $guruhRows[] = $row;
    }
}

// Izoh: Til guruhlarini qo'lda biriktirish uchun direction -> guruhlar xaritasi.
$mergeSelectedMap = [];
$mergeSelectedLanguageMap = [];
$mergeSelectedGroupMap = [];
$mergeSelectedRes = $db->query("
        SELECT
            bg.semestr_id,
            s.semestr AS semestr_num,
            bg.guruh_id,
            bg.fan_id,
            f.fan_name
        FROM chet_tili_biriktirilgan_guruhlar bg
        JOIN semestrlar s ON s.id = bg.semestr_id
        JOIN fanlar f ON f.id = bg.fan_id
    ");
if ($mergeSelectedRes) {
    while ($row = mysqli_fetch_assoc($mergeSelectedRes)) {
        $semestrId = (int)($row['semestr_id'] ?? 0);
        $semestrNum = (int)($row['semestr_num'] ?? 0);
        $guruhId = (int)($row['guruh_id'] ?? 0);
        $fanId = (int)($row['fan_id'] ?? 0);
        if ($semestrId <= 0 || $guruhId <= 0 || $fanId <= 0) {
            continue;
        }
        $mergeSelectedMap[$semestrId . '|' . $guruhId . '|' . $fanId] = true;
        if ($semestrNum > 0) {
            $mergeSelectedGroupMap[$semestrNum . '|' . $guruhId] = true;
        }

        $fanNameKey = $normalizeLanguageName((string)($row['fan_name'] ?? ''));
        if ($semestrNum > 0 && $fanNameKey !== '') {
            $mergeSelectedLanguageMap[$semestrNum . '|' . $guruhId . '|' . $fanNameKey] = true;
        }
    }
}

$mergeCards = [];
$mergeSelectedCount = 0;
$mergeSelectedStudents = 0;
$mergeSelectedUniqueKeys = [];
$mergeVisibleLimit = 30;
$mergeFilterFakultetMap = [];
$mergeFilterYonalishMap = [];
$mergeFilterSemestrMap = [];
$mergeSourceRes = $db->query("
        SELECT
            t.semestr_id,
            s.semestr AS semestr_num,
            y.id AS yonalish_id,
            y.name AS yonalish_name,
            y.kirish_yili,
            y.fakultet_id,
            y.talim_shakli_id,
            tsh.name AS talim_shakli_name,
            g.id AS guruh_id,
            g.guruh_nomer,
            f.id AS fan_id,
            f.fan_code,
            f.fan_name,
            k.name AS kafedra_name,
            SUM(t.talabalar_soni) AS talabalar_soni
        FROM chet_tili_talablar t
        JOIN semestrlar s ON s.id = t.semestr_id
        JOIN guruhlar g ON g.id = t.guruh_id
        JOIN yonalishlar y ON y.id = g.yonalish_id
        LEFT JOIN talim_shakllar tsh ON tsh.id = y.talim_shakli_id
        JOIN fanlar f ON f.id = t.fan_id
        LEFT JOIN kafedralar k ON k.id = f.kafedra_id
        WHERE f.tanlov_fan = 3
        GROUP BY
            t.semestr_id,
            s.semestr,
            y.id,
            y.name,
            y.kirish_yili,
            y.fakultet_id,
            y.talim_shakli_id,
            tsh.name,
            g.id,
            g.guruh_nomer,
            f.id,
            f.fan_code,
            f.fan_name,
            k.name
        ORDER BY s.semestr, y.name, y.kirish_yili, g.guruh_nomer, f.fan_code, f.fan_name
    ");
if ($mergeSourceRes) {
    while ($row = mysqli_fetch_assoc($mergeSourceRes)) {
        $semestrId = (int)($row['semestr_id'] ?? 0);
        $yonalishId = (int)($row['yonalish_id'] ?? 0);
        $guruhId = (int)($row['guruh_id'] ?? 0);
        $fanId = (int)($row['fan_id'] ?? 0);
        if ($semestrId <= 0 || $yonalishId <= 0 || $guruhId <= 0 || $fanId <= 0) {
            continue;
        }

        $cardKey = $semestrId . '|' . $yonalishId;
        if (!isset($mergeCards[$cardKey])) {
            $yonalishLabel = trim((string)($row['yonalish_name'] ?? ''));
            $kirishYili = trim((string)($row['kirish_yili'] ?? ''));
            $semestrNum = (int)($row['semestr_num'] ?? 0);
            $fakultetId = (int)($row['fakultet_id'] ?? 0);
            $fakultetName = trim((string)($fakultetlarMap[$fakultetId]['name'] ?? ''));
            $talimShakliId = (int)($row['talim_shakli_id'] ?? 0);
            $talimShakliName = trim((string)($row['talim_shakli_name'] ?? ''));
            $kurs = (int)floor(($semestrNum + 1) / 2);
            if ($fakultetName === '') {
                $fakultetName = 'Fakultet belgilanmagan';
            }
            $mergeCards[$cardKey] = [
                'semestr_id' => $semestrId,
                'semestr_num' => $semestrNum,
                'kurs' => $kurs,
                'yonalish_id' => $yonalishId,
                'yonalish_label' => $yonalishLabel,
                'kirish_yili' => $kirishYili,
                'fakultet_id' => $fakultetId,
                'fakultet_name' => $fakultetName,
                'talim_shakli_id' => $talimShakliId,
                'talim_shakli_name' => $talimShakliName,
                'rows' => [],
                'selected_count' => 0,
                'selected_students' => 0,
                'total_students' => 0,
            ];

            if (!isset($mergeFilterFakultetMap[$fakultetId])) {
                $mergeFilterFakultetMap[$fakultetId] = [
                    'id' => $fakultetId,
                    'name' => $fakultetName,
                ];
            }
            if (!isset($mergeFilterYonalishMap[$yonalishId])) {
                $mergeFilterYonalishMap[$yonalishId] = [
                    'id' => $yonalishId,
                    'name' => $yonalishLabel,
                    'kirish_yili' => $kirishYili,
                    'fakultet_id' => $fakultetId,
                    'fakultet_name' => $fakultetName,
                ];
            }
            if (!isset($mergeFilterSemestrMap[$semestrId])) {
                $mergeFilterSemestrMap[$semestrId] = [
                    'id' => $semestrId,
                    'semestr_num' => $semestrNum,
                    'yonalish_id' => $yonalishId,
                    'yonalish_name' => $yonalishLabel,
                    'kirish_yili' => $kirishYili,
                    'fakultet_id' => $fakultetId,
                    'fakultet_name' => $fakultetName,
                ];
            }
        }

        $rowKey = $semestrId . '|' . $guruhId . '|' . $fanId;
        $talabalarSoni = (int)($row['talabalar_soni'] ?? 0);
        $semestrNum = (int)($row['semestr_num'] ?? 0);
        $fanNameKey = $normalizeLanguageName((string)($row['fan_name'] ?? ''));
        $languageRowKey = $semestrNum . '|' . $guruhId . '|' . $fanNameKey;
        $mergeRowKey = $languageRowKey;
        $groupSelectionKey = $semestrNum . '|' . $guruhId;

        if (isset($mergeSelectedGroupMap[$groupSelectionKey])) {
            // Izoh: Avval biriktirilgan guruhlar qayta checkbox ro'yxatida ko'rinmaydi.
            continue;
        }

        $selected = isset($mergeSelectedMap[$rowKey]) || isset($mergeSelectedLanguageMap[$languageRowKey]);
        if ($selected && isset($mergeSelectedUniqueKeys[$mergeRowKey])) {
            $selected = false;
        } elseif ($selected) {
            $mergeSelectedUniqueKeys[$mergeRowKey] = true;
        }

        // Izoh: Oldin birlashtirilgan guruhlar yana tanlovda ko'rinmasin.
        if ($selected) {
            continue;
        }

        $mergeCards[$cardKey]['total_students'] += $talabalarSoni;
        $mergeCards[$cardKey]['rows'][] = [
            'semestr_id' => $semestrId,
            'yonalish_id' => $yonalishId,
            'guruh_id' => $guruhId,
            'fan_id' => $fanId,
            'fan_code' => trim((string)($row['fan_code'] ?? '')),
            'fan_name' => trim((string)($row['fan_name'] ?? '')),
            'language_key' => $fanNameKey,
            'kafedra_name' => trim((string)($row['kafedra_name'] ?? '')),
            'guruh_nomer' => trim((string)($row['guruh_nomer'] ?? '')),
            'talabalar_soni' => $talabalarSoni,
            'selected' => false,
            'merge_row_key' => $mergeRowKey,
        ];
    }
}

$mergeCards = array_values($mergeCards);
usort($mergeCards, static function (array $a, array $b): int {
    $aSem = (int)($a['semestr_num'] ?? 0);
    $bSem = (int)($b['semestr_num'] ?? 0);
    if ($aSem !== $bSem) {
        return $aSem <=> $bSem;
    }
    return strcmp(
        (string)($a['yonalish_label'] ?? ''),
        (string)($b['yonalish_label'] ?? '')
    );
});

$mergeAcademicYearOptionsMap = [];
$mergeTalimShakliOptionsMap = [];
$mergeKursOptionsMap = [];
$mergeSemestrNumOptionsMap = [];
foreach ($mergeCards as $card) {
    $kirishYili = trim((string)($card['kirish_yili'] ?? ''));
    if ($kirishYili !== '' && !isset($mergeAcademicYearOptionsMap[$kirishYili])) {
        $mergeAcademicYearOptionsMap[$kirishYili] = [
            'value' => $kirishYili,
            'label' => $academicYearLabel($kirishYili),
        ];
    }

    $talimShakliId = (int)($card['talim_shakli_id'] ?? 0);
    $talimShakliName = trim((string)($card['talim_shakli_name'] ?? ''));
    if ($talimShakliId > 0 && !isset($mergeTalimShakliOptionsMap[$talimShakliId])) {
        $mergeTalimShakliOptionsMap[$talimShakliId] = [
            'id' => $talimShakliId,
            'name' => $talimShakliName !== '' ? $talimShakliName : 'Ta\'lim shakli belgilanmagan',
        ];
    }

    $kurs = (int)($card['kurs'] ?? 0);
    if ($kurs > 0 && !isset($mergeKursOptionsMap[$kurs])) {
        $mergeKursOptionsMap[$kurs] = [
            'value' => $kurs,
            'label' => $kurs . '-kurs',
        ];
    }

    $semestrNum = (int)($card['semestr_num'] ?? 0);
    if ($semestrNum > 0 && !isset($mergeSemestrNumOptionsMap[$semestrNum])) {
        $mergeSemestrNumOptionsMap[$semestrNum] = [
            'value' => $semestrNum,
            'label' => $semestrNum . '-semestr',
        ];
    }
}

$mergeAcademicYearOptions = array_values($mergeAcademicYearOptionsMap);
usort($mergeAcademicYearOptions, static function (array $a, array $b): int {
    return strcmp((string)($a['value'] ?? ''), (string)($b['value'] ?? ''));
});
$mergeTalimShakliOptions = array_values($mergeTalimShakliOptionsMap);
usort($mergeTalimShakliOptions, static function (array $a, array $b): int {
    return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
});
$mergeKursOptions = array_values($mergeKursOptionsMap);
usort($mergeKursOptions, static function (array $a, array $b): int {
    return ((int)($a['value'] ?? 0)) <=> ((int)($b['value'] ?? 0));
});
$mergeSemestrNumOptions = array_values($mergeSemestrNumOptionsMap);
usort($mergeSemestrNumOptions, static function (array $a, array $b): int {
    return ((int)($a['value'] ?? 0)) <=> ((int)($b['value'] ?? 0));
});

$mergeFilterFakultetOptions = array_values($mergeFilterFakultetMap);
usort($mergeFilterFakultetOptions, static function (array $a, array $b): int {
    return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
});
$mergeFilterYonalishOptions = array_values($mergeFilterYonalishMap);
usort($mergeFilterYonalishOptions, static function (array $a, array $b): int {
    $aName = (string)($a['name'] ?? '');
    $bName = (string)($b['name'] ?? '');
    $cmp = strcmp($aName, $bName);
    if ($cmp !== 0) {
        return $cmp;
    }
    return strcmp((string)($a['kirish_yili'] ?? ''), (string)($b['kirish_yili'] ?? ''));
});
$mergeFilterSemestrOptions = array_values($mergeFilterSemestrMap);
usort($mergeFilterSemestrOptions, static function (array $a, array $b): int {
    $aSem = (int)($a['semestr_num'] ?? 0);
    $bSem = (int)($b['semestr_num'] ?? 0);
    if ($aSem !== $bSem) {
        return $aSem <=> $bSem;
    }
    $aName = (string)($a['yonalish_name'] ?? '');
    $bName = (string)($b['yonalish_name'] ?? '');
    $cmp = strcmp($aName, $bName);
    if ($cmp !== 0) {
        return $cmp;
    }
    return strcmp((string)($a['kirish_yili'] ?? ''), (string)($b['kirish_yili'] ?? ''));
});

// Izoh: Saqlangan qo'lda biriktirilgan guruhlar ro'yxati.
$mergeSummaryRows = [];
$mergeSummaryRes = $db->query("
        SELECT
            bg.semestr_id,
            s.semestr AS semestr_num,
            bg.fan_id,
            f.fan_code,
            f.fan_name,
            bg.guruh_id,
            bg.talabalar_soni,
            y.name AS yonalish_name,
            g.guruh_nomer
        FROM chet_tili_biriktirilgan_guruhlar bg
        JOIN semestrlar s ON s.id = bg.semestr_id
        JOIN fanlar f ON f.id = bg.fan_id
        JOIN guruhlar g ON g.id = bg.guruh_id
        JOIN yonalishlar y ON y.id = bg.yonalish_id
        ORDER BY s.semestr, f.fan_name, f.fan_code, y.name, g.guruh_nomer
    ");
if ($mergeSummaryRes) {
    $summaryMap = [];
    while ($row = mysqli_fetch_assoc($mergeSummaryRes)) {
        $semestrNum = (int)($row['semestr_num'] ?? 0);
        $fanName = trim((string)($row['fan_name'] ?? ''));
        $languageKey = $normalizeLanguageName($fanName);
        if ($semestrNum <= 0 || $languageKey === '') {
            continue;
        }

        $summaryKey = $semestrNum . '|' . $languageKey;
        if (!isset($summaryMap[$summaryKey])) {
            $summaryMap[$summaryKey] = [
                'semestr_id' => (int)($row['semestr_id'] ?? 0),
                'semestr_num' => $semestrNum,
                'fan_id' => (int)($row['fan_id'] ?? 0),
                'fan_code' => trim((string)($row['fan_code'] ?? '')),
                'fan_name' => $fanName,
                'fan_ids' => [],
                'fan_variants' => [],
                'guruh_ids' => [],
                'guruhlar' => [],
                'talabalar_soni' => 0,
            ];
        }

        $fanId = (int)($row['fan_id'] ?? 0);
        if ($fanId > 0) {
            $summaryMap[$summaryKey]['fan_ids'][$fanId] = true;
        }

        $variantText = trim((string)($row['fan_code'] ?? ''));
        $variantName = trim((string)($row['fan_name'] ?? ''));
        if ($variantName !== '') {
            $variantText = $variantText !== '' ? ($variantText . ' - ' . $variantName) : $variantName;
        }
        if ($variantText !== '') {
            $summaryMap[$summaryKey]['fan_variants'][$variantText] = true;
        }

        $guruhId = (int)($row['guruh_id'] ?? 0);
        if ($guruhId > 0) {
            $summaryMap[$summaryKey]['guruh_ids'][$guruhId] = true;
        }

        $guruhText = trim((string)($row['yonalish_name'] ?? ''));
        $guruhNomer = trim((string)($row['guruh_nomer'] ?? ''));
        if ($guruhText !== '' || $guruhNomer !== '') {
            $fullText = $guruhText;
            if ($guruhNomer !== '') {
                $fullText = $fullText !== '' ? ($fullText . ' / ' . $guruhNomer) : $guruhNomer;
            }
            if ($fullText !== '') {
                $summaryMap[$summaryKey]['guruhlar'][$fullText] = true;
            }
        }

        $summaryMap[$summaryKey]['talabalar_soni'] += (int)($row['talabalar_soni'] ?? 0);
    }

    foreach ($summaryMap as $item) {
        $guruhIds = array_map('intval', array_keys($item['guruh_ids']));
        sort($guruhIds);
        $sourceGroupCount = count($guruhIds);

        $fanIds = array_map('intval', array_keys($item['fan_ids']));
        sort($fanIds);

        $fanVariants = array_keys($item['fan_variants']);
        sort($fanVariants, SORT_NATURAL | SORT_FLAG_CASE);

        $guruhlar = array_keys($item['guruhlar']);
        sort($guruhlar, SORT_NATURAL | SORT_FLAG_CASE);

        $guruhCount = 0;
        if ($item['talabalar_soni'] > 0) {
            $guruhCount = (int)ceil($item['talabalar_soni'] / 12);
        } elseif (!empty($guruhIds)) {
            $guruhCount = count($guruhIds);
        }
        if ($guruhCount <= 0) {
            $guruhCount = 1;
        }

        $item['fan_ids'] = implode(',', $fanIds);
        $item['fan_variants'] = implode(' | ', $fanVariants);
        $item['guruh_ids'] = implode(',', $guruhIds);
        $item['guruhlar'] = implode(' | ', $guruhlar);
        $item['guruhlar_soni'] = $guruhCount;
        $item['source_guruhlar_soni'] = $sourceGroupCount;
        $mergeSummaryRows[] = $item;
    }

    usort($mergeSummaryRows, static function (array $a, array $b): int {
        $aSem = (int)($a['semestr_num'] ?? 0);
        $bSem = (int)($b['semestr_num'] ?? 0);
        if ($aSem !== $bSem) {
            return $aSem <=> $bSem;
        }
        return strcmp((string)($a['fan_name'] ?? ''), (string)($b['fan_name'] ?? ''));
    });
}

// Izoh: 2-tab uchun bazaviy chet tili fanlari (kafedra_id = 0) va variantlari.
$baseFanOptions = '<option value="">Tanlang</option>';
$baseFanMeta = [];
$baseFanIds = [];
$baseFanRes = $db->query("
        SELECT
            f.id,
            f.fan_code,
            f.fan_name,
            f.semestr_id,
            s.semestr AS semestr_num,
            y.name AS yonalish_name,
            y.kirish_yili
        FROM fanlar f
        JOIN semestrlar s ON s.id = f.semestr_id
        JOIN yonalishlar y ON y.id = s.yonalish_id
        WHERE f.tanlov_fan = 3
          AND (f.kafedra_id = 0 OR f.kafedra_id IS NULL OR f.kafedra_id = '')
        ORDER BY s.semestr, y.name, y.kirish_yili, f.fan_code, f.fan_name
    ");
if ($baseFanRes) {
    while ($row = mysqli_fetch_assoc($baseFanRes)) {
        $baseFanId = (int)($row['id'] ?? 0);
        $semestrId = (int)($row['semestr_id'] ?? 0);
        $semestrNum = (int)($row['semestr_num'] ?? 0);
        if ($baseFanId <= 0 || $semestrId <= 0 || $semestrNum <= 0) {
            continue;
        }

        $code = trim((string)($row['fan_code'] ?? ''));
        $name = trim((string)($row['fan_name'] ?? ''));
        $yonalishName = trim((string)($row['yonalish_name'] ?? ''));
        $kirishYili = trim((string)($row['kirish_yili'] ?? ''));

        $label = $code . ' - ' . $name;
        if ($yonalishName !== '') {
            $label .= ' (' . $yonalishName;
            if ($kirishYili !== '') {
                $label .= ' - ' . $kirishYili;
            }
            $label .= ', ' . $semestrNum . '-semestr)';
        }

        $baseFanOptions .= '<option value="' . $baseFanId . '">' . htmlspecialchars($label) . '</option>';
        $baseFanMeta[$baseFanId] = [
            'id' => $baseFanId,
            'label' => $label,
            'fan_code' => $code,
            'fan_name' => $name,
            'semestr_id' => $semestrId,
            'semestr_num' => $semestrNum,
        ];
        $baseFanIds[] = $baseFanId;
    }
}

$variantFansByBase = [];
if (count($baseFanIds) > 0) {
    $idsSql = implode(',', array_map('intval', array_unique($baseFanIds)));
    $variantRes = $db->query("
            SELECT
                ir.base_fan_id,
                vf.id AS fan_id,
                vf.fan_name,
                vf.fan_code,
                k.name AS kafedra_name
            FROM ishchi_oquv_reja ir
            JOIN ishchi_oquv_reja_variants iv ON iv.ishchi_reja_id = ir.id
            JOIN fanlar vf ON vf.id = iv.fan_id
            LEFT JOIN kafedralar k ON k.id = vf.kafedra_id
            WHERE ir.base_fan_id IN ($idsSql)
            ORDER BY ir.base_fan_id, vf.fan_name, vf.id
        ");
    if ($variantRes) {
        $seenVariant = [];
        while ($row = mysqli_fetch_assoc($variantRes)) {
            $baseFanId = (int)($row['base_fan_id'] ?? 0);
            $fanId = (int)($row['fan_id'] ?? 0);
            if ($baseFanId <= 0 || $fanId <= 0) {
                continue;
            }
            $key = $baseFanId . '|' . $fanId;
            if (isset($seenVariant[$key])) {
                continue;
            }
            $seenVariant[$key] = true;

            if (!isset($variantFansByBase[$baseFanId])) {
                $variantFansByBase[$baseFanId] = [];
            }
            $label = trim((string)($row['fan_name'] ?? ''));
            $kafedraName = trim((string)($row['kafedra_name'] ?? ''));
            if ($kafedraName !== '') {
                $label .= ' (' . $kafedraName . ')';
            }
            $variantFansByBase[$baseFanId][] = [
                'id' => $fanId,
                'label' => $label,
            ];
        }
    }
}

// Izoh: Fallback - agar ishchi_oquv_reja_variants bo'sh bo'lsa ham, bir xil fan_code+semestrdagi variantlarni olamiz.
foreach ($baseFanMeta as $baseFanId => $baseMeta) {
    $baseFanId = (int)$baseFanId;
    $semestrId = (int)($baseMeta['semestr_id'] ?? 0);
    $baseCode = trim((string)($baseMeta['fan_code'] ?? ''));
    if ($baseFanId <= 0 || $semestrId <= 0 || $baseCode === '') {
        continue;
    }

    $safeCode = addslashes($baseCode);
    $fallbackRes = $db->query("
            SELECT
                vf.id AS fan_id,
                vf.fan_name,
                k.name AS kafedra_name
            FROM fanlar vf
            LEFT JOIN kafedralar k ON k.id = vf.kafedra_id
            WHERE vf.tanlov_fan = 3
              AND vf.semestr_id = $semestrId
              AND vf.fan_code = '$safeCode'
              AND vf.kafedra_id > 0
              AND vf.id <> $baseFanId
            ORDER BY vf.fan_name, vf.id
        ");
    if (!$fallbackRes) {
        continue;
    }

    $seenIds = [];
    if (isset($variantFansByBase[$baseFanId])) {
        foreach ($variantFansByBase[$baseFanId] as $item) {
            $seenIds[(int)($item['id'] ?? 0)] = true;
        }
    } else {
        $variantFansByBase[$baseFanId] = [];
    }

    while ($row = mysqli_fetch_assoc($fallbackRes)) {
        $fanId = (int)($row['fan_id'] ?? 0);
        if ($fanId <= 0 || isset($seenIds[$fanId])) {
            continue;
        }
        $seenIds[$fanId] = true;

        $label = trim((string)($row['fan_name'] ?? ''));
        $kafedraName = trim((string)($row['kafedra_name'] ?? ''));
        if ($kafedraName !== '') {
            $label .= ' (' . $kafedraName . ')';
        }
        $variantFansByBase[$baseFanId][] = [
            'id' => $fanId,
            'label' => $label,
        ];
    }
}

// Izoh: Semestr -> yo'nalish va guruhlar xaritasi (2-tabda ko'p yo'nalish tanlash uchun).
$semestrOptionsByNum = [];
$groupsBySemestr = [];
$semestrRes = $db->query("
       SELECT
    s.id AS semestr_id,
    s.semestr AS semestr_num,
    y.id AS yonalish_id,
    y.fakultet_id,
    y.name AS yonalish_name,
    y.kirish_yili,
    g.id AS guruh_id,
    g.guruh_nomer,
    g.soni
        FROM semestrlar s
        JOIN yonalishlar y ON y.id = s.yonalish_id
        LEFT JOIN guruhlar g ON g.yonalish_id = y.id
        ORDER BY s.semestr, y.name, y.kirish_yili, g.guruh_nomer
    ");
if ($semestrRes) {
    $seenSemestr = [];
    while ($row = mysqli_fetch_assoc($semestrRes)) {
        $semestrId = (int)($row['semestr_id'] ?? 0);
        $semestrNum = (int)($row['semestr_num'] ?? 0);
        $yonalishId = (int)($row['yonalish_id'] ?? 0);
        if ($semestrId <= 0 || $semestrNum <= 0 || $yonalishId <= 0) {
            continue;
        }

        $yonalishName = trim((string)($row['yonalish_name'] ?? ''));
        $kirishYili = trim((string)($row['kirish_yili'] ?? ''));
        $semestrLabel = $yonalishName;
        if ($kirishYili !== '') {
            $semestrLabel .= ' - ' . $kirishYili;
        }
        $semestrLabel .= ' - ' . $semestrNum . '-semestr';

        if (!isset($seenSemestr[$semestrId])) {
            $seenSemestr[$semestrId] = true;
            if (!isset($semestrOptionsByNum[$semestrNum])) {
                $semestrOptionsByNum[$semestrNum] = [];
            }
            $semestrOptionsByNum[$semestrNum][] = [
                'id' => $semestrId,
                'label' => $semestrLabel,
                'yonalish_id' => $yonalishId,
                'fakultet_id' => (int)($row['fakultet_id'] ?? 0),
                'semestr_num' => $semestrNum,
            ];
        }

        $guruhId = (int)($row['guruh_id'] ?? 0);
        if ($guruhId <= 0) {
            continue;
        }
        if (!isset($groupsBySemestr[$semestrId])) {
            $groupsBySemestr[$semestrId] = [];
        }
        $groupsBySemestr[$semestrId][] = [
            'id' => $guruhId,
            'name' => trim((string)($row['guruh_nomer'] ?? '')),
            'size' => (int)($row['soni'] ?? 0),
            'yonalish_id' => $yonalishId,
            'yonalish_label' => $yonalishName . ($kirishYili !== '' ? ' - ' . $kirishYili : ''),
        ];
    }
}

// Izoh: Oldin saqlangan talab qiymatlarini matritsaga prefilling qilish uchun.
$talabValues = [];
$talabRes = $db->query("SELECT semestr_id, guruh_id, fan_id, talabalar_soni FROM chet_tili_talablar");
if ($talabRes) {
    while ($row = mysqli_fetch_assoc($talabRes)) {
        $semestrId = (int)($row['semestr_id'] ?? 0);
        $guruhId = (int)($row['guruh_id'] ?? 0);
        $fanId = (int)($row['fan_id'] ?? 0);
        if ($semestrId <= 0 || $guruhId <= 0 || $fanId <= 0) {
            continue;
        }
        if (!isset($talabValues[$semestrId])) {
            $talabValues[$semestrId] = [];
        }
        if (!isset($talabValues[$semestrId][$guruhId])) {
            $talabValues[$semestrId][$guruhId] = [];
        }
        $talabValues[$semestrId][$guruhId][$fanId] = (int)($row['talabalar_soni'] ?? 0);
    }
}

// Izoh: Biriktirish jadvali uchun batafsil ma'lumotlar (variantlar + guruhlar kesimida taqsimot).
foreach ($guruhRows as $rowIndex => $row) {
    $semestrIds = [];
    $semestrRaw = trim((string)($row['semestr_ids'] ?? ''));
    if ($semestrRaw !== '') {
        foreach (explode(',', $semestrRaw) as $part) {
            $sid = (int)trim($part);
            if ($sid > 0) {
                $semestrIds[$sid] = true;
            }
        }
    }
    $semestrIds = array_map('intval', array_keys($semestrIds));
    if (count($semestrIds) === 0) {
        $guruhRows[$rowIndex]['detail_variant_lines'] = [];
        $guruhRows[$rowIndex]['detail_group_lines'] = [];
        $guruhRows[$rowIndex]['detail_group_count'] = 0;
        $guruhRows[$rowIndex]['detail_total_students'] = 0;
        continue;
    }

    $sourceFanIds = [];
    $sourceRaw = trim((string)($row['source_fan_ids'] ?? ''));
    if ($sourceRaw !== '') {
        foreach (explode(',', $sourceRaw) as $part) {
            $fid = (int)trim($part);
            if ($fid > 0) {
                $sourceFanIds[$fid] = true;
            }
        }
    }

    // Izoh: source_fan_ids bo'sh bo'lsa ham fan_code + semestr bo'yicha variantlarni topamiz.
    if (count($sourceFanIds) === 0) {
        $safeCode = addslashes((string)($row['fan_code'] ?? ''));
        $semestrSql = implode(',', $semestrIds);
        if ($safeCode !== '' && $semestrSql !== '') {
            $fallbackVariantRes = $db->query("
                    SELECT id
                    FROM fanlar
                    WHERE tanlov_fan = 3
                      AND kafedra_id > 0
                      AND fan_code = '$safeCode'
                      AND semestr_id IN ($semestrSql)
                ");
            if ($fallbackVariantRes) {
                while ($variantRow = mysqli_fetch_assoc($fallbackVariantRes)) {
                    $fid = (int)($variantRow['id'] ?? 0);
                    if ($fid > 0) {
                        $sourceFanIds[$fid] = true;
                    }
                }
            }
        }
    }

    $fanIds = array_map('intval', array_keys($sourceFanIds));
    if (count($fanIds) === 0) {
        $guruhRows[$rowIndex]['detail_variant_lines'] = [];
        $guruhRows[$rowIndex]['detail_group_lines'] = [];
        $guruhRows[$rowIndex]['detail_group_count'] = 0;
        $guruhRows[$rowIndex]['detail_total_students'] = 0;
        continue;
    }

    $fanSql = implode(',', $fanIds);
    $semestrSql = implode(',', $semestrIds);

    $fanLabelById = [];
    $fanRes = $db->query("
            SELECT f.id, f.fan_name, k.name AS kafedra_name
            FROM fanlar f
            LEFT JOIN kafedralar k ON k.id = f.kafedra_id
            WHERE f.id IN ($fanSql)
            ORDER BY f.fan_name, f.id
        ");
    if ($fanRes) {
        while ($fanRow = mysqli_fetch_assoc($fanRes)) {
            $fanId = (int)($fanRow['id'] ?? 0);
            if ($fanId <= 0) {
                continue;
            }
            $label = trim((string)($fanRow['fan_name'] ?? ''));
            $kafedraName = trim((string)($fanRow['kafedra_name'] ?? ''));
            if ($kafedraName !== '') {
                $label .= ' (' . $kafedraName . ')';
            }
            $fanLabelById[$fanId] = $label;
        }
    }

    $variantTotalByFan = [];
    $variantTotalRes = $db->query("
            SELECT fan_id, SUM(talabalar_soni) AS total
            FROM chet_tili_talablar
            WHERE semestr_id IN ($semestrSql)
              AND fan_id IN ($fanSql)
            GROUP BY fan_id
        ");
    if ($variantTotalRes) {
        while ($totalRow = mysqli_fetch_assoc($variantTotalRes)) {
            $fanId = (int)($totalRow['fan_id'] ?? 0);
            if ($fanId <= 0) {
                continue;
            }
            $variantTotalByFan[$fanId] = (int)($totalRow['total'] ?? 0);
        }
    }

    $variantLines = [];
    $detailTotalStudents = 0;
    foreach ($fanIds as $fanId) {
        $label = $fanLabelById[$fanId] ?? ('Fan #' . $fanId);
        $total = (int)($variantTotalByFan[$fanId] ?? 0);
        $detailTotalStudents += $total;
        $variantLines[] = $label . ' - ' . $total . ' ta';
    }

    $groupMap = [];
    $groupRes = $db->query("
            SELECT
                t.guruh_id,
                g.guruh_nomer,
                g.soni AS guruh_jami,
                y.name AS yonalish_name,
                y.kirish_yili,
                t.fan_id,
                SUM(t.talabalar_soni) AS total
            FROM chet_tili_talablar t
            JOIN guruhlar g ON g.id = t.guruh_id
            JOIN yonalishlar y ON y.id = g.yonalish_id
            WHERE t.semestr_id IN ($semestrSql)
              AND t.fan_id IN ($fanSql)
            GROUP BY
                t.guruh_id, g.guruh_nomer, g.soni,
                y.name, y.kirish_yili, t.fan_id
            ORDER BY y.name, y.kirish_yili, g.guruh_nomer, t.fan_id
        ");
    if ($groupRes) {
        while ($groupRow = mysqli_fetch_assoc($groupRes)) {
            $guruhId = (int)($groupRow['guruh_id'] ?? 0);
            $fanId = (int)($groupRow['fan_id'] ?? 0);
            if ($guruhId <= 0 || $fanId <= 0) {
                continue;
            }

            if (!isset($groupMap[$guruhId])) {
                $yonalishLabel = trim((string)($groupRow['yonalish_name'] ?? ''));
                $yil = trim((string)($groupRow['kirish_yili'] ?? ''));
                if ($yil !== '') {
                    $yonalishLabel .= ' - ' . $yil;
                }

                $groupMap[$guruhId] = [
                    'title' => $yonalishLabel . ' / ' . trim((string)($groupRow['guruh_nomer'] ?? '-')),
                    'size' => (int)($groupRow['guruh_jami'] ?? 0),
                    'sum' => 0,
                    'parts' => [],
                ];
            }

            $count = (int)($groupRow['total'] ?? 0);
            $groupMap[$guruhId]['sum'] += $count;
            $groupMap[$guruhId]['parts'][] = ($fanLabelById[$fanId] ?? ('Fan #' . $fanId)) . ': ' . $count;
        }
    }

    $groupLines = [];
    foreach ($groupMap as $groupData) {
        $groupLines[] = $groupData['title']
            . ' - '
            . implode(', ', $groupData['parts'])
            . ' (yig\'indi: ' . (int)$groupData['sum'] . ' / ' . (int)$groupData['size'] . ')';
    }

    $guruhRows[$rowIndex]['detail_variant_lines'] = $variantLines;
    $guruhRows[$rowIndex]['detail_group_lines'] = $groupLines;
    $guruhRows[$rowIndex]['detail_group_count'] = count($groupMap);
    $guruhRows[$rowIndex]['detail_total_students'] = $detailTotalStudents;
}

?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <title>Chet tili fanlari</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .tab-header {
            display: flex;
            gap: 10px;
            margin-bottom: 16px;
        }

        .tab-btn {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #0f172a;
            padding: 8px 14px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .tab-btn.active {
            background: #16a34a;
            color: #fff;
            border-color: #16a34a;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .top-filters-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(220px, 1fr));
            gap: 12px;
        }

        .merge-top-filters-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(180px, 1fr));
            gap: 12px;
        }

        .top-filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .merge-filter-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-top: 12px;
            flex-wrap: wrap;
        }

        .merge-filter-note {
            color: #334155;
            font-size: 13px;
            font-weight: 600;
        }

        .merge-filter-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .guruh-list-empty {
            padding: 14px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            color: #64748b;
        }

        .semestr-row {
            display: flex;
            gap: 8px;
            margin-bottom: 8px;
            align-items: center;
        }

        .semestr-row .form-control {
            flex: 1;
        }

        .semestr-row-actions {
            display: flex;
            gap: 6px;
        }

        .matrix-help {
            margin-top: 12px;
            color: #334155;
            font-size: 13px;
        }

        .taqsimot-summary.ok {
            color: #15803d;
            font-weight: 600;
        }

        .taqsimot-summary.err {
            color: #dc2626;
            font-weight: 600;
        }

        .detail-list {
            margin: 0;
            padding-left: 18px;
        }

        .detail-list li {
            margin: 2px 0;
            color: #334155;
            font-size: 13px;
        }

        .detail-meta {
            color: #64748b;
            font-size: 12px;
            margin-bottom: 6px;
        }

        .detail-toggle {
            margin-top: 4px;
        }

        .detail-toggle>summary {
            cursor: pointer;
            color: #0f766e;
            font-size: 12px;
            user-select: none;
            list-style: none;
        }

        .detail-toggle>summary::-webkit-details-marker {
            display: none;
        }

        .detail-scroll {
            max-height: 180px;
            overflow: auto;
            margin-top: 6px;
            padding-right: 6px;
        }

        .merge-direction-list {
            display: grid;
            gap: 16px;
        }

        .merge-direction-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            padding: 16px;
        }

        .merge-direction-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .merge-direction-title {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .merge-direction-meta {
            margin-top: 4px;
            color: #64748b;
            font-size: 12px;
        }

        .merge-card-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .merge-selection-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #ecfdf5;
            color: #166534;
            font-weight: 700;
            font-size: 12px;
        }

        .merge-row-selected {
            background: #f0fdf4;
        }

        .merge-row-checked {
            accent-color: #16a34a;
        }

        .merge-card-hidden {
            display: none !important;
        }

        @media (max-width: 1100px) {
            .top-filters-grid {
                grid-template-columns: repeat(2, minmax(220px, 1fr));
            }

            .merge-top-filters-grid {
                grid-template-columns: repeat(2, minmax(220px, 1fr));
            }
        }

        @media (max-width: 700px) {
            .top-filters-grid {
                grid-template-columns: 1fr;
            }

            .merge-top-filters-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-navbar">
                <h1>Chet tili fanlari</h1>
            </header>
            <div class="content-container">
                <!-- Izoh: Chet tili fanlari uchun 2 ta tab -->
                <div class="tab-header">
                    <?php if ($forcedTabId === ''): ?>
                        <button type="button" class="tab-btn active" data-tab="chet-tab-yaratish">Chet tili fanini yaratish</button>
                        <button type="button" class="tab-btn" data-tab="chet-tab-biriktirish">Chet tilini biriktirish</button>
                    <?php elseif ($forcedTabId === 'chet-tab-yaratish'): ?>
                        <button type="button" class="tab-btn active" data-tab="chet-tab-yaratish">Chet tili fanini yaratish</button>
                    <?php elseif ($forcedTabId === 'chet-tab-biriktirish'): ?>
                        <button type="button" class="tab-btn active" data-tab="chet-tab-biriktirish">Chet tilini biriktirish</button>
                    <?php else: ?>
                        <button type="button" class="tab-btn active" data-tab="chet-tab-guruh-birlashtirish">Chet tili guruhlarini birlashtirish</button>
                    <?php endif; ?>
                </div>

                <div id="chet-tab-yaratish" class="tab-content <?php echo ($forcedTabId === '' || $forcedTabId === 'chet-tab-yaratish') ? 'active' : ''; ?>">
                    <form id="chetTiliYaratishForm" class="card">
                        <h3 class="section-title">Umumiy ma'lumot</h3>
                        <div class="top-filters-grid">
                            <div class="form-group">
                                <label>Fakultet filtri</label>
                                <select class="form-control" id="chetFakultetFilter">
                                    <option value="">Barcha fakultetlar</option>
                                    <?php foreach ($fakultetlar as $f): ?>
                                        <option value="<?= (int)$f['id'] ?>"><?= $h($f['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Yo'nalish filtri</label>
                                <select class="form-control" id="chetYonalishFilter">
                                    <option value="">Yo'nalishni tanlang</option>
                                    <?php foreach ($filterYonalishlar as $y): ?>
                                        <option
                                            value="<?= (int)$y['id'] ?>"
                                            data-fakultet-id="<?= (int)$y['fakultet_id'] ?>">
                                            <?= $h((string)$y['name'] . (!empty($y['kirish_yili']) ? ' - ' . (string)$y['kirish_yili'] : '')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Semestr</label>
                                <select class="form-control" name="semestr_id" id="chetSemestrSelect" required>
                                    <option value="">Semestrni tanlang</option>
                                    <?php foreach ($semestrlar as $s):
                                        $short = $makeShortCode((string)($s['yonalish_name'] ?? ''));
                                        $darajaRaw = trim((string)($s['akademik_daraja_name'] ?? ''));
                                        $daraja = function_exists('mb_strtolower')
                                            ? (string)@mb_strtolower($darajaRaw, 'UTF-8')
                                            : strtolower($darajaRaw);
                                        $darajaPrefix = '';
                                        if (strpos($daraja, 'magistr') !== false) {
                                            $darajaPrefix = 'M ';
                                        } elseif (strpos($daraja, 'bakalavr') !== false) {
                                            $darajaPrefix = 'B ';
                                        }
                                        $fakultetId = (int)($s['yonalish_fakultet_id'] ?? ($s['fakultet_id'] ?? 0));
                                        $yonalishId = (int)($s['yonalish_id'] ?? 0);
                                    ?>
                                        <option
                                            value="<?= (int)$s['id'] ?>"
                                            data-fakultet-id="<?= $fakultetId ?>"
                                            data-yonalish-id="<?= $yonalishId ?>">
                                            <?= $h($darajaPrefix . $short . '_' . ($s['kirish_yili'] ?? '') . ' - ' . ($s['semestr'] ?? '') . '-semestr') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="top-filter-actions">
                            <button type="button" class="btn btn-primary btn-sm" id="applyChetTopFiltersBtn">
                                <i class="fas fa-filter"></i> Filtrlash
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" id="resetChetTopFiltersBtn">
                                <i class="fas fa-rotate-left"></i> Tozalash
                            </button>
                        </div>

                        <div id="chetRejaWrapper">
                            <div class="reja-card" data-index="0">
                                <div class="tanlovfan-actions">
                                    <input type="hidden" name="tanlov_fan[0]" value="3" class="tanlov-input">
                                    <input type="hidden" name="tanlov_fan_code[0]" class="chet-code-input">
                                    <input type="hidden" name="tanlov_fan_base_nomi[0]" class="chet-base-input">
                                    <button type="button" class="btn btn-outline btn-sm fanTypeToggle active" disabled>
                                        <i class="fas fa-check-circle"></i> Chet tili
                                    </button>
                                </div>

                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label>Chet tili (kod + nomi)</label>
                                        <select class="form-control chet-tili-select" name="tanlov_fan_base[0]" required>
                                            <option value="">Tanlang</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="tanlov-fan-item" data-tanlov-index="0">
                                    <div class="form-grid-2">
                                        <div class="form-group">
                                            <label>Chet tili nomi</label>
                                            <input type="text" class="form-control" name="tanlov_fan_nomi[0][]" placeholder="Masalan: English 1" required>
                                        </div>

                                        <div class="form-group">
                                            <label>Kafedra</label>
                                            <select class="form-control" name="tanlov_kafedra_id[0][]" required>
                                                <option value="">Tanlang</option>
                                                <?php foreach ($kafedralar as $k): ?>
                                                    <option value="<?= $k['id'] ?>">
                                                        <?= htmlspecialchars($k['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="tanlov-fan-actions mb-3">
                                        <button type="button" class="btn btn-outline btn-sm addChetTiliFan">
                                            <i class="fas fa-plus"></i> Yana variant
                                        </button>

                                        <button type="button" class="btn btn-danger btn-sm removeChetTiliFan">
                                            <i class="fas fa-times"></i> O'chirish
                                        </button>
                                    </div>
                                </div>

                                <div class="reja-actions">
                                    <button type="button" class="btn btn-outline btn-sm addChetReja">
                                        <i class="fas fa-plus"></i> Yana fan
                                    </button>

                                    <button type="button" class="btn btn-danger btn-sm removeChetReja">
                                        <i class="fas fa-times"></i> O'chirish
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group mt-3">
                            <label>Izoh</label>
                            <textarea class="form-control"
                                name="izoh"
                                rows="3"
                                placeholder="O'quv reja bo'yicha umumiy izoh..."></textarea>
                        </div>
                        <div class="form-actions mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Saqlash
                            </button>
                        </div>
                    </form>
                </div>

                <div id="chet-tab-biriktirish" class="tab-content <?php echo $forcedTabId === 'chet-tab-biriktirish' ? 'active' : ''; ?>">
                    <form id="chetBiriktirishForm" class="card">
                        <h3 class="section-title">Chet tilini guruhlar kesimida biriktirish</h3>
                        <div class="top-filters-grid">
                            <div class="form-group">
                                <label>Fakultet filtri</label>
                                <select class="form-control" id="biriktirishFakultetFilter">
                                    <option value="">Barcha fakultetlar</option>
                                    <?php foreach ($fakultetlar as $f): ?>
                                        <option value="<?= (int)$f['id'] ?>"><?= $h($f['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>Yo'nalish + semestr</label>
                                <div id="semestrRowWrapper"></div>
                                <div class="dars-soat-actions mt-2">
                                    <button type="button" class="btn btn-outline btn-sm" id="addSemestrRowBtn">
                                        <i class="fas fa-plus"></i> Yana yo'nalish
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Bazaviy chet tili fan</label>
                                <select class="form-control" id="biriktirishBaseFanSelect" required>
                                    <option value="">Avval yo'nalish+semestr tanlang</option>
                                </select>
                            </div>
                        </div>
                        <div id="taqsimotMatrixWrapper" class="table-responsive mt-3">
                            <div class="guruh-list-empty">Avval bazaviy fan va yo'nalish+semestr tanlang.</div>
                        </div>

                        <div class="form-actions mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Biriktirishni saqlash
                            </button>
                        </div>
                    </form>

                    <div class="table-container mt-4">
                        <div class="table-header">
                            <div class="table-title">
                                <h3>Biriktirilgan chet tili fanlari</h3>
                                <span class="badge"><?php echo count($guruhRows); ?> ta</span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Fan kodi</th>
                                        <th>Fan nomi</th>
                                        <th>Yo'nalishlar</th>
                                        <th>Semestr</th>
                                        <th>Variantlar (jami)</th>
                                        <th>Guruhlar kesimi</th>
                                        <th>Yaratilgan sana</th>
                                        <th>Harakatlar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($guruhRows) === 0): ?>
                                        <tr>
                                            <td colspan="8">Biriktirilgan fanlar topilmadi</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($guruhRows as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['fan_code']); ?></td>
                                                <td><?php echo htmlspecialchars($row['fan_name']); ?></td>
                                                <td>
                                                    <?php
                                                    $yonList = $row['yonalishlar'] ?? [];
                                                    $yonText = count($yonList) > 0 ? implode(' | ', $yonList) : '-';
                                                    echo htmlspecialchars($yonText);
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['semestr_num']); ?></td>
                                                <td>
                                                    <?php
                                                    $variantLines = $row['detail_variant_lines'] ?? [];
                                                    if (count($variantLines) === 0):
                                                    ?>
                                                        <span class="detail-meta">Variant topilmadi</span>
                                                    <?php else: ?>
                                                        <div class="detail-meta">
                                                            Jami talaba: <?php echo (int)($row['detail_total_students'] ?? 0); ?> ta
                                                        </div>
                                                        <details class="detail-toggle">
                                                            <summary>Variantlarni ko'rish</summary>
                                                            <div class="detail-scroll">
                                                                <ul class="detail-list">
                                                                    <?php foreach ($variantLines as $line): ?>
                                                                        <li><?php echo htmlspecialchars($line); ?></li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            </div>
                                                        </details>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $groupLines = $row['detail_group_lines'] ?? [];
                                                    if (count($groupLines) === 0):
                                                    ?>
                                                        <span class="detail-meta">Guruh taqsimoti yo'q</span>
                                                    <?php else: ?>
                                                        <div class="detail-meta">
                                                            Guruhlar soni: <?php echo (int)($row['detail_group_count'] ?? 0); ?> ta
                                                        </div>
                                                        <details class="detail-toggle">
                                                            <summary>Guruhlar taqsimotini ko'rish</summary>
                                                            <div class="detail-scroll">
                                                                <ul class="detail-list">
                                                                    <?php foreach ($groupLines as $line): ?>
                                                                        <li><?php echo htmlspecialchars($line); ?></li>
                                                                    <?php endforeach; ?>
                                                                </ul>
                                                            </div>
                                                        </details>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['create_at']); ?></td>
                                                <td>
                                                    <button
                                                        class="btn btn-sm btn-danger deleteChetTiliBtn"
                                                        data-fan-id="<?php echo (int)$row['fan_id']; ?>"
                                                        data-semestr-num="<?php echo (int)$row['semestr_num']; ?>">
                                                        <i class="fas fa-trash-alt"></i> O'chirish
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>

                <?php if ($forcedTabId === 'chet-tab-guruh-birlashtirish'): ?>
                <div id="chet-tab-guruh-birlashtirish" class="tab-content active">
                    <form id="chetGuruhBirlashtirishForm" class="card">
                        <h3 class="section-title">Chet tili guruhlarini birlashtirish</h3>
                        <div class="detail-meta" style="margin-bottom: 12px;">
                            Yo'nalishlar ichidagi til guruhlarini checkbox orqali tanlang. Har bir belgilangan guruh yuklamada 1 ta kichik guruh hisoblanadi.
                        </div>
                        <div class="card" style="padding: 14px; margin-bottom: 12px; background: #f8fafc;">
                            <h4 class="section-title" style="margin-bottom: 8px;">Umumiy ma'lumot</h4>
                            <div class="detail-meta" style="margin-bottom: 12px;">
                                Til guruhlarini birlashtirish uchun kerakli filtrlardan foydalaning.
                            </div>
                            <div class="merge-top-filters-grid">
                                <div class="form-group">
                                    <label>O'quv yili</label>
                                    <select class="form-control" id="mergeAcademicYearFilter">
                                        <option value="">Barcha o'quv yillari</option>
                                        <?php foreach ($mergeAcademicYearOptions as $item): ?>
                                            <option value="<?php echo $h((string)($item['value'] ?? '')); ?>">
                                                <?php echo $h((string)($item['label'] ?? '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Yo'nalish(lar)</label>
                                    <select class="form-control" id="mergeYonalishFilter" multiple="multiple" data-placeholder="Yo'nalish(lar)ni tanlang">
                                        <?php foreach ($mergeFilterYonalishOptions as $item): ?>
                                            <?php
                                                $label = trim((string)($item['name'] ?? ''));
                                                $kirishYili = trim((string)($item['kirish_yili'] ?? ''));
                                                if ($kirishYili !== '') {
                                                    $label .= ' - ' . $kirishYili;
                                                }
                                            ?>
                                            <option value="<?php echo (int)($item['id'] ?? 0); ?>">
                                                <?php echo $h($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Ta'lim shakli</label>
                                    <select class="form-control" id="mergeTalimShakliFilter">
                                        <option value="">Barcha ta'lim shakllari</option>
                                        <?php foreach ($mergeTalimShakliOptions as $item): ?>
                                            <option value="<?php echo (int)($item['id'] ?? 0); ?>">
                                                <?php echo $h((string)($item['name'] ?? '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Kurs</label>
                                    <select class="form-control" id="mergeKursFilter">
                                        <option value="">Barcha kurslar</option>
                                        <?php foreach ($mergeKursOptions as $item): ?>
                                            <option value="<?php echo (int)($item['value'] ?? 0); ?>">
                                                <?php echo (int)($item['value'] ?? 0); ?>-kurs
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Semestr</label>
                                    <select class="form-control" id="mergeSemestrNumFilter">
                                        <option value="">Barcha semestrlar</option>
                                        <?php foreach ($mergeSemestrNumOptions as $item): ?>
                                            <option value="<?php echo (int)($item['value'] ?? 0); ?>">
                                                <?php echo (int)($item['value'] ?? 0); ?>-semestr
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="merge-filter-actions">
                                <div class="merge-filter-note" id="mergeFilterNote">
                                    <?php echo count($mergeCards); ?> ta yo'nalish mos keldi
                                </div>
                                <div class="merge-filter-buttons">
                                    <button type="button" class="btn btn-primary" id="applyMergeFiltersBtn">
                                        <i class="fas fa-filter"></i> Filtrlash
                                    </button>
                                    <button type="button" class="btn btn-light" id="resetMergeFiltersBtn">
                                        <i class="fas fa-eraser"></i> Tozalash
                                    </button>
                                </div>
                            </div>
                            <div class="matrix-help" style="margin-top: 10px;">
                                Default holatda birinchi <?php echo (int)$mergeVisibleLimit; ?> ta yo'nalish ko'rsatiladi. Qolganlari filtr orqali topiladi.
                            </div>
                        </div>

                        <div class="top-filter-actions" style="justify-content: flex-start; margin-top: 0; margin-bottom: 12px;">
                            <div class="merge-selection-badge">
                                <i class="fas fa-check-square"></i>
                                <span>Tanlangan guruhlar: <strong id="mergeSelectedCountBadge"><?php echo (int)$mergeSelectedCount; ?></strong> ta</span>
                            </div>
                            <div class="merge-selection-badge">
                                <i class="fas fa-users"></i>
                                <span>Talabalar yig'indisi: <strong id="mergeSelectedStudentsBadge"><?php echo (int)$mergeSelectedStudents; ?></strong> ta</span>
                            </div>
                        </div>

                        <div id="mergeFilterEmpty" class="guruh-list-empty" style="display: none; margin-bottom: 12px;">
                            Filtr bo'yicha til guruhlari topilmadi.
                        </div>

                        <div id="chetMergeDirectionList" class="merge-direction-list">
                            <?php if (count($mergeCards) === 0): ?>
                                <div class="guruh-list-empty">Tanlangan yo'nalishlarda chet tili guruhlari topilmadi.</div>
                            <?php else: ?>
                                <?php foreach ($mergeCards as $cardIndex => $card): ?>
                                    <section
                                        class="merge-direction-card <?php echo $cardIndex >= $mergeVisibleLimit ? 'merge-card-hidden' : ''; ?>"
                                        data-fakultet-id="<?php echo (int)($card['fakultet_id'] ?? 0); ?>"
                                        data-semestr-id="<?php echo (int)($card['semestr_id'] ?? 0); ?>"
                                        data-semestr-num="<?php echo (int)($card['semestr_num'] ?? 0); ?>"
                                        data-yonalish-id="<?php echo (int)($card['yonalish_id'] ?? 0); ?>"
                                        data-kirish-yili="<?php echo $h((string)($card['kirish_yili'] ?? '')); ?>"
                                        data-talim-shakli-id="<?php echo (int)($card['talim_shakli_id'] ?? 0); ?>"
                                        data-kurs="<?php echo (int)($card['kurs'] ?? 0); ?>"
                                        data-merge-index="<?php echo (int)$cardIndex; ?>">
                                        <div class="merge-direction-header">
                                            <div>
                                                <div class="merge-direction-title">
                                                    <?php echo $h((string)($card['yonalish_label'] ?? '') . (!empty($card['kirish_yili']) ? ' - ' . (string)$card['kirish_yili'] : '')); ?>
                                                </div>
                                                <div class="merge-direction-meta">
                                                    <?php echo (int)($card['semestr_num'] ?? 0); ?>-semestr
                                                    &middot; <?php echo (int)($card['kurs'] ?? 0); ?>-kurs
                                                    <?php if (!empty($card['talim_shakli_name'])): ?>
                                                        &middot; <?php echo $h((string)$card['talim_shakli_name']); ?>
                                                    <?php endif; ?>
                                                    &middot; <?php echo count($card['rows'] ?? []); ?> ta guruh
                                                    &middot; <?php echo (int)($card['total_students'] ?? 0); ?> ta talaba
                                                </div>
                                                <div class="detail-meta" style="margin-bottom: 0;">
                                                    <?php echo $h((string)($card['fakultet_name'] ?? 'Fakultet belgilanmagan')); ?>
                                                </div>
                                            </div>
                                            <div class="merge-card-actions">
                                                <label class="merge-selection-badge">
                                                    <input
                                                        type="checkbox"
                                                        class="merge-card-select-all"
                                                        <?php echo !empty($card['rows']) && (int)($card['selected_count'] ?? 0) === count($card['rows']) ? 'checked' : ''; ?>>
                                                    Barchasi
                                                </label>
                                            </div>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="data-table">
                                                <thead>
                                                    <tr>
                                                        <th></th>
                                                        <th>Til fani</th>
                                                        <th>Guruh</th>
                                                        <th>Talaba soni</th>
                                                        <th>Holat</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach (($card['rows'] ?? []) as $row): ?>
                                                        <tr class="<?php echo !empty($row['selected']) ? 'merge-row-selected' : ''; ?>">
                                                            <td>
                                                                <input
                                                                    type="checkbox"
                                                                    class="merge-group-checkbox merge-row-checked"
                                                                    data-semestr-id="<?php echo (int)($row['semestr_id'] ?? 0); ?>"
                                                                    data-semestr-num="<?php echo (int)($card['semestr_num'] ?? 0); ?>"
                                                                    data-yonalish-id="<?php echo (int)($row['yonalish_id'] ?? 0); ?>"
                                                                    data-guruh-id="<?php echo (int)($row['guruh_id'] ?? 0); ?>"
                                                                    data-fan-id="<?php echo (int)($row['fan_id'] ?? 0); ?>"
                                                                    data-fan-code="<?php echo $h(strtolower(trim((string)($row['fan_code'] ?? '')))); ?>"
                                                                    data-language-key="<?php echo $h((string)($row['language_key'] ?? '')); ?>"
                                                                    data-merge-key="<?php echo $h((string)($row['merge_row_key'] ?? '')); ?>"
                                                                    data-talabalar-soni="<?php echo (int)($row['talabalar_soni'] ?? 0); ?>"
                                                                    <?php echo !empty($row['selected']) ? 'checked' : ''; ?>>
                                                            </td>
                                                            <td>
                                                                <?php echo $h(trim((string)($row['fan_code'] ?? '')) . ' - ' . trim((string)($row['fan_name'] ?? ''))); ?>
                                                                <?php if (!empty($row['kafedra_name'])): ?>
                                                                    <div class="detail-meta"><?php echo $h((string)$row['kafedra_name']); ?></div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo $h((string)($row['guruh_nomer'] ?? '-')); ?></td>
                                                            <td><?php echo (int)($row['talabalar_soni'] ?? 0); ?></td>
                                                            <td>
                                                                <?php if (!empty($row['selected'])): ?>
                                                                    <span class="maxsus-badge">Biriktirilgan</span>
                                                                <?php else: ?>
                                                                    <span class="detail-meta">Tanlanmagan</span>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </section>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="form-actions mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guruhlarni biriktirish
                            </button>
                        </div>
                    </form>

                    <div class="table-container mt-4">
                        <div class="table-header">
                            <div class="table-title">
                                <h3>Biriktirilgan til guruhlari</h3>
                                <span class="badge"><?php echo count($mergeSummaryRows); ?> ta</span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Fan kodi</th>
                                        <th>Fan nomi</th>
                                        <th>Semestr</th>
                                        <th>Guruhlar soni</th>
                                        <th>Talabalar soni</th>
                                        <th>Biriktirilgan guruhlar</th>
                                        <th>Amallar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($mergeSummaryRows) === 0): ?>
                                        <tr>
                                            <td colspan="7">Biriktirilgan til guruhlari topilmadi</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($mergeSummaryRows as $row): ?>
                                            <tr>
                                                <td><?php echo $h((string)($row['fan_code'] ?? '')); ?></td>
                                                <td>
                                                    <?php echo $h((string)($row['fan_name'] ?? '')); ?>
                                                    <?php $fanVariants = trim((string)($row['fan_variants'] ?? '')); ?>
                                                    <?php if ($fanVariants !== ''): ?>
                                                        <div class="detail-meta">
                                                            Variantlar: <?php echo $h($fanVariants); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo (int)($row['semestr_num'] ?? 0); ?></td>
                                                <td>
                                                    <strong><?php echo (int)($row['guruhlar_soni'] ?? 0); ?></strong>
                                                    <div class="detail-meta">Manba guruh: <?php echo (int)($row['source_guruhlar_soni'] ?? 0); ?></div>
                                                </td>
                                                <td><?php echo (int)($row['talabalar_soni'] ?? 0); ?></td>
                                                <td>
                                                    <?php $mergeDetail = trim((string)($row['guruhlar'] ?? '')); ?>
                                                    <?php if ($mergeDetail !== ''): ?>
                                                        <details class="detail-toggle">
                                                            <summary>Ko'rish</summary>
                                                            <div class="detail-scroll">
                                                                <div class="detail-meta"><?php echo $h($mergeDetail); ?></div>
                                                            </div>
                                                        </details>
                                                    <?php else: ?>
                                                        <span class="detail-meta">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-row">
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline editMergedGroupBtn"
                                                            data-semestr-num="<?php echo (int)($row['semestr_num'] ?? 0); ?>"
                                                            data-language-key="<?php echo $h($normalizeLanguageName((string)($row['fan_name'] ?? ''))); ?>"
                                                            data-fan-ids="<?php echo $h((string)($row['fan_ids'] ?? '')); ?>"
                                                            data-guruh-ids="<?php echo $h((string)($row['guruh_ids'] ?? '')); ?>">
                                                            <i class="fas fa-edit"></i> Tahrirlash
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-danger deleteMergedGroupBtn"
                                                            data-semestr-id="<?php echo (int)($row['semestr_id'] ?? 0); ?>"
                                                            data-fan-ids="<?php echo $h((string)($row['fan_ids'] ?? '')); ?>"
                                                            data-fan-id="<?php echo (int)($row['fan_id'] ?? 0); ?>">
                                                            <i class="fas fa-trash"></i> O'chirish
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="/assets/vendor/select2/css/select2.min.css" rel="stylesheet" />
    <script src="/assets/vendor/jquery/jquery-3.6.0.min.js"></script>
    <script>
        window.jQuery || document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>')
    </script>
    <script src="/assets/vendor/select2/js/select2.min.js"></script>
    <script>
        if (window.jQuery && !window.jQuery.fn.select2) {
            document.write('<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"><\/script>');
        }
    </script>

    <script>
        if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.select2 !== 'function') {
            // Izoh: Hostda select2 yuklanmasa ham sahifa JS'i to'xtab qolmasin.
            window.jQuery.fn.select2 = function() {
                return this;
            };
        }

        const baseFanMeta = <?php echo json_encode($baseFanMeta, JSON_UNESCAPED_UNICODE); ?>;
        const variantFansByBase = <?php echo json_encode($variantFansByBase, JSON_UNESCAPED_UNICODE); ?>;
        const semestrOptionsByNum = <?php echo json_encode($semestrOptionsByNum, JSON_UNESCAPED_UNICODE); ?>;
        const groupsBySemestr = <?php echo json_encode($groupsBySemestr, JSON_UNESCAPED_UNICODE); ?>;
        const talabValues = <?php echo json_encode($talabValues, JSON_UNESCAPED_UNICODE); ?>;
        const mergeFilterFakultetOptions = <?php echo json_encode($mergeFilterFakultetOptions, $jsonFlags); ?>;
        const mergeFilterYonalishOptions = <?php echo json_encode($mergeFilterYonalishOptions, $jsonFlags); ?>;
        const mergeFilterSemestrOptions = <?php echo json_encode($mergeFilterSemestrOptions, $jsonFlags); ?>;
        const mergeVisibleLimit = 30;

        let chetFanIndex = 0;
        let semestrRowIndex = 0;
        const allChetYonalishFilterOptions = [];
        const allChetSemestrFilterOptions = [];

        function triggerSelectRefresh($select) {
            if ($select && $select.length && $select.hasClass('select2-hidden-accessible')) {
                $select.trigger('change.select2');
                return;
            }
            if ($select && $select.length) {
                $select.trigger('change');
            }
        }

        function getSelectedIdWithFallback($select, emptyLabels = []) {
            if (!$select || !$select.length) {
                return '';
            }

            const labels = Array.isArray(emptyLabels) ? emptyLabels : [emptyLabels];
            const placeholders = new Set(
                labels.map(label => String(label || '').trim().toLowerCase()).filter(Boolean)
            );
            const normalize = (value) => String(value || '').trim();
            const isPlaceholder = (text) => {
                const normalized = normalize(text).toLowerCase();
                return normalized === '' || placeholders.has(normalized);
            };

            const directValue = normalize($select.val());
            if (directValue !== '') {
                return directValue;
            }

            try {
                if (typeof $select.select2 === 'function' && $select.data('select2')) {
                    const data = $select.select2('data');
                    if (Array.isArray(data) && data.length > 0) {
                        const dataId = normalize(data[0] && data[0].id);
                        if (dataId !== '') {
                            if ($select.find(`option[value="${dataId}"]`).length > 0) {
                                $select.val(dataId);
                            }
                            return dataId;
                        }
                    }
                }
            } catch (e) {
                // Select2 fallback davom etadi.
            }

            const selectedOption = $select.find('option:selected').first();
            const optionValue = normalize(selectedOption.attr('value'));
            if (optionValue !== '') {
                return optionValue;
            }

            const candidates = [];
            const selectedText = normalize(selectedOption.text());
            if (selectedText !== '') {
                candidates.push(selectedText);
            }

            const rendered = $select.next('.select2-container').find('.select2-selection__rendered').first();
            if (rendered.length) {
                const renderedTitle = normalize(rendered.attr('title'));
                const renderedText = normalize(rendered.text()).replace(/^×\s*/, '').trim();
                if (renderedTitle !== '') candidates.push(renderedTitle);
                if (renderedText !== '') candidates.push(renderedText);
            }

            for (const text of candidates) {
                if (isPlaceholder(text)) {
                    continue;
                }

                let matched = $select.find('option').filter(function() {
                    return normalize($(this).text()) === text;
                }).first();

                if (!matched.length) {
                    matched = $select.find('option').filter(function() {
                        const optionText = normalize($(this).text());
                        return optionText !== '' && (optionText.includes(text) || text.includes(optionText));
                    }).first();
                }

                const matchedValue = normalize(matched.attr('value'));
                if (matchedValue !== '') {
                    $select.val(matchedValue);
                    return matchedValue;
                }
            }

            return '';
        }

        function escapeOptionText(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function cacheChetTopFilterOptions() {
            allChetYonalishFilterOptions.length = 0;
            $('#chetYonalishFilter option').each(function() {
                const value = String($(this).attr('value') || '');
                if (!value) return;
                allChetYonalishFilterOptions.push({
                    value,
                    label: $(this).text(),
                    fakultetId: String($(this).data('fakultet-id') || ''),
                });
            });

            allChetSemestrFilterOptions.length = 0;
            $('#chetSemestrSelect option').each(function() {
                const value = String($(this).attr('value') || '');
                if (!value) return;
                allChetSemestrFilterOptions.push({
                    value,
                    label: $(this).text(),
                    fakultetId: String($(this).data('fakultet-id') || ''),
                    yonalishId: String($(this).data('yonalish-id') || ''),
                });
            });
        }

        function rebuildChetYonalishFilter(preferredValue = '') {
            const selectedFakultet = getSelectedIdWithFallback($('#chetFakultetFilter'), ['Barcha fakultetlar', 'Fakultetni tanlang']);
            const select = $('#chetYonalishFilter');
            const current = String(preferredValue || select.val() || '');

            select.empty().append('<option value="">Yo\'nalishni tanlang</option>');
            allChetYonalishFilterOptions
                .filter((item) => !selectedFakultet || item.fakultetId === selectedFakultet)
                .forEach((item) => {
                    select.append(
                        $('<option>')
                        .attr('value', item.value)
                        .attr('data-fakultet-id', item.fakultetId)
                        .text(item.label)
                    );
                });

            const hasCurrent = current !== '' && select.find(`option[value="${current}"]`).length > 0;
            select.val(hasCurrent ? current : '');
            triggerSelectRefresh(select);
        }

        function rebuildChetSemestrFilter(preferredValue = '') {
            const selectedFakultet = getSelectedIdWithFallback($('#chetFakultetFilter'), ['Barcha fakultetlar', 'Fakultetni tanlang']);
            const selectedYonalish = getSelectedIdWithFallback($('#chetYonalishFilter'), ["Yo'nalishni tanlang"]);
            const select = $('#chetSemestrSelect');
            const current = String(preferredValue || select.val() || '');

            select.empty().append('<option value="">Semestrni tanlang</option>');
            allChetSemestrFilterOptions
                .filter((item) => !selectedFakultet || item.fakultetId === selectedFakultet)
                .filter((item) => !selectedYonalish || item.yonalishId === selectedYonalish)
                .forEach((item) => {
                    select.append(
                        $('<option>')
                        .attr('value', item.value)
                        .attr('data-fakultet-id', item.fakultetId)
                        .attr('data-yonalish-id', item.yonalishId)
                        .text(item.label)
                    );
                });

            const hasCurrent = current !== '' && select.find(`option[value="${current}"]`).length > 0;
            select.val(hasCurrent ? current : '');
            triggerSelectRefresh(select);

            refreshChetOptionsBySemestr();
        }

        function refreshChetOptionsBySemestr() {
            const semestrId = $('#chetSemestrSelect').val();
            $('.chet-tili-select').each(function() {
                renderChetOptions($(this), semestrId);
            });
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getBaseFanInfo() {
            const baseFanId = String($('#biriktirishBaseFanSelect').val() || '');
            return baseFanMeta[baseFanId] || null;
        }

        function normalizeVariants(list) {
            const seen = {};
            const out = [];
            (list || []).forEach(item => {
                const id = parseInt((item && item.id) || 0, 10);
                if (id <= 0 || seen[id]) {
                    return;
                }
                seen[id] = true;
                out.push({
                    id,
                    label: String((item && item.label) || '').trim() || `Variant #${id}`,
                });
            });

            out.sort((a, b) => String(a.label).localeCompare(String(b.label)));
            return out;
        }

        function getBaseVariants(info) {
            const id = String((info && info.id) || '');
            return normalizeVariants(variantFansByBase[id] || []);
        }

        const allSemestrOptions = [];
        const semestrNumById = {};
        Object.keys(semestrOptionsByNum).forEach(numKey => {
            const rows = semestrOptionsByNum[numKey] || [];
            rows.forEach(item => {
                const semestrNum = parseInt(item.semestr_num || numKey || 0, 10);
                allSemestrOptions.push({
                    id: parseInt(item.id || 0, 10),
                    label: item.label || '',
                    semestr_num: semestrNum,
                    yonalish_id: parseInt(item.yonalish_id || 0, 10),
                    fakultet_id: parseInt(item.fakultet_id || 0, 10),
                });
                semestrNumById[String(item.id)] = semestrNum;
            });
        });
        allSemestrOptions.sort((a, b) => {
            if (a.semestr_num !== b.semestr_num) return a.semestr_num - b.semestr_num;
            return String(a.label).localeCompare(String(b.label));
        });

        function buildSemestrOptions(selectedValue = '') {
            const selectedFakultet = parseInt($('#biriktirishFakultetFilter').val() || 0, 10);

            let html = '<option value="">Tanlang</option>';

            allSemestrOptions
                .filter(item => !selectedFakultet || parseInt(item.fakultet_id || 0, 10) === selectedFakultet)
                .forEach(item => {
                    const selected = String(item.id) === String(selectedValue) ? ' selected' : '';
                    html += `<option value="${item.id}"${selected}>${escapeHtml(item.label)}</option>`;
                });

            return html;
        }

        function refreshBiriktirishSemestrRows() {
            $('#semestrRowWrapper .semestr-row').each(function() {
                const row = $(this);
                const select = row.find('.biriktirish-semestr-select');
                const currentValue = String(select.val() || '');

                if (select.hasClass('select2-hidden-accessible')) {
                    select.select2('destroy');
                }

                select.html(buildSemestrOptions(currentValue));

                const hasCurrent = currentValue !== '' && select.find(`option[value="${currentValue}"]`).length > 0;
                select.val(hasCurrent ? currentValue : '');

                initSemestrSelect(select);
            });

            refreshBaseFanSelect();
            renderTaqsimotMatrix();
        }

        function initSemestrSelect(select) {
            if (select.hasClass('select2-hidden-accessible')) {
                select.select2('destroy');
            }
            select.select2({
                placeholder: "Yo'nalish+semestrni tanlang",
                allowClear: true,
                width: '100%',
            });
        }

        function addSemestrRow(selectedValue = '') {
            semestrRowIndex++;
            const row = $(`
                <div class="semestr-row" data-row-index="${semestrRowIndex}">
                    <select class="form-control biriktirish-semestr-select">
                        ${buildSemestrOptions(selectedValue)}
                    </select>
                    <div class="semestr-row-actions">
                        <button type="button" class="btn btn-danger btn-sm removeSemestrRow">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `);

            $('#semestrRowWrapper').append(row);
            initSemestrSelect(row.find('.biriktirish-semestr-select'));
            if (selectedValue) {
                row.find('.biriktirish-semestr-select').val(String(selectedValue)).trigger('change');
            }
        }

        function getSelectedSemestrIds() {
            const ids = [];
            $('#semestrRowWrapper .biriktirish-semestr-select').each(function() {
                const val = parseInt($(this).val() || 0, 10);
                if (val > 0 && !ids.includes(val)) {
                    ids.push(val);
                }
            });
            return ids;
        }

        function getSemestrConstraint() {
            const semestrIds = getSelectedSemestrIds();
            if (!semestrIds.length) {
                return {
                    ok: false,
                    reason: 'empty',
                    semestrIds: [],
                    semestrNum: 0,
                    message: "Avval kamida bitta yo'nalish+semestr tanlang",
                };
            }

            let semestrNum = 0;
            for (const semestrId of semestrIds) {
                const num = parseInt(semestrNumById[String(semestrId)] || 0, 10);
                if (num <= 0) {
                    return {
                        ok: false,
                        reason: 'invalid',
                        semestrIds,
                        semestrNum: 0,
                        message: "Semestr ma'lumoti topilmadi",
                    };
                }
                if (semestrNum === 0) {
                    semestrNum = num;
                } else if (semestrNum !== num) {
                    return {
                        ok: false,
                        reason: 'mixed',
                        semestrIds,
                        semestrNum: 0,
                        message: "Tanlangan yo'nalishlar bir xil semestrda bo'lishi shart",
                    };
                }
            }

            return {
                ok: true,
                reason: 'ok',
                semestrIds,
                semestrNum,
                message: '',
            };
        }

        function refreshBaseFanSelect() {
            const select = $('#biriktirishBaseFanSelect');
            if (!select.length) return;

            const current = String(select.val() || '');
            const semestrCheck = getSemestrConstraint();

            select.empty();

            if (!semestrCheck.ok) {
                const placeholder = semestrCheck.reason === 'mixed' ?
                    "Bir xil semestrni tanlang" :
                    "Avval yo'nalish+semestr tanlang";
                select.append(new Option(placeholder, '', false, false));
                select.prop('disabled', true).val('').trigger('change.select2');
                return;
            }

            const semestrNum = semestrCheck.semestrNum;
            const fanList = Object.values(baseFanMeta)
                .filter(item => parseInt(item.semestr_num || 0, 10) === semestrNum)
                .sort((a, b) => String(a.label || '').localeCompare(String(b.label || '')));

            if (!fanList.length) {
                select.append(new Option(`${semestrNum}-semestr uchun bazaviy fan topilmadi`, '', false, false));
                select.prop('disabled', true).val('').trigger('change.select2');
                return;
            }

            select.append(new Option('Tanlang', '', false, false));
            fanList.forEach(item => {
                select.append(new Option(item.label, item.id, false, false));
            });

            select.prop('disabled', false);
            if (current && fanList.some(item => String(item.id) === current)) {
                select.val(current).trigger('change.select2');
            } else {
                select.val('').trigger('change.select2');
            }
        }

        function collectSelectedGroups() {
            const semestrIds = getSelectedSemestrIds();
            const rows = [];
            semestrIds.forEach(semestrId => {
                const groups = groupsBySemestr[String(semestrId)] || [];
                groups.forEach(group => {
                    rows.push({
                        semestr_id: semestrId,
                        id: parseInt(group.id || 0, 10),
                        name: group.name || '-',
                        size: parseInt(group.size || 0, 10) || 0,
                        yonalish_id: parseInt(group.yonalish_id || 0, 10) || 0,
                        yonalish_label: group.yonalish_label || '-',
                    });
                });
            });
            return rows;
        }

        function renderGroupsPreview(groups) {
            let total = 0;
            let html = `
                <div class="matrix-help">Guruhlar va talaba soni aniqlandi. Endi bazaviy chet tili fanini tanlang.</div>
                <table class="data-table mt-2">
                    <thead>
                        <tr>
                            <th>Yo'nalish</th>
                            <th>Guruh</th>
                            <th>Talaba soni</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            groups.forEach(group => {
                total += group.size;
                html += `
                    <tr>
                        <td>${escapeHtml(group.yonalish_label)}</td>
                        <td>${escapeHtml(group.name)}</td>
                        <td>${group.size}</td>
                    </tr>
                `;
            });

            html += `
                    <tr>
                        <td colspan="2"><strong>Jami</strong></td>
                        <td><strong>${total}</strong></td>
                    </tr>
                </tbody>
            </table>
            `;
            return html;
        }

        function renderTaqsimotMatrix() {
            const wrapper = $('#taqsimotMatrixWrapper');
            const semestrCheck = getSemestrConstraint();
            if (!semestrCheck.ok) {
                wrapper.html(`<div class="guruh-list-empty">${escapeHtml(semestrCheck.message)}</div>`);
                return;
            }

            const groups = collectSelectedGroups();
            if (!groups.length) {
                wrapper.html('<div class="guruh-list-empty">Tanlangan yo\'nalishlarda guruh topilmadi.</div>');
                return;
            }

            const info = getBaseFanInfo();
            if (!info) {
                wrapper.html(renderGroupsPreview(groups));
                return;
            }
            if (parseInt(info.semestr_num || 0, 10) !== semestrCheck.semestrNum) {
                wrapper.html('<div class="guruh-list-empty">Tanlangan bazaviy fan semestri yo\'nalish semestri bilan mos emas.</div>');
                return;
            }

            const variants = getBaseVariants(info);
            if (!variants.length) {
                wrapper.html('<div class="guruh-list-empty">Tanlangan bazaviy fan uchun variant fanlar topilmadi.</div>');
                return;
            }

            let html = `
                <div class="matrix-help">Har bir guruh bo'yicha variantlar yig'indisi guruhdagi jami talaba soniga teng bo'lishi shart.</div>
                <table class="data-table mt-2">
                    <thead>
                        <tr>
                            <th>Yo'nalish</th>
                            <th>Guruh</th>
                            <th>Jami</th>
                            ${variants.map(v => `<th>${escapeHtml(v.label)}</th>`).join('')}
                            <th>Yig'indi</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            groups.forEach(group => {
                const semestrId = String(group.semestr_id);
                const groupId = String(group.id);
                html += `
                    <tr class="taqsimot-row" data-size="${group.size}">
                        <td>${escapeHtml(group.yonalish_label)}</td>
                        <td>${escapeHtml(group.name)}</td>
                        <td>${group.size}</td>
                `;

                variants.forEach(variant => {
                    const value = (((talabValues[semestrId] || {})[groupId] || {})[String(variant.id)] ?? 0);
                    html += `
                        <td>
                            <input
                                type="number"
                                class="form-control taqsimot-input"
                                min="0"
                                step="1"
                                data-semestr-id="${group.semestr_id}"
                                data-group-id="${group.id}"
                                data-fan-id="${variant.id}"
                                value="${parseInt(value, 10) || 0}"
                            >
                        </td>
                    `;
                });

                html += `<td><span class="taqsimot-summary">-</span></td></tr>`;
            });

            html += '</tbody></table>';
            wrapper.html(html);
            updateAllMatrixSummaries();
        }

        function updateRowSummary(row) {
            const size = parseInt(row.data('size') || 0, 10);
            let sum = 0;

            row.find('.taqsimot-input').each(function() {
                let v = parseInt($(this).val() || 0, 10);
                if (Number.isNaN(v) || v < 0) {
                    v = 0;
                    $(this).val(0);
                }
                sum += v;
            });

            const summary = row.find('.taqsimot-summary');
            summary.text(`Yig'indi: ${sum} / ${size}`);
            if (sum === size) {
                summary.removeClass('err').addClass('ok');
            } else {
                summary.removeClass('ok').addClass('err');
            }
        }

        function updateAllMatrixSummaries() {
            $('#taqsimotMatrixWrapper .taqsimot-row').each(function() {
                updateRowSummary($(this));
            });
        }

        function collectBiriktirishPayload() {
            const semestrCheck = getSemestrConstraint();
            if (!semestrCheck.ok) {
                return {
                    ok: false,
                    message: semestrCheck.message
                };
            }

            const info = getBaseFanInfo();
            if (!info) {
                return {
                    ok: false,
                    message: "Bazaviy fan tanlanmagan"
                };
            }
            if (parseInt(info.semestr_num || 0, 10) !== semestrCheck.semestrNum) {
                return {
                    ok: false,
                    message: "Bazaviy fan semestri tanlangan yo'nalish semestri bilan mos emas"
                };
            }

            const variants = getBaseVariants(info);
            if (!variants.length) {
                return {
                    ok: false,
                    message: "Variant fanlar topilmadi"
                };
            }

            const semestrIds = semestrCheck.semestrIds;

            const groups = collectSelectedGroups();
            if (!groups.length) {
                return {
                    ok: false,
                    message: "Tanlangan yo'nalishlarda guruh yo'q"
                };
            }

            const allocations = {};
            for (const group of groups) {
                const semestrId = String(group.semestr_id);
                const groupId = String(group.id);
                if (!allocations[semestrId]) allocations[semestrId] = {};
                allocations[semestrId][groupId] = {};

                let sum = 0;
                for (const variant of variants) {
                    const input = $(`.taqsimot-input[data-semestr-id="${group.semestr_id}"][data-group-id="${group.id}"][data-fan-id="${variant.id}"]`);
                    let value = parseInt(input.val() || 0, 10);
                    if (Number.isNaN(value) || value < 0) {
                        value = 0;
                    }
                    allocations[semestrId][groupId][String(variant.id)] = value;
                    sum += value;
                }

                if (sum !== parseInt(group.size, 10)) {
                    return {
                        ok: false,
                        message: `${group.name} guruhida yig'indi ${sum}, keraklisi ${group.size}`
                    };
                }
            }

            return {
                ok: true,
                baseFanId: parseInt(info.id, 10),
                semestrIds,
                allocations
            };
        }

        function updateMergeSelectionSummary() {
            let selectedCount = 0;
            let selectedStudents = 0;

            $('.merge-group-checkbox').each(function() {
                if (!this.checked) {
                    return;
                }
                selectedCount++;
                selectedStudents += parseInt($(this).data('talabalar-soni') || 0, 10) || 0;
            });

            $('#mergeSelectedCountBadge').text(selectedCount);
            $('#mergeSelectedStudentsBadge').text(selectedStudents);
        }

        function syncMergeCardState(card) {
            if (!card || !card.length) {
                return;
            }

            const checkboxes = card.find('.merge-group-checkbox');
            const checkedCount = checkboxes.filter(':checked').length;
            const totalCount = checkboxes.length;
            const cardToggle = card.find('.merge-card-select-all').first();
            if (cardToggle.length) {
                cardToggle.prop('checked', totalCount > 0 && checkedCount === totalCount);
            }
        }

        function applyMergeUniqueness(preferredCheckbox = null) {
            const selectedByKey = {};
            let resolvedCount = 0;

            $('.merge-group-checkbox:checked').each(function() {
                const checkbox = $(this);
                const mergeKey = String(checkbox.data('merge-key') || '').trim();
                if (!mergeKey) {
                    return;
                }

                if (!selectedByKey[mergeKey]) {
                    selectedByKey[mergeKey] = this;
                    return;
                }

                const previous = $(selectedByKey[mergeKey]);
                if (preferredCheckbox && this === preferredCheckbox) {
                    previous.prop('checked', false);
                    previous.closest('tr').removeClass('merge-row-selected');
                    selectedByKey[mergeKey] = this;
                    resolvedCount++;
                    return;
                }

                checkbox.prop('checked', false);
                checkbox.closest('tr').removeClass('merge-row-selected');
                resolvedCount++;
            });

            return resolvedCount;
        }

        function collectMergePayload() {
            const scopeItems = [];
            let selectedCount = 0;
            let selectedStudents = 0;
            const selectedMergeKeys = {};

            $('.merge-group-checkbox:checked').each(function() {
                const checkbox = $(this);
                const mergeKey = String(checkbox.data('merge-key') || '').trim();
                const item = {
                    semestr_id: parseInt(checkbox.data('semestr-id') || 0, 10) || 0,
                    yonalish_id: parseInt(checkbox.data('yonalish-id') || 0, 10) || 0,
                    guruh_id: parseInt(checkbox.data('guruh-id') || 0, 10) || 0,
                    fan_id: parseInt(checkbox.data('fan-id') || 0, 10) || 0,
                    talabalar_soni: parseInt(checkbox.data('talabalar-soni') || 0, 10) || 0,
                    selected: 1,
                    merge_row_key: mergeKey,
                };

                if (item.semestr_id <= 0 || item.yonalish_id <= 0 || item.guruh_id <= 0 || item.fan_id <= 0) {
                    return;
                }

                if (mergeKey !== '') {
                    if (selectedMergeKeys[mergeKey]) {
                        return;
                    }
                    selectedMergeKeys[mergeKey] = true;
                }

                scopeItems.push(item);
                selectedCount++;
                selectedStudents += item.talabalar_soni;
            });

            if (!scopeItems.length) {
                return {
                    ok: false,
                    message: "Tanlangan guruh topilmadi",
                };
            }

            return {
                ok: true,
                scopeItems,
                selectedCount,
                selectedStudents,
            };
        }

        function getSelectedMultiValues($select) {
            if (!$select || !$select.length) {
                return [];
            }

            const rawValue = $select.val();
            const values = Array.isArray(rawValue)
                ? rawValue
                : (rawValue ? [rawValue] : []);

            return values
                .map((value) => String(value || '').trim())
                .filter((value) => value !== '');
        }

        function rebuildMergeYonalishFilter() {
            return;
        }

        function rebuildMergeSemestrFilter() {
            return;
        }

        function applyMergeFilters(initial = false) {
            const selectedAcademicYear = getSelectedIdWithFallback($('#mergeAcademicYearFilter'), ["Barcha o'quv yillari"]);
            const selectedYonalishIds = getSelectedMultiValues($('#mergeYonalishFilter'));
            const selectedTalimShakli = getSelectedIdWithFallback($('#mergeTalimShakliFilter'), ['Barcha ta\'lim shakllari']);
            const selectedKurs = getSelectedIdWithFallback($('#mergeKursFilter'), ['Barcha kurslar']);
            const selectedSemestr = getSelectedIdWithFallback($('#mergeSemestrNumFilter'), ['Barcha semestrlar']);
            const hasAnyFilter =
                selectedAcademicYear !== '' ||
                selectedYonalishIds.length > 0 ||
                selectedTalimShakli !== '' ||
                selectedKurs !== '' ||
                selectedSemestr !== '';

            let visibleCount = 0;
            let totalCount = 0;
            $('.merge-direction-card').each(function(index) {
                totalCount++;
                const card = $(this);
                const cardAcademicYear = String(card.attr('data-kirish-yili') || '');
                const cardYonalish = String(card.attr('data-yonalish-id') || '');
                const cardTalimShakli = String(card.attr('data-talim-shakli-id') || '');
                const cardKurs = String(card.attr('data-kurs') || '');
                const cardSemestr = String(card.attr('data-semestr-num') || '');

                const matches =
                    (!selectedAcademicYear || cardAcademicYear === selectedAcademicYear) &&
                    (selectedYonalishIds.length === 0 || selectedYonalishIds.includes(cardYonalish)) &&
                    (!selectedTalimShakli || cardTalimShakli === selectedTalimShakli) &&
                    (!selectedKurs || cardKurs === selectedKurs) &&
                    (!selectedSemestr || cardSemestr === selectedSemestr);

                const shouldShow = matches && (hasAnyFilter || index < mergeVisibleLimit);
                card.toggleClass('merge-card-hidden', !shouldShow);
                if (shouldShow) {
                    visibleCount++;
                }
            });

            const filteredCount = hasAnyFilter ? visibleCount : totalCount;
            $('#mergeFilterNote').text(`${filteredCount} ta yo'nalish mos keldi`);
            $('#mergeFilterEmpty').toggle(totalCount > 0 && visibleCount === 0);

            if (initial) {
                return;
            }
        }

        // Izoh: Yaratish tabida eski refresh chaqiruvini buzmaslik uchun no-op.
        function refreshChetTiliOptions() {
            return Promise.resolve();
        }

        const forcedChetTab = <?php echo json_encode($forcedTabId, JSON_UNESCAPED_UNICODE); ?>;

        $(document).ready(function() {
            // Izoh: Tablarni boshqarish va holatini saqlash.
            function setActiveTab(tabId) {
                if (!tabId) return;
                $('.tab-btn').removeClass('active');
                $('.tab-btn[data-tab="' + tabId + '"]').addClass('active');
                $('.tab-content').removeClass('active');
                $('#' + tabId).addClass('active');
            }

            const rawSavedTab = forcedChetTab || localStorage.getItem('chetTiliActiveTab') || 'chet-tab-yaratish';
            const savedTab = ($(`.tab-btn[data-tab="${rawSavedTab}"]`).length > 0) ? rawSavedTab : 'chet-tab-yaratish';
            setActiveTab(savedTab);
            if (forcedChetTab) {
                localStorage.setItem('chetTiliActiveTab', forcedChetTab);
            }

            $('.tab-btn').on('click', function() {
                if (forcedChetTab) {
                    return;
                }
                const target = $(this).data('tab');
                localStorage.setItem('chetTiliActiveTab', target);
                setActiveTab(target);
            });
            $(document).on('change', '#biriktirishFakultetFilter', function() {
                refreshBiriktirishSemestrRows();
            });
 

            cacheChetTopFilterOptions();

            $('#chetFakultetFilter').select2({
                placeholder: "Fakultetni tanlang",
                allowClear: true,
                width: '100%',
            });
            $('#chetYonalishFilter').select2({
                placeholder: "Yo'nalishni tanlang",
                allowClear: true,
                width: '100%',
            });
            // Izoh: Chet tili fanini yaratish selectlari.
            $('#chetSemestrSelect').select2({
                placeholder: "Semestrni tanlang",
                allowClear: true,
                width: '100%',
            });

            rebuildChetYonalishFilter();
            rebuildChetSemestrFilter();

            initializeChetSelect2($('#chetRejaWrapper .reja-card:first'));
            refreshChetOptionsBySemestr();

            $('#biriktirishBaseFanSelect').select2({
                placeholder: "Bazaviy chet tili fanini tanlang",
                allowClear: true,
                width: '100%',
            });
            $('#biriktirishBaseFanSelect').prop('disabled', true);

            $('#mergeAcademicYearFilter').select2({
                placeholder: "Barcha o'quv yillari",
                allowClear: true,
                width: '100%',
            });
            $('#mergeYonalishFilter').select2({
                placeholder: "Yo'nalish(lar)ni tanlang",
                closeOnSelect: false,
                width: '100%',
            });
            $('#mergeTalimShakliFilter').select2({
                placeholder: "Barcha ta'lim shakllari",
                allowClear: true,
                width: '100%',
            });
            $('#mergeKursFilter').select2({
                placeholder: "Barcha kurslar",
                allowClear: true,
                width: '100%',
            });
            $('#mergeSemestrNumFilter').select2({
                placeholder: "Barcha semestrlar",
                allowClear: true,
                width: '100%',
            });

            applyMergeFilters(true);

            addSemestrRow();
            refreshBaseFanSelect();
            renderTaqsimotMatrix();

            $('#chetFakultetFilter').on('change', function() {
                rebuildChetYonalishFilter('');
                rebuildChetSemestrFilter('');
            });

            $('#chetYonalishFilter').on('change', function() {
                rebuildChetSemestrFilter('');
            });

            $('#applyChetTopFiltersBtn').on('click', function() {
                rebuildChetYonalishFilter(getSelectedIdWithFallback($('#chetYonalishFilter'), ["Yo'nalishni tanlang"]));
                rebuildChetSemestrFilter(getSelectedIdWithFallback($('#chetSemestrSelect'), ['Semestrni tanlang']));
            });

            $('#resetChetTopFiltersBtn').on('click', function() {
                $('#chetFakultetFilter').val('').trigger('change');
                rebuildChetYonalishFilter('');
                rebuildChetSemestrFilter('');
            });

            const syncChetTopFilters = () => {
                const currentYonalish = getSelectedIdWithFallback($('#chetYonalishFilter'), ["Yo'nalishni tanlang"]);
                const currentSemestr = getSelectedIdWithFallback($('#chetSemestrSelect'), ['Semestrni tanlang']);
                rebuildChetYonalishFilter(currentYonalish);
                rebuildChetSemestrFilter(currentSemestr);
            };

            syncChetTopFilters();
            setTimeout(syncChetTopFilters, 150);
            $(window).on('pageshow', function() {
                setTimeout(syncChetTopFilters, 0);
            });

            $('#chetYonalishFilter').on('select2:opening focus mousedown click', function() {
                const currentYonalish = getSelectedIdWithFallback($('#chetYonalishFilter'), ["Yo'nalishni tanlang"]);
                rebuildChetYonalishFilter(currentYonalish);
                rebuildChetSemestrFilter(getSelectedIdWithFallback($('#chetSemestrSelect'), ['Semestrni tanlang']));
            });

            $('#mergeYonalishFilter').on('change', function() {
                applyMergeFilters(false);
            });

            $('#mergeAcademicYearFilter, #mergeTalimShakliFilter, #mergeKursFilter, #mergeSemestrNumFilter').on('change', function() {
                applyMergeFilters(false);
            });

            $('#applyMergeFiltersBtn').on('click', function() {
                applyMergeFilters(false);
            });

            $('#resetMergeFiltersBtn').on('click', function() {
                $('#mergeAcademicYearFilter').val('').trigger('change');
                $('#mergeYonalishFilter').val([]).trigger('change');
                $('#mergeTalimShakliFilter').val('').trigger('change');
                $('#mergeKursFilter').val('').trigger('change');
                $('#mergeSemestrNumFilter').val('').trigger('change');
                applyMergeFilters(true);
            });

            $('.merge-direction-card').each(function() {
                const card = $(this);
                card.find('.merge-group-checkbox').each(function() {
                    $(this).closest('tr').toggleClass('merge-row-selected', this.checked);
                });
                syncMergeCardState(card);
            });
            applyMergeUniqueness();
            $('.merge-direction-card').each(function() {
                syncMergeCardState($(this));
            });
            updateMergeSelectionSummary();

            $(document).on('change', '.merge-group-checkbox', function() {
                const checkbox = $(this);
                const fixedCount = applyMergeUniqueness(this);
                checkbox.closest('tr').toggleClass('merge-row-selected', checkbox.is(':checked'));
                $('.merge-direction-card').each(function() {
                    syncMergeCardState($(this));
                });
                updateMergeSelectionSummary();
                if (fixedCount > 0) {
                    Toast.fire({
                        icon: 'info',
                        title: "Bir xil guruh/til kombinatsiyasidan faqat bittasi tanlanadi"
                    });
                }
            });

            $(document).on('change', '.merge-card-select-all', function() {
                const card = $(this).closest('.merge-direction-card');
                const checked = $(this).is(':checked');
                card.find('.merge-group-checkbox').each(function() {
                    $(this).prop('checked', checked);
                    $(this).closest('tr').toggleClass('merge-row-selected', checked);
                });
                applyMergeUniqueness();
                $('.merge-direction-card').each(function() {
                    syncMergeCardState($(this));
                });
                updateMergeSelectionSummary();
            });
        });

        // Izoh: Chet tili semestri tanlanganda fanlar ro'yxatini yangilash.
        $('#chetSemestrSelect').on('change', function() {
            refreshChetOptionsBySemestr();
        });

        $('#biriktirishBaseFanSelect').on('change', function() {
            renderTaqsimotMatrix();
        });

        $('#addSemestrRowBtn').on('click', function() {
            addSemestrRow();
            refreshBaseFanSelect();
            renderTaqsimotMatrix();
        });

        $(document).on('click', '.removeSemestrRow', function() {
            const rows = $('#semestrRowWrapper .semestr-row');
            if (rows.length <= 1) {
                return;
            }
            const row = $(this).closest('.semestr-row');
            const select = row.find('.biriktirish-semestr-select');
            if (select.hasClass('select2-hidden-accessible')) {
                select.select2('destroy');
            }
            row.remove();
            refreshBaseFanSelect();
            renderTaqsimotMatrix();
        });

        $(document).on('change', '.biriktirish-semestr-select', function() {
            refreshBaseFanSelect();
            renderTaqsimotMatrix();
        });

        $(document).on('input', '.taqsimot-input', function() {
            let v = parseInt($(this).val() || 0, 10);
            if (Number.isNaN(v) || v < 0) {
                v = 0;
            }
            $(this).val(v);
            updateRowSummary($(this).closest('.taqsimot-row'));
        });

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });

        $('#chetBiriktirishForm').on('submit', function(e) {
            e.preventDefault();

            const payload = collectBiriktirishPayload();
            if (!payload.ok) {
                Toast.fire({
                    icon: 'error',
                    title: payload.message || "Biriktirishda xatolik"
                });
                return;
            }

            const formData = new FormData();
            formData.append('base_fan_id', String(payload.baseFanId));
            formData.append('semestr_ids_json', JSON.stringify(payload.semestrIds));
            formData.append('allocations_json', JSON.stringify(payload.allocations));

            fetch('insert/save_chet_tili_taqsimot.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        localStorage.setItem('chetTiliActiveTab', 'chet-tab-biriktirish');
                        Toast.fire({
                            icon: 'success',
                            title: data.message || "Biriktirish saqlandi"
                        });
                        setTimeout(() => window.location.reload(), 400);
                    } else {
                        Toast.fire({
                            icon: 'error',
                            title: data.message || 'Xatolik yuz berdi'
                        });
                    }
                })
                .catch(() => {
                    Toast.fire({
                        icon: 'error',
                        title: "Server bilan bog'lanib bo'lmadi"
                    });
                });
        });

        $('#chetGuruhBirlashtirishForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = form.find('button[type="submit"]').first();
            const submitBtnHtml = submitBtn.length ? submitBtn.html() : '';

            const payload = collectMergePayload();
            if (!payload.ok) {
                Toast.fire({
                    icon: 'error',
                    title: payload.message || "Biriktirishda xatolik"
                });
                return;
            }

            const formData = new FormData();
            formData.append('scope_items_json', JSON.stringify(payload.scopeItems));
            if (submitBtn.length) {
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saqlanmoqda...');
            }

            const controller = typeof AbortController === 'function' ? new AbortController() : null;
            const timeoutMs = 45000;
            let requestTimer = null;
            if (controller) {
                requestTimer = setTimeout(() => controller.abort(), timeoutMs);
            }

            fetch('insert/save_chet_tili_guruh_biriktirish.php', {
                    method: 'POST',
                    body: formData,
                    signal: controller ? controller.signal : undefined,
                })
                .then(async (res) => {
                    const raw = await res.text();
                    let data = null;
                    try {
                        data = raw ? JSON.parse(raw) : {};
                    } catch (e) {
                        throw new Error(`HTTP ${res.status}: ${raw.slice(0, 220) || 'JSON parse xatoligi'}`);
                    }
                    if (!res.ok) {
                        const message = data && data.message ? data.message : `HTTP ${res.status}`;
                        const hint = data && data.error_hint ? ` (${data.error_hint})` : '';
                        throw new Error(message + hint);
                    }
                    return data;
                })
                .then(data => {
                    if (data.success) {
                        localStorage.setItem('chetTiliActiveTab', 'chet-tab-guruh-birlashtirish');
                        Toast.fire({
                            icon: 'success',
                            title: data.message || "Biriktirish saqlandi"
                        });
                        setTimeout(() => window.location.reload(), 400);
                    } else {
                        Toast.fire({
                            icon: 'error',
                            title: data.message || 'Xatolik yuz berdi'
                        });
                    }
                })
                .catch((err) => {
                    if (window.console && typeof window.console.error === 'function') {
                        window.console.error('save_chet_tili_guruh_biriktirish fetch error:', err);
                    }
                    const isAbort = err && (err.name === 'AbortError' || String(err.message || '').includes('abort'));
                    Toast.fire({
                        icon: 'error',
                        title: isAbort ? "So'rov vaqti tugadi. Qayta urinib ko'ring" : (err && err.message ? err.message : "Server bilan bog'lanib bo'lmadi")
                    });
                })
                .finally(() => {
                    if (requestTimer) {
                        clearTimeout(requestTimer);
                    }
                    if (submitBtn.length) {
                        submitBtn.prop('disabled', false).html(submitBtnHtml);
                    }
                });
        });

        const chetKafedralarList = <?php echo $kafedralarJson; ?>;

        function buildChetKafedralarOptionsHtml() {
            let html = '';
            (chetKafedralarList || []).forEach((item) => {
                const id = String(item.id || '');
                if (id === '') return;
                html += `<option value="${id}">${escapeOptionText(item.name || '')}</option>`;
            });
            return html;
        }

        const chetTiliOptionsBySemestr = <?php echo json_encode($chetTiliOptionsBySemestr, JSON_UNESCAPED_UNICODE); ?>;

        function buildChetCard(index) {
            return `
                <div class="tanlovfan-actions">
                    <input type="hidden" name="tanlov_fan[${index}]" value="3" class="tanlov-input">
                    <input type="hidden" name="tanlov_fan_code[${index}]" class="chet-code-input">
                    <input type="hidden" name="tanlov_fan_base_nomi[${index}]" class="chet-base-input">
                    <button type="button" class="btn btn-outline btn-sm fanTypeToggle active" disabled>
                        <i class="fas fa-check-circle"></i> Chet tili
                    </button>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Chet tili (kod + nomi)</label>
                        <select class="form-control chet-tili-select" name="tanlov_fan_base[${index}]" required>
                            <option value="">Tanlang</option>
                        </select>
                    </div>
                </div>

                <div class="tanlov-fan-item" data-tanlov-index="0">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Chet tili nomi</label>
                            <input type="text" class="form-control" name="tanlov_fan_nomi[${index}][]" placeholder="Masalan: English 1" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Kafedra</label>
                            <select class="form-control" name="tanlov_kafedra_id[${index}][]" required>
                                <option value="">Tanlang</option>
                                ${buildChetKafedralarOptionsHtml()}
                            </select>
                        </div>
                    </div>
                    
                    <div class="tanlov-fan-actions mb-3">
                        <button type="button" class="btn btn-outline btn-sm addChetTiliFan">
                            <i class="fas fa-plus"></i> Yana variant
                        </button>
                        
                        <button type="button" class="btn btn-danger btn-sm removeChetTiliFan">
                            <i class="fas fa-times"></i> O'chirish
                        </button>
                    </div>
                </div>

                <div class="reja-actions">
                    <button type="button" class="btn btn-outline btn-sm addChetReja">
                        <i class="fas fa-plus"></i> Yana fan
                    </button>

                    <button type="button" class="btn btn-danger btn-sm removeChetReja">
                        <i class="fas fa-times"></i> O'chirish
                    </button>
                </div>
            `;
        }

        function renderChetOptions(select, semestrId) {
            select.empty().append(new Option('Tanlang', '', false, false));
            if (!semestrId) {
                select.val(null).trigger('change');
                return;
            }
            if (chetTiliOptionsBySemestr[semestrId]) {
                select.append(chetTiliOptionsBySemestr[semestrId]);
            } else {
                select.append(new Option("Chet tili fan topilmadi", "", false, false));
            }
            select.val(null).trigger('change');
        }

        $(document).on('click', '.addChetReja', function() {
            chetFanIndex++;
            const newCard = $(`<div class="reja-card" data-index="${chetFanIndex}"></div>`);
            $('#chetRejaWrapper').append(newCard);
            newCard.html(buildChetCard(chetFanIndex));
            initializeChetSelect2(newCard);
            const semestrId = $('#chetSemestrSelect').val();
            renderChetOptions(newCard.find('.chet-tili-select'), semestrId);
        });

        $(document).on('click', '.addChetTiliFan', function() {
            const card = $(this).closest('.reja-card');
            const index = card.data('index');
            const tanlovWrapper = $(this).closest('.tanlov-fan-item');
            const tanlovIndex = parseInt(tanlovWrapper.data('tanlov-index')) + 1;

            const newTanlovItem = $(`
                <div class="tanlov-fan-item mt-3" data-tanlov-index="${tanlovIndex}">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Chet tili nomi</label>
                            <input type="text" class="form-control" name="tanlov_fan_nomi[${index}][]" placeholder="Masalan: English 1" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Kafedra</label>
                            <select class="form-control" name="tanlov_kafedra_id[${index}][]" required>
                                <option value="">Tanlang</option>
                                ${buildChetKafedralarOptionsHtml()}
                            </select>
                        </div>
                    </div>
                    
                    <div class="tanlov-fan-actions mb-3">
                        <button type="button" class="btn btn-outline btn-sm addChetTiliFan">
                            <i class="fas fa-plus"></i> Yana variant
                        </button>
                        
                        <button type="button" class="btn btn-danger btn-sm removeChetTiliFan">
                            <i class="fas fa-times"></i> O'chirish
                        </button>
                    </div>
                </div>
            `);

            tanlovWrapper.after(newTanlovItem);
            initializeChetSelect2(newTanlovItem);
        });

        $(document).on('click', '.removeChetTiliFan', function() {
            const tanlovItems = $(this).closest('.reja-card').find('.tanlov-fan-item');
            if (tanlovItems.length > 1) {
                $(this).closest('.tanlov-fan-item').remove();
            }
        });

        $(document).on('click', '.removeChetReja', function() {
            const rejas = $('#chetRejaWrapper .reja-card');
            if (rejas.length > 1) {
                const rejaToRemove = $(this).closest('.reja-card');

                rejaToRemove.find('select').each(function() {
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2('destroy');
                    }
                });

                rejaToRemove.remove();

                reorganizeChetIndexes();
            }
        });

        function reorganizeChetIndexes() {
            chetFanIndex = -1;
            $('#chetRejaWrapper .reja-card').each(function(newIndex) {
                chetFanIndex = newIndex;
                $(this).data('index', newIndex);
                const card = $(this);

                card.find('input[name^="tanlov_fan["]').attr('name', `tanlov_fan[${newIndex}]`);
                // Izoh: Chet tili select va input nomlarini indeks bo'yicha yangilash.
                card.find('input[name^="tanlov_fan_code["]').attr('name', `tanlov_fan_code[${newIndex}]`);
                card.find('input[name^="tanlov_fan_base_nomi["]').attr('name', `tanlov_fan_base_nomi[${newIndex}]`);
                card.find('select[name^="tanlov_fan_base["]').attr('name', `tanlov_fan_base[${newIndex}]`);
                card.find('input[name^="tanlov_fan_nomi["]').attr('name', `tanlov_fan_nomi[${newIndex}][]`);
                card.find('select[name^="tanlov_kafedra_id["]').attr('name', `tanlov_kafedra_id[${newIndex}][]`);
            });
        }

        function initializeChetSelect2(container) {
            setTimeout(() => {
                container.find('select').each(function() {
                    const name = $(this).attr('name') || '';

                    if ($(this).hasClass('chet-tili-select')) {
                        if (!$(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2({
                                placeholder: "Chet tili fanni tanlang",
                                allowClear: true,
                                width: '100%',
                            });
                        }
                        return;
                    }

                    if (name.includes('kafedra')) {
                        if (!$(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2({
                                placeholder: "Kafedrani tanlang",
                                allowClear: true,
                                width: '100%',
                            });
                        }
                    }
                });
            }, 10);
        }

        // Izoh: Chet tili selectdan kod va nomni hidden inputlarga yozish.
        $(document).on('change', '.chet-tili-select', function() {
            const selected = $(this).find('option:selected');
            const code = $(this).val() || '';
            const baseName = selected.data('name') || '';
            const card = $(this).closest('.reja-card');
            const codeInput = card.find('.chet-code-input');
            const baseInput = card.find('.chet-base-input');

            codeInput.val(code);
            baseInput.val(baseName);
        });

        $('#chetTiliYaratishForm').on('submit', function(e) {
            e.preventDefault();
            // Izoh: Chet tili kodi va nomi select change hodisasida hidden inputga yoziladi.

            const formData = new FormData(this);

            fetch('insert/add_oquv_reja.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Toast.fire({
                            icon: 'success',
                            title: data.message || 'Chet tili muvaffaqiyatli saqlandi'
                        });

                        // Izoh: Yangi yaratilgan bazaviy fan/variantlar 2-tabda ko'rinishi uchun sahifani yangilaymiz.
                        localStorage.setItem('chetTiliActiveTab', 'chet-tab-biriktirish');
                        setTimeout(() => window.location.reload(), 350);
                    } else {
                        Toast.fire({
                            icon: 'error',
                            title: data.message || 'Xatolik yuz berdi'
                        });
                    }
                })
                .catch(() => {
                    Toast.fire({
                        icon: 'error',
                        title: "Server bilan bog'lanib bo'lmadi"
                    });
                });
        });

        // Izoh: Chet tili guruhlarini o'chirish.
        $(document).on('click', '.deleteChetTiliBtn', function() {
            const fanId = $(this).data('fan-id');
            const semestrNum = $(this).data('semestr-num');
            if (!fanId || !semestrNum) return;

            Swal.fire({
                title: "O'chirishni tasdiqlaysizmi?",
                text: "Bu amal orqaga qaytmaydi",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ha, o'chirish",
                cancelButtonText: "Bekor qilish"
            }).then((result) => {
                if (!result.isConfirmed) return;

                const formData = new FormData();
                formData.append('fan_id', fanId);
                formData.append('semestr_num', semestrNum);

                fetch('insert/delete_chet_tili_biriktirish.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Toast.fire({
                                icon: 'success',
                                title: data.message || "O'chirildi"
                            });
                            setTimeout(() => window.location.reload(), 300);
                        } else {
                            Toast.fire({
                                icon: 'error',
                                title: data.message || 'Xatolik yuz berdi'
                            });
                        }
                    })
                    .catch(() => {
                        Toast.fire({
                            icon: 'error',
                            title: "Server bilan bog'lanib bo'lmadi"
                        });
                    });
            });
        });

        // Izoh: 3-tabdagi biriktirilgan til guruhini tahrirlash uchun checkboxlarni avtomatik belgilash.
        $(document).on('click', '.editMergedGroupBtn', function() {
            const semestrNum = String($(this).data('semestr-num') || '').trim();
            const languageKey = String($(this).data('language-key') || '').trim();
            const rawFanIds = String($(this).data('fan-ids') || '');
            const rawGroupIds = String($(this).data('guruh-ids') || '');
            const groupIds = rawGroupIds
                .split(',')
                .map((item) => parseInt(item.trim(), 10))
                .filter((item) => !Number.isNaN(item) && item > 0);
            const fanIds = rawFanIds
                .split(',')
                .map((item) => parseInt(item.trim(), 10))
                .filter((item) => !Number.isNaN(item) && item > 0);

            if (!semestrNum || !groupIds.length) {
                Toast.fire({
                    icon: 'error',
                    title: "Tahrirlash uchun ma'lumot topilmadi"
                });
                return;
            }

            localStorage.setItem('chetTiliActiveTab', 'chet-tab-guruh-birlashtirish');
            $('.tab-btn').removeClass('active');
            $('.tab-btn[data-tab="chet-tab-guruh-birlashtirish"]').addClass('active');
            $('.tab-content').removeClass('active');
            $('#chet-tab-guruh-birlashtirish').addClass('active');

            $('#mergeSemestrNumFilter').val(semestrNum).trigger('change');
            applyMergeFilters(false);

            const groupSet = new Set(groupIds);
            const fanSet = new Set(fanIds);
            $('.merge-group-checkbox').each(function() {
                const checkbox = $(this);
                const groupId = parseInt(checkbox.data('guruh-id') || 0, 10) || 0;
                const fanId = parseInt(checkbox.data('fan-id') || 0, 10) || 0;
                const checkboxSemestrNum = String(checkbox.data('semestr-num') || '').trim();
                const checkboxLanguageKey = String(checkbox.data('language-key') || '').trim();
                const languageOk = languageKey !== '' ? (checkboxLanguageKey === languageKey) : (fanSet.size === 0 || fanSet.has(fanId));
                const shouldCheck = groupSet.has(groupId) && checkboxSemestrNum === semestrNum && languageOk;
                checkbox.prop('checked', shouldCheck);
                checkbox.closest('tr').toggleClass('merge-row-selected', shouldCheck);
            });

            $('.merge-direction-card').each(function() {
                syncMergeCardState($(this));
            });
            updateMergeSelectionSummary();

            const target = $('#chetGuruhBirlashtirishForm');
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 70
                }, 200);
            }

            Toast.fire({
                icon: 'success',
                title: "Tahrirlash uchun guruhlar belgilandi"
            });
        });

        // Izoh: 3-tabdagi biriktirilgan til guruhini o'chirish.
        $(document).on('click', '.deleteMergedGroupBtn', function() {
            const semestrId = parseInt($(this).data('semestr-id') || 0, 10) || 0;
            const fanId = parseInt($(this).data('fan-id') || 0, 10) || 0;
            const rawFanIds = String($(this).data('fan-ids') || '');
            const fanIds = rawFanIds
                .split(',')
                .map((item) => parseInt(item.trim(), 10))
                .filter((item) => !Number.isNaN(item) && item > 0);
            if (semestrId <= 0 || (fanIds.length === 0 && fanId <= 0)) {
                return;
            }

            Swal.fire({
                title: "O'chirishni tasdiqlaysizmi?",
                text: "Tanlangan biriktirilgan til guruhi o'chiriladi",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ha, o'chirish",
                cancelButtonText: "Bekor qilish"
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                const formData = new FormData();
                formData.append('semestr_id', String(semestrId));
                formData.append('fan_id', String(fanId));
                if (fanIds.length > 0) {
                    formData.append('fan_ids', fanIds.join(','));
                }

                fetch('insert/delete_chet_tili_guruh_biriktirish.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            localStorage.setItem('chetTiliActiveTab', 'chet-tab-guruh-birlashtirish');
                            Toast.fire({
                                icon: 'success',
                                title: data.message || "O'chirildi"
                            });
                            setTimeout(() => window.location.reload(), 300);
                        } else {
                            Toast.fire({
                                icon: 'error',
                                title: data.message || "O'chirishda xatolik yuz berdi"
                            });
                        }
                    })
                    .catch(() => {
                        Toast.fire({
                            icon: 'error',
                            title: "Server bilan bog'lanib bo'lmadi"
                        });
                    });
            });
        });
    </script>
    <script src="../assets/js/app.js"></script>
</body>

</html>
