<?php
include_once '../config.php';
$db = new Database();

// Izoh: Og'ir hisobot so'rovlari uchun bajarilish vaqtini sekundlarda oshiramiz.
if (function_exists('set_time_limit')) {
    @set_time_limit(60);
}
@ini_set('max_execution_time', '60');

$filters = [];
$rowLimit = 150;
if (isset($_POST['show_all']) && (int)$_POST['show_all'] === 1) {
    $filters['limit'] = 0;
} else {
    $filters['limit'] = $rowLimit;
}
if (isset($_POST['kafedra_id']) && !empty($_POST['kafedra_id'])) {
    $kafedraIdRaw = trim((string)$_POST['kafedra_id']);
    if ($kafedraIdRaw !== 'all') {
        $filters['kafedra_id'] = (int)$kafedraIdRaw;
    }
}
if (isset($_POST['semestr']) && !empty($_POST['semestr'])) {
    $filters['semestr'] = (int)$_POST['semestr'];
}
if (isset($_POST['oquv_yil_start']) && !empty($_POST['oquv_yil_start'])) {
    $oquvYilStartRaw = trim((string)$_POST['oquv_yil_start']);
    if ($oquvYilStartRaw !== 'all') {
        $filters['oquv_yil_start'] = (int)$oquvYilStartRaw;
    }
}
if (isset($_POST['semestr_turi']) && !empty($_POST['semestr_turi'])) {
    $semestrTuri = trim((string)$_POST['semestr_turi']);
    if ($semestrTuri !== 'all') {
        $filters['semestr_turi'] = $semestrTuri;
    }
}
if (isset($_POST['yonalish_id']) && !empty($_POST['yonalish_id'])) {
    $yonalishIdRaw = trim((string)$_POST['yonalish_id']);
    if ($yonalishIdRaw !== 'all') {
        $filters['yonalish_id'] = (int)$yonalishIdRaw;
    }
}
if (isset($_POST['kurs']) && !empty($_POST['kurs'])) {
    $kursRaw = trim((string)$_POST['kurs']);
    if ($kursRaw !== 'all') {
        $filters['kurs'] = (int)$kursRaw;
    }
}
legacy_apply_kafedra_scope($filters);

