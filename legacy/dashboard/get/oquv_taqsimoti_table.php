<?php
include_once '../config.php';
$db = new Database();

$filters = [];
$rowLimit = 150;
if (isset($_POST['show_all']) && (int)$_POST['show_all'] === 1) {
    $filters['limit'] = 0;
} else {
    $filters['limit'] = $rowLimit;
}
if (isset($_POST['kafedra_id']) && !empty($_POST['kafedra_id'])) {
    $filters['kafedra_id'] = (int)$_POST['kafedra_id'];
}
if (isset($_POST['semestr']) && !empty($_POST['semestr'])) {
    $filters['semestr'] = (int)$_POST['semestr'];
}
if (isset($_POST['oquv_yil_start']) && !empty($_POST['oquv_yil_start'])) {
    $filters['oquv_yil_start'] = (int)$_POST['oquv_yil_start'];
}
legacy_apply_kafedra_scope($filters);

$oquv_taqsimotlar = $db->get_oquv_taqsimotlar($filters);
$examControlMap = $db->get_exam_controls_map();
$examTypeByFanCode = [];
foreach ($examControlMap as $examKey => $examRow) {
    $parts = explode('|', (string)$examKey, 2);
    $fCode = trim((string)($parts[1] ?? ''));
    $eType = strtoupper(trim((string)($examRow['exam_type'] ?? '')));
    if ($fCode === '' || !in_array($eType, ['T', 'I'], true)) {
        continue;
    }
    if (!isset($examTypeByFanCode[$fCode])) {
        $examTypeByFanCode[$fCode] = $eType;
    }
}
$resolveExamType = static function (array $row) use ($examControlMap, $examTypeByFanCode): string {
    $semestrId = (int)($row['semestr_id'] ?? 0);
    $fanCode = trim((string)($row['fan_code'] ?? ''));
    if ($fanCode === '') {
        return '';
    }
    if ($semestrId > 0) {
        $key = $semestrId . '|' . $fanCode;
        $val = strtoupper(trim((string)($examControlMap[$key]['exam_type'] ?? '')));
        if (in_array($val, ['T', 'I'], true)) {
            return $val;
        }
    }
    $fallback = strtoupper(trim((string)($examTypeByFanCode[$fanCode] ?? '')));
    return in_array($fallback, ['T', 'I'], true) ? $fallback : '';
};
$maxsus_oquv_taqsimotlar = [];
if (empty($filters['limit']) || (int)$filters['limit'] === 0) {
    $maxsus_oquv_taqsimotlar = $db->get_maxsus_oquv_taqsimotlar($filters);
} else {
    $remainingLimitForMaxsus = max(0, (int)$filters['limit'] - count($oquv_taqsimotlar));
    if ($remainingLimitForMaxsus > 0) {
        $maxsus_oquv_taqsimotlar = $db->get_maxsus_oquv_taqsimotlar($filters + ['limit' => $remainingLimitForMaxsus]);
    }
}
$oquv_taqsimotlar = array_merge($oquv_taqsimotlar, $maxsus_oquv_taqsimotlar);
usort($oquv_taqsimotlar, static function (array $a, array $b): int {
    $aSem = (int)($a['semestr'] ?? 0);
    $bSem = (int)($b['semestr'] ?? 0);
    if ($aSem !== $bSem) {
        return $aSem <=> $bSem;
    }
    return strcmp((string)($a['fan_nomi'] ?? ''), (string)($b['fan_nomi'] ?? ''));
});
$qoshimcha_oquv_taqsimotlar = [];
if (empty($filters['limit']) || (int)$filters['limit'] === 0) {
    $qoshimcha_oquv_taqsimotlar = $db->get_qoshimcha_oquv_taqsimotlar($filters);
} else {
    // Izoh: Umumiy jadval limiti (150) buzilmasligi uchun qolgan limitni qo'llaymiz.
    $remainingLimitForQoshimcha = max(0, (int)$filters['limit'] - count($oquv_taqsimotlar));
    if ($remainingLimitForQoshimcha > 0) {
        $qoshimcha_oquv_taqsimotlar = $db->get_qoshimcha_oquv_taqsimotlar($filters + ['limit' => $remainingLimitForQoshimcha]);
    }
}
$oquv_taqsimotlar = is_array($oquv_taqsimotlar) ? $oquv_taqsimotlar : [];
$qoshimcha_oquv_taqsimotlar = is_array($qoshimcha_oquv_taqsimotlar) ? $qoshimcha_oquv_taqsimotlar : [];

// Izoh: Jadvalda limit bo'lsa ham, soat ustunlarini bog'lash uchun qo'shimcha rejalarni to'liq olamiz.
$qoshimchaMapRows = $db->get_qoshimcha_oquv_taqsimotlar($filters + ['limit' => 0]);
$qoshimchaMapRows = is_array($qoshimchaMapRows) ? $qoshimchaMapRows : [];

$rowKeyFn = static function (array $row): string {
    $fan = trim((string)($row['fan_nomi'] ?? ''));
    $yonalish = trim((string)($row['yonalish_code'] ?? ''));
    $semestr = (string)((int)($row['semestr'] ?? 0));
    $kafedra = trim((string)($row['kafedra_nomi'] ?? ''));
    if (function_exists('mb_strtolower')) {
        return (string)@mb_strtolower($fan . '|' . $yonalish . '|' . $semestr . '|' . $kafedra, 'UTF-8');
    }
    return strtolower($fan . '|' . $yonalish . '|' . $semestr . '|' . $kafedra);
};

$qDarsToFieldMap = [
    1 => 'kurs_ishi',
    2 => 'kurs_loyiha',
    3 => 'oquv_ped_amaliyot',
    4 => 'uzluksiz_malakaviy',
    5 => 'dala_amaliyoti_otm',
    6 => 'dala_amaliyoti_tashqarida',
    7 => 'ishlab_chiqarish',
    8 => 'bmi_rahbarligi',
    9 => 'ilmiy_tadqiqot_ishi',
    10 => 'ilmiy_pedagogik_ishi',
    11 => 'ilmiy_stajirovka',
    12 => 'tayanch_doktorantura',
    13 => 'katta_ilmiy_tadqiqotchi',
    14 => 'stajyor_tadqiqotchi',
    15 => 'ochiq_dars',
    16 => 'yadak',
    17 => 'boshqa_soatlar',
    20 => 'oraliq_nazorat',
    21 => 'yakuniy_nazorat',
];
$qFieldToDarsMap = [];
foreach ($qDarsToFieldMap as $darsId => $fieldName) {
    $qFieldToDarsMap[$fieldName] = (int)$darsId;
}

