<?php

include_once 'config.php';
$db = new Database();
$semestrlar = $db->get_semestrlar();
$fakultetlar = $db->get_data_by_table_all('fakultetlar', 'ORDER BY name');
$yonalishlar = $db->get_data_by_table_all('yonalishlar');
$qoshimcha_dars_turlar = $db->get_data_by_table_all('qoshimcha_dars_turlar');
$kafedralar = $db->get_data_by_table_all('kafedralar');

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
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}
$qoshimchaDarsTurlarJson = json_encode($qoshimcha_dars_turlar ?? [], $jsonFlags);
if ($qoshimchaDarsTurlarJson === false) {
    $qoshimchaDarsTurlarJson = '[]';
}
$kafedralarJson = json_encode($kafedralar ?? [], $jsonFlags);
if ($kafedralarJson === false) {
    $kafedralarJson = '[]';
}
?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <title>Qo‘shimcha o‘quv reja yaratish</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
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

        .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .swal2-popup .edit-q-modal {
            text-align: left;
        }

        .swal2-popup .edit-q-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .swal2-popup .edit-q-field-full {
            grid-column: 1 / -1;
        }

        .swal2-popup .edit-q-field label {
            display: block;
            font-size: 12px;
            color: #64748b;
            margin: 0 0 4px 0;
        }

        .swal2-popup .edit-q-input {
            margin: 0;
            width: 100%;
        }

        .swal2-popup .edit-q-alloc-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 12px;
            background: #f8fafc;
        }

        .swal2-popup .edit-q-alloc-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 8px;
            font-size: 13px;
            color: #334155;
        }

        .swal2-popup .edit-q-total-hint {
            font-size: 12px;
            color: #64748b;
        }

        .swal2-popup .edit-q-total-hint.ok {
            color: #059669;
            font-weight: 600;
        }

        .swal2-popup .edit-q-total-hint.warn {
            color: #b45309;
            font-weight: 600;
        }

        .swal2-popup .edit-q-allocation-row {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 8px;
            margin-bottom: 8px;
            background: #ffffff;
        }

        .swal2-popup .edit-q-row-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 6px;
        }

        .swal2-popup .edit-q-allocation-grid {
            display: grid;
            grid-template-columns: 1fr 140px 44px;
            gap: 8px;
            align-items: end;
        }

        .swal2-popup .edit-q-remove-row[disabled] {
            opacity: 0.45;
            cursor: not-allowed;
        }

        @media (max-width: 900px) {
            .swal2-popup .edit-q-grid {
                grid-template-columns: 1fr;
            }

            .swal2-popup .edit-q-allocation-grid {
                grid-template-columns: 1fr;
            }
        }

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
    </style>
</head>