$magistrDoktorantOnly = isset($_POST['magistr_doktorant_only']) && (int)$_POST['magistr_doktorant_only'] === 1;
$oquv_yuklamalar = $magistrDoktorantOnly ? [] : $db->get_oquv_yuklamalar($filters);
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
$maxsus_oquv_yuklamalar = [];
if (!$magistrDoktorantOnly) {
    if (empty($filters['limit']) || (int)$filters['limit'] === 0) {
        $maxsus_oquv_yuklamalar = $db->get_maxsus_oquv_yuklamalar($filters);
    } else {
        $remainingLimitForMaxsus = max(0, (int)$filters['limit'] - count($oquv_yuklamalar));
        if ($remainingLimitForMaxsus > 0) {
            $maxsus_oquv_yuklamalar = $db->get_maxsus_oquv_yuklamalar($filters + ['limit' => $remainingLimitForMaxsus]);
        }
    }
    if (!empty($maxsus_oquv_yuklamalar)) {
        $oquv_yuklamalar = array_merge($oquv_yuklamalar, $maxsus_oquv_yuklamalar);
        usort($oquv_yuklamalar, static function (array $a, array $b): int {
            $aSem = (int)($a['semestr'] ?? 0);
            $bSem = (int)($b['semestr'] ?? 0);
            if ($aSem !== $bSem) {
                return $aSem <=> $bSem;
            }
            return strcmp((string)($a['fan_name'] ?? ''), (string)($b['fan_name'] ?? ''));
        });
    }
}
$qoshimcha_yuklamalar = [];
if ($magistrDoktorantOnly) {
    $qoshimcha_yuklamalar = $db->get_magistr_doktorant_yuklamalar($filters);
} elseif (empty($filters['limit']) || (int)$filters['limit'] === 0) {
    $qoshimcha_yuklamalar = $db->get_qoshimcha_oquv_yuklamalar($filters);
} else {
    $remainingLimit = max(0, (int)$filters['limit'] - count($oquv_yuklamalar));
    if ($remainingLimit > 0) {
        $qoshimcha_yuklamalar = $db->get_qoshimcha_oquv_yuklamalar($filters + ['limit' => $remainingLimit]);
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

$rowToCodes = [];
$allYonalishCodes = [];
foreach ([$oquv_yuklamalar, $qoshimcha_yuklamalar] as $rows) {
    foreach ($rows as $idx => $row) {
        $rowKey = md5(json_encode([
            $row['fan_name'] ?? $row['fan_nomi'] ?? '',
            $row['yonalish_code'] ?? '',
            $row['biriktirilgan_yonalish_code'] ?? '',
            $row['semestr'] ?? '',
            $idx,
        ], JSON_UNESCAPED_UNICODE));
        $codes = [];
        $codes = array_merge($codes, $collectCodesFromText((string)($row['yonalish_code'] ?? '')));
        $codes = array_merge($codes, $collectCodesFromText((string)($row['biriktirilgan_yonalish_code'] ?? '')));
        $codes = array_values(array_unique($codes));
        if (!empty($codes)) {
            $rowToCodes[$rowKey] = $codes;
            foreach ($codes as $code) {
                $allYonalishCodes[$code] = true;
            }
        }
    }
}

$codeToYonalishId = [];
if (!empty($allYonalishCodes)) {
    $codeRows = $db->get_data_by_table_all('yonalishlar');
    $targetCodes = array_fill_keys(array_keys($allYonalishCodes), true);
    foreach ($codeRows as $cr) {
        $code = trim((string)($cr['code'] ?? ''));
        $id = (int)($cr['id'] ?? 0);
        if ($code !== '' && $id > 0 && isset($targetCodes[$code])) {
            $codeToYonalishId[$code] = $id;
        }
    }
}

$yonalishChangeTypeMap = [];
if (!empty($codeToYonalishId)) {
    $ids = array_values(array_unique(array_values($codeToYonalishId)));
    $historyRows = $db->query("
        SELECT gh.yonalish_id, gh.change_type
        FROM guruhlar_history gh
        INNER JOIN (
            SELECT yonalish_id, MAX(id) AS max_id
            FROM guruhlar_history
            WHERE change_type IN ('create', 'delete')
              AND yonalish_id IN (" . implode(',', array_map('intval', $ids)) . ")
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
if (!empty($codeToYonalishId)) {
    $ids = array_values(array_unique(array_values($codeToYonalishId)));
    $groupHistoryRows = $db->query("
        SELECT gh.yonalish_id, gh.guruh_nomer, gh.change_type
        FROM guruhlar_history gh
        INNER JOIN (
            SELECT yonalish_id, guruh_nomer, MAX(id) AS max_id
            FROM guruhlar_history
            WHERE change_type IN ('create', 'delete')
              AND yonalish_id IN (" . implode(',', array_map('intval', $ids)) . ")
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
              AND yonalish_id IN (" . implode(',', array_map('intval', $ids)) . ")
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

$resolveRowGroupChange = static function (array $row, int $idx) use ($rowToCodes, $codeToYonalishId, $yonalishChangeTypeMap, $groupHistoryStatusByYonalish, $normalizeGroupToken): string {
    $rowKey = md5(json_encode([
        $row['fan_name'] ?? $row['fan_nomi'] ?? '',
        $row['yonalish_code'] ?? '',
        $row['biriktirilgan_yonalish_code'] ?? '',
        $row['semestr'] ?? '',
        $idx,
    ], JSON_UNESCAPED_UNICODE));
    $codes = $rowToCodes[$rowKey] ?? [];
    if (empty($codes)) {
        return '';
    }
    $groupsInRow = preg_split('/\s*\|\s*/', (string)($row['guruh_raqami'] ?? '')) ?: [];
    $hasCreate = false;
    $hasDelete = false;
    foreach ($codes as $code) {
        $yid = (int)($codeToYonalishId[$code] ?? 0);
        if ($yid <= 0) {
            continue;
        }
        if (!empty($groupHistoryStatusByYonalish[$yid]) && !empty($groupsInRow)) {
            foreach ($groupsInRow as $g) {
                $norm = $normalizeGroupToken((string)$g);
                if ($norm === '') {
                    continue;
                }
                $gs = $groupHistoryStatusByYonalish[$yid][$norm] ?? '';
                if ($gs === 'create') {
                    $hasCreate = true;
                } elseif ($gs === 'delete') {
                    $hasDelete = true;
                }
            }
        }
        $ctype = $yonalishChangeTypeMap[$yid] ?? '';
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

$resolveDeletedGroupNames = static function (array $row, int $idx) use ($rowToCodes, $codeToYonalishId, $activeDeletedGroupsByYonalish): array {
    $rowKey = md5(json_encode([
        $row['fan_name'] ?? $row['fan_nomi'] ?? '',
        $row['yonalish_code'] ?? '',
        $row['biriktirilgan_yonalish_code'] ?? '',
        $row['semestr'] ?? '',
        $idx,
    ], JSON_UNESCAPED_UNICODE));
    $codes = $rowToCodes[$rowKey] ?? [];
    $deleted = [];
    foreach ($codes as $code) {
        $yid = (int)($codeToYonalishId[$code] ?? 0);
        if ($yid > 0 && !empty($activeDeletedGroupsByYonalish[$yid])) {
            foreach ($activeDeletedGroupsByYonalish[$yid] as $name) {
                $deleted[(string)$name] = true;
            }
        }
    }
    return array_keys($deleted);
};
?>
<style>
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
    .guruh-token-create {
        color: #16a34a;
        font-weight: 700;
    }
    .guruh-token-delete {
        color: #dc3545;
        font-weight: 700;
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
        O'QITUVCHILARNING O'QUV YUKLAMASI
    </div>
    
    <div class="table-wrapper">
        <table id="yuklamaTable">
            <thead>
                <tr>
                    <th rowspan="3">№</th>
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

                    <th colspan="8">O'quv soatlari</th>
                    <th colspan="2">Reyting nazorati</th>

                    <th rowspan="3" class="vertical">Kurs ishi va himoyasi</th>
                    <th rowspan="3" class="vertical">Kurs loyihasi va himoyasi</th>

                    <th colspan="5">Malakaviy amaliyot</th>

                    <th rowspan="3" class="vertical">BMI rahbarligi</th>

                    <th colspan="3">Magistratura</th>
                    <th colspan="3">Doktorantura</th>

                    <th rowspan="3" class="vertical">Ochiq dars</th>
                    <th rowspan="3" class="vertical">Yakuniy davlat attestatsiyasi</th>
                    <th rowspan="3" class="vertical">Boshqa soatlar</th>
                    <th rowspan="3" class="vertical">JAMI</th>
                    <th rowspan="3" class="vertical">Kafedra</th>
                </tr>

                <tr>
                    <th colspan="4">O'quv reja bo'yicha</th>
                    <th colspan="4">Amalda bajarilgan</th>

                    <th rowspan="2" class="vertical">Oraliq nazorat</th>
                    <th rowspan="2" class="vertical">Yakuniy nazorat</th>

                    <th rowspan="2" class="vertical">O'quv-pedagogik amaliyot</th>
                    <th rowspan="2" class="vertical">Uzluksiz malakaviy amaliyot</th>
                    <th rowspan="2" class="vertical">Dala amaliyoti</th>
                    <th rowspan="2" class="vertical">Dala amaliyoti (OTM)</th>
                    <th rowspan="2" class="vertical">Ishlab chiqarish amaliyoti</th>

                    <th rowspan="2" class="vertical">Ilmiy-tadqiqot ishi</th>
                    <th rowspan="2" class="vertical">Ilmiy-pedagogik ish</th>
                    <th rowspan="2" class="vertical">Ilmiy stajirovka</th>

                    <th rowspan="2" class="vertical">Tayanch doktorantura</th>
                    <th rowspan="2" class="vertical">Katta ilmiy tadqiqotchi</th>
                    <th rowspan="2" class="vertical">Stajyor-tadqiqotchi</th>
                </tr>

                <tr>
                    <th class="vertical">Ma'ruza</th>
                    <th class="vertical">Amaliy</th>
                    <th class="vertical">Laboratoriya</th>
                    <th class="vertical">Seminar</th>

                    <th class="vertical">Ma'ruza</th>
                    <th class="vertical">Amaliy</th>
                    <th class="vertical">Laboratoriya</th>
                    <th class="vertical">Seminar</th>
                </tr>
            </thead>
            
            <tbody>
                <?php 
                $counter = 1;
                $formatSoat = static function ($value): string {
                    $numeric = (float)$value;
                    if ($numeric == 0.0) {
                        return '';
                    }
                    if (fmod($numeric, 1.0) == 0.0) {
                        return (string)(int)round($numeric);
                    }
                    return rtrim(rtrim(number_format($numeric, 2, '.', ''), '0'), '.');
                };
                $totals = [
                    'reja_maruza' => 0, 'reja_amaliy' => 0, 'reja_lab' => 0, 'reja_seminar' => 0,
                    'amalda_maruza' => 0, 'amalda_amaliy' => 0, 'amalda_lab' => 0, 'amalda_seminar' => 0,
                    'oraliq' => 0, 'yakuniy' => 0,
                    'kurs_ishi' => 0, 'kurs_loyiha' => 0,
                    'oquv_ped' => 0, 'uzluksiz' => 0, 'dala_otm' => 0, 'dala_tash' => 0, 'ishlab' => 0,
                    'bmi' => 0,
                    'mag_ilmiy' => 0, 'mag_ped' => 0, 'mag_staj' => 0,
                    'dok_tayanch' => 0, 'dok_katta' => 0, 'dok_staj' => 0,
                    'ochiq' => 0, 'yadak' => 0, 'boshqa' => 0,
                    'jami' => 0
                ];
                if (!empty($oquv_yuklamalar) || !empty($qoshimcha_yuklamalar)):
                    foreach ($oquv_yuklamalar as $idx => $row): 
                        $rejaMaruza = (float)($row['maruza_soat'] ?? ($row['reja_maruz'] ?? 0));
                        $rejaAmaliy = (float)($row['amaliy_soat'] ?? ($row['reja_amaliy'] ?? 0));
                        $rejaLab = (float)($row['laboratoriya_soat'] ?? ($row['reja_laboratoriya'] ?? 0));
                        $rejaSeminar = (float)($row['seminar_soat'] ?? ($row['reja_seminar'] ?? 0));
                        $talaba = (int)($row['talabalar_soni'] ?? 0);
                        $auditoriyaSoat = $rejaMaruza + $rejaAmaliy + $rejaLab + $rejaSeminar;
                        $shakl = mb_strtolower(trim($row['oquv_shakli'] ?? ''), 'UTF-8');
                        $isMasofaviy = strpos($shakl, 'masof') !== false;
                        $guruhRaqami = mb_strtolower(trim((string)($row['guruh_raqami'] ?? '')), 'UTF-8');
                        $isIqtidorli = strpos($guruhRaqami, 'iqtidor') !== false;
                        $isMaxsus = !empty($row['is_maxsus']);

                        $examType = $resolveExamType($row);
                        $oraliq = 0;
                        if (!$isMaxsus && !$isMasofaviy && $talaba > 0) {
                            if ($auditoriyaSoat >= 60) {
                                $oraliq = round($talaba * 0.4);
                            } elseif ($auditoriyaSoat >= 30) {
                                $oraliq = round($talaba * 0.2);
                            }
                        }
                        // T bo'lsa yakuniy lock=0; I bo'lsa ham talaba soni formulasi bo'yicha hisoblanadi.
                        $yakuniy = 0;
                        if ($examType !== 'T') {
                            $yakuniy = (!$isMaxsus && $talaba > 0 && $auditoriyaSoat > 0) ? round($talaba * 0.3) : 0;
                        }
                        // Izoh: Kurs ishi/kurs loyihasi faqat qo'shimcha o'quv rejadan kiritilganda ko'rsatiladi.
                        $kursIshi = 0;
                        $kursLoyiha = 0;
                        // Izoh: Uzluksiz malakaviy amaliyot faqat qo'shimcha o'quv rejadan kiritilganda ko'rsatiladi.
                        $uzluksiz = 0;
                        $isBirlashtirilganRow = !empty($row['is_birlashtirilgan']);
                        if ($isBirlashtirilganRow) {
                            // Izoh: Birlashtirilgan fanlarda backend agregati ustuvor ishlaydi.
                            $amaldaMaruza = isset($row['amalda_maruz'])
                                ? (float)$row['amalda_maruz']
                                : $rejaMaruza;
                            $amaldaAmaliy = isset($row['amalda_amaliy'])
                                ? (float)$row['amalda_amaliy']
                                : ($rejaAmaliy * (int)($row['guruhlar_soni'] ?? 0));
                            $amaldaLab = isset($row['amalda_laboratoriya'])
                                ? (float)$row['amalda_laboratoriya']
                                : ($rejaLab * (int)($row['kichikguruh_soni'] ?? 0));
                            $amaldaSeminar = isset($row['amalda_seminar'])
                                ? (float)$row['amalda_seminar']
                                : ($rejaSeminar * (int)($row['guruhlar_soni'] ?? 0));
                        } else {
                            // Izoh: Oddiy fanlarda backenddan kelgan hisob ustuvor, shu jumladan tanlov fan taqsimoti.
                            $amaldaMaruza = isset($row['amalda_maruz'])
                                ? (float)$row['amalda_maruz']
                                : $rejaMaruza;
                            $amaldaAmaliy = isset($row['amalda_amaliy'])
                                ? (float)$row['amalda_amaliy']
                                : ($rejaAmaliy * (int)($row['kattaguruh_soni'] ?? 0));
                            $amaldaLab = isset($row['amalda_lab'])
                                ? (float)$row['amalda_lab']
                                : (isset($row['amalda_laboratoriya'])
                                    ? (float)$row['amalda_laboratoriya']
                                    : ($rejaLab * (int)($row['kichikguruh_soni'] ?? 0)));
                            $amaldaSeminar = isset($row['amalda_seminar'])
                                ? (float)$row['amalda_seminar']
                                : ($rejaSeminar * (int)($row['kattaguruh_soni'] ?? 0));
                        }

                        $jamiAll = (float)($row['jami_soat'] ?? 0) + $oraliq + $yakuniy + $kursIshi + $kursLoyiha + $uzluksiz;

                        $totals['reja_maruza'] += $rejaMaruza;
                        $totals['reja_amaliy'] += $rejaAmaliy;
                        $totals['reja_lab'] += $rejaLab;
                        $totals['reja_seminar'] += $rejaSeminar;
                        $totals['amalda_maruza'] += $amaldaMaruza;
                        $totals['amalda_amaliy'] += $amaldaAmaliy;
                        $totals['amalda_lab'] += $amaldaLab;
                        $totals['amalda_seminar'] += $amaldaSeminar;
                        $totals['oraliq'] += $oraliq;
                        $totals['yakuniy'] += $yakuniy;
                        $totals['kurs_ishi'] += $kursIshi;
                        $totals['kurs_loyiha'] += $kursLoyiha;
                        $totals['uzluksiz'] += $uzluksiz;
                        $totals['jami'] += $jamiAll;
                ?>
                <?php
                    $rowGroupChange = $resolveRowGroupChange($row, $idx);
                    $deletedGroupNames = $resolveDeletedGroupNames($row, $idx);
                    $renderGuruhCell = static function (array $row, array $codes) use ($normalizeGroupToken, $groupHistoryStatusByYonalish, $codeToYonalishId): string {
                        $raw = (string)($row['guruh_raqami'] ?? '');
                        $parts = preg_split('/\s*\|\s*/', $raw) ?: [];
                        if (empty($parts)) {
                            return htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
                        }
                        $yids = [];
                        foreach ($codes as $code) {
                            $yid = (int)($codeToYonalishId[$code] ?? 0);
                            if ($yid > 0) {
                                $yids[$yid] = true;
                            }
                        }
                        $out = [];
                        foreach ($parts as $token) {
                            $token = trim((string)$token);
                            if ($token === '') {
                                continue;
                            }
                            $norm = $normalizeGroupToken($token);
                            $status = '';
                            foreach (array_keys($yids) as $yid) {
                                $s = $groupHistoryStatusByYonalish[(int)$yid][$norm] ?? '';
                                if ($s === 'create') {
                                    $status = 'create';
                                    break;
                                }
                                if ($s === 'delete') {
                                    $status = 'delete';
                                }
                            }
                            if ($status === 'create') {
                                $out[] = '<span class="guruh-token-create">' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '</span>';
                            } elseif ($status === 'delete') {
                                $out[] = '<span class="guruh-token-delete">' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '</span>';
                            } else {
                                $out[] = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
                            }
                        }
                        return implode(' | ', $out);
                    };
                    $rowCodes = $rowToCodes[md5(json_encode([
                        $row['fan_name'] ?? $row['fan_nomi'] ?? '',
                        $row['yonalish_code'] ?? '',
                        $row['biriktirilgan_yonalish_code'] ?? '',
                        $row['semestr'] ?? '',
                        $idx,
                    ], JSON_UNESCAPED_UNICODE))] ?? [];
                ?>
                <tr>
                    <td><?= $counter++ ?></td>
                    <td class="left">
                        <?= htmlspecialchars($row['fan_name']) ?>
                        <?php if (!empty($row['is_maxsus'])): ?>
                            <span class="maxsus-badge">Maxsus guruh</span>
                        <?php endif; ?>
                    </td>
                    <td class="left">
                        <?php
                            $isBirlashtirilgan = !empty($row['is_birlashtirilgan']) && !empty($row['biriktirilgan_yonalishlar']);
                            if ($isBirlashtirilgan) {
                                $bCode = trim((string)($row['biriktirilgan_yonalish_code'] ?? ''));
                                $bName = trim((string)($row['biriktirilgan_yonalishlar'] ?? ''));
                                if ($bCode !== '' && $bName !== '') {
                                    $talimYonalishiText = $bCode . ' - ' . $bName;
                                } elseif ($bName !== '') {
                                    $talimYonalishiText = $bName;
                                } else {
                                    $talimYonalishiText = $bCode;
                                }
                            } else {
                                $talimYonalishiText = trim((string)($row['yonalish_code'] ?? '')) . ' - ' . trim((string)($row['talim_yonalishi'] ?? ''));
                            }
                        ?>
                        <?= htmlspecialchars($talimYonalishiText) ?>
                    </td>
                    <td>
                        <?= $renderGuruhCell($row, $rowCodes) ?>
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
                    <!-- O'quv reja bo'yicha -->
                    <td><?= $formatSoat($rejaMaruza) ?></td>
                    <td><?= $formatSoat($rejaAmaliy) ?></td>
                    <td><?= $formatSoat($rejaLab) ?></td>
                    <td><?= $formatSoat($rejaSeminar) ?></td>
                    <!-- Amalda bajarilgan -->
                    <td><?= $formatSoat($amaldaMaruza) ?></td>
                    <td><?= $formatSoat($amaldaAmaliy) ?></td>
                    <td><?= $formatSoat($amaldaLab) ?></td>
                    <td><?= $formatSoat($amaldaSeminar) ?></td>
                    <!-- Reyting nazorati -->
                    <td><?= $oraliq ?: '' ?></td>
                    <td><?= $yakuniy ?: '' ?></td>
                    <!-- Kurs ishlari -->
                    <td><?= $kursIshi ?: '' ?></td>
                    <td><?= $kursLoyiha ?: '' ?></td>
                    <!-- Malakaviy amaliyot -->
                    <td></td>
                    <td><?= $uzluksiz ?: '' ?></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <!-- BMI rahbarligi -->
                    <td></td>
                    <!-- Magistratura -->
                    <td></td>
                    <td></td>
                    <td></td>
                    <!-- Doktorantura -->
                    <td></td>
                    <td></td>
                    <td></td>
                    <!-- Qo'shimcha soatlar -->
                    <td></td>
                    <td></td>
                    <td></td>
                    <!-- JAMI soat -->
                    <td><?= $jamiAll ?></td>
                    <td class="left"><?= htmlspecialchars($row['kafedra_nomi'] ?? '') ?></td>
                </tr>
                <?php 
                    endforeach;
                    foreach ($qoshimcha_yuklamalar as $idx => $row):
                        $qoshimchaFanNomi = legacy_qoshimcha_display_name(
                            (string)($row['fan_nomi'] ?? ''),
                            (int)($row['qoshimcha_dars_id'] ?? 0),
                            (string)($row['subtype_code'] ?? '')
                        );
                        $totals['oraliq'] += (float)($row['oraliq_nazorat'] ?? 0);
                        $totals['yakuniy'] += (float)($row['yakuniy_nazorat'] ?? 0);
                        $totals['kurs_ishi'] += (float)($row['kurs_ishi'] ?? 0);
                        $totals['kurs_loyiha'] += (float)($row['kurs_loyiha'] ?? 0);
                        $totals['oquv_ped'] += (float)($row['oquv_ped_amaliyot'] ?? 0);
                        $totals['uzluksiz'] += (float)($row['uzluksiz_malakaviy'] ?? 0);
                        $totals['dala_otm'] += (float)($row['dala_amaliyoti_otm'] ?? 0);
                        $totals['dala_tash'] += (float)($row['dala_amaliyoti_tashqarida'] ?? 0);
                        $totals['ishlab'] += (float)($row['ishlab_chiqarish'] ?? 0);
                        $totals['bmi'] += (float)($row['bmi_rahbarligi'] ?? 0);
                        $totals['mag_ilmiy'] += (float)($row['ilmiy_tadqiqot_ishi'] ?? 0);
                        $totals['mag_ped'] += (float)($row['ilmiy_pedagogik_ishi'] ?? 0);
                        $totals['mag_staj'] += (float)($row['ilmiy_stajirovka'] ?? 0);
                        $totals['dok_tayanch'] += (float)($row['tayanch_doktorantura'] ?? 0);
                        $totals['dok_katta'] += (float)($row['katta_ilmiy_tadqiqotchi'] ?? 0);
                        $totals['dok_staj'] += (float)($row['stajyor_tadqiqotchi'] ?? 0);
                        $totals['ochiq'] += (float)($row['ochiq_dars'] ?? 0);
                        $totals['yadak'] += (float)($row['yadak'] ?? 0);
                        $totals['boshqa'] += (float)($row['boshqa_soatlar'] ?? 0);
                        $totals['jami'] += (float)($row['jami_soat'] ?? 0);
                ?>
                <?php
                    $rowGroupChange = $resolveRowGroupChange($row, $idx);
                    $deletedGroupNames = $resolveDeletedGroupNames($row, $idx);
                    $rowCodes = $rowToCodes[md5(json_encode([
                        $row['fan_name'] ?? $row['fan_nomi'] ?? '',
                        $row['yonalish_code'] ?? '',
                        $row['biriktirilgan_yonalish_code'] ?? '',
                        $row['semestr'] ?? '',
                        $idx,
                    ], JSON_UNESCAPED_UNICODE))] ?? [];
                ?>
                <tr>
                    <td><?= $counter++ ?></td>
                    <td class="left"><?= htmlspecialchars($qoshimchaFanNomi) ?></td>
                    <td class="left"><?= htmlspecialchars($row['yonalish_code'] . ' - ' . $row['talim_yonalishi']) ?></td>
                    <td>
                        <?= $renderGuruhCell($row, $rowCodes) ?>
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
                    <!-- O'quv reja bo'yicha -->
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <!-- Amalda bajarilgan -->
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <!-- Reyting nazorati -->
                    <td><?= $row['oraliq_nazorat'] ?></td>
                    <td><?= $row['yakuniy_nazorat'] ?></td>
                    <!-- Kurs ishlari -->
                    <td><?= ($row['kurs_ishi'] ?? 0) ?: '' ?></td>
                    <td><?= ($row['kurs_loyiha'] ?? 0) ?: '' ?></td>
                    <!-- Malakaviy amaliyot -->
                    <td><?= $row['oquv_ped_amaliyot'] ?></td>
                    <td><?= $row['uzluksiz_malakaviy'] ?></td>
                    <td><?= $row['dala_amaliyoti_otm'] ?></td>
                    <td><?= $row['dala_amaliyoti_tashqarida'] ?></td>
                    <td><?= $row['ishlab_chiqarish'] ?></td>
                    <!-- BMI rahbarligi -->
                    <td><?= $row['bmi_rahbarligi'] ?></td>
                    <!-- Magistratura -->
                    <td><?= $row['ilmiy_tadqiqot_ishi'] ?></td>
                    <td><?= $row['ilmiy_pedagogik_ishi'] ?></td>
                    <td><?= $row['ilmiy_stajirovka'] ?></td>
                    <!-- Doktorantura -->
                    <td><?= $row['tayanch_doktorantura']  ?></td>
                    <td><?= $row['katta_ilmiy_tadqiqotchi']  ?></td>
                    <td><?= $row['stajyor_tadqiqotchi']  ?></td>
                    
                    <td><?= $row['ochiq_dars'] ?></td>
                    <td><?= $row['yadak'] ?></td>
                    <td><?= $row['boshqa_soatlar'] ?></td>
                    
                    <!-- JAMI soat -->
                    <td><?= $row['jami_soat'] ?></td>
                    <td class="left"><?= htmlspecialchars($row['kafedra_nomi'] ?? '') ?></td>
                </tr>
                <?php 
                    endforeach;
                ?>
                <tr class="total-row">
                    <td colspan="11" class="left"><strong>Jami</strong></td>
                    <!-- O'quv reja bo'yicha -->
                    <td><strong><?= $totals['reja_maruza'] ?></strong></td>
                    <td><strong><?= $totals['reja_amaliy'] ?></strong></td>
                    <td><strong><?= $totals['reja_lab'] ?></strong></td>
                    <td><strong><?= $totals['reja_seminar'] ?></strong></td>
                    <!-- Amalda bajarilgan -->
                    <td><strong><?= $formatSoat($totals['amalda_maruza']) ?></strong></td>
                    <td><strong><?= $formatSoat($totals['amalda_amaliy']) ?></strong></td>
                    <td><strong><?= $formatSoat($totals['amalda_lab']) ?></strong></td>
                    <td><strong><?= $formatSoat($totals['amalda_seminar']) ?></strong></td>
                    <!-- Reyting nazorati -->
                    <td><strong><?= $totals['oraliq'] ?></strong></td>
                    <td><strong><?= $totals['yakuniy'] ?></strong></td>
                    <!-- Kurs ishlari -->
                    <td><strong><?= $totals['kurs_ishi'] ?></strong></td>
                    <td><strong><?= $totals['kurs_loyiha'] ?></strong></td>
                    <!-- Malakaviy amaliyot -->
                    <td><strong><?= $totals['oquv_ped'] ?></strong></td>
                    <td><strong><?= $totals['uzluksiz'] ?></strong></td>
                    <td><strong><?= $totals['dala_tash'] ?></strong></td>
                    <td><strong><?= $totals['dala_otm'] ?></strong></td>
                    <td><strong><?= $totals['ishlab'] ?></strong></td>
                    <!-- BMI rahbarligi -->
                    <td><strong><?= $totals['bmi'] ?></strong></td>
                    <!-- Magistratura -->
                    <td><strong><?= $totals['mag_ilmiy'] ?></strong></td>
                    <td><strong><?= $totals['mag_ped'] ?></strong></td>
                    <td><strong><?= $totals['mag_staj'] ?></strong></td>
                    <!-- Doktorantura -->
                    <td><strong><?= $totals['dok_tayanch'] ?></strong></td>
                    <td><strong><?= $totals['dok_katta'] ?></strong></td>
                    <td><strong><?= $totals['dok_staj'] ?></strong></td>
                    <!-- Qo'shimcha soatlar -->
                    <td><strong><?= $totals['ochiq'] ?></strong></td>
                    <td><strong><?= $totals['yadak'] ?></strong></td>
                    <td><strong><?= $totals['boshqa'] ?></strong></td>
                    <!-- JAMI soat -->
                    <td><strong><?= $totals['jami'] ?></strong></td>
                    <td></td>
                </tr>
                <?php else: ?>
                <tr>
                    <td colspan="40" style="text-align: center; padding: 20px;">
                        <i class="fas fa-info-circle"></i> Ma'lumotlar mavjud emas
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