$qFieldRejaIdMap = [];
$qFieldRejaIdScopeMap = [];
$qFieldSoatMap = [];
$qFieldSoatScopeMap = [];
foreach ($qDarsToFieldMap as $fieldName) {
    $qFieldRejaIdMap[$fieldName] = [];
    $qFieldRejaIdScopeMap[$fieldName] = [];
    $qFieldSoatMap[$fieldName] = [];
    $qFieldSoatScopeMap[$fieldName] = [];
}

foreach ($qoshimchaMapRows as $qRow) {
    $rid = (int)($qRow['qoshimcha_reja_id'] ?? 0);
    $k = $rowKeyFn($qRow);
    $scopeKey = trim((string)($qRow['yonalish_code'] ?? '')) . '|' . (string)((int)($qRow['semestr'] ?? 0)) . '|' . trim((string)($qRow['kafedra_nomi'] ?? ''));
    if (function_exists('mb_strtolower')) {
        $scopeKey = (string)@mb_strtolower($scopeKey, 'UTF-8');
    } else {
        $scopeKey = strtolower($scopeKey);
    }
    $qDarsId = (int)($qRow['qoshimcha_dars_id'] ?? 0);
    $fieldName = $qDarsToFieldMap[$qDarsId] ?? '';
    if ($fieldName === '') {
        continue;
    }

    $fieldSoat = (float)($qRow[$fieldName] ?? ($qRow['jami_soat'] ?? 0));
    if ($fieldSoat > 0) {
        if (!isset($qFieldSoatMap[$fieldName][$k])) {
            $qFieldSoatMap[$fieldName][$k] = 0.0;
        }
        $qFieldSoatMap[$fieldName][$k] += $fieldSoat;

        if (!isset($qFieldSoatScopeMap[$fieldName][$scopeKey])) {
            $qFieldSoatScopeMap[$fieldName][$scopeKey] = 0.0;
        }
        $qFieldSoatScopeMap[$fieldName][$scopeKey] += $fieldSoat;
    }

    if ($rid > 0 && !isset($qFieldRejaIdMap[$fieldName][$k])) {
        $qFieldRejaIdMap[$fieldName][$k] = $rid;
    }
    if ($rid > 0 && !isset($qFieldRejaIdScopeMap[$fieldName][$scopeKey])) {
        $qFieldRejaIdScopeMap[$fieldName][$scopeKey] = $rid;
    }
}

// Izoh: config.php dagi ayrim eski versiyalarda needs_resync SELECTda bo'lmasligi mumkin.
// Shu holatda fallback sifatida pending eventlarni yonalish_id bo'yicha shu faylda tekshiramiz.
$pendingYonalishMap = [];
$allYonalishIds = [];
foreach ([$oquv_taqsimotlar, $qoshimcha_oquv_taqsimotlar] as $rows) {
    foreach ($rows as $r) {
        if (!empty($r['yonalish_id'])) {
            $allYonalishIds[] = (int)$r['yonalish_id'];
        }
    }
}
$allYonalishIds = array_values(array_unique(array_filter($allYonalishIds)));
if (!empty($allYonalishIds)) {
    $pendingRows = $db->get_data_by_table_all(
        'taqsimot_resync_events',
        "WHERE status='pending' AND yonalish_id IN (" . implode(',', $allYonalishIds) . ")"
    );
    foreach ($pendingRows as $pr) {
        $pendingYonalishMap[(int)$pr['yonalish_id']] = true;
    }
}

$collectCodesFromText = static function (string $text): array {
    $text = trim($text);
    if ($text === '') {
        return [];
    }
    $parts = preg_split('/\s*[|,]\s*/', $text) ?: [];
    $codes = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('/^([0-9A-Za-z_()\-\/\.]+)/u', $part, $m)) {
            $code = trim((string)$m[1]);
            if ($code !== '') {
                $codes[$code] = true;
            }
        }
    }
    return array_keys($codes);
};

$allCodes = [];
foreach ([$oquv_taqsimotlar, $qoshimcha_oquv_taqsimotlar] as $rows) {
    foreach ($rows as $row) {
        foreach ($collectCodesFromText((string)($row['yonalish_code'] ?? '')) as $code) {
            $allCodes[$code] = true;
        }
    }
}

$codeToYonalishId = [];
if (!empty($allCodes)) {
    $codeRows = $db->get_data_by_table_all('yonalishlar');
    $targetCodes = array_fill_keys(array_keys($allCodes), true);
    foreach ($codeRows as $cr) {
        $code = trim((string)($cr['code'] ?? ''));
        $id = (int)($cr['id'] ?? 0);
        if ($code !== '' && $id > 0 && isset($targetCodes[$code])) {
            $codeToYonalishId[$code] = $id;
            $allYonalishIds[] = $id;
        }
    }
}

