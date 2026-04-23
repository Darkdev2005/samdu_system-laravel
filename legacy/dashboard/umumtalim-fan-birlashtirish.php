<?php
    // Izoh: Birlashtiriladigan fanlar va yo'nalish+semestrlar ro'yxatini olish.
    include_once 'config.php';
    $db = new Database();
    $h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $academicYearLabel = static function ($value): string {
        $year = (int) trim((string) $value);
        if ($year <= 0) {
            return trim((string) $value);
        }
        return $year . '-' . ($year + 1);
    };
    $normalizeFanName = static function (string $value): string {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        if (function_exists('mb_strtolower')) {
            return (string) mb_strtolower($value, 'UTF-8');
        }
        return strtolower($value);
    };
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    // Izoh: Avvalgi bazada umumta'lim fanlar jadvali to'ldirilmagan bo'lsa, fanlar jadvalidan sinxronlaymiz.
    $db->query("
        INSERT INTO umumtalim_fanlar (fan_code, fan_name, kafedra_id, semestr)
        SELECT f.fan_code, f.fan_name, f.kafedra_id, s.semestr
        FROM fanlar f
        JOIN semestrlar s ON s.id = f.semestr_id
        WHERE f.tanlov_fan = 2
          AND EXISTS (
              SELECT 1
              FROM oquv_rejalar o
              WHERE o.fan_id = f.id
          )
          AND COALESCE(f.fan_code, '') <> ''
          AND COALESCE(f.fan_name, '') <> ''
          AND COALESCE(s.semestr, 0) > 0
          AND NOT EXISTS (
              SELECT 1
              FROM umumtalim_fanlar uf
              WHERE uf.fan_code = f.fan_code
                AND uf.fan_name = f.fan_name
                AND uf.semestr = s.semestr
          )
    ");

    $mandatoryFanRows = [];
    // Izoh: Fanlar ro'yxati o'quv reja yaratishdagi Majburiy fanlar (tanlov_fan=0)dan olinadi.
    $fanResult = $db->query("
        SELECT
            f.id,
            f.fan_name,
            f.fan_code,
            f.tanlov_fan,
            f.semestr_id,
            f.kafedra_id,
            k.name AS kafedra_name,
            s.semestr AS semestr_num,
            s.yonalish_id,
            y.name AS yonalish_name,
            y.kirish_yili,
            y.talim_shakli_id,
            tsh.name AS talim_shakli_name,
            COALESCE(y.fakultet_id, s.fakultet_id) AS fakultet_id
        FROM fanlar f
        JOIN semestrlar s ON s.id = f.semestr_id
        JOIN yonalishlar y ON y.id = s.yonalish_id
        LEFT JOIN talim_shakllar tsh ON tsh.id = y.talim_shakli_id
        LEFT JOIN kafedralar k ON k.id = f.kafedra_id
        WHERE f.tanlov_fan = 0
          AND EXISTS (
              SELECT 1
              FROM oquv_rejalar o
              WHERE o.fan_id = f.id
          )
          AND NOT EXISTS (
              SELECT 1
              FROM umumtalim_fan_biriktirish ub
              LEFT JOIN fanlar sf ON sf.id = ub.source_fan_id
              LEFT JOIN umumtalim_fanlar uf ON uf.id = ub.umumtalim_fan_id
              WHERE NOT EXISTS (
                  SELECT 1
                  FROM umumtalim_fan_biriktirish_guruhlar ubg
                  WHERE ubg.biriktirish_id = ub.id
              )
                AND (
                    ub.source_fan_id = f.id
                    OR (
                        ub.semestr_id = f.semestr_id
                        AND (
                            (
                                ub.source_fan_id IS NOT NULL
                                AND LOWER(TRIM(COALESCE(sf.fan_name, ''))) = LOWER(TRIM(COALESCE(f.fan_name, '')))
                            )
                            OR (
                                ub.source_fan_id IS NULL
                                AND (
                                    (COALESCE(uf.fan_code, '') <> '' AND uf.fan_code = f.fan_code AND uf.fan_name = f.fan_name)
                                    OR (COALESCE(uf.fan_code, '') = '' AND uf.fan_name = f.fan_name)
                                )
                            )
                        )
                    )
                )
          )
        ORDER BY f.semestr_id, f.fan_name, f.id DESC
    ");
    if ($fanResult) {
        while ($row = mysqli_fetch_assoc($fanResult)) {
            $semestrId = (int) ($row['semestr_id'] ?? 0);
            if ($semestrId <= 0) {
                continue;
            }
            $semestrNum = (int) ($row['semestr_num'] ?? 0);
            $mandatoryFanRows[] = [
                'fan_id' => (int) ($row['id'] ?? 0),
                'fan_code' => (string) ($row['fan_code'] ?? ''),
                'fan_name' => (string) ($row['fan_name'] ?? ''),
                'kafedra_id' => (int) ($row['kafedra_id'] ?? 0),
                'kafedra_name' => (string) ($row['kafedra_name'] ?? ''),
                'semestr_id' => $semestrId,
                'semestr_num' => $semestrNum,
                'kurs' => $semestrNum > 0 ? (int) ceil($semestrNum / 2) : 0,
                'yonalish_id' => (int) ($row['yonalish_id'] ?? 0),
                'yonalish_name' => (string) ($row['yonalish_name'] ?? ''),
                'kirish_yili' => (string) ($row['kirish_yili'] ?? ''),
                'kirish_yili_num' => (int) ($row['kirish_yili'] ?? 0),
                'kirish_yili_label' => $academicYearLabel((string) ($row['kirish_yili'] ?? '')),
                'talim_shakli_id' => (int) ($row['talim_shakli_id'] ?? 0),
                'talim_shakli_name' => (string) ($row['talim_shakli_name'] ?? ''),
                'fakultet_id' => (int) ($row['fakultet_id'] ?? 0),
            ];
        }
    }

    $guruhlarByYonalish = [];
    $guruhResult = $db->query("
        SELECT id, yonalish_id, guruh_nomer, soni
        FROM guruhlar
        ORDER BY yonalish_id, guruh_nomer, id
    ");
    if ($guruhResult) {
        while ($guruh = mysqli_fetch_assoc($guruhResult)) {
            $yonalishId = (int)($guruh['yonalish_id'] ?? 0);
            if ($yonalishId <= 0) {
                continue;
            }
            if (!isset($guruhlarByYonalish[$yonalishId])) {
                $guruhlarByYonalish[$yonalishId] = [];
            }
            $guruhlarByYonalish[$yonalishId][] = [
                'id' => (int)($guruh['id'] ?? 0),
                'nomer' => (string)($guruh['guruh_nomer'] ?? ''),
                'soni' => (int)($guruh['soni'] ?? 0),
            ];
        }
    }
    $usedGuruhlarByFan = [];
    $usedGuruhResult = $db->query("
        SELECT source_fan_id, guruh_id
        FROM umumtalim_fan_biriktirish_guruhlar
    ");
    if ($usedGuruhResult) {
        while ($used = mysqli_fetch_assoc($usedGuruhResult)) {
            $fanId = (int)($used['source_fan_id'] ?? 0);
            $guruhId = (int)($used['guruh_id'] ?? 0);
            if ($fanId <= 0 || $guruhId <= 0) {
                continue;
            }
            if (!isset($usedGuruhlarByFan[$fanId])) {
                $usedGuruhlarByFan[$fanId] = [];
            }
            $usedGuruhlarByFan[$fanId][] = $guruhId;
        }
    }

    $semestrlar = $db->get_semestrlar();
    $talimShakliRows = $db->get_data_by_table_all('talim_shakllar');

    $filterYonalishMap = [];
    $kirishYiliSet = [];
    $semestrNumSet = [];
    $kursSet = [];
    $semestrSelectItems = [];

    foreach ($semestrlar as $s) {
        $yonalishName = trim($s['yonalish_name'] ?? '');
        $kirishYili = trim($s['kirish_yili'] ?? '');
        $semestrNum = (int) ($s['semestr'] ?? 0);
        $kurs = $semestrNum > 0 ? (int) ceil($semestrNum / 2) : 0;
        $fakultetId = (int) ($s['yonalish_fakultet_id'] ?? ($s['fakultet_id'] ?? 0));
        $yonalishId = (int) ($s['yonalish_id'] ?? 0);
        $talimShakliId = (int) ($s['talim_shakli_id'] ?? 0);
        $talimShakliName = trim((string) ($s['talim_shakli_name'] ?? ''));
        $daraja = mb_strtolower(trim($s['akademik_daraja_name'] ?? ''), 'UTF-8');
        $darajaPrefix = '';
        if (strpos($daraja, 'magistr') !== false) {
            $darajaPrefix = 'M ';
        } elseif (strpos($daraja, 'bakalavr') !== false) {
            $darajaPrefix = 'B ';
        }

        // Izoh: Yo'nalish nomini to'liq ko'rsatamiz.
        $labelParts = [];
        if ($yonalishName !== '') {
            $labelParts[] = $yonalishName;
        }
        if ($kirishYili !== '') {
            $labelParts[] = $kirishYili;
        }
        $label = implode(' - ', $labelParts);
        if ($semestrNum > 0) {
            $label = ($label !== '' ? $label . ' - ' : '') . $semestrNum . '-semestr';
        }
        if ($label === '') {
            $label = 'Semestr: ' . (int)$s['id'];
        }
        $label = $darajaPrefix . $label;

        $semestrSelectItems[] = [
            'id' => (int) $s['id'],
            'label' => $label,
            'fakultet_id' => $fakultetId,
            'yonalish_id' => $yonalishId,
            'kirish_yili' => $kirishYili,
            'kirish_yili_num' => (int) $kirishYili,
            'talim_shakli_id' => $talimShakliId,
            'semestr_num' => $semestrNum,
            'kurs' => $kurs,
        ];

        if ($yonalishId > 0 && !isset($filterYonalishMap[$yonalishId])) {
            $filterYonalishMap[$yonalishId] = [
                'id' => $yonalishId,
                'label' => $yonalishName . ($kirishYili !== '' ? ' - ' . $kirishYili : ''),
                'fakultet_id' => $fakultetId,
            ];
        }
        if ($kirishYili !== '') {
            $kirishYiliSet[$kirishYili] = true;
        }
        if ($semestrNum > 0) {
            $semestrNumSet[$semestrNum] = true;
        }
        if ($kurs > 0) {
            $kursSet[$kurs] = true;
        }
    }
    $filterYonalishlar = array_values($filterYonalishMap);
    usort($filterYonalishlar, static function (array $a, array $b): int {
        return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
    });
    $talimShakliOptions = [];
    foreach ($talimShakliRows as $ts) {
        $tsId = (int)($ts['id'] ?? 0);
        $tsName = trim((string)($ts['name'] ?? ''));
        if ($tsId <= 0 || $tsName === '') {
            continue;
        }
        $talimShakliOptions[] = [
            'id' => $tsId,
            'label' => $tsName,
        ];
    }
    usort($talimShakliOptions, static function (array $a, array $b): int {
        return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
    });

    $kirishYiliOptions = array_keys($kirishYiliSet);
    rsort($kirishYiliOptions, SORT_NATURAL);

    $semestrNumOptions = array_map('intval', array_keys($semestrNumSet));
    sort($semestrNumOptions, SORT_NUMERIC);

    $kursOptions = array_map('intval', array_keys($kursSet));
    sort($kursOptions, SORT_NUMERIC);

    $semestrSelectItemsJson = json_encode($semestrSelectItems, $jsonFlags);
    if ($semestrSelectItemsJson === false) {
        $semestrSelectItemsJson = '[]';
    }
    $filterYonalishlarJson = json_encode($filterYonalishlar, $jsonFlags);
    if ($filterYonalishlarJson === false) {
        $filterYonalishlarJson = '[]';
    }
    $mandatoryFanRowsJson = json_encode($mandatoryFanRows, $jsonFlags);
    if ($mandatoryFanRowsJson === false) {
        $mandatoryFanRowsJson = '[]';
    }
    $guruhlarByYonalishJson = json_encode($guruhlarByYonalish, $jsonFlags);
    if ($guruhlarByYonalishJson === false) {
        $guruhlarByYonalishJson = '{}';
    }
    $usedGuruhlarByFanJson = json_encode($usedGuruhlarByFan, $jsonFlags);
    if ($usedGuruhlarByFanJson === false) {
        $usedGuruhlarByFanJson = '{}';
    }
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Birlashtiriladigan fanlarni biriktirish</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .top-filters-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(180px, 1fr));
            gap: 12px;
        }
        .top-filter-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        .top-filter-note {
            font-size: 13px;
            color: #475569;
        }
        .top-filters-grid .select2-container {
            width: 100% !important;
        }
        .top-filters-grid .select2-container--default .select2-selection--single {
            height: 46px;
            border: 1px solid #cfd8e3;
            border-radius: 12px;
            background: #fff;
        }
        .top-filters-grid .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 44px;
            color: #334155;
            padding-left: 14px;
        }
        .top-filters-grid .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 44px;
            right: 8px;
        }
        .top-filters-grid .select2-container--default .select2-selection--multiple {
            min-height: 58px;
            border: 1px solid #cfd8e3;
            border-radius: 12px;
            background: #fff;
            padding: 8px 10px 6px;
        }
        .top-filters-grid .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            display: block;
            padding: 0 !important;
            white-space: normal;
        }
        .top-filters-grid .select2-container--default .select2-selection--multiple .select2-selection__choice {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
            margin: 0 6px 6px 0;
            padding: 5px 10px 5px 24px;
            border: 1px solid #b8e6c9;
            border-radius: 8px;
            background: #e8f8ef;
            color: #11663f;
            font-size: 14px;
            position: relative;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .top-filters-grid .select2-container--default .select2-selection--multiple .select2-selection__choice {
            float: left;
        }
        .top-filters-grid .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
            position: absolute;
            left: 8px;
            top: 5px;
            margin: 0;
            color: #11663f;
            border: none;
            background: transparent;
            font-weight: 700;
            line-height: 1;
        }
        .top-filters-grid .select2-container--default .select2-selection--multiple .select2-search--inline {
            clear: both;
            float: none;
            display: block;
            width: 100%;
            margin-top: 2px;
        }
        .top-filters-grid .select2-container--default .select2-selection--multiple .select2-search--inline .select2-search__field {
            margin: 0 !important;
            width: 100% !important;
            min-width: 0 !important;
            height: 34px;
            line-height: 34px;
            font-size: 14px;
            padding: 0 10px !important;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            box-sizing: border-box;
        }
        .top-filters-grid .select2-container--default .select2-selection--multiple .select2-search--inline .select2-search__field::placeholder {
            color: #94a3b8;
        }
        .select2-container--default .select2-dropdown {
            border: 1px solid #cfd8e3;
            border-radius: 12px;
            overflow: hidden;
        }
        .select2-container--default .select2-results__option {
            padding: 11px 14px;
            font-size: 16px;
        }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {
            background: #f1f5f9;
            color: #0f172a;
        }
        .select2-container--default .select2-results__message {
            padding: 11px 14px;
            color: #64748b;
            font-size: 14px;
        }
        .mandatory-list-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 12px;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
        }
        .mandatory-search-box {
            position: relative;
            flex: 1 1 360px;
            max-width: 560px;
            min-width: 260px;
        }
        .mandatory-search-box i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }
        .mandatory-list-search {
            width: 100%;
            min-width: 0;
            max-width: none;
            height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            background: #fff;
            padding: 0 12px 0 38px;
            font-size: 15px;
            color: #334155;
        }
        .mandatory-list-search:focus {
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12);
            outline: none;
        }
        .mandatory-list-meta {
            font-size: 13px;
            color: #0f766e;
            font-weight: 600;
            background: #ecfeff;
            border: 1px solid #bae6fd;
            border-radius: 999px;
            padding: 6px 12px;
            white-space: nowrap;
        }
        .mandatory-groups-row td {
            background: #fbfdff;
            border-top: 0;
        }
        .mandatory-groups {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 8px 0 10px;
        }
        .mandatory-group-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 7px 10px;
            border: 1px solid #d7e4ee;
            border-radius: 999px;
            background: #fff;
            color: #334155;
            font-size: 13px;
            cursor: pointer;
        }
        .mandatory-group-chip.is-disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }
        .mandatory-group-empty {
            color: #94a3b8;
            font-size: 13px;
            padding: 6px 0;
        }
        @media (max-width: 1200px) {
            .top-filters-grid {
                grid-template-columns: repeat(3, minmax(180px, 1fr));
            }
        }
        @media (max-width: 900px) {
            .top-filters-grid {
                grid-template-columns: repeat(2, minmax(180px, 1fr));
            }
        }
        @media (max-width: 640px) {
            .top-filters-grid {
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
                <h1>Birlashtiriladigan fanlarni biriktirish</h1>
            </header>
            <div class="content-container">
                <form id="birlashtirishForm" class="card" novalidate>
                    <h3 class="section-title">Umumiy ma'lumot</h3>
                    <input type="hidden" id="masterFanId" name="master_fan_id" value="">

                    <div class="form-group">
                        <label>Majburiy fanlar uchun filtrlash</label>
                        <div class="top-filters-grid">
                            <div class="form-group">
                                <select class="form-control" id="kirishYiliFilter">
                                    <option value="">Barcha o'quv yillari</option>
                                    <?php foreach ($kirishYiliOptions as $year): ?>
                                        <option value="<?php echo $h($year); ?>"><?php echo $h($academicYearLabel($year)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <select class="form-control" id="yonalishFilter" multiple="multiple" data-placeholder="Yo'nalish(lar)ni tanlang">
                                    <?php foreach ($filterYonalishlar as $y): ?>
                                        <option value="<?php echo (int)$y['id']; ?>">
                                            <?php echo $h($y['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <select class="form-control" id="talimShakliFilter">
                                    <option value="">Barcha ta'lim shakllari</option>
                                    <?php foreach ($talimShakliOptions as $ts): ?>
                                        <option value="<?php echo (int)$ts['id']; ?>">
                                            <?php echo $h($ts['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <select class="form-control" id="kursFilter">
                                    <option value="">Barcha kurslar</option>
                                    <?php foreach ($kursOptions as $kurs): ?>
                                        <option value="<?php echo (int)$kurs; ?>"><?php echo (int)$kurs; ?>-kurs</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <select class="form-control" id="semestrNumFilter">
                                    <option value="">Barcha semestrlar</option>
                                    <?php foreach ($semestrNumOptions as $num): ?>
                                        <option value="<?php echo (int)$num; ?>"><?php echo (int)$num; ?>-semestr</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="top-filter-actions">
                            <div class="top-filter-note" id="filteredSemestrCount">0 ta semestr mos keldi</div>
                            <div>
                                <button type="button" class="btn btn-primary btn-sm" id="applyTopFiltersBtn">
                                    <i class="fas fa-filter"></i> Filtrlash
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" id="resetTopFiltersBtn">
                                    <i class="fas fa-rotate-left"></i> Tozalash
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-container mt-3">
                        <div class="table-header">
                            <div class="table-title">
                                <h3>Majburiy fanlar ro'yxati (filter natijasi)</h3>
                                <span class="badge" id="mandatoryFanCount">0 ta</span>
                            </div>
                        </div>
                        <div class="mandatory-list-controls">
                            <div class="mandatory-search-box">
                                <i class="fas fa-search"></i>
                                <input
                                    type="text"
                                    class="mandatory-list-search"
                                    id="mandatoryFanSearch"
                                    placeholder="Fan kodi yoki fan nomi bo'yicha qidirish..."
                                >
                            </div>
                            <div class="mandatory-list-meta" id="mandatoryFanSelectedCount">0 ta tanlandi</div>
                        </div>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="width: 44px;">
                                            <input type="checkbox" id="mandatoryFanSelectAll" title="Barchasini tanlash">
                                        </th>
                                        <th>Fan kodi</th>
                                        <th>Fan nomi</th>
                                        <th>Kafedra</th>
                                        <th>Yo'nalish</th>
                                        <th>O'quv yili</th>
                                        <th>Kurs</th>
                                        <th>Semestr</th>
                                    </tr>
                                </thead>
                                <tbody id="mandatoryFanTableBody">
                                    <tr>
                                        <td colspan="8">Natija bo'sh</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="form-actions mt-3">
                        <a href="umumtalim-fanlar-royxati.php" class="btn btn-secondary">
                            <i class="fas fa-list"></i> Birlashtirilgan fanlar ro'yxati
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Birlashtirish
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="/assets/vendor/select2/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="/assets/vendor/jquery/jquery-3.6.0.min.js"></script>
    <script>window.jQuery || document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>')</script>
    <script src="/assets/vendor/select2/js/select2.min.js"></script>
    <script>if (window.jQuery && !window.jQuery.fn.select2) { document.write('<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"><\/script>'); }</script>

    <script>
        const allSemestrOptions = <?php echo $semestrSelectItemsJson; ?>;
        const allYonalishOptions = <?php echo $filterYonalishlarJson; ?>;
        const allMandatoryFanRows = <?php echo $mandatoryFanRowsJson; ?>;
        const allGuruhlarByYonalish = <?php echo $guruhlarByYonalishJson; ?>;
        const usedGuruhlarByFan = <?php echo $usedGuruhlarByFanJson; ?>;
        const mandatoryFanById = Object.create(null);
        const selectedMandatoryGroupKeys = new Set();
        const mandatoryRenderLimit = 150;
        let filteredMandatoryFanRows = [];

        allMandatoryFanRows.forEach((row) => {
            mandatoryFanById[String(row.fan_id)] = row;
        });

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function normalizeFanName(value) {
            return String(value || '')
                .trim()
                .replace(/\s+/g, ' ')
                .toLocaleLowerCase('uz-UZ');
        }

        function parseYear(value) {
            const match = String(value || '').match(/\d{4}/);
            return match ? parseInt(match[0], 10) : 0;
        }

        function getAcademicYearLabel(startYear) {
            const year = parseInt(startYear || 0, 10);
            return year > 0 ? `${year}-${year + 1}` : '-';
        }

        function getCurrentCourseForAcademicYear(item, academicYearStart) {
            const startYear = parseInt(academicYearStart || 0, 10);
            const entryYear = parseYear(item.kirish_yili_num || item.kirish_yili || '');
            if (startYear <= 0 || entryYear <= 0) {
                return 0;
            }
            return startYear - entryYear + 1;
        }

        function isItemInAcademicYear(item, academicYearStart) {
            const currentCourse = getCurrentCourseForAcademicYear(item, academicYearStart);
            const semestrNum = parseInt(item.semestr_num || 0, 10);
            if (currentCourse <= 0 || semestrNum <= 0) {
                return false;
            }
            return semestrNum === (currentCourse * 2 - 1) || semestrNum === (currentCourse * 2);
        }

        function getDisplayCourse(item, filters) {
            if (filters && filters.kirishYili !== '') {
                return getCurrentCourseForAcademicYear(item, filters.kirishYili);
            }
            return parseInt(item.kurs || 0, 10) || 0;
        }

        function getDisplayAcademicYearLabel(item, filters) {
            if (filters && filters.kirishYili !== '') {
                return getAcademicYearLabel(filters.kirishYili);
            }
            return item.kirish_yili_label || item.kirish_yili || '-';
        }

        function getRowGroups(row) {
            const fanId = String(row.fan_id || '');
            const yonalishId = String(row.yonalish_id || '');
            const usedSet = new Set((usedGuruhlarByFan[fanId] || []).map((id) => String(id || '')));
            return (allGuruhlarByYonalish[yonalishId] || []).map((group) => {
                return {
                    id: String(group.id || ''),
                    nomer: String(group.nomer || ''),
                    soni: parseInt(group.soni || 0, 10) || 0,
                    isUsed: usedSet.has(String(group.id || ''))
                };
            }).filter((group) => group.id !== '');
        }

        function getGroupKey(row, groupId) {
            return `${row.fan_id || ''}:${groupId || ''}`;
        }

        function getAvailableGroups(row) {
            return getRowGroups(row).filter((group) => !group.isUsed);
        }

        function getSelectedGroupRows() {
            const selected = [];
            selectedMandatoryGroupKeys.forEach((key) => {
                const [fanId, guruhId] = String(key).split(':');
                const row = mandatoryFanById[String(fanId)];
                if (!row || !guruhId) {
                    return;
                }
                const group = getRowGroups(row).find((item) => item.id === String(guruhId));
                if (!group || group.isUsed) {
                    return;
                }
                selected.push({ row, group });
            });
            return selected;
        }

        function setupSelect2ForFilters() {
            $('#yonalishFilter').select2({
                placeholder: "Yo'nalish(lar)ni tanlang",
                allowClear: false,
                closeOnSelect: false,
                language: {
                    noResults: function() {
                        return "Natija topilmadi";
                    }
                },
                escapeMarkup: function(markup) {
                    return markup;
                },
                width: '100%',
            });
            $('#kirishYiliFilter').select2({
                placeholder: "Barcha o'quv yillari",
                allowClear: true,
                width: '100%',
            });
            $('#talimShakliFilter').select2({
                placeholder: "Barcha ta'lim shakllari",
                allowClear: true,
                width: '100%',
            });
            $('#kursFilter').select2({
                placeholder: "Barcha kurslar",
                allowClear: true,
                width: '100%',
            });
            $('#semestrNumFilter').select2({
                placeholder: "Barcha semestrlar",
                allowClear: true,
                width: '100%',
            });
        }

        function getTopFilters() {
            const yonalishRawValue = $('#yonalishFilter').val();
            const yonalishIds = Array.isArray(yonalishRawValue)
                ? yonalishRawValue.map((value) => String(value || '')).filter((value) => value !== '')
                : [];

            return {
                yonalishIds: yonalishIds,
                kirishYili: String($('#kirishYiliFilter').val() || ''),
                talimShakliId: String($('#talimShakliFilter').val() || ''),
                kurs: String($('#kursFilter').val() || ''),
                semestrNum: String($('#semestrNumFilter').val() || '')
            };
        }

        function getFilteredSemestrOptions() {
            const filters = getTopFilters();
            return allSemestrOptions.filter(item => {
                if (filters.yonalishIds.length > 0 && !filters.yonalishIds.includes(String(item.yonalish_id || ''))) {
                    return false;
                }
                if (filters.kirishYili !== '' && !isItemInAcademicYear(item, filters.kirishYili)) {
                    return false;
                }
                if (filters.talimShakliId !== '' && String(item.talim_shakli_id || '') !== filters.talimShakliId) {
                    return false;
                }
                const displayCourse = getDisplayCourse(item, filters);
                if (filters.kurs !== '' && String(displayCourse || '') !== filters.kurs) {
                    return false;
                }
                if (filters.semestrNum !== '' && String(item.semestr_num || '') !== filters.semestrNum) {
                    return false;
                }
                return true;
            });
        }

        function updateFilteredSemestrCount() {
            const count = getFilteredSemestrOptions().length;
            $('#filteredSemestrCount').text(`${count} ta semestr mos keldi`);
        }

        function rebuildYonalishFilterOptions(preserveValues = []) {
            const select = $('#yonalishFilter');
            const currentValues = Array.isArray(preserveValues)
                ? preserveValues.map((value) => String(value || '')).filter((value) => value !== '')
                : [];

            select.empty();
            allYonalishOptions.forEach(item => {
                select.append(new Option(item.label || '', item.id || '', false, false));
            });

            const availableValues = currentValues.filter((value) => select.find(`option[value="${value}"]`).length > 0);
            select.val(availableValues);
            select.trigger('change.select2');
        }

        function renderMandatoryFanTable() {
            const tbody = $('#mandatoryFanTableBody');
            const visibleRows = filteredMandatoryFanRows.slice(0, mandatoryRenderLimit);
            const selectedGroups = getSelectedGroupRows();
            const selectedStudents = selectedGroups.reduce((sum, item) => sum + (parseInt(item.group.soni || 0, 10) || 0), 0);
            $('#mandatoryFanCount').text(
                filteredMandatoryFanRows.length > mandatoryRenderLimit
                    ? `${filteredMandatoryFanRows.length} ta / ${mandatoryRenderLimit} ta ko'rsatildi`
                    : `${filteredMandatoryFanRows.length} ta`
            );
            $('#mandatoryFanSelectedCount').text(`${selectedGroups.length} ta guruh / ${selectedStudents} talaba tanlandi`);

            if (visibleRows.length === 0) {
                tbody.html('<tr><td colspan="8">Tanlangan filter bo\'yicha majburiy fan topilmadi</td></tr>');
                $('#mandatoryFanSelectAll').prop('checked', false);
                return;
            }

            let html = '';
            const filters = getTopFilters();
            visibleRows.forEach((row) => {
                const fanId = String(row.fan_id || '');
                const groups = getRowGroups(row);
                const availableGroups = groups.filter((group) => !group.isUsed);
                const allRowGroupsSelected = availableGroups.length > 0
                    && availableGroups.every((group) => selectedMandatoryGroupKeys.has(getGroupKey(row, group.id)));
                const checked = allRowGroupsSelected ? ' checked' : '';
                const disabled = availableGroups.length === 0 ? ' disabled' : '';
                const displayCourse = getDisplayCourse(row, filters);
                const groupHtml = groups.length > 0
                    ? groups.map((group) => {
                        const key = getGroupKey(row, group.id);
                        const groupChecked = selectedMandatoryGroupKeys.has(key) ? ' checked' : '';
                        const groupDisabled = group.isUsed ? ' disabled' : '';
                        const disabledClass = group.isUsed ? ' is-disabled' : '';
                        const usedText = group.isUsed ? ' - biriktirilgan' : '';
                        return `
                            <label class="mandatory-group-chip${disabledClass}">
                                <input type="checkbox" class="mandatory-group-check" data-fan-id="${escapeHtml(fanId)}" value="${escapeHtml(group.id)}"${groupChecked}${groupDisabled}>
                                <span>${escapeHtml(group.nomer || '-')} (${escapeHtml(group.soni)} talaba${escapeHtml(usedText)})</span>
                            </label>
                        `;
                    }).join('')
                    : '<div class="mandatory-group-empty">Bu yo\'nalish uchun guruh topilmadi</div>';
                html += `
                    <tr>
                        <td><input type="checkbox" class="mandatory-fan-check" value="${escapeHtml(fanId)}"${checked}${disabled}></td>
                        <td>${escapeHtml(row.fan_code || '-')}</td>
                        <td>${escapeHtml(row.fan_name || '-')}</td>
                        <td>${escapeHtml(row.kafedra_name || '-')}</td>
                        <td>${escapeHtml(row.yonalish_name || '-')}</td>
                        <td>${escapeHtml(getDisplayAcademicYearLabel(row, filters))}</td>
                        <td>${escapeHtml(displayCourse ? `${displayCourse}-kurs` : '-')}</td>
                        <td>${escapeHtml(row.semestr_num ? `${row.semestr_num}-semestr` : '-')}</td>
                    </tr>
                    <tr class="mandatory-groups-row">
                        <td></td>
                        <td colspan="7">
                            <div class="mandatory-groups">${groupHtml}</div>
                        </td>
                    </tr>
                `;
            });
            if (filteredMandatoryFanRows.length > mandatoryRenderLimit) {
                html += `
                    <tr>
                        <td colspan="8" class="created-list-empty">
                            Tez ishlashi uchun hozir birinchi ${mandatoryRenderLimit} ta qator ko'rsatildi. Qolganlarini ko'rish uchun yuqoridagi filter yoki qidiruvdan foydalaning.
                        </td>
                    </tr>
                `;
            }
            tbody.html(html);

            const allVisibleSelected = visibleRows.length > 0
                && visibleRows.every((row) => {
                    const availableGroups = getAvailableGroups(row);
                    return availableGroups.length > 0
                        && availableGroups.every((group) => selectedMandatoryGroupKeys.has(getGroupKey(row, group.id)));
                });
            $('#mandatoryFanSelectAll').prop('checked', allVisibleSelected);
        }

        function retainOnlyFilteredSelections() {
            const visibleGroupKeys = new Set();
            filteredMandatoryFanRows.slice(0, mandatoryRenderLimit).forEach((row) => {
                getAvailableGroups(row).forEach((group) => {
                    visibleGroupKeys.add(getGroupKey(row, group.id));
                });
            });

            Array.from(selectedMandatoryGroupKeys).forEach((key) => {
                if (!visibleGroupKeys.has(key)) {
                    selectedMandatoryGroupKeys.delete(key);
                }
            });
        }

        function applyTopFiltersToMandatoryList() {
            const filters = getTopFilters();
            const search = String($('#mandatoryFanSearch').val() || '').trim().toLowerCase();

            filteredMandatoryFanRows = allMandatoryFanRows.filter((row) => {
                if (filters.yonalishIds.length > 0 && !filters.yonalishIds.includes(String(row.yonalish_id || ''))) {
                    return false;
                }
                if (filters.kirishYili !== '' && !isItemInAcademicYear(row, filters.kirishYili)) {
                    return false;
                }
                if (filters.talimShakliId !== '' && String(row.talim_shakli_id || '') !== filters.talimShakliId) {
                    return false;
                }
                const displayCourse = getDisplayCourse(row, filters);
                if (filters.kurs !== '' && String(displayCourse || '') !== filters.kurs) {
                    return false;
                }
                if (filters.semestrNum !== '' && String(row.semestr_num || '') !== filters.semestrNum) {
                    return false;
                }
                if (getAvailableGroups(row).length === 0) {
                    return false;
                }

                if (search !== '') {
                    const haystack = `${row.fan_code || ''} ${row.fan_name || ''}`.toLowerCase();
                    if (!haystack.includes(search)) {
                        return false;
                    }
                }
                return true;
            });

            // Izoh: Filterdan keyin ko'rinmay qolgan eski tanlovlar xato validatsiya bermasin.
            retainOnlyFilteredSelections();
            renderMandatoryFanTable();
        }

        function applyTopFiltersToRows() {
            updateFilteredSemestrCount();
            applyTopFiltersToMandatoryList();
        }

        $(document).ready(function() {
            setupSelect2ForFilters();
            rebuildYonalishFilterOptions($('#yonalishFilter').val() || []);
            applyTopFiltersToRows();
        });

        $('#yonalishFilter, #kirishYiliFilter, #talimShakliFilter, #kursFilter, #semestrNumFilter').on('change', function() {
            applyTopFiltersToRows();
        });
        $('#applyTopFiltersBtn').on('click', function() {
            applyTopFiltersToRows();
        });
        $('#resetTopFiltersBtn').on('click', function() {
            rebuildYonalishFilterOptions([]);
            $('#kirishYiliFilter').val('').trigger('change.select2');
            $('#talimShakliFilter').val('').trigger('change.select2');
            $('#kursFilter').val('').trigger('change.select2');
            $('#semestrNumFilter').val('').trigger('change.select2');
            applyTopFiltersToRows();
        });
        $('#mandatoryFanSearch').on('input', function() {
            applyTopFiltersToMandatoryList();
        });
        $('#mandatoryFanSelectAll').on('change', function() {
            const checked = $(this).is(':checked');
            filteredMandatoryFanRows.slice(0, mandatoryRenderLimit).forEach((row) => {
                getAvailableGroups(row).forEach((group) => {
                    const key = getGroupKey(row, group.id);
                    if (checked) {
                        selectedMandatoryGroupKeys.add(key);
                    } else {
                        selectedMandatoryGroupKeys.delete(key);
                    }
                });
            });
            renderMandatoryFanTable();
        });
        $(document).on('change', '.mandatory-fan-check', function() {
            const fanId = String($(this).val() || '');
            if (fanId === '') {
                return;
            }
            const row = mandatoryFanById[fanId];
            if (!row) {
                return;
            }
            if ($(this).is(':checked')) {
                getAvailableGroups(row).forEach((group) => selectedMandatoryGroupKeys.add(getGroupKey(row, group.id)));
            } else {
                getAvailableGroups(row).forEach((group) => selectedMandatoryGroupKeys.delete(getGroupKey(row, group.id)));
            }
            renderMandatoryFanTable();
        });
        $(document).on('change', '.mandatory-group-check', function() {
            const fanId = String($(this).data('fan-id') || '');
            const groupId = String($(this).val() || '');
            if (fanId === '' || groupId === '') {
                return;
            }
            const row = mandatoryFanById[fanId];
            if (!row) {
                return;
            }
            const key = getGroupKey(row, groupId);
            if ($(this).is(':checked')) {
                selectedMandatoryGroupKeys.add(key);
            } else {
                selectedMandatoryGroupKeys.delete(key);
            }
            renderMandatoryFanTable();
        });

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });

        $('#birlashtirishForm').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData();
            const masterInput = $('#masterFanId');
            const selectedListRows = getSelectedGroupRows();

            if (selectedListRows.length > 0) {
                const first = selectedListRows[0].row;
                const firstFanId = String(first.fan_id || '');
                const firstFanKey = normalizeFanName(String(first.fan_name || ''));
                const firstSemestrNum = String(first.semestr_num || '');

                if (firstFanId === '') {
                    Toast.fire({ icon: 'error', title: "Tanlangan fanlarda xatolik bor" });
                    return;
                }

                for (const item of selectedListRows) {
                    const row = item.row;
                    const group = item.group;
                    const fanId = String(row.fan_id || '');
                    const semestrId = String(row.semestr_id || '');
                    const fanKey = normalizeFanName(String(row.fan_name || ''));
                    const semestrNum = String(row.semestr_num || '');

                    if (fanId === '' || semestrId === '') {
                        Toast.fire({ icon: 'error', title: "Tanlangan fanlarda semestr ma'lumoti yetarli emas" });
                        return;
                    }
                    if (fanKey !== firstFanKey) {
                        Toast.fire({ icon: 'error', title: "Faqat bir xil fan nomi tanlanishi mumkin" });
                        return;
                    }
                    if (semestrNum !== firstSemestrNum) {
                        Toast.fire({ icon: 'error', title: "Faqat bir xil semestr raqamidagi fanlar tanlanishi mumkin" });
                        return;
                    }
                    formData.append('semestr_ids[]', semestrId);
                    formData.append('fan_ids[]', fanId);
                    formData.append('guruh_ids[]', String(group.id || ''));
                }

                if (selectedListRows.length === 0) {
                    Toast.fire({ icon: 'error', title: "Biriktirish uchun kamida 1 ta guruh tanlang" });
                    return;
                }

                masterInput.val(firstFanId);
            } else {
                Toast.fire({ icon: 'error', title: "Ro'yxatdan kamida 1 ta fan tanlang" });
                return;
            }

            formData.append('master_fan_id', masterInput.val());
            // Izoh: Faqat ushbu sahifada biriktirish fan nomi bo'yicha ishlaydi.
            formData.append('merge_by_name_only', '1');

            // Izoh: Birlashtiriladigan fanlarni biriktirish ma'lumotini serverga yuborish.
            fetch('insert/add_umumtalim_birlashtirish.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Toast.fire({
                        icon: 'success',
                        title: data.message || "Birlashtiriladigan fan biriktirildi"
                    });

                    this.reset();
                    $('#masterFanId').val('');
                    selectedMandatoryGroupKeys.clear();
                    applyTopFiltersToRows();
                    // Izoh: Biriktirilgan fanlar ro'yxati ko'rinishi uchun sahifani yangilaymiz.
                    setTimeout(() => {
                        window.location.reload();
                    }, 400);
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
    </script>
    <script src="../assets/js/app.js"></script>
</body>
</html>
