<?php
    // Izoh: Bu sahifada Majburiy, Tanlov, Chet tili va Birlashtiriladigan fanlar yaratiladi.
    // Izoh: Majburiy/Birlashtiriladigan fanlarda fan kodi va fan nomi inputdan kiritiladi.

    include_once 'config.php';
    $db = new Database();
    $semestrlar = $db->get_semestrlar();
    $fakultetlar = $db->get_data_by_table_all('fakultetlar');
    $dars_soat_turlari = $db->get_data_by_table_all('dars_soat_turlar');
    $kafedralar = $db->get_data_by_table_all('kafedralar');
    // Izoh: Majburiy/Birlashtiriladigan fanlar selectdan olinmaydi, shuning uchun fanlar ro'yxati kerak emas.
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
    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $darsSoatTurlariJson = json_encode($dars_soat_turlari ?? [], $jsonFlags);
    if ($darsSoatTurlariJson === false) {
        $darsSoatTurlariJson = '[]';
    }
    $kafedralarJson = json_encode($kafedralar ?? [], $jsonFlags);
    if ($kafedralarJson === false) {
        $kafedralarJson = '[]';
    }
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

?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>O'quv reja yaratish</title>
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
        .calc-summary {
            display: grid;
            grid-template-columns: repeat(3, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 12px;
            margin-bottom: 12px;
            padding: 14px;
            border: 1px solid #dbeafe;
            border-radius: 14px;
            background: #f8fbff;
        }
        .calc-summary-note {
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: end;
            padding-bottom: 10px;
        }
        .calc-summary-note.is-warning {
            color: #b45309;
            font-weight: 600;
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
            .calc-summary {
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
                <h1>O'quv reja yaratish</h1>
            </header>
            <div class="content-container">
                <form id="oquvRejaForm" class="card">
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
                                <option value="">Barcha yo'nalishlar</option>
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
                                        $fakultetId = (int)($s['yonalish_fakultet_id'] ?? ($s['fakultet_id'] ?? 0));
                                        $yonalishId = (int)($s['yonalish_id'] ?? 0);
                                    ?>
                                    <option
                                        value="<?= $s['id'] ?>"
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
                                <input type="hidden" name="tanlov_fan[0]" value="0" class="tanlov-input">

                                <button type="button" class="btn btn-outline btn-sm fanTypeToggle active" data-type="0">
                                    <i class="fas fa-book"></i> Majburiy fan
                                </button>
                                <button type="button" class="btn btn-outline btn-sm fanTypeToggle" data-type="1">
                                    <i class="fas fa-check-circle"></i> Tanlov fan
                                </button>
                                <button type="button" class="btn btn-outline btn-sm fanTypeToggle" data-type="3">
                                    <i class="fas fa-language"></i> Chet tili
                                </button>
                                <button type="button" class="btn btn-outline btn-sm fanTypeToggle" data-type="2">
                                    <i class="fas fa-graduation-cap"></i> Birlashtiriladigan fan
                                </button>
                            </div>

                            <div class="form-grid-2">
                                <div class="form-group">
                                    <label>Fan kodi</label>
                                    <!-- Izoh: Majburiy/Birlashtiriladigan fan kodi inputdan kiritiladi -->
                                    <input type="text" class="form-control fan-code-input" name="fan_code[0]" placeholder="Masalan: HIS1101" required>
                                </div>

                                <div class="form-group">
                                    <label>Fan nomi</label>
                                    <!-- Izoh: Majburiy/Birlashtiriladigan fan nomi inputdan kiritiladi -->
                                    <input type="text" class="form-control fan-name-input" name="fan_nomi[0]" placeholder="Masalan: Hisob (Calculus) I-qism" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Kafedra</label>
                                    <select class="form-control" name="kafedra_id[0]" required>
                                        <option value="">Tanlang</option>
                                        <?php foreach ($kafedralar as $k): ?>
                                            <option value="<?= $k['id'] ?>">
                                                <?= $h($k['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="darsSoatWrapper">
                                <?php foreach ($dars_soat_turlari as $d): ?>
                                    <div class="form-grid-2 dars-soat-row">
                                        <div class="form-group">
                                            <label>Dars turi</label>
                                            <input type="text"
                                                class="form-control"
                                                value="<?= $h($d['name']) ?>"
                                                readonly
                                                tabindex="-1">
                                            <input type="hidden" name="dars_turi[0][]" value="<?= (int)$d['id'] ?>">
                                        </div>

                                        <div class="form-group">
                                            <label>Dars soati</label>
                                            <input type="number"
                                                class="form-control"
                                                name="dars_soati[0][]"
                                                min="0">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="calc-summary">
                                <div class="form-group">
                                    <label>Jami soat</label>
                                    <input type="number" class="form-control jami-soat-input" value="0" readonly tabindex="-1">
                                </div>
                                <div class="form-group">
                                    <label>Kredit</label>
                                    <input type="number" class="form-control kredit-input" value="0" readonly tabindex="-1">
                                </div>
                                <div class="calc-summary-note" data-role="credit-note">
                                    30 soat = 1 kredit
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
                        <!-- /reja-card -->

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
                            <h3>Yaratilgan fanlar ro'yxati</h3>
                            <span class="badge" id="createdRejaCount">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <button type="button" class="btn btn-outline btn-sm" id="refreshCreatedRejaBtn">
                                <i class="fas fa-rotate"></i> Yangilash
                            </button>
                        </div>
                    </div>
                    <div class="created-list-note">
                        Ro'yxat yuqoridagi fakultet, yo'nalish va semestr filtrlariga ko'ra ko'rsatiladi. "Tahrirlash" orqali dars soatlarini yangilang yoki "O'chirish" bilan fanni olib tashlang.
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
        let fanIndex = 0;
        let allSemestrOptions = [];
        let allYonalishOptions = [];
        let createdRowsById = {};
        let createdRowsAll = [];
        let createdRowsFiltered = [];
        let createdListPage = 1;
        let createdListPerPage = 20;
        let createdListDarsTurlari = [];
        const darsTurlariListDefault = <?php echo $darsSoatTurlariJson; ?>;
        const kafedralarList = <?php echo $kafedralarJson; ?>;
        const fanTypeLabels = {
            0: "Majburiy",
            1: "Tanlov",
            2: "Birlashtiriladigan",
            3: "Chet tili",
        };

        $(document).ready(function() {
            cacheSemestrOptions();
            cacheYonalishOptions();

            if ($.fn && typeof $.fn.select2 === 'function') {
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
            }
            
            initializeSelect2($('.reja-card:first'));

            $('#fakultetFilter').on('change', function() {
                filterYonalishByFakultet();
                filterSemestrByFilters();
            });

            $('#yonalishFilter').on('change', function() {
                filterSemestrByFilters();
            });

            $('#semestrSelect').on('change', function() {
                // Semestr tanlanganda forma ichidagi qiymat yangilanadi.
            });

            $('#applyTopFiltersBtn').on('click', function() {
                loadCreatedRejaList();
            });

            $('#resetTopFiltersBtn').on('click', function() {
                $('#fakultetFilter').val('').trigger('change.select2');
                filterYonalishByFakultet('');
                $('#yonalishFilter').val('').trigger('change.select2');
                filterSemestrByFilters('');
                $('#semestrSelect').val('').trigger('change.select2');
                loadCreatedRejaList();
            });

            $('#refreshCreatedRejaBtn').on('click', function() {
                loadCreatedRejaList();
            });

            $('#createdRejaSearchInput').on('input', function() {
                applyCreatedRejaTableFilters(true);
            });

            $('#createdRejaPerPage').on('change', function() {
                const val = parseInt($(this).val() || 20, 10);
                createdListPerPage = Number.isFinite(val) && val > 0 ? val : 20;
                applyCreatedRejaTableFilters(true);
            });

            $('#createdRejaPrevPage').on('click', function() {
                if (createdListPage <= 1) {
                    return;
                }
                createdListPage -= 1;
                renderCreatedRejaTableCurrentPage();
            });

            $('#createdRejaNextPage').on('click', function() {
                const total = createdRowsFiltered.length;
                const perPage = Math.max(1, createdListPerPage);
                const totalPages = Math.max(1, Math.ceil(total / perPage));
                if (createdListPage >= totalPages) {
                    return;
                }
                createdListPage += 1;
                renderCreatedRejaTableCurrentPage();
            });

            filterYonalishByFakultet();
            filterSemestrByFilters();
            loadCreatedRejaList();
            updateAllCardCalculators();
        });

        $(document).on('click', '.fanTypeToggle', function() {
            const btn = $(this);
            const card = btn.closest('.reja-card');
            const index = card.data('index');
            const type = parseInt(btn.data('type'), 10);
            // Izoh: Tanlov fan / Chet tili uchun soddalashtirilgan forma, qolganlari oddiy forma.
            if (type === 1 || type === 3) {
                switchToElective(card, index, type);
            } else {
                switchToMandatory(card, index, type);
            }

            initializeSelect2(card);
            updateCardCalculator(card);
        });

        // Izoh: Fan kodi va nomi input bo'lgani uchun select change handler kerak emas.

        function renderTypeButtons(index, activeType) {
            return `
                <div class="tanlovfan-actions">
                    <input type="hidden" name="tanlov_fan[${index}]" value="${activeType}" class="tanlov-input">
                    <!-- Izoh: Majburiy/Tanlov/Birlashtiriladigan fan tugmalari -->
                    <button type="button" class="btn btn-outline btn-sm fanTypeToggle ${activeType === 0 ? 'active' : ''}" data-type="0">
                        <i class="fas fa-book"></i> Majburiy fan
                    </button>
                    <button type="button" class="btn btn-outline btn-sm fanTypeToggle ${activeType === 1 ? 'active' : ''}" data-type="1">
                        <i class="fas fa-check-circle"></i> Tanlov fan
                    </button>
                    <button type="button" class="btn btn-outline btn-sm fanTypeToggle ${activeType === 3 ? 'active' : ''}" data-type="3">
                        <i class="fas fa-language"></i> Chet tili
                    </button>
                    <button type="button" class="btn btn-outline btn-sm fanTypeToggle ${activeType === 2 ? 'active' : ''}" data-type="2">
                        <i class="fas fa-graduation-cap"></i> Birlashtiriladigan fan
                    </button>
                </div>
            `;
        }

        function buildKafedralarOptionsHtml(selectedId = '') {
            const selected = String(selectedId || '');
            let html = '';
            (kafedralarList || []).forEach(item => {
                const id = String(item.id || '');
                const selectedAttr = id === selected ? ' selected' : '';
                html += `<option value="${escapeHtml(id)}"${selectedAttr}>${escapeHtml(item.name || '')}</option>`;
            });
            return html;
        }

        function buildAllDarsRowsHtml(index) {
            let html = '';
            (darsTurlariListDefault || []).forEach(item => {
                const id = String(item.id || '');
                const name = escapeHtml(item.name || '');
                html += `
                    <div class="form-grid-2 dars-soat-row">
                        <div class="form-group">
                            <label>Dars turi</label>
                            <input type="text" class="form-control" value="${name}" readonly tabindex="-1">
                            <input type="hidden" name="dars_turi[${index}][]" value="${escapeHtml(id)}">
                        </div>
                        <div class="form-group">
                            <label>Dars soati</label>
                            <input type="number" class="form-control" name="dars_soati[${index}][]" min="0">
                        </div>
                    </div>
                `;
            });
            return html;
        }

        function buildCalculatorSummaryHtml() {
            return `
                <div class="calc-summary">
                    <div class="form-group">
                        <label>Jami soat</label>
                        <input type="number" class="form-control jami-soat-input" value="0" readonly tabindex="-1">
                    </div>
                    <div class="form-group">
                        <label>Kredit</label>
                        <input type="number" class="form-control kredit-input" value="0" readonly tabindex="-1">
                    </div>
                    <div class="calc-summary-note" data-role="credit-note">30 soat = 1 kredit</div>
                </div>
            `;
        }

        function updateCardCalculator(card) {
            const soatInputs = card.find('input[name^="dars_soati["]');
            let totalHours = 0;

            soatInputs.each(function() {
                const raw = String($(this).val() ?? '').trim();
                if (raw === '') return;
                const value = Number(raw);
                if (Number.isFinite(value) && value > 0) {
                    totalHours += value;
                }
            });

            const credit = Math.round(totalHours / 30);
            card.find('.jami-soat-input').val(totalHours);
            card.find('.kredit-input').val(credit);

            const note = card.find('[data-role="credit-note"]');
            if (!note.length) return;

            if (totalHours > 0 && totalHours % 30 !== 0) {
                note
                    .text(`30 soat = 1 kredit. Joriy jami ${totalHours} soat, kredit round(${totalHours}/30) = ${credit}`)
                    .addClass('is-warning');
            } else {
                note
                    .text('30 soat = 1 kredit')
                    .removeClass('is-warning');
            }
        }

        function updateAllCardCalculators() {
            $('.reja-card').each(function() {
                updateCardCalculator($(this));
            });
        }

        function switchToMandatory(card, index, typeValue = 0) {
            const kafedralarOptions = buildKafedralarOptionsHtml('');

            const mandatoryHtml = `
                ${renderTypeButtons(index, typeValue)}
                
                <div class="form-grid-2">
                    <div class="form-group">
                        <label>Fan kodi</label>
                        <!-- Izoh: Majburiy/Birlashtiriladigan fan kodi inputdan kiritiladi -->
                        <input type="text" class="form-control fan-code-input" name="fan_code[${index}]" placeholder="Masalan: HIS1101" required>
                    </div>
                    <div class="form-group">
                        <label>Fan nomi</label>
                        <!-- Izoh: Majburiy/Birlashtiriladigan fan nomi inputdan kiritiladi -->
                        <input type="text" class="form-control fan-name-input" name="fan_nomi[${index}]" placeholder="Masalan: Hisob (Calculus) I-qism" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Kafedra</label>
                        <select class="form-control" name="kafedra_id[${index}]" required>
                            <option value="">Tanlang</option>
                            ${kafedralarOptions}
                        </select>
                    </div>
                </div>
                
                <div class="darsSoatWrapper">
                    ${buildAllDarsRowsHtml(index)}
                </div>

                ${buildCalculatorSummaryHtml()}
                
                <div class="reja-actions">
                    <button type="button" class="btn btn-outline btn-sm addReja">
                        <i class="fas fa-plus"></i> Yana fan
                    </button>

                    <button type="button" class="btn btn-danger btn-sm removeReja">
                        <i class="fas fa-times"></i> O'chirish
                    </button>
                </div>
            `;
            
            card.html(mandatoryHtml);
        }


        // Izoh: Tanlov fan / Chet tili uchun kod + nom va dars turi + dars soati qo'shiladi.
        function switchToElective(card, index, typeValue = 1) {
            const isLanguage = typeValue === 3;
            const isTanlov = typeValue === 1;
            const codeLabel = isLanguage ? 'Chet tili kodi' : 'Tanlov fan kodi';
            const nameLabel = isLanguage ? 'Chet tili nomi' : 'Tanlov fan nomi';
            const codePlaceholder = isLanguage ? 'Masalan: EN1' : 'Masalan: T1';
            const namePlaceholder = isLanguage ? 'Masalan: Ingliz tili' : 'Masalan: Oliy matematika';
            const codeInputClass = isTanlov ? 'tanlov-code-input' : '';

            const electiveHtml = `
                ${renderTypeButtons(index, typeValue)}

                <div class="form-grid-2">
                    <div class="form-group">
                        <label>${codeLabel}</label>
                        <input type="text" class="form-control ${codeInputClass}" name="tanlov_fan_code[${index}]" placeholder="${codePlaceholder}" required>
                    </div>
                    <div class="form-group">
                        <label>${nameLabel}</label>
                        <input type="text" class="form-control" name="tanlov_fan_nomi[${index}]" placeholder="${namePlaceholder}" required>
                    </div>
                </div>

                <div class="darsSoatWrapper">
                    ${buildAllDarsRowsHtml(index)}
                </div>

                ${buildCalculatorSummaryHtml()}

                <div class="reja-actions">
                    <button type="button" class="btn btn-outline btn-sm addReja">
                        <i class="fas fa-plus"></i> Yana fan
                    </button>

                    <button type="button" class="btn btn-danger btn-sm removeReja">
                        <i class="fas fa-times"></i> O'chirish
                    </button>
                </div>
            `;
            card.html(electiveHtml);
        }

        function cacheSemestrOptions() {
            allSemestrOptions = [];
            $('#semestrSelect option').each(function() {
                const val = String($(this).attr('value') || '');
                if (val === '') return;
                allSemestrOptions.push({
                    id: val,
                    text: $(this).text(),
                    fakultetId: String($(this).data('fakultet-id') || ''),
                    yonalishId: String($(this).data('yonalish-id') || ''),
                });
            });
        }

        function cacheYonalishOptions() {
            allYonalishOptions = [];
            $('#yonalishFilter option').each(function() {
                const val = String($(this).attr('value') || '');
                if (val === '') return;
                allYonalishOptions.push({
                    id: val,
                    text: $(this).text(),
                    fakultetId: String($(this).data('fakultet-id') || ''),
                });
            });
        }

        function rebuildYonalishOptions(selectedValue = '') {
            const selectedFakultet = String($('#fakultetFilter').val() || '');
            const select = $('#yonalishFilter');
            let html = "<option value=\"\">Barcha yo'nalishlar</option>";

            allYonalishOptions.forEach(item => {
                if (selectedFakultet !== '' && String(item.fakultetId) !== selectedFakultet) {
                    return;
                }
                const selected = String(item.id) === String(selectedValue) ? ' selected' : '';
                const dataAttr = item.fakultetId !== '' ? ` data-fakultet-id="${item.fakultetId}"` : '';
                html += `<option value="${item.id}"${dataAttr}${selected}>${escapeHtml(item.text)}</option>`;
            });

            select.html(html);
        }

        function filterYonalishByFakultet(selectedValue = null) {
            const currentYonalish = selectedValue !== null
                ? String(selectedValue || '')
                : String($('#yonalishFilter').val() || '');

            rebuildYonalishOptions(currentYonalish);
            const hasCurrent = $('#yonalishFilter option[value="' + currentYonalish + '"]').length > 0;

            if (!hasCurrent) {
                $('#yonalishFilter').val('').trigger('change.select2');
                return;
            }
            $('#yonalishFilter').val(currentYonalish).trigger('change.select2');
        }

        function rebuildSemestrOptions(selectedValue = '') {
            const selectedFakultet = String($('#fakultetFilter').val() || '');
            const selectedYonalish = String($('#yonalishFilter').val() || '');
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
                const fakultetAttr = item.fakultetId !== '' ? ` data-fakultet-id="${item.fakultetId}"` : '';
                const yonalishAttr = item.yonalishId !== '' ? ` data-yonalish-id="${item.yonalishId}"` : '';
                html += `<option value="${item.id}"${fakultetAttr}${yonalishAttr}${selected}>${escapeHtml(item.text)}</option>`;
            });

            select.html(html);
        }

        function filterSemestrByFilters(selectedValue = null) {
            const currentSemestr = String($('#semestrSelect').val() || '');
            const targetSemestr = selectedValue !== null ? String(selectedValue || '') : currentSemestr;
            rebuildSemestrOptions(targetSemestr);
            const hasCurrent = $('#semestrSelect option[value="' + targetSemestr + '"]').length > 0;
            if (!hasCurrent) {
                $('#semestrSelect').val('').trigger('change.select2');
                return;
            }
            $('#semestrSelect').val(targetSemestr).trigger('change.select2');
        }

        $(document).on('click', '.addReja', function() {
            const card = $(this).closest('.reja-card');
            const fanType = parseInt(card.find('.tanlov-input').val() || 0);
            
            fanIndex++;
            const newCard = $(`
                <div class="reja-card" data-index="${fanIndex}"></div>
            `);
            
            $('#rejaWrapper').append(newCard);

            // Izoh: Tanlov fan / Chet tili uchun soddalashtirilgan forma, qolganlari oddiy forma.
            if (fanType === 1 || fanType === 3) {
                switchToElective(newCard, fanIndex, fanType);
            } else {
                switchToMandatory(newCard, fanIndex, fanType);
            }
            
            initializeSelect2(newCard);
            updateCardCalculator(newCard);
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
                const oldIndex = $(this).data('index');
                $(this).data('index', newIndex);
                
                const card = $(this);
                // Izoh: Tanlov fan formasi va oddiy forma uchun name indekslari alohida.
                const fanType = parseInt(card.find('.tanlov-input').val() || 0);
                const isElective = fanType === 1 || fanType === 3;

                card.find('input[name^="tanlov_fan["]').attr('name', `tanlov_fan[${newIndex}]`);

                if (isElective) {
                    card.find('input[name^="tanlov_fan_code["]').attr('name', `tanlov_fan_code[${newIndex}]`);
                    card.find('input[name^="tanlov_fan_nomi["]').attr('name', `tanlov_fan_nomi[${newIndex}]`);
                } else {
                    card.find('input[name^="fan_code["]').attr('name', `fan_code[${newIndex}]`);
                    card.find('input[name^="fan_nomi["]').attr('name', `fan_nomi[${newIndex}]`);
                    card.find('select[name^="kafedra_id["]').attr('name', `kafedra_id[${newIndex}]`);
                }
                
                card.find('select[name^="dars_turi["]').attr('name', `dars_turi[${newIndex}][]`);
                card.find('input[name^="dars_soati["]').attr('name', `dars_soati[${newIndex}][]`);
            });
            updateAllCardCalculators();
        }

        function initializeSelect2(container) {
            if (!window.jQuery || !$.fn || typeof $.fn.select2 !== 'function') {
                return;
            }
            setTimeout(() => {
                container.find('select').each(function() {
                    const name = $(this).attr('name') || '';

                    if (name.startsWith('dars_turi')) return;

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

        function prepareDarsRowsForSubmit(formElement) {
            const tempDisabledFields = [];
            let hasInvalidRow = false;

            $(formElement).find('.dars-soat-row').each(function() {
                const typeSelect = $(this).find('[name^="dars_turi["]').first();
                const soatInput = $(this).find('input[name^="dars_soati["]').first();
                if (!typeSelect.length || !soatInput.length) {
                    return;
                }

                const rawSoat = String(soatInput.val() ?? '').trim();
                const soatValue = rawSoat === '' ? 0 : Number(rawSoat);

                if (rawSoat === '' || !Number.isFinite(soatValue) || soatValue <= 0) {
                    typeSelect.prop('disabled', true);
                    soatInput.prop('disabled', true);
                    tempDisabledFields.push(typeSelect[0], soatInput[0]);
                    if (typeSelect.is('select')) {
                        typeSelect.removeClass('is-invalid');
                    }
                    return;
                }

                if (String(typeSelect.val() || '').trim() === '') {
                    hasInvalidRow = true;
                    if (typeSelect.is('select')) {
                        typeSelect.addClass('is-invalid');
                        if (document.activeElement !== typeSelect[0]) {
                            typeSelect.trigger('focus');
                        }
                    }
                } else {
                    if (typeSelect.is('select')) {
                        typeSelect.removeClass('is-invalid');
                    }
                }
            });

            return { tempDisabledFields, hasInvalidRow };
        }

        function restoreTemporarilyDisabledFields(fields) {
            (fields || []).forEach(field => {
                if (field) field.disabled = false;
            });
        }

        const SwalApi = window.Swal || {
            mixin: () => ({ fire: () => {} }),
            fire: () => Promise.resolve({ isConfirmed: false }),
            showValidationMessage: () => {},
        };

        const Toast = SwalApi.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
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

            if (!parts.length) return '-';
            return `<ul class="compact-list">${parts.map(p => `<li>${escapeHtml(p)}</li>`).join('')}</ul>`;
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
                tbody.html('<tr><td colspan="7">Tanlangan filter bo\'yicha fan topilmadi</td></tr>');
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
            const fakultetId = $('#fakultetFilter').val() || '';
            const yonalishId = $('#yonalishFilter').val() || '';
            const semestrId = $('#semestrSelect').val() || '';
            const url = `api/get_oquv_reja_created_list.php?fakultet_id=${encodeURIComponent(fakultetId)}&yonalish_id=${encodeURIComponent(yonalishId)}&semestr_id=${encodeURIComponent(semestrId)}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (!data || !data.success) {
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
            kafedralarList.forEach(k => {
                const id = String(k.id || '');
                const sel = selected === id ? ' selected' : '';
                html += `<option value="${id}"${sel}>${escapeHtml(k.name || '')}</option>`;
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

        $(document).on('click', '.editCreatedRejaBtn', function() {
            const fanId = String($(this).data('fan-id') || '');
            const row = createdRowsById[fanId];
            if (!row) return;

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
                        if (Number.isNaN(value) || value < 0) value = 0;
                        dars[darsTurId] = value;
                        if (value > 0) hasPositive = true;
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
                if (!result.isConfirmed || !result.value) return;
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
            if (!row) return;

            SwalApi.fire({
                title: "Fanni o'chirishni tasdiqlaysizmi?",
                text: `${row.fan_code || ''} - ${row.fan_name || ''}`,
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ha, o'chirilsin",
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
                        Toast.fire({
                            icon: 'success',
                            title: data.message || "Fan o'chirildi"
                        });
                        loadCreatedRejaList();
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

        $('#oquvRejaForm').on('submit', function(e) {
            e.preventDefault();

            const { tempDisabledFields, hasInvalidRow } = prepareDarsRowsForSubmit(this);
            if (hasInvalidRow) {
                restoreTemporarilyDisabledFields(tempDisabledFields);
                Toast.fire({
                    icon: 'error',
                    title: "Dars soati kiritilgan qatorlarda dars turi tanlanishi shart"
                });
                return;
            }

            const formData = new FormData(this);
            restoreTemporarilyDisabledFields(tempDisabledFields);
            const selectedFakultet = $('#fakultetFilter').val() || '';
            const selectedYonalish = $('#yonalishFilter').val() || '';
            const selectedSemestr = $('#semestrSelect').val() || '';
            
            fetch('insert/add_oquv_reja.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Toast.fire({
                        icon: 'success',
                        title: data.message || 'Oquv reja muvaffaqiyatli saqlandi'
                    });

                    this.reset();
                    $('#fakultetFilter').val(selectedFakultet).trigger('change.select2');
                    filterYonalishByFakultet(selectedYonalish);
                    $('#yonalishFilter').val(selectedYonalish).trigger('change.select2');
                    filterSemestrByFilters(selectedSemestr);
                    $('#semestrSelect').val(selectedSemestr).trigger('change.select2');
                    
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
                    switchToMandatory(firstCard, 0, 0);
                    initializeSelect2(firstCard);
                    updateCardCalculator(firstCard);
                    loadCreatedRejaList();
                    
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

        $(document).on('input', 'input[name^="dars_soati["]', function() {
            updateCardCalculator($(this).closest('.reja-card'));
        });

        updateAllCardCalculators();
    </script>
</body>
</html>