$allYonalishIds = array_values(array_unique(array_filter($allYonalishIds)));
$yonalishChangeTypeMap = [];
if (!empty($allYonalishIds)) {
    $historyRows = $db->query("
        SELECT gh.yonalish_id, gh.change_type
        FROM guruhlar_history gh
        INNER JOIN (
            SELECT yonalish_id, MAX(id) AS max_id
            FROM guruhlar_history
            WHERE change_type IN ('create', 'delete')
              AND yonalish_id IN (" . implode(',', array_map('intval', $allYonalishIds)) . ")
            GROUP BY yonalish_id
        ) latest ON latest.max_id = gh.id
    ");
    if ($historyRows) {
        while ($hr = mysqli_fetch_assoc($historyRows)) {
            $yid = (int)($hr['yonalish_id'] ?? 0);
            $ctype = trim((string)($hr['change_type'] ?? ''));
            if ($yid > 0 && ($ctype === 'create' || $ctype === 'delete')) {
                $yonalishChangeTypeMap[$yid] = $ctype;
            }
        }
    }
}

$normalizeGroupToken = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (strpos($value, '/') !== false) {
        $parts = explode('/', $value);
        $value = trim((string)end($parts));
    }
    if (function_exists('mb_strtolower')) {
        return (string)mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
};

$groupHistoryStatusByYonalish = [];
$activeDeletedGroupsByYonalish = [];
if (!empty($allYonalishIds)) {
    $groupHistoryRows = $db->query("
        SELECT gh.yonalish_id, gh.guruh_nomer, gh.change_type
        FROM guruhlar_history gh
        INNER JOIN (
            SELECT yonalish_id, guruh_nomer, MAX(id) AS max_id
            FROM guruhlar_history
            WHERE change_type IN ('create', 'delete')
              AND yonalish_id IN (" . implode(',', array_map('intval', $allYonalishIds)) . ")
            GROUP BY yonalish_id, guruh_nomer
        ) latest ON latest.max_id = gh.id
    ");
    if ($groupHistoryRows) {
        while ($gr = mysqli_fetch_assoc($groupHistoryRows)) {
            $yid = (int)($gr['yonalish_id'] ?? 0);
            $gname = $normalizeGroupToken((string)($gr['guruh_nomer'] ?? ''));
            $ctype = trim((string)($gr['change_type'] ?? ''));
            if ($yid > 0 && $gname !== '' && ($ctype === 'create' || $ctype === 'delete')) {
                if (!isset($groupHistoryStatusByYonalish[$yid])) {
                    $groupHistoryStatusByYonalish[$yid] = [];
                }
                $groupHistoryStatusByYonalish[$yid][$gname] = $ctype;
            }
        }
    }

    $latestDeletedRows = $db->query("
        SELECT gh.yonalish_id, gh.guruh_nomer
        FROM guruhlar_history gh
        INNER JOIN (
            SELECT yonalish_id, guruh_nomer, MAX(id) AS max_id
            FROM guruhlar_history
            WHERE change_type IN ('create', 'delete')
              AND yonalish_id IN (" . implode(',', array_map('intval', $allYonalishIds)) . ")
            GROUP BY yonalish_id, guruh_nomer
        ) ld ON ld.max_id = gh.id
        WHERE gh.change_type = 'delete'
    ");
    if ($latestDeletedRows) {
        while ($dr = mysqli_fetch_assoc($latestDeletedRows)) {
            $yid = (int)($dr['yonalish_id'] ?? 0);
            $gname = trim((string)($dr['guruh_nomer'] ?? ''));
            if ($yid > 0 && $gname !== '') {
                if (!isset($activeDeletedGroupsByYonalish[$yid])) {
                    $activeDeletedGroupsByYonalish[$yid] = [];
                }
                $activeDeletedGroupsByYonalish[$yid][] = $gname;
            }
        }
    }
}

$resolveRowGroupChange = static function (array $row) use ($collectCodesFromText, $codeToYonalishId, $yonalishChangeTypeMap, $groupHistoryStatusByYonalish, $normalizeGroupToken): string {
    $ids = [];
    $directId = (int)($row['yonalish_id'] ?? 0);
    if ($directId > 0) {
        $ids[$directId] = true;
    }
    foreach ($collectCodesFromText((string)($row['yonalish_code'] ?? '')) as $code) {
        $mappedId = (int)($codeToYonalishId[$code] ?? 0);
        if ($mappedId > 0) {
            $ids[$mappedId] = true;
        }
    }
    if (empty($ids)) {
        return '';
    }
    $groupsInRow = preg_split('/\s*\|\s*/', (string)($row['guruh_raqami'] ?? '')) ?: [];
    $hasCreate = false;
    $hasDelete = false;
    foreach (array_keys($ids) as $id) {
        if (!empty($groupHistoryStatusByYonalish[(int)$id]) && !empty($groupsInRow)) {
            foreach ($groupsInRow as $g) {
                $norm = $normalizeGroupToken((string)$g);
                if ($norm === '') {
                    continue;
                }
                $gs = $groupHistoryStatusByYonalish[(int)$id][$norm] ?? '';
                if ($gs === 'create') {
                    $hasCreate = true;
                } elseif ($gs === 'delete') {
                    $hasDelete = true;
                }
            }
        }
        $ctype = $yonalishChangeTypeMap[(int)$id] ?? '';
        if ($ctype === 'delete') {
            $hasDelete = true;
        } elseif ($ctype === 'create') {
            $hasCreate = true;
        }
    }
    if ($hasCreate) {
        return 'create';
    }
    if ($hasDelete) {
        return 'delete';
    }
    return '';
};

$resolveDeletedGroupNames = static function (array $row) use ($collectCodesFromText, $codeToYonalishId, $activeDeletedGroupsByYonalish): array {
    $ids = [];
    $directId = (int)($row['yonalish_id'] ?? 0);
    if ($directId > 0) {
        $ids[$directId] = true;
    }
    foreach ($collectCodesFromText((string)($row['yonalish_code'] ?? '')) as $code) {
        $mappedId = (int)($codeToYonalishId[$code] ?? 0);
        if ($mappedId > 0) {
            $ids[$mappedId] = true;
        }
    }
    $deleted = [];
    foreach (array_keys($ids) as $id) {
        if (!empty($activeDeletedGroupsByYonalish[(int)$id])) {
            foreach ($activeDeletedGroupsByYonalish[(int)$id] as $name) {
                $deleted[(string)$name] = true;
            }
        }
    }
    return array_keys($deleted);
};
?>
<style>
    .full-soat {
    background: #00f038 !important;   /* yashil */
    border: 2px solid #28a745;
}

.partial-soat {
    background: #ffc107ff !important;   /* sariq */
    border: 2px solid #ffc107;
}

.taqsim-info {
    font-size: 11px;
    font-weight: bold;
    margin-top: 4px;
}

.needs-resync > td {
    background: #ffe8e8 !important;
}

.resync-badge {
    display: inline-block;
    margin-top: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    color: #fff;
    background: #dc3545;
}
.guruh-cell {
    white-space: normal;
    word-break: break-word;
}
.maxsus-badge {
    display: inline-block;
    margin-left: 8px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    color: #fff;
    background: #0ea5e9;
    vertical-align: middle;
    white-space: nowrap;
}
.guruh-change-badge {
    display: inline-block;
    margin-top: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    color: #fff;
}
.guruh-change-badge.delete {
    background: #dc3545;
}
.guruh-change-badge.create {
    background: #16a34a;
}
.soat-cell {
    cursor: pointer;
    transition: box-shadow 0.15s ease, outline-color 0.15s ease;
}
.soat-cell:hover {
    outline: 2px solid #22c55e;
    outline-offset: -2px;
    box-shadow: inset 0 0 0 1px rgba(34, 197, 94, 0.35);
}

</style>
<div class="table-container-wrapper">
    <div class="zoom-controls">
        <button class="zoom-btn" onclick="zoomOut()" title="Kichiklashtirish">-</button>
        <button class="zoom-btn" onclick="resetZoom()" title="Asl o'lcham">100%</button>
        <button class="zoom-btn" onclick="zoomIn()" title="Kattalashtirish">+</button>
        <div class="zoom-level" id="zoomLevel">100%</div>
    </div>
    
    <div class="table-title">
        O'ZBEKISTON RESPUBLIKASI OLIY TA'LIM MUASSASASI<br>
        O'QITUVCHILARNING O'QUV YUKLAMASI TAQSIMOTI
    </div>
    
    <div class="table-wrapper">
        <table id="yuklamaTable">
            <thead>
                <tr>
                    <th rowspan="3">в„–</th>
                    <th rowspan="3">O'qitiladigan fan va boshqa turdagi o'quv ishlari</th>
                    <th rowspan="3">Ta'lim yo'nalishi</th>
                    <th rowspan="3" class="vertical">Guruh raqami</th>
                    <th rowspan="3" class="vertical">O'quv shakli</th>
                    <th rowspan="3" class="vertical">Kurs</th>
                    <th rowspan="3" class="vertical">Semestr</th>
                    <th rowspan="3" class="vertical">Talabalar soni</th>
                    <th rowspan="3" class="vertical">Potoklar soni</th>
                    <th rowspan="3" class="vertical">Guruhlar soni</th>
                    <th rowspan="3" class="vertical">Kichik guruhlar soni</th>

                    <th colspan="4">O'quv soatlari</th>

                    <th colspan="2">Reyting nazorati</th>

                    <th rowspan="3" class="vertical">Kurs ishi va himoyasi</th>
                    <th rowspan="3" class="vertical">Kurs loyihasi va himoyasi</th>

                    <th rowspan="3" class="vertical">O'quv-pedagogik amaliyot</th>
                    <th rowspan="3" class="vertical">Uzluksiz malakaviy amaliyot</th>
                    <th rowspan="3" class="vertical">Dala amaliyoti</th>
                    <th rowspan="3" class="vertical">Dala amaliyoti (OTM)</th>
                    <th rowspan="3" class="vertical">Ishlab chiqarish amaliyoti</th>

                    <th rowspan="3" class="vertical">BMI rahbarligi</th>

                    <th colspan="3">Magistratura</th>
                    <th colspan="3">Doktorantura</th>

                    <th rowspan="3" class="vertical">Ochiq dars</th>
                    <th rowspan="3" class="vertical">Yakuniy davlat attestatsiyasi</th>
                    <th rowspan="3" class="vertical">Boshqa soatlar</th>
                    <th rowspan="3" class="vertical">JAMI</th>
                </tr>

                <tr>
                    <th rowspan="2" class="vertical">Ma'ruza</th>
                    <th rowspan="2" class="vertical">Amaliy</th>
                    <th rowspan="2" class="vertical">Laboratoriya</th>
                    <th rowspan="2" class="vertical">Seminar</th>
                    <!-- Reyting -->
                    <th rowspan="2" class="vertical">Oraliq nazorat</th>
                    <th rowspan="2" class="vertical">Yakuniy nazorat</th>
                    <!-- Magistratura -->
                    <th rowspan="2" class="vertical">Ilmiy-tadqiqot ishi</th>
                    <th rowspan="2" class="vertical">Ilmiy-pedagogik ish</th>
                    <th rowspan="2" class="vertical">Ilmiy stajirovka</th>
                    <!-- Doktorantura -->
                    <th rowspan="2" class="vertical">Tayanch doktorantura</th>
                    <th rowspan="2" class="vertical">Katta ilmiy tadqiqotchi</th>
                    <th rowspan="2" class="vertical">Stajyor-tadqiqotchi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $counter = 1;
                function buildTaqsimotSoatMap($db, $rows): array {
                    if (!is_array($rows) || empty($rows)) {
                        return [];
                    }

                    $rejaIdsByType = [];

                    foreach ($rows as $row) {
                        $rowType = trim((string)($row['taqsimot_type'] ?? 'A'));
                        if (!in_array($rowType, ['A', 'Q', 'M'], true)) {
                            $rowType = 'A';
                        }
                        foreach ([
                            'maruza_reja_id',
                            'amaliy_reja_id',
                            'laboratoriya_reja_id',
                            'seminar_reja_id',
                            'legacy_maruza_reja_id',
                            'legacy_amaliy_reja_id',
                            'legacy_laboratoriya_reja_id',
                            'legacy_seminar_reja_id',
                        ] as $field) {
                            $rejaId = (int)($row[$field] ?? 0);
                            if ($rejaId > 0) {
                                if (!isset($rejaIdsByType[$rowType])) {
                                    $rejaIdsByType[$rowType] = [];
                                }
                                $rejaIdsByType[$rowType][$rejaId] = true;
                            }
                        }
                    }

                    if (empty($rejaIdsByType)) {
                        return [];
                    }

                    $resultMap = [];
                    foreach ($rejaIdsByType as $rowType => $ids) {
                        $rejaIds = array_keys($ids);
                        foreach (array_chunk($rejaIds, 500) as $idChunk) {
                            $sql = "
                                SELECT oquv_reja_id, SUM(soat) AS jami_soat
                                FROM taqsimotlar
                                WHERE type = '{$rowType}'
                                  AND oquv_reja_id IN (" . implode(',', array_map('intval', $idChunk)) . ")
                                GROUP BY oquv_reja_id
                            ";
                            $queryResult = $db->query($sql);
                            if ($queryResult === false) {
                                continue;
                            }

                            while ($taqsimotRow = mysqli_fetch_assoc($queryResult)) {
                                $rid = (int)($taqsimotRow['oquv_reja_id'] ?? 0);
                                $resultMap[$rowType . ':' . $rid] = (float)($taqsimotRow['jami_soat'] ?? 0);
                            }
                        }
                    }

                    return $resultMap;
                }
                function getMappedTaqsimotSoat($db, array &$taqsimotSoatMap, int $rejaId, string $type = 'A', string $soatTuri = ''): float {
                    if ($rejaId <= 0) {
                        return 0.0;
                    }

                    $type = trim($type);
                    if (!in_array($type, ['A', 'Q', 'M'], true)) {
                        $type = 'A';
                    }
                    $useScopedSoatTuri = legacy_is_scoped_taqsimot_soat_turi($soatTuri);
                    $cacheKey = $useScopedSoatTuri
                        ? ($type . ':' . $rejaId . ':' . $soatTuri)
                        : ($type . ':' . $rejaId);

                    if (array_key_exists($cacheKey, $taqsimotSoatMap)) {
                        return (float)$taqsimotSoatMap[$cacheKey];
                    }

                    $sql = "
                        SELECT SUM(soat) AS jami_soat
                        FROM taqsimotlar
                        WHERE type = '{$type}' AND oquv_reja_id = {$rejaId}
                    ";
                    if ($useScopedSoatTuri) {
                        $sql .= " AND COALESCE(soat_turi, '') = '" . addslashes($soatTuri) . "'";
                    }
                    $queryResult = $db->query($sql);
                    $jamiSoat = 0.0;
                    if ($queryResult !== false) {
                        $taqsimotRow = mysqli_fetch_assoc($queryResult);
                        $jamiSoat = (float)($taqsimotRow['jami_soat'] ?? 0);
                    }

                    $taqsimotSoatMap[$cacheKey] = $jamiSoat;
                    return $jamiSoat;
                }
                function getTaqsimotSoatWithLegacy(
                    $db,
                    array &$taqsimotSoatMap,
                    int $rejaId,
                    int $legacyRejaId,
                    string $type,
                    bool $allowLegacy
                ): float {
                    $assigned = getMappedTaqsimotSoat($db, $taqsimotSoatMap, $rejaId, $type);
                    if ($assigned > 0 || !$allowLegacy || $legacyRejaId <= 0 || $legacyRejaId === $rejaId) {
                        return $assigned;
                    }

                    return getMappedTaqsimotSoat($db, $taqsimotSoatMap, $legacyRejaId, $type);
                }
                function getCellClass($jami, $max) {
                    if ($max <= 0) return '';
                    if ($jami == $max) return 'full-soat';     
                    if ($jami < $max && $jami > 0)  return 'partial-soat';  
                    return '';
                }
                function formatSoatValue($value): string {
                    $num = (float)$value;
                    if ($num <= 0) {
                        return '';
                    }
                    return rtrim(rtrim(number_format($num, 2, '.', ''), '0'), '.');
                }
                function resolvePrimaryRejaId(array $row): int {
                    foreach ([
                        'maruza_reja_id',
                        'amaliy_reja_id',
                        'laboratoriya_reja_id',
                        'seminar_reja_id',
                        'legacy_maruza_reja_id',
                        'legacy_amaliy_reja_id',
                        'legacy_laboratoriya_reja_id',
                        'legacy_seminar_reja_id',
                    ] as $field) {
                        $rejaId = (int)($row[$field] ?? 0);
                        if ($rejaId > 0) {
                            return $rejaId;
                        }
                    }

                    return 0;
                }
                function isMaxsusLikeRow(array $row): bool {
                    $rowType = strtoupper(trim((string)($row['taqsimot_type'] ?? 'A')));
                    if ($rowType === 'M' || !empty($row['is_maxsus'])) {
                        return true;
                    }

                    $guruhRaqamiRaw = trim((string)($row['guruh_raqami'] ?? ''));
                    if (function_exists('mb_strtolower')) {
                        $guruhRaqamiRaw = (string)@mb_strtolower($guruhRaqamiRaw, 'UTF-8');
                    } else {
                        $guruhRaqamiRaw = strtolower($guruhRaqamiRaw);
                    }

                    return strpos($guruhRaqamiRaw, 'iqtidor') !== false;
                }
                function renderQSoatCell(
                    string $field,
                    array $qCellData,
                    array $qFieldToDarsMap,
                    array $row,
                    Database $db,
                    array &$taqsimotSoatMap
                ): string {
                    global $filters;
                    $rid = (int)($qCellData[$field]['rid'] ?? 0);
                    $soat = (float)($qCellData[$field]['soat'] ?? 0);
                    $isScopedSoatTuri = legacy_is_scoped_taqsimot_soat_turi($field);
                    $rowType = strtoupper(trim((string)($row['taqsimot_type'] ?? 'A')));
                    if ($rowType === '') {
                        $rowType = 'A';
                    }
                    if (isMaxsusLikeRow($row) && ($field === 'oraliq_nazorat' || $field === 'yakuniy_nazorat')) {
                        $rid = 0;
                        $soat = 0.0;
                    } elseif ($isScopedSoatTuri) {
                        $rid = $soat > 0 ? resolvePrimaryRejaId($row) : 0;
                    }
                    $clickable = $soat > 0;
                    $assigned = $rid > 0
                        ? getMappedTaqsimotSoat($db, $taqsimotSoatMap, $rid, $isScopedSoatTuri ? $rowType : 'Q', $isScopedSoatTuri ? $field : '')
                        : 0.0;
                    $classes = [];
                    if ($clickable) {
                        $classes[] = 'soat-cell';
                    }
                    if ($rid > 0) {
                        $cellClass = getCellClass($assigned, $soat);
                        if ($cellClass !== '') {
                            $classes[] = $cellClass;
                        }
                    }

                    $attrs = [
                        'class' => implode(' ', $classes),
                        'data-type' => $isScopedSoatTuri ? $rowType : 'Q',
                        'data-yuklama-id' => (string)$rid,
                        'data-soat-turi' => $field,
                        'data-max-soat' => (string)$soat,
                        'data-q-dars-id' => (string)((int)($qFieldToDarsMap[$field] ?? 0)),
                        'data-yonalish-id' => (string)((int)($row['yonalish_id'] ?? 0)),
                        'data-semestr' => (string)((int)($row['semestr'] ?? 0)),
                        'data-kafedra-id' => (string)((int)($row['kafedra_id'] ?? ($filters['kafedra_id'] ?? 0))),
                        'data-kafedra-nomi' => (string)($row['kafedra_nomi'] ?? ''),
                        'data-fan-nomi' => (string)($row['fan_nomi'] ?? ''),
                    ];

                    $attrHtml = '';
                    foreach ($attrs as $name => $value) {
                        if ($name === 'class' && trim($value) === '') {
                            continue;
                        }
                        $attrHtml .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
                    }

                    return '<td' . $attrHtml . '>' . formatSoatValue($soat) . '</td>';
                }
                function renderExistingQRejaCell(string $field, array $row, Database $db, array &$taqsimotSoatMap): string {
                    $rid = (int)($row['qoshimcha_reja_id'] ?? 0);
                    $soat = (float)($row[$field] ?? 0);
                    if (isMaxsusLikeRow($row) && ($field === 'oraliq_nazorat' || $field === 'yakuniy_nazorat')) {
                        $rid = 0;
                        $soat = 0.0;
                    }
                    $assigned = $rid > 0 ? getMappedTaqsimotSoat($db, $taqsimotSoatMap, $rid, 'Q') : 0.0;
                    $classes = ['soat-cell'];
                    $cellClass = getCellClass($assigned, $soat);
                    if ($cellClass !== '') {
                        $classes[] = $cellClass;
                    }

                    return '<td class="' . htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') . '"'
                        . ' data-type="Q"'
                        . ' data-yuklama-id="' . $rid . '"'
                        . ' data-soat-turi="' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '"'
                        . ' data-max-soat="' . htmlspecialchars((string)$soat, ENT_QUOTES, 'UTF-8') . '">'
                        . formatSoatValue($soat)
                        . '</td>';
                }
                $taqsimotSoatMap = buildTaqsimotSoatMap($db, $oquv_taqsimotlar);
                if (!empty($oquv_taqsimotlar) || !empty($qoshimcha_oquv_taqsimotlar)):
                    foreach ($oquv_taqsimotlar as $row): 
                        $needsResync = !empty($row['needs_resync']);
                        $rowGroupChange = $resolveRowGroupChange($row);
                        $deletedGroupNames = $resolveDeletedGroupNames($row);
                        $rowYonalishId = !empty($row['yonalish_id']) ? (int)$row['yonalish_id'] : 0;
                        if (!$needsResync && $rowYonalishId > 0 && !empty($pendingYonalishMap[$rowYonalishId])) {
                            $needsResync = true;
                        }
                        $maruzaRejaId = (int)($row['maruza_reja_id'] ?? 0);
                        $amaliyRejaId = (int)($row['amaliy_reja_id'] ?? 0);
                        $labRejaId = (int)($row['laboratoriya_reja_id'] ?? 0);
                        $seminarRejaId = (int)($row['seminar_reja_id'] ?? 0);
                        $legacyMaruzaRejaId = (int)($row['legacy_maruza_reja_id'] ?? 0);
                        $legacyAmaliyRejaId = (int)($row['legacy_amaliy_reja_id'] ?? 0);
                        $legacyLabRejaId = (int)($row['legacy_laboratoriya_reja_id'] ?? 0);
                        $legacySeminarRejaId = (int)($row['legacy_seminar_reja_id'] ?? 0);
                        $allowLegacyTaqsimot = !empty($row['is_legacy_tanlov_owner']);
                        $rowType = strtoupper(trim((string)($row['taqsimot_type'] ?? 'A')));
                        if ($rowType === '') {
                            $rowType = 'A';
                        }
                        $maruzaJami = getTaqsimotSoatWithLegacy($db, $taqsimotSoatMap, $maruzaRejaId, $legacyMaruzaRejaId, $rowType, $allowLegacyTaqsimot);
                        $amaliyJami = getTaqsimotSoatWithLegacy($db, $taqsimotSoatMap, $amaliyRejaId, $legacyAmaliyRejaId, $rowType, $allowLegacyTaqsimot);
                        $labJami = getTaqsimotSoatWithLegacy($db, $taqsimotSoatMap, $labRejaId, $legacyLabRejaId, $rowType, $allowLegacyTaqsimot);
                        $seminarJami = getTaqsimotSoatWithLegacy($db, $taqsimotSoatMap, $seminarRejaId, $legacySeminarRejaId, $rowType, $allowLegacyTaqsimot);

                        // Izoh: Yuklama sahifasidagi qoida bilan bir xil reyting nazorat hisob-kitobi.
                        $rejaMaruza = (float)($row['reja_maruz'] ?? ($row['maruza_soat'] ?? 0));
                        $rejaAmaliy = (float)($row['reja_amaliy'] ?? ($row['amaliy_soat'] ?? 0));
                        $rejaLab = (float)($row['reja_laboratoriya'] ?? ($row['laboratoriya_soat'] ?? 0));
                        $rejaSeminar = (float)($row['reja_seminar'] ?? ($row['seminar_soat'] ?? 0));
                        $talabaSoni = (int)($row['talabalar_soni'] ?? 0);
                        $auditoriyaSoat = $rejaMaruza + $rejaAmaliy + $rejaLab + $rejaSeminar;
                        $shaklRaw = trim((string)($row['oquv_shakli'] ?? ''));
                        $shakl = function_exists('mb_strtolower')
                            ? (string)@mb_strtolower($shaklRaw, 'UTF-8')
                            : strtolower($shaklRaw);
                        $isMasofaviy = strpos($shakl, 'masof') !== false;
                        $isMaxsusRow = ($rowType === 'M' || !empty($row['is_maxsus']));
                        $examType = $resolveExamType($row);
                        $oraliqNazorat = 0;
                        if (!$isMaxsusRow && !$isMasofaviy && $talabaSoni > 0) {
                            if ($auditoriyaSoat >= 60) {
                                $oraliqNazorat = (int)round($talabaSoni * 0.4);
                            } elseif ($auditoriyaSoat >= 30) {
                                $oraliqNazorat = (int)round($talabaSoni * 0.2);
                            }
                        }
                        $yakuniyNazorat = 0;
                        if ($examType !== 'T') {
                            $yakuniyNazorat = (!$isMaxsusRow && $talabaSoni > 0 && $auditoriyaSoat > 0)
                                ? (int)round($talabaSoni * 0.3)
                                : 0;
                        }
                        $rowKey = $rowKeyFn($row);
                        $scopeKey = trim((string)($row['yonalish_code'] ?? '')) . '|' . (string)((int)($row['semestr'] ?? 0)) . '|' . trim((string)($row['kafedra_nomi'] ?? ''));
                        if (function_exists('mb_strtolower')) {
                            $scopeKey = (string)@mb_strtolower($scopeKey, 'UTF-8');
                        } else {
                            $scopeKey = strtolower($scopeKey);
                        }
                        $qFields = [
                            'oraliq_nazorat',
                            'yakuniy_nazorat',
                            'kurs_ishi',
                            'kurs_loyiha',
                            'oquv_ped_amaliyot',
                            'uzluksiz_malakaviy',
                            'dala_amaliyoti_otm',
                            'dala_amaliyoti_tashqarida',
                            'ishlab_chiqarish',
                            'bmi_rahbarligi',
                            'ilmiy_tadqiqot_ishi',
                            'ilmiy_pedagogik_ishi',
                            'ilmiy_stajirovka',
                            'tayanch_doktorantura',
                            'katta_ilmiy_tadqiqotchi',
                            'stajyor_tadqiqotchi',
                            'ochiq_dars',
                            'yadak',
                            'boshqa_soatlar',
                        ];
                        $qCellData = [];
                        foreach ($qFields as $qField) {
                            // Izoh: Qo'shimcha soatlar boshqa fan/qatorga ko'chib ketmasligi uchun
                            // faqat aniq row-key bo'yicha bog'laymiz (scope fallback ishlatilmaydi).
                            $mappedId = (int)($qFieldRejaIdMap[$qField][$rowKey] ?? 0);
                            $mappedSoat = (float)($qFieldSoatMap[$qField][$rowKey] ?? 0);
                            $directSoat = (float)($row[$qField] ?? 0);
                            $finalSoat = max($mappedSoat, $directSoat);
                            $qCellData[$qField] = [
                                'rid' => $mappedId,
                                'soat' => $finalSoat,
                            ];
                        }
                        if ($isMaxsusRow) {
                            $qCellData['oraliq_nazorat']['soat'] = 0;
                            $qCellData['yakuniy_nazorat']['soat'] = 0;
                            $qCellData['oraliq_nazorat']['rid'] = 0;
                            $qCellData['yakuniy_nazorat']['rid'] = 0;
                        } else {
                            // Izoh: Reyting formulasi bo'yicha hisoblangan qiymat bo'lsa, uni ustuvor ko'rsatamiz.
                            if ($oraliqNazorat > 0) {
                                $qCellData['oraliq_nazorat']['soat'] = max((float)$qCellData['oraliq_nazorat']['soat'], (float)$oraliqNazorat);
                            }
                            if ($yakuniyNazorat > 0) {
                                $qCellData['yakuniy_nazorat']['soat'] = max((float)$qCellData['yakuniy_nazorat']['soat'], (float)$yakuniyNazorat);
                            }
                        }
                ?>
                <tr class="<?= $needsResync ? 'needs-resync' : '' ?>">
                    <td><?= $counter++ ?></td>
                    <td class="left fan-nomi">
                        <?= htmlspecialchars($row['fan_nomi']) ?>
                        <?php if ($rowType === 'M' || !empty($row['is_maxsus'])): ?>
                            <span class="maxsus-badge">Maxsus guruh</span>
                        <?php endif; ?>
                    </td>
                    <td class="left">
                        <?= htmlspecialchars($row['yonalish_code'] . ' - ' . $row['talim_yonalishi']) ?>
                        <?php if ($needsResync): ?>
                            <div class="resync-badge">Qayta taqsimot kerak</div>
                        <?php endif; ?>
                    </td>
                    <td class="guruh-cell">
                        <?= htmlspecialchars($row['guruh_raqami']) ?>
                        <?php if (!empty($deletedGroupNames) || $rowGroupChange === 'delete'): ?>
                            <div class="guruh-change-badge delete"><?= htmlspecialchars((!empty($deletedGroupNames) ? implode(', ', $deletedGroupNames) . ' ' : '') . "o'chirilgan", ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if ($rowGroupChange === 'create'): ?>
                            <div class="guruh-change-badge create">Guruh qo'shilgan</div>
                        <?php endif; ?>
                    </td>
                    <td><?= $row['oquv_shakli'] ?></td>
                    <td><?= $row['kurs'] ?></td>
                    <td><?= $row['semestr'] ?></td>
                    <td><?= $row['talabalar_soni'] ?></td>
                    <td><?= $row['patok_soni'] ?></td>
                    <td><?= $row['kattaguruh_soni'] ?></td>
                    <td><?= $row['kichikguruh_soni'] ?></td>
                    <td class="soat-cell  <?= getCellClass($maruzaJami, $row['amalda_maruz']) ?>"
                        data-type="<?= htmlspecialchars($rowType) ?>"
                        data-yuklama-id="<?= $row['maruza_reja_id'] ?: 0 ?>"
                        data-legacy-yuklama-id="<?= $allowLegacyTaqsimot ? ($legacyMaruzaRejaId ?: 0) : 0 ?>"
                        data-soat-turi="amalda_maruz"
                        data-max-soat="<?= $row['amalda_maruz'] ?>">
                        <?= $row['amalda_maruz'] ?: '' ?>
                        
                    </td>
                    <!-- AMALIY -->
                    <td class="soat-cell <?= getCellClass($amaliyJami, $row['amalda_amaliy']) ?>"
                        data-type="<?= htmlspecialchars($rowType) ?>"
                        data-yuklama-id="<?= $row['amaliy_reja_id'] ?: 0 ?>"
                        data-legacy-yuklama-id="<?= $allowLegacyTaqsimot ? ($legacyAmaliyRejaId ?: 0) : 0 ?>"
                        data-soat-turi="amalda_amaliy"
                        data-max-soat="<?= $row['amalda_amaliy'] ?: 0 ?>">
                        <?= $row['amalda_amaliy'] ?: '' ?>
                        
                    </td>
                    <!-- LAB -->
                    <td class="soat-cell <?= getCellClass($labJami, $row['amalda_laboratoriya']) ?>"
                        data-type="<?= htmlspecialchars($rowType) ?>"
                        data-yuklama-id="<?= $row['laboratoriya_reja_id'] ?: 0 ?>"
                        data-legacy-yuklama-id="<?= $allowLegacyTaqsimot ? ($legacyLabRejaId ?: 0) : 0 ?>"
                        data-soat-turi="amalda_laboratoriya"
                        data-max-soat="<?= $row['amalda_laboratoriya'] ?: 0 ?>">
                        <?= $row['amalda_laboratoriya'] ?: '' ?>
                        
                    </td>
                    <!-- SEMINAR -->
                    <td class="soat-cell <?= getCellClass($seminarJami, $row['amalda_seminar']) ?>"
                        data-type="<?= htmlspecialchars($rowType) ?>"
                        data-yuklama-id="<?= $row['seminar_reja_id'] ?: 0 ?>"
                        data-legacy-yuklama-id="<?= $allowLegacyTaqsimot ? ($legacySeminarRejaId ?: 0) : 0 ?>"
                        data-soat-turi="amalda_seminar"
                        data-max-soat="<?= $row['amalda_seminar'] ?: 0 ?>">
                        <?= $row['amalda_seminar'] ?: '' ?>
                        
                    </td>
                    <!-- Reyting -->
                    <?= renderQSoatCell('oraliq_nazorat', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    <?= renderQSoatCell('yakuniy_nazorat', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    <!-- Kurs ishlari -->
                    <?= renderQSoatCell('kurs_ishi', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    <?= renderQSoatCell('kurs_loyiha', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    <!-- Malakaviy amaliyot -->
                    <?= renderQSoatCell('oquv_ped_amaliyot', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    <?= renderQSoatCell('uzluksiz_malakaviy', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    <?= renderQSoatCell('dala_amaliyoti_otm', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    <?= renderQSoatCell('dala_amaliyoti_tashqarida', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    <?= renderQSoatCell('ishlab_chiqarish', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    <!-- BMI rahbarligi -->
                    <?= renderQSoatCell('bmi_rahbarligi', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    
                    <!-- Magistratura -->
                    <?= renderQSoatCell('ilmiy_tadqiqot_ishi', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    <?= renderQSoatCell('ilmiy_pedagogik_ishi', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    <?= renderQSoatCell('ilmiy_stajirovka', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    
                    <!-- Doktorantura -->
                    <?= renderQSoatCell('tayanch_doktorantura', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    <?= renderQSoatCell('katta_ilmiy_tadqiqotchi', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    <?= renderQSoatCell('stajyor_tadqiqotchi', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    
                    <!-- Qo'shimcha soatlar -->
                    <?= renderQSoatCell('ochiq_dars', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    <?= renderQSoatCell('yadak', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    <?= renderQSoatCell('boshqa_soatlar', $qCellData, $qFieldToDarsMap, $row, $db, $taqsimotSoatMap) ?>
                    
                    <!-- JAMI soat -->
                    <td class="total-cell">
                        <?= $row['jami_soat'] ?> 
                    </td>
                </tr>
                <?php 
                    endforeach;
                    foreach ($qoshimcha_oquv_taqsimotlar as $row):
                        $needsResync = !empty($row['needs_resync']);
                        $rowGroupChange = $resolveRowGroupChange($row);
                        $deletedGroupNames = $resolveDeletedGroupNames($row);
                        $rowYonalishId = !empty($row['yonalish_id']) ? (int)$row['yonalish_id'] : 0;
                        if (!$needsResync && $rowYonalishId > 0 && !empty($pendingYonalishMap[$rowYonalishId])) {
                            $needsResync = true;
                        }
                        $qoshimchaBaseName = (int)($row['qoshimcha_dars_id'] ?? 0) === 16
                            ? 'YADAK'
                            : (string)($row['fan_nomi'] ?? '');
                        $qoshimchaFanNomi = legacy_qoshimcha_display_name(
                            $qoshimchaBaseName,
                            (int)($row['qoshimcha_dars_id'] ?? 0),
                            (string)($row['subtype_code'] ?? '')
                        );
                ?>
                <tr class="<?= $needsResync ? 'needs-resync' : '' ?>">
                    <td><?= $counter++ ?></td>
                    <td class="left fan-nomi"><?= htmlspecialchars($qoshimchaFanNomi) ?></td>
                    <td class="left">
                        <?= htmlspecialchars($row['yonalish_code'] . ' - ' . $row['talim_yonalishi']) ?>
                        <?php if ($needsResync): ?>
                            <div class="resync-badge">Qayta taqsimot kerak</div>
                        <?php endif; ?>
                    </td>
                    <td class="guruh-cell">
                        <?= htmlspecialchars($row['guruh_raqami']) ?>
                        <?php if (!empty($deletedGroupNames) || $rowGroupChange === 'delete'): ?>
                            <div class="guruh-change-badge delete"><?= htmlspecialchars((!empty($deletedGroupNames) ? implode(', ', $deletedGroupNames) . ' ' : '') . "o'chirilgan", ENT_QUOTES, 'UTF-8') ?></div>
                        <?php endif; ?>
                        <?php if ($rowGroupChange === 'create'): ?>
                            <div class="guruh-change-badge create">Guruh qo'shilgan</div>
                        <?php endif; ?>
                    </td>
                    <td><?= $row['oquv_shakli'] ?></td>
                    <td><?= $row['kurs'] ?></td>
                    <td><?= $row['semestr'] ?></td>
                    <td><?= $row['talabalar_soni'] ?></td>
                    <td><?= $row['patok_soni'] ?></td>
                    <td><?= $row['kattaguruh_soni'] ?></td>
                    <td><?= $row['kichikguruh_soni'] ?></td>
                    
                    <!-- Amalda bajarilgan -->
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <!-- Reyting nazorati -->
                    <?= renderExistingQRejaCell('oraliq_nazorat', $row, $db, $taqsimotSoatMap) ?>
                    <?= renderExistingQRejaCell('yakuniy_nazorat', $row, $db, $taqsimotSoatMap) ?>
                    <?= renderExistingQRejaCell('kurs_ishi', $row, $db, $taqsimotSoatMap) ?>
                    <?= renderExistingQRejaCell('kurs_loyiha', $row, $db, $taqsimotSoatMap) ?>
                    <!-- Malakaviy amaliyot -->
                    <?= renderExistingQRejaCell('oquv_ped_amaliyot', $row, $db, $taqsimotSoatMap) ?>
                    <?= renderExistingQRejaCell('uzluksiz_malakaviy', $row, $db, $taqsimotSoatMap) ?>
                    <?= renderExistingQRejaCell('dala_amaliyoti_otm', $row, $db, $taqsimotSoatMap) ?>
                    <?= renderExistingQRejaCell('dala_amaliyoti_tashqarida', $row, $db, $taqsimotSoatMap) ?>
                    <?= renderExistingQRejaCell('ishlab_chiqarish', $row, $db, $taqsimotSoatMap) ?>
                    <!-- BMI rahbarligi -->
                    <?= renderExistingQRejaCell('bmi_rahbarligi', $row, $db, $taqsimotSoatMap) ?>
                    <!-- Magistratura -->
                    <?= renderExistingQRejaCell('ilmiy_tadqiqot_ishi', $row, $db, $taqsimotSoatMap) ?>
                    <?= renderExistingQRejaCell('ilmiy_pedagogik_ishi', $row, $db, $taqsimotSoatMap) ?>
                    <?= renderExistingQRejaCell('ilmiy_stajirovka', $row, $db, $taqsimotSoatMap) ?>
                    <!-- Doktorantura -->
                    <?= renderExistingQRejaCell('tayanch_doktorantura', $row, $db, $taqsimotSoatMap) ?>
                    <?= renderExistingQRejaCell('katta_ilmiy_tadqiqotchi', $row, $db, $taqsimotSoatMap) ?>
                    <?= renderExistingQRejaCell('stajyor_tadqiqotchi', $row, $db, $taqsimotSoatMap) ?>
                    
                    <!-- Qo'shimcha soatlar -->
                    <?= renderExistingQRejaCell('ochiq_dars', $row, $db, $taqsimotSoatMap) ?>
                    <?= renderExistingQRejaCell('yadak', $row, $db, $taqsimotSoatMap) ?>
                    <?= renderExistingQRejaCell('boshqa_soatlar', $row, $db, $taqsimotSoatMap) ?>
                    <!-- JAMI soat -->
                    <td class="total-cell">
                        <?= $row['jami_soat'] ?> 
                    </td>
                </tr>
                <?php 
                    endforeach;
                else: ?>
                <tr>
                    <td colspan="37" style="text-align: center; padding: 20px;">
                        <i class="fas fa-info-circle"></i> Ma'lumotlar mavjud emas
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