<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-navbar">
                <h1>Qo‘shimcha o‘quv reja yaratish</h1>
            </header>
            <div class="content-container">
                <form id="oquvRejaForm" class="card">
                    <h3 class="section-title">Umumiy ma’lumot</h3>
                    <div class="top-filters-grid">
                        <div class="form-group">
                            <label>Fakultet filtri</label>
                            <select class="form-control" id="fakultetFilter">
                                <option value="">Barcha fakultetlar</option>
                                <?php foreach ($fakultetlar as $f): ?>
                                    <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars((string)$f['name'], ENT_QUOTES, 'UTF-8') ?></option>
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
                                        data-fakultet-id="<?= (int)$y['fakultet_id'] ?>">
                                        <?= htmlspecialchars((string)$y['name'] . (!empty($y['kirish_yili']) ? ' - ' . (string)$y['kirish_yili'] : ''), ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semestr</label>
                            <select class="form-control" name="semestr_id" id="semestrSelect" required>
                                <option value="">Tanlang</option>
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
                                    <option value="<?= $s['id'] ?>"
                                        data-fakultet-id="<?= $fakultetId ?>"
                                        data-yonalish-id="<?= $yonalishId ?>"
                                        data-talaba="<?= $s['jami_talabalar'] ?>"
                                        data-talim-shakli="<?= htmlspecialchars($s['talim_shakli_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-talim-shakli-id="<?= (int)($s['talim_shakli_id'] ?? 0) ?>"
                                        data-daraja="<?= htmlspecialchars($s['akademik_daraja_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-patok="<?= (int)($s['patok_soni'] ?? 0) ?>"
                                        data-guruh="<?= (int)($s['guruhlar_soni'] ?? 0) ?>">
                                        <?= htmlspecialchars($darajaPrefix . $short . '_' . ($s['kirish_yili'] ?? '') . ' - ' . ($s['semestr'] ?? '') . '-semestr(' . ($s['jami_talabalar'] ?? 0) . ')', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="top-filter-actions">
                        <button type="button" class="btn btn-primary btn-sm" id="applyFiltersBtn">
                            <i class="fas fa-filter"></i> Filtrlash
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" id="resetFiltersBtn">
                            <i class="fas fa-rotate-left"></i> Tozalash
                        </button>
                    </div>
                    <div id="rejaWrapper">
                        <div class="reja-card" data-index="0">
                            <div class="form-grid-3">
                                <div class="form-group">
                                    <label>Fan (kod + nomi)</label>
                                    <select class="form-control fan-select" name="fan_nomi[]" required>
                                        <option value="">Tanlang</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Qo'shimcha dars turi</label>
                                    <select class="form-control" name="qoshimcha_dars_id[]" required>
                                        <option value="">Tanlang</option>
                                        <?php foreach ($qoshimcha_dars_turlar as $qdt): ?>
                                            <option value="<?= $qdt['id'] ?>"
                                                data-koef="<?= $qdt['koifesent'] ?>">
                                                <?= htmlspecialchars($qdt['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Hisoblangan fan soati</label>
                                    <input type="number" class="form-control fan-soat-input" name="fan_soat[]" required>
                                    <div class="calc-hint" style="display:none;font-size:12px;color:#6c757d;margin-top:4px;"></div>
                                </div>
                            </div>
                            <div class="extra-fields">
                                <div class="form-grid-3 extra-field extra-hafta" style="display:none;">
                                    <div class="form-group">
                                        <label>Hafta soni</label>
                                        <input type="number" class="form-control calc-input hafta-input" min="0" step="1" placeholder="Masalan: 4">
                                    </div>
                                </div>
                                <div class="form-grid-3 extra-field extra-bmi" style="display:none;">
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" class="calc-input bmi-tech"> Texnik yo'nalish (30 soat)
                                        </label>
                                    </div>
                                </div>
                                <div class="form-grid-3 extra-field extra-yakuniy" style="display:none;">
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" class="calc-input yakuniy-test"> Yakuniy test shaklida
                                        </label>
                                    </div>
                                </div>
                                <div class="form-grid-3 extra-field extra-yadak" style="display:none;">
                                    <div class="form-group">
                                        <label>YADAK turi</label>
                                        <select class="form-control calc-input yadak-type">
                                            <option value="basic">Umumiy (konsultatsiya/yozma)</option>
                                            <option value="mag1">1-kurs magistr himoya (0.4)</option>
                                            <option value="mag2">Magistr dissertatsiya (0.8)</option>
                                        </select>
                                    </div>
                                    <div class="form-group yadak-teacher-wrap">
                                        <label>YADAK o'qituvchi soni</label>
                                        <input type="number" class="form-control calc-input yadak-teacher" min="0" step="1" value="1">
                                    </div>
                                    <div class="form-group yadak-fan-wrap">
                                        <label>YADAK fan soni</label>
                                        <input type="number" class="form-control calc-input yadak-fan-count" min="1" step="1" value="5">
                                    </div>
                                </div>
                                <div class="form-grid-3 extra-field extra-ochiq" style="display:none;">
                                    <div class="form-group">
                                        <label>Ochiq dars soni</label>
                                        <input type="number" class="form-control calc-input ochiq-count" min="1" step="1" value="1">
                                    </div>
                                </div>
                            </div>
                            <div class="darsSoatWrapper">
                                <div class="form-grid-2 dars-soat-row">
                                    <div class="form-group">
                                        <label>Kafedra</label>
                                        <select class="form-control" name="kafedra_id[0][]" required>
                                            <option value="">Tanlang</option>
                                            <?php foreach ($kafedralar as $k): ?>
                                                <option value="<?= $k['id'] ?>">
                                                    <?= htmlspecialchars($k['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label>Kafedra bo'yicha dars soati</label>
                                        <input type="number"
                                            class="form-control"
                                            name="dars_soati[0][]"
                                            min="0"
                                            required>
                                    </div>
                                </div>
                                <div class="dars-soat-actions">
                                    <button type="button" class="btn btn-outline btn-sm addDarsSoat">
                                        <i class="fas fa-plus"></i>
                                    </button>

                                    <button type="button" class="btn btn-danger btn-sm removeDarsSoat">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="reja-actions">
                                <button type="button" class="btn btn-outline btn-sm addReja">
                                    <i class="fas fa-plus"></i> Yana fan
                                </button>

                                <button type="button" class="btn btn-danger btn-sm removeReja">
                                    <i class="fas fa-times"></i> O‘chirish
                                </button>
                            </div>
                        </div>
                        <!-- /reja-card -->

                    </div>
                    <div class="form-group mt-3">
                        <label>Izoh</label>
                        <textarea class="form-control"
                            name="izoh"
                            rows="3"
                            placeholder="O‘quv reja bo‘yicha umumiy izoh..."></textarea>
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
                            <h3>Yaratilgan qo'shimcha fanlar ro'yxati</h3>
                            <span class="badge" id="createdQoshimchaCount">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <button type="button" class="btn btn-outline btn-sm" id="refreshCreatedQoshimchaBtn">
                                <i class="fas fa-rotate"></i> Yangilash
                            </button>
                        </div>
                    </div>
                    <div class="created-list-note">
                        Ro'yxat yuqoridagi fakultet, yo'nalish va semestr filtrlariga ko'ra ko'rsatiladi. "Tahrirlash" orqali fan ma'lumotlarini yangilang yoki "O'chirish" bilan fanni olib tashlang.
                    </div>
                    <div class="created-list-controls">
                        <div class="created-list-controls-left">
                            <input type="text" class="form-control created-list-search" id="createdQoshimchaSearchInput" placeholder="Jadvaldan qidirish...">
                            <select class="form-control created-list-per-page" id="createdQoshimchaPerPage">
                                <option value="10">10 ta</option>
                                <option value="20" selected>20 ta</option>
                                <option value="50">50 ta</option>
                                <option value="100">100 ta</option>
                            </select>
                        </div>
                        <div class="created-list-controls-right">
                            <span class="created-list-page-info" id="createdQoshimchaPageInfo">0-0 / 0</span>
                            <button type="button" class="btn btn-outline btn-sm" id="createdQoshimchaPrevPage" disabled>Oldingi</button>
                            <button type="button" class="btn btn-outline btn-sm" id="createdQoshimchaNextPage" disabled>Keyingi</button>
                        </div>
                    </div>
                    <div class="table-responsive mt-2">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Fan nomi</th>
                                    <th>Qo'shimcha dars turi</th>
                                    <th>Hisoblangan fan soati</th>
                                    <th>Kafedralar</th>
                                    <th>Dars soatlari</th>
                                    <th>Semestr</th>
                                    <th>Harakat</th>
                                </tr>
                            </thead>
                            <tbody id="createdQoshimchaTableBody">
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
        $(document).ready(function() {
            $(document).on('change', 'select[name="qoshimcha_dars_id[]"], select[name="fan_nomi[]"]', function() {
                const card = $(this).closest('.reja-card');
                calculateForSingleCard(card);
            });

            $(document).on('input change', '.calc-input', function() {
                const card = $(this).closest('.reja-card');
                calculateForSingleCard(card);
            });
        });

        $(document).on('input', 'input[name^="dars_soati"]', function() {
            $(this).data('manual', true);
        });

        const QOSHIMCHA_IDS = {
            KURS_ISHI: 1,
            KURS_LOYIHA: 2,
            OQUV_PED: 3,
            UZLUKSIZ_MALAKA: 4,
            DALA_OTM: 5,
            DALA_TASH: 6,
            ISHLAB_CHIQARISH: 7,
            BMI: 8,
            OCHIQ_DARS: 15,
            YADAK: 16,
            ORALIQ: 20,
            YAKUNIY: 21
        };

        const SwalApi = window.Swal || {
            mixin: () => ({
                fire: () => {}
            }),
            fire: () => Promise.resolve({
                isConfirmed: false
            }),
            showValidationMessage: () => {},
        };

        const Toast = SwalApi.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });

        let fanIndex = 0;
        const qoshimchaDarsTurlari = <?php echo $qoshimchaDarsTurlarJson; ?>;
        const kafedralarList = <?php echo $kafedralarJson; ?>;
        const fanOptionsBySemestr = {};
        const fanMap = {};

        let allSemestrOptions = [];
        let allSemestrOptionsMaster = [];
        let allYonalishOptions = [];
        let isTopFilterSyncing = false;
        let createdQoshimchaRowsById = {};
        let createdQoshimchaRowsAll = [];
        let createdQoshimchaRowsFiltered = [];
        let createdQoshimchaPage = 1;
        let createdQoshimchaPerPage = 20;

        function initSelect2Safe($el, placeholderText) {
            if (!$el || !$el.length) return;
            if (window.jQuery && $.fn && typeof $.fn.select2 === 'function') {
                $el.select2({
                    placeholder: placeholderText,
                    allowClear: true,
                    width: '100%',
                });
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

        function buildQoshimchaDarsOptionsHtml(selectedValue = '') {
            const selected = String(selectedValue || '');
            let html = '';
            (qoshimchaDarsTurlari || []).forEach(item => {
                const id = String(item.id || '');
                if (id === '') return;
                const name = String(item.name || '');
                const koef = String(item.koifesent || '');
                const selectedAttr = id === selected ? ' selected' : '';
                html += `<option value="${id}" data-koef="${escapeOptionText(koef)}"${selectedAttr}>${escapeOptionText(name)}</option>`;
            });
            return html;
        }

        function buildKafedralarOptionsHtml(selectedValue = '') {
            const selected = String(selectedValue || '');
            let html = '';
            (kafedralarList || []).forEach(item => {
                const id = String(item.id || '');
                if (id === '') return;
                const name = String(item.name || '');
                const selectedAttr = id === selected ? ' selected' : '';
                html += `<option value="${id}"${selectedAttr}>${escapeOptionText(name)}</option>`;
            });
            return html;
        }

        function escapeHtml(value) {
            return escapeOptionText(value);
        }

        async function ensureFanOptionsLoaded(semestrId) {
            const targetSemestrId = String(semestrId || '').trim();
            if (!targetSemestrId) {
                return [];
            }

            if (Array.isArray(fanOptionsBySemestr[targetSemestrId])) {
                return fanOptionsBySemestr[targetSemestrId];
            }

            const response = await fetch(`api/get_qoshimcha_fan_options.php?semestr_id=${encodeURIComponent(targetSemestrId)}`, {
                cache: 'no-store'
            });
            const data = await response.json();
            const rows = (data && data.success && Array.isArray(data.rows)) ? data.rows : [];

            fanOptionsBySemestr[targetSemestrId] = rows.map(item => ({
                semestr_id: String(item.semestr_id || targetSemestrId),
                value: String(item.value || ''),
                label: String(item.label || ''),
                auditoriya_soat: Number(item.auditoriya_soat || 0)
            })).filter(item => item.value !== '' && item.label !== '');

            fanOptionsBySemestr[targetSemestrId].forEach(item => {
                fanMap[String(item.value)] = item;
            });

            return fanOptionsBySemestr[targetSemestrId];
        }

        function getCreatedQoshimchaSearchText(row) {
            if (!row) return '';
            const allocations = Array.isArray(row.allocations) ? row.allocations : [];
            const allocText = allocations.map(a => `${a.kafedra_name || ''} ${a.dars_soati || 0}`).join(' ');

            return [
                row.fan_name || '',
                row.qoshimcha_dars_name || '',
                row.fan_soat || '',
                row.yonalish_name || '',
                row.kirish_yili || '',
                row.semestr_num || '',
                row.izoh || '',
                allocText
            ].join(' ').toLowerCase();
        }

        function renderCreatedQoshimchaTableCurrentPage() {
            const tbody = $('#createdQoshimchaTableBody');
            const countBadge = $('#createdQoshimchaCount');
            const totalRows = createdQoshimchaRowsFiltered.length;
            countBadge.text(`${totalRows} ta`);

            if (!totalRows) {
                tbody.html('<tr><td colspan="7">Tanlangan filter bo\'yicha fan topilmadi</td></tr>');
                $('#createdQoshimchaPageInfo').text('0-0 / 0');
                $('#createdQoshimchaPrevPage').prop('disabled', true);
                $('#createdQoshimchaNextPage').prop('disabled', true);
                return;
            }

            const perPage = Math.max(1, createdQoshimchaPerPage);
            const totalPages = Math.max(1, Math.ceil(totalRows / perPage));
            if (createdQoshimchaPage > totalPages) {
                createdQoshimchaPage = totalPages;
            }
            if (createdQoshimchaPage < 1) {
                createdQoshimchaPage = 1;
            }

            const fromIndex = (createdQoshimchaPage - 1) * perPage;
            const pageRows = createdQoshimchaRowsFiltered.slice(fromIndex, fromIndex + perPage);

            let html = '';
            pageRows.forEach(row => {
                const allocations = Array.isArray(row.allocations) ? row.allocations : [];
                const kafedraNames = allocations.map(a => escapeHtml(a.kafedra_name || '-')).join(', ') || '-';
                const darsList = allocations.length ?
                    `<ul class="compact-list">${allocations.map(a => `<li>${escapeHtml(a.kafedra_name || '-')} : ${escapeHtml(a.dars_soati || 0)}</li>`).join('')}</ul>` :
                    '-';
                const semestrLabel = `${row.yonalish_name || '-'} - ${row.kirish_yili || '-'} / ${row.semestr_num || '-'}`;

                html += `
                    <tr>
                        <td>${escapeHtml(row.fan_name || '-')}</td>
                        <td>${escapeHtml(row.qoshimcha_dars_name || '-')}</td>
                        <td>${escapeHtml(row.fan_soat || 0)}</td>
                        <td>${kafedraNames}</td>
                        <td>${darsList}</td>
                        <td>${escapeHtml(semestrLabel)}</td>
                        <td>
                            <div class="table-actions">
                                <button type="button" class="btn btn-outline btn-sm editCreatedQoshimchaBtn" data-qoshimcha-fanid="${row.qoshimcha_fanid}">
                                    <i class="fas fa-pen"></i> Tahrirlash
                                </button>
                                <button type="button" class="btn btn-danger btn-sm deleteCreatedQoshimchaBtn" data-qoshimcha-fanid="${row.qoshimcha_fanid}">
                                    <i class="fas fa-trash-alt"></i> O'chirish
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            tbody.html(html);
            const toIndex = fromIndex + pageRows.length;
            $('#createdQoshimchaPageInfo').text(`${fromIndex + 1}-${toIndex} / ${totalRows}`);
            $('#createdQoshimchaPrevPage').prop('disabled', createdQoshimchaPage <= 1);
            $('#createdQoshimchaNextPage').prop('disabled', createdQoshimchaPage >= totalPages);
        }

        function applyCreatedQoshimchaTableFilters(resetPage = false) {
            const searchValue = String($('#createdQoshimchaSearchInput').val() || '').trim().toLowerCase();
            if (resetPage) {
                createdQoshimchaPage = 1;
            }

            if (!searchValue) {
                createdQoshimchaRowsFiltered = createdQoshimchaRowsAll.slice();
            } else {
                createdQoshimchaRowsFiltered = createdQoshimchaRowsAll.filter(row => (
                    getCreatedQoshimchaSearchText(row).includes(searchValue)
                ));
            }

            renderCreatedQoshimchaTableCurrentPage();
        }

        function buildEditQoshimchaTurOptions(selectedValue = '') {
            const selected = String(selectedValue || '');
            let html = '<option value="">Tanlang</option>';
            (qoshimchaDarsTurlari || []).forEach(item => {
                const id = String(item.id || '');
                if (!id) return;
                const selectedAttr = id === selected ? ' selected' : '';
                html += `<option value="${id}"${selectedAttr}>${escapeHtml(item.name || '')}</option>`;
            });
            return html;
        }

        function buildEditSemestrOptions(selectedValue = '', row = null) {
            const selected = String(selectedValue || '');
            const rowYonalishId = String((row && row.yonalish_id) || '');
            const semestrSource = allSemestrOptionsMaster.length ?
                allSemestrOptionsMaster :
                allSemestrOptions;

            let options = semestrSource.filter(item => {
                if (rowYonalishId === '') return true;
                return String(item.yonalishId || '') === rowYonalishId;
            });

            if (!options.length) {
                options = semestrSource.slice();
            }

            if (selected !== '' && !options.some(item => String(item.id || '') === selected)) {
                options.unshift({
                    id: selected,
                    text: `${row?.yonalish_name || '-'} - ${row?.kirish_yili || '-'} / ${row?.semestr_num || '-'}`,
                });
            }

            let html = '<option value="">Tanlang</option>';
            options.forEach(item => {
                const id = String(item.id || '');
                if (!id) return;
                const selectedAttr = id === selected ? ' selected' : '';
                html += `<option value="${id}"${selectedAttr}>${escapeHtml(item.text || '')}</option>`;
            });
            return html;
        }

        function buildEditQoshimchaKafedraRow(allocation = {}, rowIndex = 1) {
            const selectedKafedra = String(allocation.kafedra_id || '');
            const selectedKafedraName = String(allocation.kafedra_name || '').trim();
            const soat = parseInt(allocation.dars_soati || 0, 10) || 0;

            let optionsHtml = '<option value="">Tanlang</option>';

            (kafedralarList || []).forEach(item => {
                const id = String(item.id || '');
                if (!id) return;

                const name = String(item.name || '');
                const selectedAttr = id === selectedKafedra ? ' selected' : '';
                optionsHtml += `<option value="${escapeHtml(id)}"${selectedAttr}>${escapeHtml(name)}</option>`;
            });

            if (
                selectedKafedra !== '' &&
                selectedKafedraName !== '' &&
                !(kafedralarList || []).some(item => String(item.id || '') === selectedKafedra)
            ) {
                optionsHtml += `<option value="${escapeHtml(selectedKafedra)}" selected>${escapeHtml(selectedKafedraName)}</option>`;
            }

            return `
        <div class="edit-q-allocation-row">
            <div class="edit-q-row-label">Kafedra #${rowIndex}</div>
            <div class="edit-q-allocation-grid">
                <div class="edit-q-field">
                    <label>Kafedra</label>
                    <select class="swal2-input edit-q-kafedra edit-q-input">
                        ${optionsHtml}
                    </select>
                </div>
                <div class="edit-q-field">
                    <label>Soat</label>
                    <input
                        type="number"
                        min="0"
                        step="1"
                        class="swal2-input edit-q-soat edit-q-input"
                        value="${soat}"
                    >
                </div>
                <div class="edit-q-field">
                    <label>&nbsp;</label>
                    <button type="button" class="btn btn-danger btn-sm edit-q-remove-row">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
        }

        function openEditCreatedQoshimchaModal(row) {
            SwalApi.fire({
                title: "Qo'shimcha fan tahrirlash",
                width: 920,
                html: `
            <div class="edit-q-modal">
                <div class="edit-q-grid">
                    <div class="edit-q-field edit-q-field-full">
                        <label>Fan nomi</label>
                        <input
                            type="text"
                            id="editQFanName"
                            class="swal2-input edit-q-input"
                            placeholder="Fan nomi"
                            value="${escapeHtml(row.fan_name || '')}"
                        >
                    </div>

                    <div class="edit-q-field">
                        <label>Hisoblangan fan soati</label>
                        <input
                            type="number"
                            min="0"
                            step="1"
                            id="editQFanSoat"
                            class="swal2-input edit-q-input"
                            placeholder="Hisoblangan fan soati"
                            value="${escapeHtml(row.fan_soat || 0)}"
                        >
                    </div>

                    <div class="edit-q-field">
                        <label>Qo'shimcha dars turi</label>
                        <select id="editQQoshimchaDarsId" class="swal2-input edit-q-input">
                            ${buildEditQoshimchaTurOptions(row.qoshimcha_dars_id || '')}
                        </select>
                    </div>

                    <div class="edit-q-field edit-q-field-full">
                        <label>Semestr</label>
                        <select id="editQSemestrId" class="swal2-input edit-q-input">
                            ${buildEditSemestrOptions(row.semestr_id || '', row)}
                        </select>
                    </div>
                </div>

                <div class="edit-q-alloc-card">
                    <div class="edit-q-alloc-header">
                        <strong>Kafedralar va dars soatlari</strong>
                        <span class="edit-q-total-hint" id="editQTotalsHint">
                            Yig'indi: 0 | Fan soati: 0
                        </span>
                    </div>

                    <div id="editQAllocationsContainer"></div>

                    <div style="display:flex;justify-content:flex-start;margin-top:6px;">
                        <button type="button" class="btn btn-outline btn-sm" id="editQAddAllocationRow">
                            <i class="fas fa-plus"></i> Qator qo'shish
                        </button>
                    </div>
                </div>

                <div class="edit-q-field">
                    <label>Izoh</label>
                    <textarea
                        id="editQIzoh"
                        class="swal2-textarea edit-q-input"
                        placeholder="Izoh"
                    >${escapeHtml(row.izoh || '')}</textarea>
                </div>
            </div>
        `,
                showCancelButton: true,
                confirmButtonText: "Saqlash",
                cancelButtonText: "Bekor qilish",

                didOpen: () => {
                    const container = $('#editQAllocationsContainer');
                    const allocations = Array.isArray(row.allocations) && row.allocations.length ?
                        row.allocations :
                        [{
                            kafedra_id: '',
                            dars_soati: 0
                        }];

                    const updateTotalsHint = () => {
                        const fanSoat = Number($('#editQFanSoat').val() || 0);
                        let sum = 0;

                        container.find('.edit-q-soat').each(function() {
                            const value = Number($(this).val() || 0);
                            if (Number.isFinite(value) && value > 0) {
                                sum += value;
                            }
                        });

                        const hint = $('#editQTotalsHint');
                        hint.removeClass('ok warn');
                        hint.text(`Yig'indi: ${sum} | Fan soati: ${fanSoat}`);

                        if (fanSoat > 0 && Math.abs(sum - fanSoat) < 0.0001) {
                            hint.addClass('ok');
                        } else if (sum > 0) {
                            hint.addClass('warn');
                        }
                    };

                    const refreshRowMeta = () => {
                        const rows = container.find('.edit-q-allocation-row');

                        rows.each(function(index) {
                            $(this).find('.edit-q-row-label').text(`Kafedra #${index + 1}`);
                        });

                        rows.find('.edit-q-remove-row').prop('disabled', rows.length <= 1);
                    };

                    const destroyAllocationSelect2IfAny = () => {
                        container.find('.edit-q-kafedra').each(function() {
                            const $select = $(this);
                            if ($select.hasClass('select2-hidden-accessible')) {
                                $select.select2('destroy');
                            }
                        });
                    };

                    container.html(
                        allocations.map((a, idx) => buildEditQoshimchaKafedraRow(a, idx + 1)).join('')
                    );

                    destroyAllocationSelect2IfAny();
                    refreshRowMeta();
                    updateTotalsHint();

                    $('#editQFanSoat').off('input.editQ').on('input.editQ', function() {
                        updateTotalsHint();
                    });

                    container.off('input.editQSoat').on('input.editQSoat', '.edit-q-soat', function() {
                        updateTotalsHint();
                    });

                    container.off('change.editQKafedra').on('change.editQKafedra', '.edit-q-kafedra', function() {
                        refreshRowMeta();
                    });

                    $('#editQAddAllocationRow').off('click.editQAdd').on('click.editQAdd', function() {
                        const nextIndex = container.find('.edit-q-allocation-row').length + 1;
                        const newRowHtml = buildEditQoshimchaKafedraRow({}, nextIndex);
                        container.append(newRowHtml);

                        destroyAllocationSelect2IfAny();
                        refreshRowMeta();
                        updateTotalsHint();
                    });

                    container.off('click.editQRemove').on('click.editQRemove', '.edit-q-remove-row', function() {
                        const rows = container.find('.edit-q-allocation-row');

                        if (rows.length <= 1) {
                            return;
                        }

                        const rowEl = $(this).closest('.edit-q-allocation-row');

                        rowEl.find('.edit-q-kafedra').each(function() {
                            const $select = $(this);
                            if ($select.hasClass('select2-hidden-accessible')) {
                                $select.select2('destroy');
                            }
                        });

                        rowEl.remove();
                        refreshRowMeta();
                        updateTotalsHint();
                    });
                },

                preConfirm: () => {
                    const fanName = String($('#editQFanName').val() || '').trim();
                    const fanSoat = Number($('#editQFanSoat').val() || 0);
                    const qoshimchaDarsId = parseInt($('#editQQoshimchaDarsId').val() || 0, 10);
                    const semestrId = parseInt($('#editQSemestrId').val() || 0, 10);
                    const izoh = String($('#editQIzoh').val() || '').trim();

                    if (fanName === '') {
                        SwalApi.showValidationMessage("Fan nomi to'ldirilishi shart");
                        return false;
                    }

                    if (!Number.isFinite(fanSoat) || fanSoat < 0) {
                        SwalApi.showValidationMessage("Hisoblangan fan soati noto'g'ri");
                        return false;
                    }

                    if (qoshimchaDarsId <= 0) {
                        SwalApi.showValidationMessage("Qo'shimcha dars turini tanlang");
                        return false;
                    }

                    if (semestrId <= 0) {
                        SwalApi.showValidationMessage("Semestrni tanlang");
                        return false;
                    }

                    const allocations = [];
                    let totalSoat = 0;
                    let hasPositive = false;
                    let hasInvalid = false;

                    $('#editQAllocationsContainer .edit-q-allocation-row').each(function() {
                        const kafedraId = parseInt($(this).find('.edit-q-kafedra').val() || 0, 10);
                        const darsSoati = Number($(this).find('.edit-q-soat').val() || 0);

                        if (kafedraId <= 0 || !Number.isFinite(darsSoati) || darsSoati < 0) {
                            hasInvalid = true;
                            return;
                        }

                        allocations.push({
                            kafedra_id: kafedraId,
                            dars_soati: darsSoati
                        });

                        totalSoat += darsSoati;

                        if (darsSoati > 0) {
                            hasPositive = true;
                        }
                    });

                    if (hasInvalid || allocations.length === 0) {
                        SwalApi.showValidationMessage("Kafedra va dars soati qatorlarini to'g'ri to'ldiring");
                        return false;
                    }

                    if (!hasPositive) {
                        SwalApi.showValidationMessage("Kamida bitta kafedra soati 0 dan katta bo'lishi kerak");
                        return false;
                    }

                    if (Math.abs(totalSoat - fanSoat) > 0.0001) {
                        SwalApi.showValidationMessage(
                            `Hisoblangan fan soati (${fanSoat}) va kafedralar yig'indisi (${totalSoat}) teng bo'lishi kerak`
                        );
                        return false;
                    }

                    return {
                        qoshimcha_fanid: parseInt(row.qoshimcha_fanid || 0, 10),
                        fan_name: fanName,
                        fan_soat: fanSoat,
                        qoshimcha_dars_id: qoshimchaDarsId,
                        semestr_id: semestrId,
                        izoh: izoh,
                        allocations: allocations
                    };
                }
            }).then((result) => {
                if (!result.isConfirmed || !result.value) return;

                const payload = result.value;
                const formData = new FormData();

                formData.append('qoshimcha_fanid', String(payload.qoshimcha_fanid));
                formData.append('fan_name', payload.fan_name);
                formData.append('fan_soat', String(payload.fan_soat));
                formData.append('qoshimcha_dars_id', String(payload.qoshimcha_dars_id));
                formData.append('semestr_id', String(payload.semestr_id));
                formData.append('izoh', payload.izoh);
                formData.append('allocations_json', JSON.stringify(payload.allocations));

                fetch('insert/update_qoshimcha_oquv_reja_item.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.success) {
                            Toast.fire({
                                icon: 'success',
                                title: data.message || "Yangilandi"
                            });
                            loadCreatedQoshimchaList();
                        } else {
                            Toast.fire({
                                icon: 'error',
                                title: (data && data.message) || "Yangilashda xatolik"
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
        }

        function loadCreatedQoshimchaList() {
            const fakultetId = getSelectedIdWithFallback($('#fakultetFilter'), ['Barcha fakultetlar', 'Fakultetni tanlang']);
            const yonalishId = getSelectedIdWithFallback($('#yonalishFilter'), ["Yo'nalishni tanlang"]);
            const semestrId = getSelectedIdWithFallback($('#semestrSelect'), ['Tanlang', 'Semestrni tanlang']);
            const url = `api/get_qoshimcha_oquv_reja_created_list.php?fakultet_id=${encodeURIComponent(fakultetId)}&yonalish_id=${encodeURIComponent(yonalishId)}&semestr_id=${encodeURIComponent(semestrId)}`;

            fetch(url, {
                    cache: 'no-store'
                })
                .then(res => res.json())
                .then(data => {
                    if (!data || !data.success) {
                        createdQoshimchaRowsById = {};
                        createdQoshimchaRowsAll = [];
                        createdQoshimchaRowsFiltered = [];
                        $('#createdQoshimchaTableBody').html('<tr><td colspan="7">Ro\'yxatni yuklab bo\'lmadi</td></tr>');
                        $('#createdQoshimchaCount').text('0 ta');
                        $('#createdQoshimchaPageInfo').text('0-0 / 0');
                        $('#createdQoshimchaPrevPage').prop('disabled', true);
                        $('#createdQoshimchaNextPage').prop('disabled', true);
                        return;
                    }

                    const rows = Array.isArray(data.rows) ? data.rows : [];
                    createdQoshimchaRowsById = {};
                    rows.forEach(row => {
                        const id = parseInt(row.qoshimcha_fanid || 0, 10);
                        if (id > 0) {
                            createdQoshimchaRowsById[String(id)] = row;
                        }
                    });

                    createdQoshimchaRowsAll = rows;
                    applyCreatedQoshimchaTableFilters(true);
                })
                .catch(() => {
                    createdQoshimchaRowsById = {};
                    createdQoshimchaRowsAll = [];
                    createdQoshimchaRowsFiltered = [];
                    $('#createdQoshimchaTableBody').html('<tr><td colspan="7">Server bilan bog\'lanib bo\'lmadi</td></tr>');
                    $('#createdQoshimchaCount').text('0 ta');
                    $('#createdQoshimchaPageInfo').text('0-0 / 0');
                    $('#createdQoshimchaPrevPage').prop('disabled', true);
                    $('#createdQoshimchaNextPage').prop('disabled', true);
                });
        }

        function cacheYonalishOptionsFromServer(rows) {
            allYonalishOptions = (Array.isArray(rows) ? rows : []).map(item => ({
                id: String(item.id || ''),
                text: String(item.label || item.text || ''),
                fakultetId: String(item.fakultet_id || item.fakultetId || ''),
            })).filter(item => item.id !== '');
        }

        function cacheSemestrOptionsFromServer(rows) {
            allSemestrOptions = (Array.isArray(rows) ? rows : []).map(item => ({
                id: String(item.id || ''),
                text: String(item.label || item.text || ''),
                fakultetId: String(item.fakultet_id || item.fakultetId || ''),
                yonalishId: String(item.yonalish_id || item.yonalishId || ''),
                talaba: String(item.talaba || item.jami_talabalar || '0'),
                talimShakli: String(item.talim_shakli || item.talimShakli || ''),
                talimShakliId: String(item.talim_shakli_id || item.talimShakliId || '0'),
                daraja: String(item.daraja || item.akademik_daraja_name || ''),
                patok: String(item.patok || item.patok_soni || '0'),
                guruh: String(item.guruh || item.guruhlar_soni || '0'),
            })).filter(item => item.id !== '');
        }

        async function syncTopFiltersFromServer(preferredYonalish = null, preferredSemestr = null) {
            if (isTopFilterSyncing) {
                return;
            }

            const fakultetId = getSelectedIdWithFallback($('#fakultetFilter'), ['Barcha fakultetlar', 'Fakultetni tanlang']);
            const currentYonalish = preferredYonalish !== null ?
                String(preferredYonalish || '') :
                getSelectedIdWithFallback($('#yonalishFilter'), ["Yo'nalishni tanlang"]);
            const currentSemestr = preferredSemestr !== null ?
                String(preferredSemestr || '') :
                getSelectedIdWithFallback($('#semestrSelect'), ['Tanlang', 'Semestrni tanlang']);

            isTopFilterSyncing = true;

            try {
                const params = new URLSearchParams();
                if (fakultetId !== '') {
                    params.set('fakultet_id', fakultetId);
                }
                if (currentYonalish !== '') {
                    params.set('yonalish_id', currentYonalish);
                }

                const response = await fetch(`api/get_qoshimcha_filter_options.php?${params.toString()}`, {
                    cache: 'no-store',
                });
                const data = await response.json();

                if (!data || !data.success) {
                    throw new Error('Filter options API javobi noto‘g‘ri');
                }

                cacheYonalishOptionsFromServer(data.yonalishlar || []);
                cacheSemestrOptionsFromServer(data.semestrlar || []);

                const effectiveYonalish = String(data.effective_yonalish_id || '');
                const targetYonalish = effectiveYonalish !== '' ? effectiveYonalish : currentYonalish;

                rebuildYonalishOptions(targetYonalish);

                const selectedYonalish = getSelectedIdWithFallback($('#yonalishFilter'), ["Yo'nalishni tanlang"]);
                const semestrTarget = currentSemestr;
                rebuildSemestrOptions(semestrTarget);
                const hasSemestr = $('#semestrSelect option[value="' + semestrTarget + '"]').length > 0;
                if (!hasSemestr) {
                    $('#semestrSelect').val('').trigger('change.select2');
                } else {
                    $('#semestrSelect').val(semestrTarget).trigger('change.select2');
                }

                if (selectedYonalish !== targetYonalish && targetYonalish !== '' && $('#yonalishFilter option[value="' + targetYonalish + '"]').length > 0) {
                    $('#yonalishFilter').val(targetYonalish).trigger('change.select2');
                    rebuildSemestrOptions(semestrTarget);
                }
            } catch (err) {
                console.error('Top filter sync xatoligi:', err);
                rebuildYonalishOptions(currentYonalish);
                rebuildSemestrOptions(currentSemestr);
            } finally {
                isTopFilterSyncing = false;
                $('#semestrSelect').trigger('change');
            }
        }

        function cacheSemestrOptions() {
            allSemestrOptions = [];
            $('#semestrSelect option').each(function() {
                const id = String($(this).attr('value') || '');
                if (!id) return;

                allSemestrOptions.push({
                    id: id,
                    text: $(this).text(),
                    fakultetId: String($(this).data('fakultet-id') || ''),
                    yonalishId: String($(this).data('yonalish-id') || ''),
                    talaba: String($(this).data('talaba') || '0'),
                    talimShakli: String($(this).data('talim-shakli') || ''),
                    talimShakliId: String($(this).data('talim-shakli-id') || '0'),
                    daraja: String($(this).data('daraja') || ''),
                    patok: String($(this).data('patok') || '0'),
                    guruh: String($(this).data('guruh') || '0'),
                });
            });
            if (!allSemestrOptionsMaster.length) {
                allSemestrOptionsMaster = allSemestrOptions.map(function(item) {
                    return Object.assign({}, item);
                });
            }
        }

        function cacheYonalishOptions() {
            allYonalishOptions = [];
            $('#yonalishFilter option').each(function() {
                const id = String($(this).attr('value') || '');
                if (!id) return;
                allYonalishOptions.push({
                    id: id,
                    text: $(this).text(),
                    fakultetId: String($(this).data('fakultet-id') || ''),
                });
            });
        }

        function rebuildYonalishOptions(selectedValue = '') {
            const selectedFakultet = getSelectedIdWithFallback($('#fakultetFilter'), ['Barcha fakultetlar', 'Fakultetni tanlang']);
            const select = $('#yonalishFilter');
            let html = "<option value=\"\">Yo'nalishni tanlang</option>";

            allYonalishOptions.forEach(item => {
                if (selectedFakultet !== '' && String(item.fakultetId) !== selectedFakultet) {
                    return;
                }
                const selected = String(item.id) === String(selectedValue) ? ' selected' : '';
                const dataAttr = item.fakultetId !== '' ? ` data-fakultet-id="${item.fakultetId}"` : '';
                html += `<option value="${item.id}"${dataAttr}${selected}>${escapeOptionText(item.text)}</option>`;
            });

            select.html(html);
        }

        function filterYonalishByFakultet(selectedValue = null) {
            const currentYonalish = selectedValue !== null ?
                String(selectedValue || '') :
                getSelectedIdWithFallback($('#yonalishFilter'), ["Yo'nalishni tanlang"]);

            rebuildYonalishOptions(currentYonalish);
            const hasCurrent = $('#yonalishFilter option[value="' + currentYonalish + '"]').length > 0;

            if (!hasCurrent) {
                $('#yonalishFilter').val('').trigger('change.select2');
                return;
            }
            $('#yonalishFilter').val(currentYonalish).trigger('change.select2');
        }

        function rebuildSemestrOptions(selectedValue = '') {
            const selectedFakultet = getSelectedIdWithFallback($('#fakultetFilter'), ['Barcha fakultetlar', 'Fakultetni tanlang']);
            const selectedYonalish = getSelectedIdWithFallback($('#yonalishFilter'), ["Yo'nalishni tanlang"]);
            const select = $('#semestrSelect');
            let html = '<option value="">Tanlang</option>';

            allSemestrOptions.forEach(item => {
                if (selectedFakultet !== '' && String(item.fakultetId) !== selectedFakultet) {
                    return;
                }
                if (selectedYonalish !== '' && String(item.yonalishId) !== selectedYonalish) {
                    return;
                }
                const selected = String(item.id) === String(selectedValue) ? ' selected' : '';
                html += `<option value="${item.id}" data-fakultet-id="${item.fakultetId}" data-yonalish-id="${item.yonalishId}" data-talaba="${escapeOptionText(item.talaba)}" data-talim-shakli="${escapeOptionText(item.talimShakli)}" data-talim-shakli-id="${escapeOptionText(item.talimShakliId)}" data-daraja="${escapeOptionText(item.daraja)}" data-patok="${escapeOptionText(item.patok)}" data-guruh="${escapeOptionText(item.guruh)}"${selected}>${escapeOptionText(item.text)}</option>`;
            });

            select.html(html);
        }

        function filterSemestrByFilters(selectedValue = null) {
            const currentSemestr = getSelectedIdWithFallback($('#semestrSelect'), ['Tanlang', 'Semestrni tanlang']);
            const targetSemestr = selectedValue !== null ? String(selectedValue || '') : currentSemestr;

            rebuildSemestrOptions(targetSemestr);

            const hasCurrent = $('#semestrSelect option[value="' + targetSemestr + '"]').length > 0;
            if (!hasCurrent) {
                $('#semestrSelect').val('').trigger('change.select2');
                return;
            }
            $('#semestrSelect').val(targetSemestr).trigger('change.select2');
        }

        function getSemestrMeta() {
            const option = $('#semestrSelect').find('option:selected');
            return {
                talaba: parseInt(option.data('talaba'), 10) || 0,
                talimShakli: String(option.data('talim-shakli') || '').toLowerCase(),
                daraja: String(option.data('daraja') || '').toLowerCase(),
                patok: parseInt(option.data('patok'), 10) || 0,
                guruh: parseInt(option.data('guruh'), 10) || 0
            };
        }

        function isExternalTalim(talimShakli) {
            return talimShakli.includes('sirtqi') || talimShakli.includes('masofaviy') || talimShakli.includes('kechki');
        }

        function setCalcHint(card, text) {
            const hint = card.find('.calc-hint');
            if (!hint.length) return;
            if (!text) {
                hint.text('').hide();
                return;
            }
            hint.text(text).show();
        }

        function updateExtraFields(card, qoshimchaId) {
            card.find('.extra-field').hide();

            if ([QOSHIMCHA_IDS.OQUV_PED, QOSHIMCHA_IDS.DALA_OTM, QOSHIMCHA_IDS.DALA_TASH, QOSHIMCHA_IDS.ISHLAB_CHIQARISH].includes(qoshimchaId)) {
                card.find('.extra-hafta').show();
            }

            if (qoshimchaId === QOSHIMCHA_IDS.BMI) {
                card.find('.extra-bmi').show();
            }

            if (qoshimchaId === QOSHIMCHA_IDS.YAKUNIY) {
                card.find('.extra-yakuniy').show();
            }

            if (qoshimchaId === QOSHIMCHA_IDS.YADAK) {
                card.find('.extra-yadak').show();
                updateYadakMode(card);
            }

            if (qoshimchaId === QOSHIMCHA_IDS.OCHIQ_DARS) {
                card.find('.extra-ochiq').show();
            }
        }

        function updateYadakMode(card) {
            const semestrMeta = getSemestrMeta();
            const isMagistr = semestrMeta.daraja.includes('magistr');
            const yadakSelect = card.find('.yadak-type');
            const magOptions = yadakSelect.find('option[value="mag1"], option[value="mag2"]');

            if (isMagistr) {
                magOptions.prop('disabled', false).show();
            } else {
                magOptions.prop('disabled', true).hide();
                if (yadakSelect.val() !== 'basic') {
                    yadakSelect.val('basic');
                }
            }

            const type = yadakSelect.val() || 'basic';
            if (type === 'basic') {
                card.find('.yadak-fan-wrap').show();
            } else {
                card.find('.yadak-fan-wrap').hide();
            }
        }

        function calculateForSingleCard(card) {
            const semestrSelect = $('#semestrSelect');
            const fanSelect = card.find('select[name="fan_nomi[]"]');
            const qoshimchaSelect = card.find('select[name="qoshimcha_dars_id[]"]');
            const fanSoatInput = card.find('input[name="fan_soat[]"]');

            const semestrId = semestrSelect.val();
            const fanId = fanSelect.val();
            const qoshimchaId = parseInt(qoshimchaSelect.val(), 10) || 0;

            updateExtraFields(card, qoshimchaId);

            if (!semestrId || !qoshimchaId || !fanId) {
                fanSoatInput.val('');
                fanSoatInput.prop('readonly', false);
                setCalcHint(card, '');
                return;
            }

            const semestrMeta = getSemestrMeta();
            const isExternal = isExternalTalim(semestrMeta.talimShakli);
            const koef = parseFloat(qoshimchaSelect.find('option:selected').data('koef')) || 0;
            const fanMeta = fanMap[String(fanId)] || {};
            const auditoriyaSoat = parseFloat(fanMeta.auditoriya_soat) || 0;

            let fanSoat = null;
            let auto = true;
            let hintText = '';
            const formatRawNumber = (value) => {
                const numeric = Number(value);
                if (!Number.isFinite(numeric)) {
                    return '0';
                }
                if (Math.abs(numeric - Math.round(numeric)) < 0.0001) {
                    return String(Math.round(numeric));
                }
                return String(parseFloat(numeric.toFixed(2)));
            };
            const buildMulHint = (left, factor, result) => {
                const raw = Number(left) * Number(factor);
                const rawText = formatRawNumber(raw);
                if (Math.abs(raw - Number(result)) < 0.0001) {
                    return `${left} x ${factor} = ${result}`;
                }
                return `${left} x ${factor} = ${rawText} (yaxlitlab: ${result})`;
            };

            switch (qoshimchaId) {
                case QOSHIMCHA_IDS.ORALIQ:
                    if (isExternal) {
                        fanSoat = 0;
                        hintText = "Sirtqi/masofaviy/kechki: 0";
                    } else if (auditoriyaSoat >= 60) {
                        fanSoat = Math.round(semestrMeta.talaba * 0.4);
                        hintText = buildMulHint(semestrMeta.talaba, 0.4, fanSoat);
                    } else if (auditoriyaSoat >= 30) {
                        fanSoat = Math.round(semestrMeta.talaba * 0.2);
                        hintText = buildMulHint(semestrMeta.talaba, 0.2, fanSoat);
                    } else {
                        fanSoat = 0;
                        hintText = "Auditoriya soat 30 dan kam: 0";
                    }
                    break;
                case QOSHIMCHA_IDS.YAKUNIY: {
                    const isTest = card.find('.yakuniy-test').is(':checked');
                    fanSoat = isTest ? 0 : Math.round(semestrMeta.talaba * 0.3);
                    hintText = isTest ? "Yakuniy test: 0" : buildMulHint(semestrMeta.talaba, 0.3, fanSoat);
                    break;
                }
                case QOSHIMCHA_IDS.KURS_ISHI:
                    fanSoat = Math.round(semestrMeta.talaba * 2.4);
                    hintText = buildMulHint(semestrMeta.talaba, 2.4, fanSoat);
                    break;
                case QOSHIMCHA_IDS.KURS_LOYIHA:
                    fanSoat = Math.round(semestrMeta.talaba * 3.6);
                    hintText = buildMulHint(semestrMeta.talaba, 3.6, fanSoat);
                    break;
                case QOSHIMCHA_IDS.UZLUKSIZ_MALAKA:
                    fanSoat = Math.round(semestrMeta.talaba * (isExternal ? 0.4 : 2));
                    hintText = buildMulHint(semestrMeta.talaba, isExternal ? 0.4 : 2, fanSoat);
                    break;
                case QOSHIMCHA_IDS.OQUV_PED:
                case QOSHIMCHA_IDS.DALA_OTM:
                case QOSHIMCHA_IDS.DALA_TASH:
                case QOSHIMCHA_IDS.ISHLAB_CHIQARISH: {
                    const hafta = parseFloat(card.find('.hafta-input').val()) || 0;
                    const guruh = semestrMeta.guruh || 0;
                    const perWeek = qoshimchaId === QOSHIMCHA_IDS.DALA_TASH ? 30 : 18;
                    fanSoat = Math.round(guruh * hafta * perWeek);
                    const raw = guruh * hafta * perWeek;
                    const rawText = formatRawNumber(raw);
                    hintText = Math.abs(raw - fanSoat) < 0.0001
                        ? `${guruh} x ${hafta} x ${perWeek} = ${fanSoat}`
                        : `${guruh} x ${hafta} x ${perWeek} = ${rawText} (yaxlitlab: ${fanSoat})`;
                    break;
                }
                case QOSHIMCHA_IDS.BMI: {
                    const isTech = card.find('.bmi-tech').is(':checked');
                    const per = isTech ? 30 : 25;
                    fanSoat = Math.round(semestrMeta.talaba * per);
                    hintText = buildMulHint(semestrMeta.talaba, per, fanSoat);
                    break;
                }
                case QOSHIMCHA_IDS.OCHIQ_DARS: {
                    const count = parseInt(card.find('.ochiq-count').val(), 10) || 1;
                    fanSoat = count * 10;
                    hintText = `${count} x 10 = ${fanSoat}`;
                    break;
                }
                case QOSHIMCHA_IDS.YADAK: {
                    const yadakType = card.find('.yadak-type').val() || 'basic';
                    const teacherCount = parseInt(card.find('.yadak-teacher').val(), 10) || 0;
                    const fanCount = parseInt(card.find('.yadak-fan-count').val(), 10) || 5;

                    if (yadakType === 'mag1') {
                        fanSoat = Math.round(semestrMeta.talaba * teacherCount * 0.4);
                        hintText = `${semestrMeta.talaba} × ${teacherCount} × 0.4 = ${fanSoat}`;
                    } else if (yadakType === 'mag2') {
                        fanSoat = Math.round(semestrMeta.talaba * teacherCount * 0.8);
                        hintText = `${semestrMeta.talaba} × ${teacherCount} × 0.8 = ${fanSoat}`;
                    } else {
                        const partA = Math.round(semestrMeta.talaba * teacherCount * 0.4);
                        const partB = (semestrMeta.patok || 0) * 6 * fanCount;
                        const partC = Math.round((semestrMeta.talaba / 5) * fanCount * 0.2);
                        fanSoat = partA + partB + partC;
                        hintText = `A: ${semestrMeta.talaba}×${teacherCount}×0.4=${partA}, B: ${semestrMeta.patok}×6×${fanCount}=${partB}, C: (${semestrMeta.talaba}/5)×${fanCount}×0.2=${partC}; Jami=${fanSoat}`;
                    }
                    break;
                }
                default:
                    if (koef > 0) {
                        fanSoat = Math.round(semestrMeta.talaba * koef);
                        hintText = `${semestrMeta.talaba} × ${koef} = ${fanSoat}`;
                    } else {
                        auto = false;
                    }
                    break;
            }

            if (fanSoat === null || Number.isNaN(fanSoat)) {
                if (auto) {
                    fanSoatInput.val('');
                    setCalcHint(card, '');
                } else {
                    setCalcHint(card, hintText);
                }
                fanSoatInput.prop('readonly', false);
                return;
            }

            fanSoatInput.val(fanSoat);
            fanSoatInput.prop('readonly', auto);
            setCalcHint(card, auto ? hintText : '');

            if (auto) {
                const darsInputs = card.find('input[name^="dars_soati"]');
                if (darsInputs.length === 1) {
                    const darsInput = darsInputs.first();
                    if (!darsInput.data('manual')) {
                        darsInput.val(fanSoat);
                    }
                }
            }
        }
        $(document).ready(function() {
            cacheYonalishOptions();
            cacheSemestrOptions();
            createdQoshimchaPerPage = parseInt($('#createdQoshimchaPerPage').val() || 20, 10) || 20;

            initSelect2Safe($('#fakultetFilter'), "Fakultetni tanlang");
            initSelect2Safe($('#yonalishFilter'), "Yo'nalishni tanlang");
            initSelect2Safe($('#semestrSelect'), "Semestrni tanlang");

            initInitialSelect2();

            // Dastlabki holatni serverdan qayta sinxronlash (hostda barqaror ishlashi uchun)
            syncTopFiltersFromServer();

            $('#fakultetFilter').on('change', function() {
                if (isTopFilterSyncing) return;
                syncTopFiltersFromServer('', '');
            });

            $('#yonalishFilter').on('change', function() {
                if (isTopFilterSyncing) return;
                syncTopFiltersFromServer(String($(this).val() || ''), '');
            });

            $('#applyFiltersBtn').on('click', function() {
                syncTopFiltersFromServer();
            });

            $('#resetFiltersBtn').on('click', function() {
                if (isTopFilterSyncing) return;
                isTopFilterSyncing = true;
                $('#fakultetFilter').val('').trigger('change.select2');
                $('#yonalishFilter').val('').trigger('change.select2');
                $('#semestrSelect').val('').trigger('change.select2');
                isTopFilterSyncing = false;
                syncTopFiltersFromServer('', '');
            });

            // Ba'zi brauzerlarda select qiymatlari sahifa ochilgandan keyin tiklanadi.
            // Shu sabab filtrlarni qayta sync qilamiz.
            const syncTopFilters = () => {
                const currentYonalish = getSelectedIdWithFallback($('#yonalishFilter'), ["Yo'nalishni tanlang"]);
                const currentSemestr = getSelectedIdWithFallback($('#semestrSelect'), ['Tanlang', 'Semestrni tanlang']);
                syncTopFiltersFromServer(currentYonalish, currentSemestr);
            };

            syncTopFilters();
            setTimeout(syncTopFilters, 150);
            $(window).on('pageshow', function() {
                setTimeout(syncTopFilters, 0);
            });

            $('#yonalishFilter').on('select2:opening focus mousedown click', function() {
                if (isTopFilterSyncing) return;
                const currentYonalish = getSelectedIdWithFallback($('#yonalishFilter'), ["Yo'nalishni tanlang"]);
                filterYonalishByFakultet(currentYonalish);
            });

            $('#semestrSelect').on('select2:opening focus mousedown click', function() {
                if (isTopFilterSyncing) return;
                const currentSemestr = getSelectedIdWithFallback($('#semestrSelect'), ['Tanlang', 'Semestrni tanlang']);
                filterSemestrByFilters(currentSemestr);
            });

            $('#createdQoshimchaSearchInput').on('input', function() {
                applyCreatedQoshimchaTableFilters(true);
            });

            $('#createdQoshimchaPerPage').on('change', function() {
                createdQoshimchaPerPage = parseInt($(this).val() || 20, 10) || 20;
                applyCreatedQoshimchaTableFilters(true);
            });

            $('#createdQoshimchaPrevPage').on('click', function() {
                if (createdQoshimchaPage > 1) {
                    createdQoshimchaPage -= 1;
                    renderCreatedQoshimchaTableCurrentPage();
                }
            });

            $('#createdQoshimchaNextPage').on('click', function() {
                const totalRows = createdQoshimchaRowsFiltered.length;
                const totalPages = Math.max(1, Math.ceil(totalRows / Math.max(1, createdQoshimchaPerPage)));
                if (createdQoshimchaPage < totalPages) {
                    createdQoshimchaPage += 1;
                    renderCreatedQoshimchaTableCurrentPage();
                }
            });

            $('#refreshCreatedQoshimchaBtn').on('click', function() {
                loadCreatedQoshimchaList();
            });

            loadCreatedQoshimchaList();
        });

        $('#semestrSelect').on('change', async function() {
            const cards = $('.reja-card').toArray();
            for (const cardElement of cards) {
                const card = $(cardElement);
                await renderFanOptions(card);
                calculateForSingleCard(card);
            }
            loadCreatedQoshimchaList();
        });

        function initInitialSelect2() {
            initSelect2Safe($('select[name="qoshimcha_dars_id[]"]'), "Qo'shimcha dars turini tanlang");
            initSelect2Safe($('select[name="fan_nomi[]"]'), "Fan (kod + nomi) tanlang");
            initSelect2Safe($('select[name="kafedra_id[0][]"]'), "Kafedrani tanlang");

            renderFanOptions($('.reja-card:first'));
        }

        async function renderFanOptions(card) {
            const select = card.find('.fan-select');
            if (select.length === 0) return;

            const semestrId = $('#semestrSelect').val();
            select.empty();

            if (!semestrId) {
                select.prop('disabled', true);
                select.append(new Option('Tanlang', '', true, true));
                select.val(null).trigger('change');
                return;
            }

            select.prop('disabled', true);
            select.append(new Option('Fanlar yuklanmoqda...', '', true, true));
            select.val(null).trigger('change');

            let items = [];
            try {
                items = await ensureFanOptionsLoaded(semestrId);
            } catch (error) {
                console.error('fan options load failed:', error);
                select.empty();
                select.prop('disabled', true);
                select.append(new Option('Fanlarni yuklab bo\'lmadi', '', true, true));
                select.val(null).trigger('change');
                return;
            }

            select.empty();
            if (items.length === 0) {
                select.prop('disabled', true);
                select.append(new Option('Fan topilmadi', '', true, true));
                select.val(null).trigger('change');
                return;
            }

            select.prop('disabled', false);
            select.append(new Option('Tanlang', '', true, true));
            items.forEach(f => {
                select.append(new Option(f.label, f.value, false, false));
            });
            select.val(null).trigger('change');
        }

        function createNewReja() {
            fanIndex++;

            const originalHtml = `
                <div class="reja-card" data-index="${fanIndex}">
                    <div class="form-grid-3">
                        <div class="form-group">
                            <label>Fan (kod + nomi)</label>
                            <select class="form-control fan-select" name="fan_nomi[]" required>
                                <option value="">Tanlang</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Qo'shimcha dars turi</label>
                            <select class="form-control qoshimcha-select" name="qoshimcha_dars_id[]" required>
                                <option value="">Tanlang</option>
                                ${buildQoshimchaDarsOptionsHtml('')}
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Hisoblangan fan soati</label>
                            <input type="number" class="form-control fan-soat-input" name="fan_soat[]" required>
                            <div class="calc-hint" style="display:none;font-size:12px;color:#6c757d;margin-top:4px;"></div>
                        </div>
                    </div>
                    <div class="extra-fields">
                        <div class="form-grid-3 extra-field extra-hafta" style="display:none;">
                            <div class="form-group">
                                <label>Hafta soni</label>
                                <input type="number" class="form-control calc-input hafta-input" min="0" step="1" placeholder="Masalan: 4">
                            </div>
                        </div>
                        <div class="form-grid-3 extra-field extra-bmi" style="display:none;">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" class="calc-input bmi-tech"> Texnik yo'nalish (30 soat)
                                </label>
                            </div>
                        </div>
                        <div class="form-grid-3 extra-field extra-yakuniy" style="display:none;">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" class="calc-input yakuniy-test"> Yakuniy test shaklida
                                </label>
                            </div>
                        </div>
                        <div class="form-grid-3 extra-field extra-yadak" style="display:none;">
                            <div class="form-group">
                                <label>YADAK turi</label>
                                <select class="form-control calc-input yadak-type">
                                    <option value="basic">Umumiy (konsultatsiya/yozma)</option>
                                    <option value="mag1">1-kurs magistr himoya (0.4)</option>
                                    <option value="mag2">Magistr dissertatsiya (0.8)</option>
                                </select>
                            </div>
                            <div class="form-group yadak-teacher-wrap">
                                <label>YADAK o'qituvchi soni</label>
                                <input type="number" class="form-control calc-input yadak-teacher" min="0" step="1" value="1">
                            </div>
                            <div class="form-group yadak-fan-wrap">
                                <label>YADAK fan soni</label>
                                <input type="number" class="form-control calc-input yadak-fan-count" min="1" step="1" value="5">
                            </div>
                        </div>
                        <div class="form-grid-3 extra-field extra-ochiq" style="display:none;">
                            <div class="form-group">
                                <label>Ochiq dars soni</label>
                                <input type="number" class="form-control calc-input ochiq-count" min="1" step="1" value="1">
                            </div>
                        </div>
                    </div>
                    <div class="darsSoatWrapper">
                        <div class="form-grid-2 dars-soat-row">
                            <div class="form-group">
                                <label>Kafedra</label>
                                <select class="form-control kafedra-select" name="kafedra_id[${fanIndex}][]" required>
                                    <option value="">Tanlang</option>
                                    ${buildKafedralarOptionsHtml('')}
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Kafedra bo'yicha dars soati</label>
                                <input type="number" class="form-control" name="dars_soati[${fanIndex}][]" min="0" required>
                            </div>
                        </div>
                        <div class="dars-soat-actions">
                            <button type="button" class="btn btn-outline btn-sm addDarsSoat">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm removeDarsSoat">
                                <i class="fas fa-times"></i>
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
            `;

            const newReja = $(originalHtml);
            $('#rejaWrapper').append(newReja);

            setTimeout(() => {
                initSelect2Safe(newReja.find('.qoshimcha-select'), "Qo'shimcha dars turini tanlang");
                initSelect2Safe(newReja.find('.fan-select'), "Fan (kod + nomi) tanlang");
                initSelect2Safe(newReja.find('.kafedra-select'), "Kafedrani tanlang");
            }, 50);

            renderFanOptions(newReja);

            return newReja;
        }

        $(document).on('click', '.addDarsSoat', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const wrapper = $(this).closest('.darsSoatWrapper');
            const card = $(this).closest('.reja-card');
            const index = card.data('index');

            const newRowHtml = `
                <div class="form-grid-2 dars-soat-row">
                    <div class="form-group">
                        <label>Kafedra</label>
                        <select class="form-control kafedra-select" name="kafedra_id[${index}][]" required>
                            <option value="">Tanlang</option>
                            ${buildKafedralarOptionsHtml('')}
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kafedra bo'yicha dars soati</label>
                        <input type="number" class="form-control" name="dars_soati[${index}][]" min="0" required>
                    </div>
                </div>
            `;

            const newRow = $(newRowHtml);
            wrapper.find('.dars-soat-actions').before(newRow);

            setTimeout(() => {
                initSelect2Safe(newRow.find('.kafedra-select'), "Kafedrani tanlang");
            }, 50);
        });

        $(document).on('click', '.removeDarsSoat', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const wrapper = $(this).closest('.darsSoatWrapper');
            const rows = wrapper.find('.dars-soat-row');

            if (rows.length > 1) {
                const lastRow = rows.last();

                const select = lastRow.find('.kafedra-select');
                if (select.hasClass('select2-hidden-accessible')) {
                    select.select2('destroy');
                }

                lastRow.remove();
            }
        });

        $(document).on('click', '.addReja', function(e) {
            e.preventDefault();
            e.stopPropagation();
            createNewReja();
        });

        $(document).on('click', '.removeReja', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const rejas = $('.reja-card');
            const currentReja = $(this).closest('.reja-card');
            const currentIndex = currentReja.data('index');

            if (rejas.length > 1 && currentIndex !== 0) {
                currentReja.find('select').each(function() {
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2('destroy');
                    }
                });

                currentReja.remove();
            }
        });

        $(document).on('click', '.editCreatedQoshimchaBtn', function() {
            const fanId = String($(this).data('qoshimcha-fanid') || '');
            const row = createdQoshimchaRowsById[fanId];
            if (!row) {
                return;
            }
            openEditCreatedQoshimchaModal(row);
        });

        $(document).on('click', '.deleteCreatedQoshimchaBtn', function() {
            const fanId = String($(this).data('qoshimcha-fanid') || '');
            const row = createdQoshimchaRowsById[fanId];
            if (!row) {
                return;
            }

            SwalApi.fire({
                title: "Qo'shimcha fanni o'chirishni tasdiqlaysizmi?",
                text: `${row.fan_name || ''}`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ha, o'chirilsin",
                cancelButtonText: "Bekor qilish"
            }).then((result) => {
                if (!result.isConfirmed) {
                    return;
                }

                const formData = new FormData();
                formData.append('qoshimcha_fanid', fanId);

                fetch('insert/delete_qoshimcha_oquv_reja_item.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.success) {
                            Toast.fire({
                                icon: 'success',
                                title: data.message || "Qo'shimcha fan o'chirildi"
                            });
                            loadCreatedQoshimchaList();
                        } else {
                            Toast.fire({
                                icon: 'error',
                                title: (data && data.message) || "O'chirishda xatolik"
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

        $(document).on('submit', '#oquvRejaForm', function(e) {
            e.preventDefault();

            let isValid = true;
            const errors = [];
            if (!$('#semestrSelect').val()) {
                isValid = false;
                errors.push('Semestr tanlanmagan');
                $('#semestrSelect').next('.select2-container').css('border-color', '#e74c3c');
            } else {
                $('#semestrSelect').next('.select2-container').css('border-color', '');
            }

            $('.reja-card').each(function(index) {
                const card = $(this);
                const cardIndex = index + 1;

                const fanNomi = card.find('select[name="fan_nomi[]"]');
                if (!fanNomi.val()) {
                    isValid = false;
                    errors.push(`${cardIndex}-fan nomi kiritilmagan`);
                    fanNomi.next('.select2-container').css('border-color', '#e74c3c');
                } else {
                    fanNomi.next('.select2-container').css('border-color', '');
                }

                const fanSoat = card.find('input[name="fan_soat[]"]');
                const fanSoatVal = fanSoat.val();
                if (fanSoatVal === '' || parseFloat(fanSoatVal) < 0) {
                    isValid = false;
                    errors.push(`${cardIndex}-fan soati noto'g'ri`);
                    fanSoat.css('border-color', '#e74c3c');
                } else {
                    fanSoat.css('border-color', '');
                }

                const qoshimchaSelect = card.find('select[name="qoshimcha_dars_id[]"]');
                if (!qoshimchaSelect.val()) {
                    isValid = false;
                    errors.push(`${cardIndex}-fan uchun qo'shimcha dars turi tanlanmagan`);
                    qoshimchaSelect.next('.select2-container').css('border-color', '#e74c3c');
                } else {
                    qoshimchaSelect.next('.select2-container').css('border-color', '');
                }

                const haftaInput = card.find('.hafta-input:visible');
                if (haftaInput.length && (!haftaInput.val() || parseFloat(haftaInput.val()) <= 0)) {
                    isValid = false;
                    errors.push(`${cardIndex}-fan uchun hafta soni noto'g'ri`);
                    haftaInput.css('border-color', '#e74c3c');
                } else {
                    haftaInput.css('border-color', '');
                }

                const yadakTeacher = card.find('.yadak-teacher:visible');
                if (yadakTeacher.length && (!yadakTeacher.val() || parseFloat(yadakTeacher.val()) <= 0)) {
                    isValid = false;
                    errors.push(`${cardIndex}-fan uchun YADAK o'qituvchi soni noto'g'ri`);
                    yadakTeacher.css('border-color', '#e74c3c');
                } else {
                    yadakTeacher.css('border-color', '');
                }

                const yadakFanCount = card.find('.yadak-fan-count:visible');
                if (yadakFanCount.length && (!yadakFanCount.val() || parseFloat(yadakFanCount.val()) <= 0)) {
                    isValid = false;
                    errors.push(`${cardIndex}-fan uchun YADAK fan soni noto'g'ri`);
                    yadakFanCount.css('border-color', '#e74c3c');
                } else {
                    yadakFanCount.css('border-color', '');
                }

                const ochiqCount = card.find('.ochiq-count:visible');
                if (ochiqCount.length && (!ochiqCount.val() || parseFloat(ochiqCount.val()) <= 0)) {
                    isValid = false;
                    errors.push(`${cardIndex}-fan uchun ochiq dars soni noto'g'ri`);
                    ochiqCount.css('border-color', '#e74c3c');
                } else {
                    ochiqCount.css('border-color', '');
                }

                card.find('select[name^="kafedra_id"]').each(function(kafedraIndex) {
                    if (!$(this).val()) {
                        isValid = false;
                        errors.push(`${cardIndex}-fan uchun ${kafedraIndex + 1}-kafedra tanlanmagan`);
                        $(this).next('.select2-container').css('border-color', '#e74c3c');
                    } else {
                        $(this).next('.select2-container').css('border-color', '');
                    }
                });

                card.find('input[name^="dars_soati"]').each(function(soatIndex) {
                    const soatVal = $(this).val();
                    if (soatVal === '' || parseFloat(soatVal) < 0) {
                        isValid = false;
                        errors.push(`${cardIndex}-fan uchun ${soatIndex + 1}-dars soati noto'g'ri`);
                        $(this).css('border-color', '#e74c3c');
                    } else {
                        $(this).css('border-color', '');
                    }
                });
            });

            if (!isValid) {
                Toast.fire({
                    icon: 'error',
                    title: errors[0] || 'Iltimos, barcha maydonlarni to\'ldiring'
                });
                return;
            }

            let hourMismatch = false;
            let mismatchMessage = "";

            $('.reja-card').each(function(index) {
                const card = $(this);
                const cardIndex = index + 1;

                const fanSoat = card.find('input[name="fan_soat[]"]');
                const fanSoatValue = parseFloat(fanSoat.val()) || 0;
                let kafedraSoatlariYigindisi = 0;

                card.find('input[name^="dars_soati"]').each(function() {
                    const soatValue = parseFloat($(this).val()) || 0;
                    kafedraSoatlariYigindisi += soatValue;
                });

                if (fanSoatValue !== kafedraSoatlariYigindisi) {
                    hourMismatch = true;
                    mismatchMessage = `${cardIndex}-fan soati (${fanSoatValue}) kafedralarga bo'lingan soatlar yig'indisiga (${kafedraSoatlariYigindisi}) teng emas!`;
                    return false;
                }
            });

            if (hourMismatch) {
                SwalApi.fire({
                    icon: 'warning',
                    title: 'Soatlar mos emas!',
                    text: mismatchMessage,
                    confirmButtonText: 'Tushunarli'
                });
                return;
            }

            const form = $(this);
            const formData = new FormData(this);

            fetch('insert/add_qoshimcha_oquv_reja.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Toast.fire({
                            icon: 'success',
                            title: data.message || 'Qo\'shimcha o\'quv reja muvaffaqiyatli saqlandi'
                        });

                        form[0].reset();

                        $('#fakultetFilter').val('').trigger('change');
                        $('#yonalishFilter').val('').trigger('change');

                        $('select[name="qoshimcha_dars_id[]"]').each(function() {
                            $(this).val(null).trigger('change');
                        });
                        $('select[name="fan_nomi[]"]').each(function() {
                            $(this).val(null).trigger('change');
                        });
                        $('select[name^="kafedra_id"]').each(function() {
                            $(this).val(null).trigger('change');
                        });

                        $('.reja-card').each(function(index) {
                            if (index > 0) {
                                $(this).find('select').each(function() {
                                    if ($(this).hasClass('select2-hidden-accessible')) {
                                        $(this).select2('destroy');
                                    }
                                });
                                $(this).remove();
                            }
                        });

                        fanIndex = 0;
                        loadCreatedQoshimchaList();

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
                        title: 'Server bilan bog\'lanib bo\'lmadi'
                    });
                });
        });
    </script>
</body>

</html>
