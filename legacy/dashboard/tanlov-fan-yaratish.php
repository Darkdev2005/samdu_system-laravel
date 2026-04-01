<?php
    // Izoh: Tanlov fan yaratish - o'quv reja sahifasida yaratilgan tanlov fan (kod + nom) selectda chiqadi.
    // Izoh: Selectdan olingan kod hidden `tanlov_fan_code` ga yoziladi, nomi esa `tanlov_fan_base_nomi` ga.
    include_once 'config.php';
    $db = new Database();
    $semestrlar = $db->get_semestrlar();
    $fakultetlar = $db->get_data_by_table_all('fakultetlar');
    $yonalishlar = $db->get_data_by_table_all('yonalishlar');
    $kafedralar = $db->get_data_by_table_all('kafedralar');
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
    // Izoh: Tanlov fan select uchun faqat o'quv rejada yaratilgan fanlar olinadi (semestr bo'yicha).
    $tanlov_fanlar = $db->get_data_by_table_all('fanlar', 'WHERE tanlov_fan = 1 AND (kafedra_id = 0 OR kafedra_id IS NULL OR kafedra_id = "")');
    $tanlovFanOptionsBySemestr = [];
    $tanlovSeen = [];
    foreach ($tanlov_fanlar as $fan) {
        $semestrId = (int) ($fan['semestr_id'] ?? 0);
        $code = trim($fan['fan_code'] ?? '');
        $name = trim($fan['fan_name'] ?? '');
        if ($semestrId <= 0 || $code === '' || $name === '') {
            continue;
        }
        $key = $semestrId . '|' . $code . '|' . $name;
        if (isset($tanlovSeen[$key])) {
            continue;
        }
        $tanlovSeen[$key] = true;
        $safeCode = htmlspecialchars($code);
        $safeName = htmlspecialchars($name);
        if (!isset($tanlovFanOptionsBySemestr[$semestrId])) {
            $tanlovFanOptionsBySemestr[$semestrId] = '';
        }
        $tanlovFanOptionsBySemestr[$semestrId] .= "<option value=\"{$safeCode}\" data-name=\"{$safeName}\">{$safeCode} - {$safeName}</option>";
    }
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Tanlov fan yaratish</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .top-filters-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(220px, 1fr));
            gap: 12px;
        }
        .top-filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        @media (max-width: 1100px) {
            .top-filters-grid {
                grid-template-columns: repeat(2, minmax(220px, 1fr));
            }
        }
        @media (max-width: 700px) {
            .top-filters-grid {
                grid-template-columns: 1fr;
            }
        }
        .created-list-note {
            margin-top: 8px;
            color: #64748b;
            font-size: 13px;
        }
        .created-list-controls {
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .created-list-controls-left {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            min-width: 220px;
        }
        .created-list-search {
            max-width: 360px;
            width: 100%;
        }
        .created-list-empty {
            text-align: center;
            color: #64748b;
            padding: 18px 12px;
        }
        .table-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .created-list-footer {
            margin-top: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        .created-list-pagination {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .created-list-page-size {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #334155;
            font-size: 13px;
        }
        .created-list-page-size select {
            width: auto;
            min-width: 72px;
        }
        .created-list-page-info {
            color: #64748b;
            font-size: 13px;
            min-width: 100px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-navbar">
                <h1>Tanlov fan yaratish</h1>
            </header>
            <div class="content-container">
                <form id="tanlovFanForm" class="card">
                    <h3 class="section-title">Umumiy ma'lumot</h3>
                    <div class="top-filters-grid">
                        <div class="form-group">
                            <label>Fakultet filtri</label>
                            <select class="form-control" id="fakultetFilter">
                                <option value="">Barcha fakultetlar</option>
                                <?php foreach ($fakultetlar as $f): ?>
                                    <option value="<?= (int)$f['id'] ?>"><?= $h($f['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Yo'nalish filtri</label>
                            <select class="form-control" id="yonalishFilter">
                                <option value="">Yo'nalishni tanlang</option>
                                <?php foreach ($filterYonalishlar as $y): ?>
                                    <option
                                        value="<?= (int)$y['id'] ?>"
                                        data-fakultet-id="<?= (int)$y['fakultet_id'] ?>"
                                    >
                                        <?= $h((string)$y['name'] . (!empty($y['kirish_yili']) ? ' - ' . (string)$y['kirish_yili'] : '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semestr</label>
                            <select class="form-control" name="semestr_id" id="semestrSelect" required>
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
                                        $yonalishId = (int)($s['yonalish_id'] ?? 0);
                                        $fakultetId = (int)($yonalishFakultetMap[$yonalishId] ?? 0);
                                        if ($fakultetId <= 0) {
                                            $fakultetId = (int)($s['yonalish_fakultet_id'] ?? ($s['fakultet_id'] ?? 0));
                                        }
                                    ?>
                                    <option
                                        value="<?= (int)$s['id'] ?>"
                                        data-fakultet-id="<?= $fakultetId ?>"
                                        data-yonalish-id="<?= $yonalishId ?>"
                                    >
                                        <?= $h($darajaPrefix . $short . '_' . ($s['kirish_yili'] ?? '') . ' - ' . ($s['semestr'] ?? '') . '-semestr') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="top-filter-actions">
                        <button type="button" class="btn btn-primary btn-sm" id="applyTopFiltersBtn">
                            <i class="fas fa-filter"></i> Filtrlash
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" id="resetTopFiltersBtn">
                            <i class="fas fa-rotate-left"></i> Tozalash
                        </button>
                    </div>

                    <div id="rejaWrapper">
                        <div class="reja-card" data-index="0">
                            <div class="tanlovfan-actions">
                                <input type="hidden" name="tanlov_fan[0]" value="1" class="tanlov-input">
                                <input type="hidden" name="tanlov_fan_code[0]" class="tanlov-code-input">
                                <input type="hidden" name="tanlov_fan_base_nomi[0]" class="tanlov-base-input">
                                <button type="button" class="btn btn-outline btn-sm fanTypeToggle active" disabled>
                                    <i class="fas fa-check-circle"></i> Tanlov fan
                                </button>
                            </div>

                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>Tanlov fan (kod + nomi)</label>
                                    <select class="form-control tanlov-fan-select" name="tanlov_fan_base[0]" required>
                                        <option value="">Tanlang</option>
                                    </select>
                                </div>
                            </div>

                            <div class="tanlov-fan-item" data-tanlov-index="0">
                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <label>Tanlov fan nomi</label>
                                        <!-- Izoh: Selectdan keyin variant fan nomi inputdan kiritiladi -->
                                        <input type="text" class="form-control" name="tanlov_fan_nomi[0][]" placeholder="Masalan: Matematika 1" required>
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
                                    <button type="button" class="btn btn-outline btn-sm addTanlovFan">
                                        <i class="fas fa-plus"></i> Yana tanlov varianti
                                    </button>
                                    
                                    <button type="button" class="btn btn-danger btn-sm removeTanlovFan">
                                        <i class="fas fa-times"></i> O'chirish
                                    </button>
                                </div>
                            </div>

                            <div class="reja-actions">
                                <button type="button" class="btn btn-outline btn-sm addReja">
                                    <i class="fas fa-plus"></i> Yana fan
                                </button>

                                <button type="button" class="btn btn-danger btn-sm removeReja">
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

                <div class="card mt-4">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Yaratilgan tanlov fanlar ro'yxati</h3>
                            <span class="badge" id="createdTanlovCount">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <button type="button" class="btn btn-outline btn-sm" id="refreshCreatedTanlovBtn">
                                <i class="fas fa-rotate"></i> Yangilash
                            </button>
                        </div>
                    </div>
                    <div class="created-list-note">
                        Jadvalda faqat kiritilgan tanlov fan variantlari ko'rsatiladi. Yuqoridagi filtrlar bo'yicha ro'yxatni toraytirishingiz mumkin.
                    </div>
                    <div class="created-list-controls">
                        <div class="created-list-controls-left">
                            <input
                                type="text"
                                class="form-control created-list-search"
                                id="createdTanlovSearchInput"
                                placeholder="Jadvaldan qidirish..."
                            >
                        </div>
                    </div>
                    <div class="table-responsive mt-2">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Fan kodi</th>
                                    <th>Asosiy fan</th>
                                    <th>Tanlov varianti</th>
                                    <th>Kafedra</th>
                                    <th>Semestr</th>
                                    <th>Harakat</th>
                                </tr>
                            </thead>
                            <tbody id="createdTanlovTableBody">
                                <tr>
                                    <td colspan="6" class="created-list-empty">Yuklanmoqda...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="created-list-footer">
                        <div class="created-list-page-size">
                            <label for="createdTanlovRowsPerPage">Sahifada:</label>
                            <select id="createdTanlovRowsPerPage" class="form-control form-control-sm">
                                <option value="10" selected>10 ta</option>
                                <option value="25">25 ta</option>
                                <option value="50">50 ta</option>
                                <option value="100">100 ta</option>
                            </select>
                        </div>
                        <div class="created-list-pagination">
                            <button type="button" class="btn btn-outline btn-sm" id="createdTanlovPrevPage">Oldingi</button>
                            <span class="created-list-page-info" id="createdTanlovPageInfo">0-0 / 0</span>
                            <button type="button" class="btn btn-outline btn-sm" id="createdTanlovNextPage">Keyingi</button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="/assets/vendor/select2/css/select2.min.css" rel="stylesheet" />
    <script src="/assets/vendor/jquery/jquery-3.6.0.min.js"></script>
    <script>window.jQuery || document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>')</script>
    <script src="/assets/vendor/select2/js/select2.min.js"></script>
    <script>if (window.jQuery && !window.jQuery.fn.select2) { document.write('<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"><\/script>'); }</script>

    <script>
        if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.select2 !== 'function') {
            // Izoh: Hostda select2 yuklanmasa ham sahifa JS'i to'xtab qolmasin.
            window.jQuery.fn.select2 = function() { return this; };
        }

        let fanIndex = 0;
        const allYonalishFilterOptions = [];
        const allSemestrFilterOptions = [];
        let createdTanlovRowsById = {};
        let createdTanlovRowsAll = [];
        let createdTanlovRowsFiltered = [];
        let createdTanlovPage = 1;
        let createdTanlovRowsPerPage = 10;

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

        function cacheTopFilterOptions() {
            allYonalishFilterOptions.length = 0;
            $('#yonalishFilter option').each(function() {
                const value = String($(this).attr('value') || '');
                if (!value) return;
                allYonalishFilterOptions.push({
                    value,
                    label: $(this).text(),
                    fakultetId: String($(this).data('fakultet-id') || ''),
                });
            });

            allSemestrFilterOptions.length = 0;
            $('#semestrSelect option').each(function() {
                const value = String($(this).attr('value') || '');
                if (!value) return;
                allSemestrFilterOptions.push({
                    value,
                    label: $(this).text(),
                    fakultetId: String($(this).data('fakultet-id') || ''),
                    yonalishId: String($(this).data('yonalish-id') || ''),
                });
            });
        }

        function rebuildYonalishFilter(preferredValue = '') {
            const selectedFakultet = getSelectedIdWithFallback($('#fakultetFilter'), ['Barcha fakultetlar', 'Fakultetni tanlang']);
            const select = $('#yonalishFilter');
            const current = String(preferredValue || getSelectedIdWithFallback(select, ["Yo'nalishni tanlang"]));

            select.empty().append('<option value="">Yo\'nalishni tanlang</option>');
            allYonalishFilterOptions
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

        function rebuildSemestrFilter(preferredValue = '') {
            const selectedFakultet = getSelectedIdWithFallback($('#fakultetFilter'), ['Barcha fakultetlar', 'Fakultetni tanlang']);
            const selectedYonalish = getSelectedIdWithFallback($('#yonalishFilter'), ["Yo'nalishni tanlang"]);
            const select = $('#semestrSelect');
            const current = String(preferredValue || getSelectedIdWithFallback(select, ['Semestrni tanlang']));

            select.empty().append('<option value="">Semestrni tanlang</option>');
            allSemestrFilterOptions
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

            refreshTanlovOptionsBySemestr();
        }

        function refreshTanlovOptionsBySemestr() {
            const semestrId = getSelectedIdWithFallback($('#semestrSelect'), ['Semestrni tanlang']);
            $('.tanlov-fan-select').each(function() {
                renderTanlovOptions($(this), semestrId);
            });
        }

        function getCreatedTanlovSemestrLabel(row) {
            const yonalishName = String(row.yonalish_name || '-');
            const kirishYili = String(row.kirish_yili || '-');
            const semestrNum = String(row.semestr_num || '-');
            return `${yonalishName} - ${kirishYili} / ${semestrNum}`;
        }

        function getCreatedTanlovRowSearchText(row) {
            return [
                row.fan_code || '',
                row.base_fan_name || '',
                row.fan_name || '',
                row.kafedra_name || '',
                row.yonalish_name || '',
                row.kirish_yili || '',
                row.semestr_num || ''
            ].join(' ').toLowerCase();
        }

        function renderCreatedTanlovTable() {
            const tbody = $('#createdTanlovTableBody');
            const countBadge = $('#createdTanlovCount');
            const rows = createdTanlovRowsFiltered;
            const totalRows = rows.length;
            countBadge.text(`${totalRows} ta`);

            if (!totalRows) {
                tbody.html('<tr><td colspan="6" class="created-list-empty">Tanlangan filter bo\'yicha tanlov fan topilmadi</td></tr>');
                $('#createdTanlovPageInfo').text('0-0 / 0');
                $('#createdTanlovPrevPage').prop('disabled', true);
                $('#createdTanlovNextPage').prop('disabled', true);
                return;
            }

            const totalPages = Math.max(1, Math.ceil(totalRows / createdTanlovRowsPerPage));
            if (createdTanlovPage > totalPages) {
                createdTanlovPage = totalPages;
            }
            if (createdTanlovPage < 1) {
                createdTanlovPage = 1;
            }

            const fromIndex = (createdTanlovPage - 1) * createdTanlovRowsPerPage;
            const pageRows = rows.slice(fromIndex, fromIndex + createdTanlovRowsPerPage);
            let html = '';
            pageRows.forEach((row) => {
                html += `
                    <tr>
                        <td>${escapeOptionText(row.fan_code || '-')}</td>
                        <td>${escapeOptionText(row.base_fan_name || '-')}</td>
                        <td>${escapeOptionText(row.fan_name || '-')}</td>
                        <td>${escapeOptionText(row.kafedra_name || '-')}</td>
                        <td>${escapeOptionText(getCreatedTanlovSemestrLabel(row))}</td>
                        <td>
                            <div class="table-actions">
                                <button type="button" class="btn btn-outline btn-sm editCreatedTanlovBtn" data-fan-id="${row.fan_id}">
                                    <i class="fas fa-pen"></i> Tahrirlash
                                </button>
                                <button type="button" class="btn btn-danger btn-sm deleteCreatedTanlovBtn" data-fan-id="${row.fan_id}">
                                    <i class="fas fa-trash-alt"></i> O'chirish
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            tbody.html(html);
            const toIndex = Math.min(fromIndex + pageRows.length, totalRows);
            $('#createdTanlovPageInfo').text(`${fromIndex + 1}-${toIndex} / ${totalRows}`);
            $('#createdTanlovPrevPage').prop('disabled', createdTanlovPage <= 1);
            $('#createdTanlovNextPage').prop('disabled', createdTanlovPage >= totalPages);
        }

        function applyCreatedTanlovSearch(resetPage = true) {
            const query = String($('#createdTanlovSearchInput').val() || '').trim().toLowerCase();
            if (query === '') {
                createdTanlovRowsFiltered = createdTanlovRowsAll.slice();
            } else {
                createdTanlovRowsFiltered = createdTanlovRowsAll.filter((row) => {
                    return getCreatedTanlovRowSearchText(row).includes(query);
                });
            }
            if (resetPage) {
                createdTanlovPage = 1;
            }
            renderCreatedTanlovTable();
        }

        function loadCreatedTanlovList(resetPage = true) {
            const fakultetId = getSelectedIdWithFallback($('#fakultetFilter'), ['Barcha fakultetlar', 'Fakultetni tanlang']) || '';
            const yonalishId = getSelectedIdWithFallback($('#yonalishFilter'), ["Yo'nalishni tanlang", "Barcha yo'nalishlar"]) || '';
            const semestrId = getSelectedIdWithFallback($('#semestrSelect'), ['Semestrni tanlang', 'Barcha semestrlar']) || '';
            const url = `api/get_tanlov_fan_created_list.php?fakultet_id=${encodeURIComponent(fakultetId)}&yonalish_id=${encodeURIComponent(yonalishId)}&semestr_id=${encodeURIComponent(semestrId)}`;

            fetch(url)
                .then((res) => res.json())
                .then((data) => {
                    if (!data || !data.success) {
                        createdTanlovRowsById = {};
                        createdTanlovRowsAll = [];
                        createdTanlovRowsFiltered = [];
                        const message = (data && data.message) ? String(data.message) : "Ro'yxatni yuklab bo'lmadi";
                        $('#createdTanlovTableBody').html(`<tr><td colspan="6" class="created-list-empty">${escapeOptionText(message)}</td></tr>`);
                        $('#createdTanlovCount').text('0 ta');
                        $('#createdTanlovPageInfo').text('0-0 / 0');
                        $('#createdTanlovPrevPage').prop('disabled', true);
                        $('#createdTanlovNextPage').prop('disabled', true);
                        return;
                    }

                    const rows = Array.isArray(data.rows) ? data.rows : [];
                    createdTanlovRowsById = {};
                    rows.forEach((row) => {
                        const fanId = parseInt(row.fan_id || 0, 10);
                        if (fanId > 0) {
                            createdTanlovRowsById[String(fanId)] = row;
                        }
                    });

                    createdTanlovRowsAll = rows;
                    applyCreatedTanlovSearch(resetPage);
                })
                .catch(() => {
                    createdTanlovRowsById = {};
                    createdTanlovRowsAll = [];
                    createdTanlovRowsFiltered = [];
                    $('#createdTanlovTableBody').html('<tr><td colspan="6" class="created-list-empty">Server bilan bog\'lanib bo\'lmadi</td></tr>');
                    $('#createdTanlovCount').text('0 ta');
                    $('#createdTanlovPageInfo').text('0-0 / 0');
                    $('#createdTanlovPrevPage').prop('disabled', true);
                    $('#createdTanlovNextPage').prop('disabled', true);
                });
        }

        function buildEditKafedraOptions(selectedId) {
            const selected = String(selectedId || '');
            let html = '<option value="">Tanlang</option>';
            (kafedralarList || []).forEach((item) => {
                const id = String(item.id || '');
                if (!id) return;
                const selectedAttr = id === selected ? ' selected' : '';
                html += `<option value="${id}"${selectedAttr}>${escapeOptionText(item.name || '')}</option>`;
            });
            return `<select id="editTanlovKafedraId" class="swal2-input">${html}</select>`;
        }

        $(document).ready(function() {
            cacheTopFilterOptions();

            $('#fakultetFilter').select2({
                placeholder: "Fakultetni tanlang",
                allowClear: true,
                width: '100%',
            });
            $('#yonalishFilter').select2({
                placeholder: "Yo'nalishni tanlang",
                allowClear: true,
                width: '100%',
            });
            $('#semestrSelect').select2({
                placeholder: "Semestrni tanlang",
                allowClear: true,
                width: '100%',
            });

            rebuildYonalishFilter();
            rebuildSemestrFilter();

            initializeSelect2($('.reja-card:first'));
            refreshTanlovOptionsBySemestr();

            $('#fakultetFilter').on('change', function() {
                rebuildYonalishFilter('');
                rebuildSemestrFilter('');
            });

            $('#yonalishFilter').on('change', function() {
                rebuildSemestrFilter('');
            });

            $('#applyTopFiltersBtn').on('click', function() {
                rebuildYonalishFilter(getSelectedIdWithFallback($('#yonalishFilter'), ["Yo'nalishni tanlang"]));
                rebuildSemestrFilter(getSelectedIdWithFallback($('#semestrSelect'), ['Semestrni tanlang']));
                loadCreatedTanlovList(true);
            });

            $('#resetTopFiltersBtn').on('click', function() {
                $('#fakultetFilter').val('').trigger('change');
                rebuildYonalishFilter('');
                rebuildSemestrFilter('');
                loadCreatedTanlovList(true);
            });

            const syncTopFilters = () => {
                const currentYonalish = getSelectedIdWithFallback($('#yonalishFilter'), ["Yo'nalishni tanlang"]);
                const currentSemestr = getSelectedIdWithFallback($('#semestrSelect'), ['Semestrni tanlang']);
                rebuildYonalishFilter(currentYonalish);
                rebuildSemestrFilter(currentSemestr);
            };

            syncTopFilters();
            setTimeout(syncTopFilters, 150);
            $(window).on('pageshow', function() {
                setTimeout(syncTopFilters, 0);
            });

            $('#yonalishFilter').on('select2:opening focus mousedown click', function() {
                const currentYonalish = getSelectedIdWithFallback($('#yonalishFilter'), ["Yo'nalishni tanlang"]);
                rebuildYonalishFilter(currentYonalish);
                rebuildSemestrFilter(getSelectedIdWithFallback($('#semestrSelect'), ['Semestrni tanlang']));
            });

            $('#createdTanlovSearchInput').on('input', function() {
                applyCreatedTanlovSearch(true);
            });
            $('#refreshCreatedTanlovBtn').on('click', function() {
                loadCreatedTanlovList(false);
            });
            $('#createdTanlovRowsPerPage').on('change', function() {
                const val = parseInt($(this).val() || '10', 10);
                createdTanlovRowsPerPage = Number.isNaN(val) || val <= 0 ? 10 : val;
                createdTanlovPage = 1;
                renderCreatedTanlovTable();
            });
            $('#createdTanlovPrevPage').on('click', function() {
                if (createdTanlovPage > 1) {
                    createdTanlovPage--;
                    renderCreatedTanlovTable();
                }
            });
            $('#createdTanlovNextPage').on('click', function() {
                const total = createdTanlovRowsFiltered.length;
                const totalPages = Math.max(1, Math.ceil(total / createdTanlovRowsPerPage));
                if (createdTanlovPage < totalPages) {
                    createdTanlovPage++;
                    renderCreatedTanlovTable();
                }
            });

            loadCreatedTanlovList(true);
        });

        // Izoh: Semestr tanlanganda tanlov fanlar ro'yxatini yangilash.
        $('#semestrSelect').on('change', function() {
            refreshTanlovOptionsBySemestr();
            loadCreatedTanlovList(true);
        });

        const kafedralarList = <?php echo $kafedralarJson; ?>;

        function buildKafedralarOptionsHtml() {
            let html = '';
            (kafedralarList || []).forEach((item) => {
                const id = String(item.id || '');
                if (id === '') return;
                html += `<option value="${id}">${escapeOptionText(item.name || '')}</option>`;
            });
            return html;
        }

        // Izoh: Tanlov fan select uchun optionlar (semestr bo'yicha).
        const tanlovFanOptionsBySemestr = <?php echo json_encode($tanlovFanOptionsBySemestr, JSON_UNESCAPED_UNICODE); ?>;

        function buildTanlovCard(index) {
            return `
                <div class="tanlovfan-actions">
                    <input type="hidden" name="tanlov_fan[${index}]" value="1" class="tanlov-input">
                    <input type="hidden" name="tanlov_fan_code[${index}]" class="tanlov-code-input">
                    <input type="hidden" name="tanlov_fan_base_nomi[${index}]" class="tanlov-base-input">
                    <button type="button" class="btn btn-outline btn-sm fanTypeToggle active" disabled>
                        <i class="fas fa-check-circle"></i> Tanlov fan
                    </button>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Tanlov fan (kod + nomi)</label>
                        <select class="form-control tanlov-fan-select" name="tanlov_fan_base[${index}]" required>
                            <option value="">Tanlang</option>
                        </select>
                    </div>
                </div>

                <div class="tanlov-fan-item" data-tanlov-index="0">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Tanlov fan nomi</label>
                            <!-- Izoh: Selectdan keyin variant fan nomi inputdan kiritiladi -->
                            <input type="text" class="form-control" name="tanlov_fan_nomi[${index}][]" placeholder="Masalan: Matematika 1" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Kafedra</label>
                            <select class="form-control" name="tanlov_kafedra_id[${index}][]" required>
                                <option value="">Tanlang</option>
                                ${buildKafedralarOptionsHtml()}
                            </select>
                        </div>
                    </div>
                    
                    <div class="tanlov-fan-actions mb-3">
                        <button type="button" class="btn btn-outline btn-sm addTanlovFan">
                            <i class="fas fa-plus"></i> Yana tanlov varianti
                        </button>
                        
                        <button type="button" class="btn btn-danger btn-sm removeTanlovFan">
                            <i class="fas fa-times"></i> O'chirish
                        </button>
                    </div>
                </div>

                <div class="reja-actions">
                    <button type="button" class="btn btn-outline btn-sm addReja">
                        <i class="fas fa-plus"></i> Yana fan
                    </button>

                    <button type="button" class="btn btn-danger btn-sm removeReja">
                        <i class="fas fa-times"></i> O'chirish
                    </button>
                </div>
            `;
        }

        function renderTanlovOptions(select, semestrId) {
            select.empty().append(new Option('Tanlang', '', false, false));
            if (!semestrId) {
                select.val(null).trigger('change');
                return;
            }
            if (tanlovFanOptionsBySemestr[semestrId]) {
                select.append(tanlovFanOptionsBySemestr[semestrId]);
            } else {
                select.append(new Option("Tanlov fan topilmadi", "", false, false));
            }
            select.val(null).trigger('change');
        }

        $(document).on('click', '.addReja', function() {
            fanIndex++;
            const newCard = $(`<div class="reja-card" data-index="${fanIndex}"></div>`);
            $('#rejaWrapper').append(newCard);
            newCard.html(buildTanlovCard(fanIndex));
            initializeSelect2(newCard);
            const semestrId = getSelectedIdWithFallback($('#semestrSelect'), ['Semestrni tanlang']);
            const tanlovSelect = newCard.find('.tanlov-fan-select');
            renderTanlovOptions(tanlovSelect, semestrId);
        });

        $(document).on('click', '.addTanlovFan', function() {
            const card = $(this).closest('.reja-card');
            const index = card.data('index');
            const tanlovWrapper = $(this).closest('.tanlov-fan-item');
            const tanlovIndex = parseInt(tanlovWrapper.data('tanlov-index')) + 1;
            
            const newTanlovItem = $(`
                <div class="tanlov-fan-item mt-3" data-tanlov-index="${tanlovIndex}">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Tanlov fan nomi</label>
                            <!-- Izoh: Selectdan keyin variant fan nomi inputdan kiritiladi -->
                            <input type="text" class="form-control" name="tanlov_fan_nomi[${index}][]" placeholder="Masalan: Matematika 1" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Kafedra</label>
                            <select class="form-control" name="tanlov_kafedra_id[${index}][]" required>
                                <option value="">Tanlang</option>
                                ${buildKafedralarOptionsHtml()}
                            </select>
                        </div>
                    </div>
                    
                    <div class="tanlov-fan-actions mb-3">
                        <button type="button" class="btn btn-outline btn-sm addTanlovFan">
                            <i class="fas fa-plus"></i> Yana tanlov varianti
                        </button>
                        
                        <button type="button" class="btn btn-danger btn-sm removeTanlovFan">
                            <i class="fas fa-times"></i> O'chirish
                        </button>
                    </div>
                </div>
            `);
            
            tanlovWrapper.after(newTanlovItem);
            initializeSelect2(newTanlovItem);
        });

        $(document).on('click', '.removeTanlovFan', function() {
            const tanlovItems = $(this).closest('.reja-card').find('.tanlov-fan-item');
            if (tanlovItems.length > 1) {
                $(this).closest('.tanlov-fan-item').remove();
            }
        });

        $(document).on('click', '.removeReja', function() {
            const rejas = $('.reja-card');
            if (rejas.length > 1) {
                const rejaToRemove = $(this).closest('.reja-card');
                
                rejaToRemove.find('select').each(function() {
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2('destroy');
                    }
                });
                
                rejaToRemove.remove();
                
                reorganizeIndexes();
            }
        });

        function reorganizeIndexes() {
            fanIndex = -1;
            $('.reja-card').each(function(newIndex) {
                fanIndex = newIndex;
                $(this).data('index', newIndex);
                const card = $(this);
                
                card.find('input[name^="tanlov_fan["]').attr('name', `tanlov_fan[${newIndex}]`);
                // Izoh: Tanlov fan select va input nomlarini indeks bo'yicha yangilash.
                card.find('input[name^="tanlov_fan_code["]').attr('name', `tanlov_fan_code[${newIndex}]`);
                card.find('input[name^="tanlov_fan_base_nomi["]').attr('name', `tanlov_fan_base_nomi[${newIndex}]`);
                card.find('select[name^="tanlov_fan_base["]').attr('name', `tanlov_fan_base[${newIndex}]`);
                card.find('input[name^="tanlov_fan_nomi["]').attr('name', `tanlov_fan_nomi[${newIndex}][]`);
                card.find('select[name^="tanlov_kafedra_id["]').attr('name', `tanlov_kafedra_id[${newIndex}][]`);
            });
        }

        function initializeSelect2(container) {
            setTimeout(() => {
                container.find('select').each(function() {
                    const name = $(this).attr('name') || '';

                    if ($(this).hasClass('tanlov-fan-select')) {
                        // Izoh: Tanlov fan select uchun select2 qo'llash.
                        if (!$(this).hasClass('select2-hidden-accessible')) {
                            $(this).select2({
                                placeholder: "Tanlov fanni tanlang",
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

        // Izoh: Tanlov fan selectdan kod va nomni hidden inputlarga yozish.
        $(document).on('change', '.tanlov-fan-select', function() {
            const selected = $(this).find('option:selected');
            const code = $(this).val() || '';
            const baseName = selected.data('name') || '';
            const card = $(this).closest('.reja-card');
            const codeInput = card.find('.tanlov-code-input');
            const baseInput = card.find('.tanlov-base-input');

            codeInput.val(code);
            baseInput.val(baseName);
        });

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });

        $(document).on('click', '.editCreatedTanlovBtn', function() {
            const fanId = String($(this).data('fan-id') || '');
            const row = createdTanlovRowsById[fanId];
            if (!row) return;

            Swal.fire({
                title: "Tanlov fanini tahrirlash",
                html: `
                    <input id="editTanlovFanName" class="swal2-input" placeholder="Tanlov varianti nomi" value="${escapeOptionText(row.fan_name || '')}">
                    <div style="text-align:left;margin:8px 0 4px 0;font-size:13px;color:#64748b;">Kafedra</div>
                    ${buildEditKafedraOptions(row.kafedra_id || '')}
                `,
                showCancelButton: true,
                confirmButtonText: "Saqlash",
                cancelButtonText: "Bekor qilish",
                preConfirm: () => {
                    const fanName = String($('#editTanlovFanName').val() || '').trim();
                    const kafedraId = parseInt($('#editTanlovKafedraId').val() || '0', 10);
                    if (fanName === '') {
                        Swal.showValidationMessage("Tanlov varianti nomini kiriting");
                        return false;
                    }
                    if (!kafedraId || kafedraId <= 0) {
                        Swal.showValidationMessage("Kafedrani tanlang");
                        return false;
                    }
                    return { fanName, kafedraId };
                }
            }).then((result) => {
                if (!result.isConfirmed || !result.value) return;

                const payload = result.value;
                const formData = new FormData();
                formData.append('fan_id', fanId);
                formData.append('fan_name', payload.fanName);
                formData.append('kafedra_id', String(payload.kafedraId));

                fetch('insert/update_tanlov_fan_item.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        Toast.fire({ icon: 'success', title: data.message || "Tanlov fan yangilandi" });
                        loadCreatedTanlovList(false);
                    } else {
                        Toast.fire({ icon: 'error', title: (data && data.message) || "Yangilashda xatolik" });
                    }
                })
                .catch(() => {
                    Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                });
            });
        });

        $(document).on('click', '.deleteCreatedTanlovBtn', function() {
            const fanId = String($(this).data('fan-id') || '');
            const row = createdTanlovRowsById[fanId];
            if (!row) return;

            Swal.fire({
                title: "Tanlov fanini o'chirasizmi?",
                text: `${row.fan_code || ''} - ${row.fan_name || ''}`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ha, o'chirish",
                cancelButtonText: "Bekor qilish"
            }).then((result) => {
                if (!result.isConfirmed) return;

                const formData = new FormData();
                formData.append('fan_id', fanId);

                fetch('insert/delete_oquv_reja_item.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        Toast.fire({ icon: 'success', title: data.message || "Tanlov fan o'chirildi" });
                        loadCreatedTanlovList(false);
                    } else {
                        Toast.fire({ icon: 'error', title: (data && data.message) || "O'chirishda xatolik" });
                    }
                })
                .catch(() => {
                    Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                });
            });
        });

        $('#tanlovFanForm').on('submit', function(e) {
            e.preventDefault();
            // Izoh: Tanlov fan kodi va nomi select change hodisasida hidden inputga yoziladi.
            
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
                        title: data.message || 'Tanlov fan muvaffaqiyatli saqlandi'
                    });

                    this.reset();
                    $('#semestrSelect').val(null).trigger('change');
                    
                    $('.reja-card:gt(0)').each(function() {
                        $(this).find('select').each(function() {
                            if ($(this).hasClass('select2-hidden-accessible')) {
                                $(this).select2('destroy');
                            }
                        });
                        $(this).remove();
                    });
                    
                    fanIndex = 0;
                    
                    const firstCard = $('.reja-card:first');
                    firstCard.data('index', 0);
                    firstCard.html(buildTanlovCard(0));
                    initializeSelect2(firstCard);
                    loadCreatedTanlovList(false);
                    
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
</body>
</html>
