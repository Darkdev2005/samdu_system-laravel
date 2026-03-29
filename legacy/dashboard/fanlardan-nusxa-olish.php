<?php
include_once 'config.php';
$db = new Database();
$semestrlar = $db->get_semestrlar();
$fakultetlar = $db->get_data_by_table_all('fakultetlar');

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

$filterYonalishlarMap = [];
foreach ($semestrlar as $s) {
    $yonalishId = (int)($s['yonalish_id'] ?? 0);
    if ($yonalishId <= 0 || isset($filterYonalishlarMap[$yonalishId])) {
        continue;
    }

    $filterYonalishlarMap[$yonalishId] = [
        'id' => $yonalishId,
        'name' => (string)($s['yonalish_name'] ?? ''),
        'kirish_yili' => (string)($s['kirish_yili'] ?? ''),
        'fakultet_id' => (int)($s['yonalish_fakultet_id'] ?? ($s['fakultet_id'] ?? 0)),
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

$dars_soat_turlari = $db->get_data_by_table_all('dars_soat_turlar');
$kafedralar = $db->get_data_by_table_all('kafedralar');
$jsonFlags = 0;
if (defined('JSON_UNESCAPED_UNICODE')) {
    $jsonFlags |= JSON_UNESCAPED_UNICODE;
}
if (defined('JSON_UNESCAPED_SLASHES')) {
    $jsonFlags |= JSON_UNESCAPED_SLASHES;
}
if (defined('JSON_HEX_TAG')) {
    $jsonFlags |= JSON_HEX_TAG;
}
if (defined('JSON_HEX_AMP')) {
    $jsonFlags |= JSON_HEX_AMP;
}
if (defined('JSON_HEX_APOS')) {
    $jsonFlags |= JSON_HEX_APOS;
}
if (defined('JSON_HEX_QUOT')) {
    $jsonFlags |= JSON_HEX_QUOT;
}

$darsSoatTurlariJson = json_encode($dars_soat_turlari, $jsonFlags);
if ($darsSoatTurlariJson === false) {
    $darsSoatTurlariJson = '[]';
}
$kafedralarJson = json_encode($kafedralar, $jsonFlags);
if ($kafedralarJson === false) {
    $kafedralarJson = '[]';
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Fanlardan nusxa olish</title>
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
        .created-list-note {
            color: #64748b;
            font-size: 13px;
            margin-top: 6px;
        }
        .created-list-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .created-list-controls-left,
        .created-list-controls-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .created-list-search {
            min-width: 220px;
            max-width: 360px;
        }
        .created-list-per-page {
            width: 95px;
        }
        .created-list-page-info {
            font-size: 13px;
            color: #64748b;
            min-width: 110px;
            text-align: center;
        }
        .compact-list {
            margin: 0;
            padding-left: 18px;
            color: #334155;
            font-size: 13px;
        }
        .compact-list li {
            margin: 2px 0;
        }
        .copy-steps {
            display: grid;
            grid-template-columns: repeat(3, minmax(220px, 1fr));
            gap: 10px;
            margin: 8px 0 14px 0;
        }
        .copy-step {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 13px;
            color: #475569;
        }
        .copy-step b {
            color: #0f172a;
        }
        .mapping-preview {
            margin-top: 12px;
            background: #f8fafc;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            padding: 10px 12px;
        }
        .mapping-preview-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 8px;
        }
        .mapping-preview-note {
            color: #64748b;
            font-size: 12px;
        }
        .mapping-preview-list {
            margin: 0;
            padding-left: 18px;
            color: #334155;
            font-size: 13px;
        }
        .mapping-preview-list li {
            margin: 4px 0;
        }
        .mapping-ok {
            color: #15803d;
        }
        .mapping-miss {
            color: #b91c1c;
        }
        .pair-rows {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 8px;
        }
        .pair-row {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 10px;
            align-items: end;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px;
        }
        .pair-row-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
            display: block;
        }
        .pair-row-index {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 999px;
            background: #e2e8f0;
            color: #0f172a;
            font-size: 12px;
            font-weight: 700;
            margin-right: 8px;
        }
        .pair-row-header {
            display: flex;
            align-items: center;
            color: #334155;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .pair-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
        }
        @media (max-width: 1100px) {
            .top-filters-grid {
                grid-template-columns: repeat(2, minmax(220px, 1fr));
            }
            .copy-steps {
                grid-template-columns: repeat(2, minmax(220px, 1fr));
            }
            .pair-row {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 700px) {
            .top-filters-grid {
                grid-template-columns: 1fr;
            }
            .copy-steps {
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
                <h1>Fanlardan nusxa olish</h1>
            </header>
            <div class="content-container">
                <div class="card">
                    <div class="copy-steps">
                        <div class="copy-step"><b>1-qadam:</b> Maqsad fakultet, yo'nalish va semestr(lar)ni tanlang.</div>
                        <div class="copy-step"><b>2-qadam:</b> Manba fakultet va yo'nalishni tanlang.</div>
                        <div class="copy-step"><b>3-qadam:</b> `+` bilan maqsad-manba semestr juftliklarini qo'shing va nusxalashni bosing.</div>
                    </div>
                    <h3 class="section-title">Maqsad semestr (qabul qiluvchi)</h3>
                    <div class="top-filters-grid">
                        <div class="form-group">
                            <label>Maqsad fakultet</label>
                            <select class="form-control" id="targetFakultet">
                                <option value="">Fakultetni tanlang</option>
                                <?php foreach ($fakultetlar as $f): ?>
                                    <option value="<?= (int)$f['id'] ?>"><?= $h($f['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Maqsad yo'nalish</label>
                            <select class="form-control" id="targetYonalish">
                                <option value="">Yo'nalishni tanlang</option>
                                <?php foreach ($filterYonalishlar as $y): ?>
                                    <option value="<?= (int)$y['id'] ?>" data-fakultet-id="<?= (int)$y['fakultet_id'] ?>">
                                        <?= $h((string)$y['name'] . (!empty($y['kirish_yili']) ? ' - ' . (string)$y['kirish_yili'] : '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Maqsad semestr</label>
                            <select class="form-control" id="targetSemestr" multiple>
                                <option value="">Semestr(lar)ni tanlang</option>
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
                                        data-yonalish-id="<?= $yonalishId ?>"
                                        data-semestr-num="<?= (int)($s['semestr'] ?? 0) ?>"
                                    >
                                        <?= $h($darajaPrefix . $short . '_' . ($s['kirish_yili'] ?? '') . ' - ' . ($s['semestr'] ?? '') . '-semestr') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <h3 class="section-title mt-4">Manba semestr</h3>
                    <div class="created-list-note">
                        Manba sifatida fakultet va yo'nalishni tanlang. Semestrlar avtomatik emas, pastdagi juftliklarda qo'lda tanlanadi.
                    </div>
                    <div class="top-filters-grid mt-2">
                        <div class="form-group">
                            <label>Manba fakultet</label>
                            <select class="form-control" id="sourceFakultet">
                                <option value="">Fakultetni tanlang</option>
                                <?php foreach ($fakultetlar as $f): ?>
                                    <option value="<?= (int)$f['id'] ?>"><?= $h($f['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Manba yo'nalish</label>
                            <select class="form-control" id="sourceYonalish">
                                <option value="">Yo'nalishni tanlang</option>
                                <?php foreach ($filterYonalishlar as $y): ?>
                                    <option value="<?= (int)$y['id'] ?>" data-fakultet-id="<?= (int)$y['fakultet_id'] ?>">
                                        <?= $h((string)$y['name'] . (!empty($y['kirish_yili']) ? ' - ' . (string)$y['kirish_yili'] : '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Juftlik rejimi</label>
                            <input type="text" class="form-control" value="Har qatorda maqsad semestr va manba semestrni alohida tanlaysiz" readonly>
                        </div>
                    </div>

                    <div class="mapping-preview">
                        <div class="mapping-preview-title">Semestr juftliklari</div>
                        <div class="mapping-preview-note">
                            Maqsad semestr(lar)ni va manba semestr(lar)ni qatorma-qator bog'lang. Bir qator = bitta nusxalash juftligi.
                        </div>
                        <div class="pair-rows" id="pairRowsContainer"></div>
                        <div class="pair-actions">
                            <button type="button" class="btn btn-outline btn-sm" id="addPairRowBtn">
                                <i class="fas fa-plus"></i> Juftlik qo'shish
                            </button>
                        </div>
                    </div>

                    <div class="top-filter-actions">
                        <button type="button" class="btn btn-primary btn-sm" id="copyRunBtn">
                            <i class="fas fa-copy"></i> Fanlarni nusxalash
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" id="copyResetBtn">
                            <i class="fas fa-rotate-left"></i> Tozalash
                        </button>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Nusxalangan fanlar ro'yxati</h3>
                            <span class="badge" id="createdRejaCount">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <button type="button" class="btn btn-outline btn-sm" id="refreshCreatedRejaBtn">
                                <i class="fas fa-rotate"></i> Yangilash
                            </button>
                        </div>
                    </div>
                    <div class="created-list-note">
                        Ro'yxat yuqoridagi maqsad filtrlariga ko'ra ko'rsatiladi. "Tahrirlash" va "O'chirish" shu yerda ishlaydi.
                    </div>
                    <div class="created-list-controls">
                        <div class="created-list-controls-left">
                            <input type="text" class="form-control created-list-search" id="createdRejaSearchInput" placeholder="Jadvaldan qidirish...">
                            <select class="form-control created-list-per-page" id="createdRejaPerPage">
                                <option value="10">10 ta</option>
                                <option value="20" selected>20 ta</option>
                                <option value="50">50 ta</option>
                                <option value="100">100 ta</option>
                            </select>
                        </div>
                        <div class="created-list-controls-right">
                            <span class="created-list-page-info" id="createdRejaPageInfo">0-0 / 0</span>
                            <button type="button" class="btn btn-outline btn-sm" id="createdRejaPrevPage" disabled>Oldingi</button>
                            <button type="button" class="btn btn-outline btn-sm" id="createdRejaNextPage" disabled>Keyingi</button>
                        </div>
                    </div>
                    <div class="table-responsive mt-2">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Fan kodi</th>
                                    <th>Fan nomi</th>
                                    <th>Fan turi</th>
                                    <th>Kafedra</th>
                                    <th>Dars soatlari</th>
                                    <th>Semestr</th>
                                    <th>Harakat</th>
                                </tr>
                            </thead>
                            <tbody id="createdRejaTableBody">
                                <tr>
                                    <td colspan="7">Yuklanmoqda...</td>
                                </tr>
                            </tbody>
                        </table>
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
        let allYonalishOptions = [];
        let allSemestrOptions = [];
        let createdRowsById = {};
        let createdRowsAll = [];
        let createdRowsFiltered = [];
        let createdListPage = 1;
        let createdListPerPage = 20;
        let createdListDarsTurlari = [];
        let pairRows = [];
        const darsTurlariListDefault = <?php echo $darsSoatTurlariJson; ?>;
        const kafedralarList = <?php echo $kafedralarJson; ?>;
        const fanTypeLabels = {
            0: "Majburiy",
            1: "Tanlov",
            2: "Birlashtiriladigan",
            3: "Chet tili",
        };

        const SwalApi = window.Swal || {
            mixin: () => ({ fire: () => {} }),
            fire: () => Promise.resolve({ isConfirmed: false }),
            showValidationMessage: () => {},
        };
        const Toast = SwalApi.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2200,
            timerProgressBar: true
        });

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function normalizeArray(value) {
            if (Array.isArray(value)) {
                return value
                    .map(item => String(item || '').trim())
                    .filter(item => item !== '');
            }
            const single = String(value || '').trim();
            return single === '' ? [] : [single];
        }

        function cacheOptions() {
            allYonalishOptions = [];
            $('#targetYonalish option').each(function() {
                const val = String($(this).attr('value') || '');
                if (val === '') return;
                allYonalishOptions.push({
                    id: val,
                    text: $(this).text(),
                    fakultetId: String($(this).data('fakultet-id') || ''),
                });
            });

            allSemestrOptions = [];
            $('#targetSemestr option').each(function() {
                const val = String($(this).attr('value') || '');
                if (val === '') return;
                allSemestrOptions.push({
                    id: val,
                    text: $(this).text(),
                    fakultetId: String($(this).data('fakultet-id') || ''),
                    yonalishId: String($(this).data('yonalish-id') || ''),
                    semestrNum: String($(this).data('semestr-num') || ''),
                });
            });
        }

        function rebuildYonalishOptions(targetSelectId, fakultetValue, selectedValue = '') {
            const select = $('#' + targetSelectId);
            let html = "<option value=\"\">Yo'nalishni tanlang</option>";

            allYonalishOptions.forEach(item => {
                if (fakultetValue !== '' && String(item.fakultetId) !== fakultetValue) {
                    return;
                }
                const selected = String(item.id) === String(selectedValue) ? ' selected' : '';
                const dataAttr = item.fakultetId !== '' ? ` data-fakultet-id="${item.fakultetId}"` : '';
                html += `<option value="${item.id}"${dataAttr}${selected}>${escapeHtml(item.text)}</option>`;
            });

            select.html(html);
        }

        function rebuildSemestrOptions(targetSelectId, fakultetValue, yonalishValue, selectedValue = '') {
            const select = $('#' + targetSelectId);
            const selectedSet = new Set(
                targetSelectId === 'targetSemestr'
                    ? normalizeArray(selectedValue)
                    : [String(selectedValue || '')]
            );
            let html = '<option value="">Semestrni tanlang</option>';

            allSemestrOptions.forEach(item => {
                if (fakultetValue !== '' && String(item.fakultetId) !== fakultetValue) {
                    return;
                }
                if (yonalishValue !== '' && String(item.yonalishId) !== yonalishValue) {
                    return;
                }

                const selected = selectedSet.has(String(item.id)) ? ' selected' : '';
                const fakultetAttr = item.fakultetId !== '' ? ` data-fakultet-id="${item.fakultetId}"` : '';
                const yonalishAttr = item.yonalishId !== '' ? ` data-yonalish-id="${item.yonalishId}"` : '';
                const semestrNumAttr = item.semestrNum !== '' ? ` data-semestr-num="${item.semestrNum}"` : '';
                html += `<option value="${item.id}"${fakultetAttr}${yonalishAttr}${semestrNumAttr}${selected}>${escapeHtml(item.text)}</option>`;
            });

            select.html(html);
        }

        function syncYonalish(prefix, selectedValue = null) {
            const fakultet = String($('#' + prefix + 'Fakultet').val() || '');
            const current = selectedValue !== null
                ? String(selectedValue || '')
                : String($('#' + prefix + 'Yonalish').val() || '');

            rebuildYonalishOptions(prefix + 'Yonalish', fakultet, current);
            const hasCurrent = $('#' + prefix + 'Yonalish option[value="' + current + '"]').length > 0;
            $('#' + prefix + 'Yonalish').val(hasCurrent ? current : '').trigger('change.select2');
        }

        function syncSemestr(prefix, selectedValue = null) {
            const fakultet = String($('#' + prefix + 'Fakultet').val() || '');
            const yonalish = String($('#' + prefix + 'Yonalish').val() || '');
            const current = selectedValue !== null
                ? (prefix === 'target' ? normalizeArray(selectedValue) : String(selectedValue || ''))
                : (prefix === 'target'
                    ? normalizeArray($('#' + prefix + 'Semestr').val())
                    : String($('#' + prefix + 'Semestr').val() || ''));

            rebuildSemestrOptions(prefix + 'Semestr', fakultet, yonalish, current);
            if (prefix === 'target') {
                const validValues = normalizeArray(current).filter(val => (
                    $('#' + prefix + 'Semestr option[value="' + val + '"]').length > 0
                ));
                $('#' + prefix + 'Semestr').val(validValues).trigger('change.select2');
            } else {
                const hasCurrent = $('#' + prefix + 'Semestr option[value="' + current + '"]').length > 0;
                $('#' + prefix + 'Semestr').val(hasCurrent ? current : '').trigger('change.select2');
            }
        }

        function getFilteredSemestrOptions(prefix) {
            const fakultet = String($('#' + prefix + 'Fakultet').val() || '');
            const yonalish = String($('#' + prefix + 'Yonalish').val() || '');
            return allSemestrOptions.filter(item => {
                if (fakultet !== '' && String(item.fakultetId) !== fakultet) {
                    return false;
                }
                if (yonalish !== '' && String(item.yonalishId) !== yonalish) {
                    return false;
                }
                return true;
            });
        }

        function getSemestrOptionTextById(id) {
            const sid = String(id || '');
            if (sid === '') return '';
            const found = allSemestrOptions.find(item => String(item.id) === sid);
            return found ? String(found.text || '') : '';
        }

        function ensurePairRowsValid() {
            const targetValidSet = new Set(getFilteredSemestrOptions('target').map(item => String(item.id)));
            const sourceValidSet = new Set(getFilteredSemestrOptions('source').map(item => String(item.id)));

            pairRows = pairRows.map(row => {
                const targetId = String(row.targetSemestrId || '');
                const sourceId = String(row.sourceSemestrId || '');
                return {
                    targetSemestrId: targetValidSet.has(targetId) ? targetId : '',
                    sourceSemestrId: sourceValidSet.has(sourceId) ? sourceId : '',
                };
            });
        }

        function buildPairSelectOptions(options, selectedId, placeholder) {
            const selected = String(selectedId || '');
            let html = `<option value="">${escapeHtml(placeholder)}</option>`;
            options.forEach(item => {
                const val = String(item.id || '');
                if (!val) return;
                const selectedAttr = val === selected ? ' selected' : '';
                html += `<option value="${escapeHtml(val)}"${selectedAttr}>${escapeHtml(item.text || '')}</option>`;
            });
            return html;
        }

        function renderPairRows() {
            ensurePairRowsValid();
            const container = $('#pairRowsContainer');
            if (!pairRows.length) {
                pairRows = [{ targetSemestrId: '', sourceSemestrId: '' }];
            }

            const targetOptions = getFilteredSemestrOptions('target');
            const sourceOptions = getFilteredSemestrOptions('source');
            let html = '';

            pairRows.forEach((row, index) => {
                html += `
                    <div class="pair-row" data-row-index="${index}">
                        <div>
                            <div class="pair-row-header"><span class="pair-row-index">${index + 1}</span>Juftlik</div>
                            <label class="pair-row-label">Maqsad semestr</label>
                            <select class="form-control pair-target-semestr" data-row-index="${index}">
                                ${buildPairSelectOptions(targetOptions, row.targetSemestrId, "Maqsad semestrni tanlang")}
                            </select>
                        </div>
                        <div>
                            <div class="pair-row-header" style="visibility:hidden;"><span class="pair-row-index">${index + 1}</span>Juftlik</div>
                            <label class="pair-row-label">Manba semestr</label>
                            <select class="form-control pair-source-semestr" data-row-index="${index}">
                                ${buildPairSelectOptions(sourceOptions, row.sourceSemestrId, "Manba semestrni tanlang")}
                            </select>
                        </div>
                        <div style="padding-bottom:2px;">
                            <button
                                type="button"
                                class="btn btn-danger btn-sm removePairRowBtn"
                                data-row-index="${index}"
                                ${pairRows.length <= 1 ? 'disabled' : ''}
                            >
                                <i class="fas fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                `;
            });

            container.html(html);
        }

        function getPairMapPayload() {
            return pairRows
                .map(row => ({
                    target_semestr_id: parseInt(row.targetSemestrId || 0, 10) || 0,
                    source_semestr_id: parseInt(row.sourceSemestrId || 0, 10) || 0,
                }))
                .filter(pair => pair.target_semestr_id > 0 && pair.source_semestr_id > 0);
        }

        function renderDarsSummary(row, darsTurlari) {
            const dars = row.dars || {};
            const parts = [];

            (darsTurlari || []).forEach(tur => {
                const tid = String(tur.id || '');
                const soat = parseInt(dars[tid] || 0, 10) || 0;
                if (soat > 0) {
                    parts.push(`${tur.name}: ${soat}`);
                }
            });

            if (!parts.length) {
                return '-';
            }
            return `<ul class="compact-list">${parts.map(item => `<li>${escapeHtml(item)}</li>`).join('')}</ul>`;
        }

        function getCreatedRowSearchText(row, darsTurlari) {
            if (!row) return '';

            const fanTypeLabel = fanTypeLabels[parseInt(row.tanlov_fan || 0, 10)] || 'Noma\'lum';
            const dars = row.dars || {};
            const darsText = (darsTurlari || []).map(tur => {
                const tid = String(tur.id || '');
                const soat = parseInt(dars[tid] || 0, 10) || 0;
                return soat > 0 ? `${tur.name || ''} ${soat}` : '';
            }).filter(Boolean).join(' ');

            return [
                row.fan_code || '',
                row.fan_name || '',
                fanTypeLabel,
                row.kafedra_name || '',
                row.yonalish_name || '',
                row.kirish_yili || '',
                row.semestr_num || '',
                darsText
            ].join(' ').toLowerCase();
        }

        function applyCreatedRejaTableFilters(resetPage = false) {
            const searchValue = String($('#createdRejaSearchInput').val() || '').trim().toLowerCase();
            if (resetPage) {
                createdListPage = 1;
            }

            if (!searchValue) {
                createdRowsFiltered = createdRowsAll.slice();
            } else {
                createdRowsFiltered = createdRowsAll.filter(row => (
                    getCreatedRowSearchText(row, createdListDarsTurlari).includes(searchValue)
                ));
            }

            renderCreatedRejaTableCurrentPage();
        }

        function renderCreatedRejaTableCurrentPage() {
            const tbody = $('#createdRejaTableBody');
            const countBadge = $('#createdRejaCount');
            const totalRows = createdRowsFiltered.length;
            countBadge.text(`${totalRows} ta`);

            if (!totalRows) {
                tbody.html('<tr><td colspan="7">Tanlangan maqsad filtr bo\'yicha fan topilmadi</td></tr>');
                $('#createdRejaPageInfo').text('0-0 / 0');
                $('#createdRejaPrevPage').prop('disabled', true);
                $('#createdRejaNextPage').prop('disabled', true);
                return;
            }

            const perPage = Math.max(1, createdListPerPage);
            const totalPages = Math.max(1, Math.ceil(totalRows / perPage));
            if (createdListPage > totalPages) {
                createdListPage = totalPages;
            }
            if (createdListPage < 1) {
                createdListPage = 1;
            }

            const fromIndex = (createdListPage - 1) * perPage;
            const pageRows = createdRowsFiltered.slice(fromIndex, fromIndex + perPage);

            let html = '';
            pageRows.forEach(row => {
                const fanTypeLabel = fanTypeLabels[parseInt(row.tanlov_fan || 0, 10)] || 'Noma\'lum';
                const semestrLabel = `${row.yonalish_name || '-'} - ${row.kirish_yili || '-'} / ${row.semestr_num || '-'}`;
                html += `
                    <tr>
                        <td>${escapeHtml(row.fan_code || '-')}</td>
                        <td>${escapeHtml(row.fan_name || '-')}</td>
                        <td>${escapeHtml(fanTypeLabel)}</td>
                        <td>${escapeHtml(row.kafedra_name || '-')}</td>
                        <td>${renderDarsSummary(row, createdListDarsTurlari)}</td>
                        <td>${escapeHtml(semestrLabel)}</td>
                        <td>
                            <div class="table-actions">
                                <button type="button" class="btn btn-outline btn-sm editCreatedRejaBtn" data-fan-id="${row.fan_id}">
                                    <i class="fas fa-pen"></i> Tahrirlash
                                </button>
                                <button type="button" class="btn btn-danger btn-sm deleteCreatedRejaBtn" data-fan-id="${row.fan_id}">
                                    <i class="fas fa-trash-alt"></i> O'chirish
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            tbody.html(html);
            const toIndex = fromIndex + pageRows.length;
            $('#createdRejaPageInfo').text(`${fromIndex + 1}-${toIndex} / ${totalRows}`);
            $('#createdRejaPrevPage').prop('disabled', createdListPage <= 1);
            $('#createdRejaNextPage').prop('disabled', createdListPage >= totalPages);
        }

        function loadCreatedRejaList() {
            const fakultetId = String($('#targetFakultet').val() || '');
            const yonalishId = String($('#targetYonalish').val() || '');
            const targetSemestrValues = normalizeArray($('#targetSemestr').val());
            const semestrId = targetSemestrValues.length === 1 ? targetSemestrValues[0] : '';
            const url = `api/get_oquv_reja_created_list.php?fakultet_id=${encodeURIComponent(fakultetId)}&yonalish_id=${encodeURIComponent(yonalishId)}&semestr_id=${encodeURIComponent(semestrId)}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (!data || !data.success) {
                        createdRowsById = {};
                        createdRowsAll = [];
                        createdRowsFiltered = [];
                        $('#createdRejaTableBody').html('<tr><td colspan="7">Ro\'yxatni yuklab bo\'lmadi</td></tr>');
                        $('#createdRejaCount').text('0 ta');
                        $('#createdRejaPageInfo').text('0-0 / 0');
                        $('#createdRejaPrevPage').prop('disabled', true);
                        $('#createdRejaNextPage').prop('disabled', true);
                        return;
                    }

                    const rows = Array.isArray(data.rows) ? data.rows : [];
                    const darsTurlari = Array.isArray(data.dars_turlari) && data.dars_turlari.length
                        ? data.dars_turlari
                        : darsTurlariListDefault;

                    createdRowsById = {};
                    rows.forEach(r => {
                        const fid = parseInt(r.fan_id || 0, 10);
                        if (fid > 0) {
                            createdRowsById[String(fid)] = r;
                        }
                    });

                    createdRowsAll = rows;
                    createdListDarsTurlari = darsTurlari;
                    applyCreatedRejaTableFilters(true);
                })
                .catch(() => {
                    createdRowsById = {};
                    createdRowsAll = [];
                    createdRowsFiltered = [];
                    $('#createdRejaTableBody').html('<tr><td colspan="7">Server bilan bog\'lanib bo\'lmadi</td></tr>');
                    $('#createdRejaCount').text('0 ta');
                    $('#createdRejaPageInfo').text('0-0 / 0');
                    $('#createdRejaPrevPage').prop('disabled', true);
                    $('#createdRejaNextPage').prop('disabled', true);
                });
        }

        function buildKafedraOptions(selectedId, lockKafedra) {
            const selected = String(selectedId || '');
            let html = '<option value="">Tanlang</option>';
            (kafedralarList || []).forEach(k => {
                const id = String(k.id || '');
                const selectedAttr = selected === id ? ' selected' : '';
                html += `<option value="${id}"${selectedAttr}>${escapeHtml(k.name || '')}</option>`;
            });
            return `<select class="swal2-input" id="editKafedraId" ${lockKafedra ? 'disabled' : ''}>${html}</select>`;
        }

        function buildEditModalHtml(row, darsTurlari) {
            const dars = row.dars || {};
            let darsRows = '';
            (darsTurlari || darsTurlariListDefault).forEach(tur => {
                const tid = String(tur.id || '');
                const soat = parseInt(dars[tid] || 0, 10) || 0;
                darsRows += `
                    <div style="display:flex;gap:8px;align-items:center;margin:6px 0;">
                        <label style="flex:1;text-align:left;">${escapeHtml(tur.name || '')}</label>
                        <input type="number" min="0" step="1" class="swal2-input edit-dars-input" data-dars-tur-id="${tid}" value="${soat}" style="width:120px;margin:0;">
                    </div>
                `;
            });

            const lockKafedra = parseInt(row.kafedra_lock || 0, 10) === 1;
            return `
                <input type="text" id="editFanCode" class="swal2-input" placeholder="Fan kodi" value="${escapeHtml(row.fan_code || '')}">
                <input type="text" id="editFanName" class="swal2-input" placeholder="Fan nomi" value="${escapeHtml(row.fan_name || '')}">
                <div style="text-align:left;margin:8px 0 4px 0;font-size:13px;color:#64748b;">Kafedra</div>
                ${buildKafedraOptions(row.kafedra_id || '', lockKafedra)}
                <div style="text-align:left;margin:10px 0 4px 0;font-size:13px;color:#64748b;">Dars soatlari</div>
                <div style="max-height:220px;overflow:auto;padding-right:4px;">${darsRows}</div>
                <textarea id="editIzoh" class="swal2-textarea" placeholder="Izoh">${escapeHtml(row.izoh || '')}</textarea>
                ${lockKafedra ? '<div style="text-align:left;font-size:12px;color:#64748b;">Tanlov/Chet tili bazaviy fanida kafedra o\'zgartirilmaydi.</div>' : ''}
            `;
        }

        function initSelect2() {
            if (!window.jQuery || !$.fn || typeof $.fn.select2 !== 'function') {
                return;
            }

            [
                '#targetFakultet',
                '#targetYonalish',
                '#targetSemestr',
                '#sourceFakultet',
                '#sourceYonalish'
            ].forEach(selector => {
                const isTargetSemestr = selector === '#targetSemestr';
                $(selector).select2({
                    placeholder: "Tanlang",
                    allowClear: true,
                    closeOnSelect: !isTargetSemestr,
                    width: '100%',
                });
            });
        }

        $(document).ready(function() {
            cacheOptions();
            initSelect2();
            createdListPerPage = parseInt($('#createdRejaPerPage').val() || 20, 10) || 20;

            $('#createdRejaSearchInput').on('input', function() {
                applyCreatedRejaTableFilters(true);
            });

            $('#createdRejaPerPage').on('change', function() {
                createdListPerPage = parseInt($(this).val() || 20, 10) || 20;
                applyCreatedRejaTableFilters(true);
            });

            $('#createdRejaPrevPage').on('click', function() {
                if (createdListPage > 1) {
                    createdListPage -= 1;
                    renderCreatedRejaTableCurrentPage();
                }
            });

            $('#createdRejaNextPage').on('click', function() {
                const totalRows = createdRowsFiltered.length;
                const totalPages = Math.max(1, Math.ceil(totalRows / Math.max(1, createdListPerPage)));
                if (createdListPage < totalPages) {
                    createdListPage += 1;
                    renderCreatedRejaTableCurrentPage();
                }
            });

            $('#targetFakultet').on('change', function() {
                syncYonalish('target');
                syncSemestr('target');
                renderPairRows();
                loadCreatedRejaList();
            });
            $('#targetYonalish').on('change', function() {
                syncSemestr('target');
                renderPairRows();
                loadCreatedRejaList();
            });
            $('#targetSemestr').on('change', function() {
                loadCreatedRejaList();
            });

            $('#sourceFakultet').on('change', function() {
                syncYonalish('source');
                renderPairRows();
            });
            $('#sourceYonalish').on('change', function() {
                renderPairRows();
            });
            $('#addPairRowBtn').on('click', function() {
                pairRows.push({ targetSemestrId: '', sourceSemestrId: '' });
                renderPairRows();
            });
            $(document).on('click', '.removePairRowBtn', function() {
                const index = parseInt($(this).data('row-index') || -1, 10);
                if (index < 0 || index >= pairRows.length) {
                    return;
                }
                pairRows.splice(index, 1);
                if (!pairRows.length) {
                    pairRows = [{ targetSemestrId: '', sourceSemestrId: '' }];
                }
                renderPairRows();
            });
            $(document).on('change', '.pair-target-semestr', function() {
                const index = parseInt($(this).data('row-index') || -1, 10);
                if (index < 0 || index >= pairRows.length) {
                    return;
                }
                pairRows[index].targetSemestrId = String($(this).val() || '');
            });
            $(document).on('change', '.pair-source-semestr', function() {
                const index = parseInt($(this).data('row-index') || -1, 10);
                if (index < 0 || index >= pairRows.length) {
                    return;
                }
                pairRows[index].sourceSemestrId = String($(this).val() || '');
            });
            $('#refreshCreatedRejaBtn').on('click', function() {
                loadCreatedRejaList();
            });

            $('#copyResetBtn').on('click', function() {
                $('#targetFakultet, #targetYonalish, #targetSemestr, #sourceFakultet, #sourceYonalish').val('').trigger('change.select2');
                syncYonalish('target', '');
                syncSemestr('target', '');
                syncYonalish('source', '');
                pairRows = [{ targetSemestrId: '', sourceSemestrId: '' }];
                renderPairRows();
                loadCreatedRejaList();
            });

            $('#copyRunBtn').on('click', function() {
                const sourceYonalishId = String($('#sourceYonalish').val() || '');
                if (sourceYonalishId === '') {
                    Toast.fire({ icon: 'error', title: "Manba yo'nalishni tanlang" });
                    return;
                }

                const pairMap = getPairMapPayload();
                if (!pairMap.length) {
                    Toast.fire({ icon: 'error', title: "Kamida bitta maqsad-manba semestr juftligini tanlang" });
                    return;
                }

                const duplicatedTargets = new Set();
                const seenTargets = new Set();
                pairMap.forEach(pair => {
                    const targetId = String(pair.target_semestr_id || '');
                    if (!targetId) return;
                    if (seenTargets.has(targetId)) {
                        duplicatedTargets.add(targetId);
                    }
                    seenTargets.add(targetId);
                });
                if (duplicatedTargets.size > 0) {
                    Toast.fire({ icon: 'error', title: "Bitta maqsad semestr faqat bitta juftlikda bo'lishi kerak" });
                    return;
                }

                const sourceYonalishText = String($('#sourceYonalish option:selected').text() || '').trim();
                const targetTextHtml = pairMap.map((pair, idx) => {
                    const targetText = getSemestrOptionTextById(pair.target_semestr_id);
                    const sourceText = getSemestrOptionTextById(pair.source_semestr_id);
                    return `<li><b>${idx + 1}-juftlik:</b> ${escapeHtml(targetText || String(pair.target_semestr_id))} &larr; ${escapeHtml(sourceText || String(pair.source_semestr_id))}</li>`;
                }).join('');

                SwalApi.fire({
                    title: "Fanlarni nusxalash",
                    icon: "question",
                    html: `
                        <div style="text-align:left;">
                            <div><b>Manba yo'nalish:</b> ${escapeHtml(sourceYonalishText)}</div>
                            <div style="margin-top:6px;"><b>Semestr juftliklari:</b></div>
                            <ul style="margin:6px 0 0 16px;">${targetTextHtml || '<li>-</li>'}</ul>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: "Ha, nusxalash",
                    cancelButtonText: "Bekor qilish"
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const formData = new FormData();
                    formData.append('pair_map_json', JSON.stringify(pairMap));
                    formData.append('source_yonalish_id', sourceYonalishId);

                    fetch('insert/copy_oquv_reja_items.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.success) {
                            Toast.fire({
                                icon: 'success',
                                title: data.message || "Fanlar nusxalandi"
                            });
                            loadCreatedRejaList();
                        } else {
                            Toast.fire({
                                icon: 'error',
                                title: (data && data.message) || "Nusxalashda xatolik yuz berdi"
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

            syncYonalish('target');
            syncSemestr('target');
            syncYonalish('source');
            pairRows = [{ targetSemestrId: '', sourceSemestrId: '' }];
            renderPairRows();
            loadCreatedRejaList();
        });

        $(document).on('click', '.editCreatedRejaBtn', function() {
            const fanId = String($(this).data('fan-id') || '');
            const row = createdRowsById[fanId];
            if (!row) {
                return;
            }

            const darsTurlari = darsTurlariListDefault;
            SwalApi.fire({
                title: "O'quv reja tahrirlash",
                width: 860,
                html: buildEditModalHtml(row, darsTurlari),
                showCancelButton: true,
                confirmButtonText: "Saqlash",
                cancelButtonText: "Bekor qilish",
                focusConfirm: false,
                preConfirm: () => {
                    const fanCode = String($('#editFanCode').val() || '').trim();
                    const fanName = String($('#editFanName').val() || '').trim();
                    const kafedraVal = String($('#editKafedraId').val() || '').trim();
                    const izoh = String($('#editIzoh').val() || '').trim();

                    if (fanCode === '' || fanName === '') {
                        SwalApi.showValidationMessage("Fan kodi va fan nomi to'ldirilishi shart");
                        return false;
                    }

                    const lockKafedra = parseInt(row.kafedra_lock || 0, 10) === 1;
                    const kafedraId = lockKafedra ? parseInt(row.kafedra_id || 0, 10) : parseInt(kafedraVal || 0, 10);
                    if (!lockKafedra && kafedraId <= 0) {
                        SwalApi.showValidationMessage("Kafedrani tanlang");
                        return false;
                    }

                    const dars = {};
                    let hasPositive = false;
                    $('.edit-dars-input').each(function() {
                        const darsTurId = String($(this).data('dars-tur-id') || '');
                        let value = parseInt($(this).val() || 0, 10);
                        if (Number.isNaN(value) || value < 0) {
                            value = 0;
                        }
                        dars[darsTurId] = value;
                        if (value > 0) {
                            hasPositive = true;
                        }
                    });

                    if (!hasPositive) {
                        SwalApi.showValidationMessage("Kamida bitta dars soati 0 dan katta bo'lishi kerak");
                        return false;
                    }

                    return {
                        fan_id: parseInt(row.fan_id || 0, 10),
                        fan_code: fanCode,
                        fan_name: fanName,
                        kafedra_id: kafedraId,
                        izoh: izoh,
                        dars: dars,
                    };
                }
            }).then((result) => {
                if (!result.isConfirmed || !result.value) {
                    return;
                }

                const payload = result.value;
                const formData = new FormData();
                formData.append('fan_id', String(payload.fan_id));
                formData.append('fan_code', payload.fan_code);
                formData.append('fan_name', payload.fan_name);
                formData.append('kafedra_id', String(payload.kafedra_id));
                formData.append('izoh', payload.izoh);
                formData.append('dars_json', JSON.stringify(payload.dars));

                fetch('insert/update_oquv_reja_item.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        Toast.fire({ icon: 'success', title: data.message || "Yangilandi" });
                        loadCreatedRejaList();
                    } else {
                        Toast.fire({ icon: 'error', title: (data && data.message) || "Yangilashda xatolik" });
                    }
                })
                .catch(() => {
                    Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                });
            });
        });

        $(document).on('click', '.deleteCreatedRejaBtn', function() {
            const fanId = String($(this).data('fan-id') || '');
            const row = createdRowsById[fanId];
            if (!row) {
                return;
            }

            SwalApi.fire({
                title: "Fanni o'chirishni tasdiqlaysizmi?",
                text: `${row.fan_code || ''} - ${row.fan_name || ''}`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ha, o'chirilsin",
                cancelButtonText: "Bekor qilish"
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                const formData = new FormData();
                formData.append('fan_id', fanId);

                fetch('insert/delete_oquv_reja_item.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        Toast.fire({ icon: 'success', title: data.message || "Fan o'chirildi" });
                        loadCreatedRejaList();
                    } else {
                        Toast.fire({ icon: 'error', title: (data && data.message) || "O'chirishda xatolik" });
                    }
                })
                .catch(() => {
                    Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                });
            });
        });
    </script>
</body>
</html>
