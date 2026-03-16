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
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label>Fakultet filtri</label>
                            <select class="form-control" id="fakultetFilter">
                                <option value="">Barcha fakultetlar</option>
                                <?php foreach ($fakultetlar as $f): ?>
                                    <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semestr</label>
                            <select class="form-control" name="semestr_id" id="semestrSelect" required>
                                <option value="">Tanlang</option>
                                    <?php foreach ($semestrlar as $s): 
                                        $short = '';
                                        $words = preg_split('/\s+/u', trim($s['yonalish_name']));
                                        foreach ($words as $w) {
                                            $short .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
                                        }
                                        $daraja = mb_strtolower(trim($s['akademik_daraja_name'] ?? ''), 'UTF-8');
                                        $darajaPrefix = '';
                                        if (strpos($daraja, 'magistr') !== false) {
                                            $darajaPrefix = 'M ';
                                        } elseif (strpos($daraja, 'bakalavr') !== false) {
                                            $darajaPrefix = 'B ';
                                        }
                                        $fakultetId = (int)($s['yonalish_fakultet_id'] ?? ($s['fakultet_id'] ?? 0));
                                    ?>
                                    <option value="<?= $s['id'] ?>" data-fakultet-id="<?= $fakultetId ?>">
                                        <?= $darajaPrefix . $short . '_' . $s['kirish_yili'] . ' - ' . $s['semestr'] . '-semestr'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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
                                                <?= htmlspecialchars($k['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="darsSoatWrapper">
                                <div class="form-grid-2 dars-soat-row">
                                    <div class="form-group">
                                        <label>Dars turi</label>
                                        <select class="form-control" name="dars_turi[0][]" required>
                                            <option value="">Tanlang</option>
                                            <?php foreach ($dars_soat_turlari as $d): ?>
                                                <option value="<?= $d['id'] ?>">
                                                    <?= htmlspecialchars($d['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label>Dars soati</label>
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
                        Ro'yxat yuqoridagi fakultet va semestr filtriga ko'ra ko'rsatiladi. "Tahrirlash" orqali dars soatlarini yangilang.
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
    <link href="../assets/vendor/select2/css/select2.min.css" rel="stylesheet" />

    <script src="../assets/vendor/jquery/jquery-3.6.0.min.js"></script>
    <script src="../assets/vendor/select2/js/select2.min.js"></script>

    <script>
        let fanIndex = 0;
        let allSemestrOptions = [];
        let createdRowsById = {};
        const darsTurlariListDefault = <?php echo json_encode($dars_soat_turlari, JSON_UNESCAPED_UNICODE); ?>;
        const kafedralarList = <?php echo json_encode($kafedralar, JSON_UNESCAPED_UNICODE); ?>;
        const fanTypeLabels = {
            0: "Majburiy",
            1: "Tanlov",
            2: "Birlashtiriladigan",
            3: "Chet tili",
        };

        $(document).ready(function() {
            cacheSemestrOptions();

            $('#fakultetFilter').select2({
                placeholder: "Fakultetni tanlang",
                allowClear: true,
                width: '100%',
            });
            $('#semestrSelect').select2({
                placeholder: "Semestrni tanlang",
                allowClear: true,
                width: '100%',
            });
            
            initializeSelect2($('.reja-card:first'));

            $('#fakultetFilter').on('change', function() {
                filterSemestrByFakultet();
                loadCreatedRejaList();
            });

            $('#semestrSelect').on('change', function() {
                loadCreatedRejaList();
            });

            $('#refreshCreatedRejaBtn').on('click', function() {
                loadCreatedRejaList();
            });

            filterSemestrByFakultet();
            loadCreatedRejaList();
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

        function switchToMandatory(card, index, typeValue = 0) {
            const kafedralarOptions = `<?php foreach ($kafedralar as $k): ?>
                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['name']) ?></option>
            <?php endforeach; ?>`;
            
            const darsTurlariOptions = `<?php foreach ($dars_soat_turlari as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>`;

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
                    <div class="form-grid-2 dars-soat-row">
                        <div class="form-group">
                            <label>Dars turi</label>
                            <select class="form-control" name="dars_turi[${index}][]" required>
                                <option value="">Tanlang</option>
                                ${darsTurlariOptions}
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Dars soati</label>
                            <input type="number"
                                class="form-control"
                                name="dars_soati[${index}][]"
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
                    <div class="form-grid-2 dars-soat-row">
                        <div class="form-group">
                            <label>Dars turi</label>
                            <select class="form-control" name="dars_turi[${index}][]" required>
                                <option value="">Tanlang</option>
                                <?php foreach ($dars_soat_turlari as $d): ?>
                                    <option value="<?= $d['id'] ?>">
                                        <?= htmlspecialchars($d['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Dars soati</label>
                            <input type="number"
                                class="form-control"
                                name="dars_soati[${index}][]"
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
                });
            });
        }

        function rebuildSemestrOptions(selectedValue = '') {
            const selectedFakultet = String($('#fakultetFilter').val() || '');
            const select = $('#semestrSelect');
            let html = '<option value="">Tanlang</option>';

            allSemestrOptions.forEach(item => {
                if (selectedFakultet !== '' && String(item.fakultetId) !== selectedFakultet) {
                    return;
                }
                const selected = String(item.id) === String(selectedValue) ? ' selected' : '';
                const dataAttr = item.fakultetId !== '' ? ` data-fakultet-id="${item.fakultetId}"` : '';
                html += `<option value="${item.id}"${dataAttr}${selected}>${escapeHtml(item.text)}</option>`;
            });

            select.html(html);
        }

        function filterSemestrByFakultet() {
            const currentSemestr = String($('#semestrSelect').val() || '');
            rebuildSemestrOptions(currentSemestr);
            const hasCurrent = $('#semestrSelect option[value="' + currentSemestr + '"]').length > 0;
            if (!hasCurrent) {
                $('#semestrSelect').val('').trigger('change.select2');
                return;
            }
            $('#semestrSelect').val(currentSemestr).trigger('change.select2');
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
        });

        $(document).on('click', '.addDarsSoat', function() {
            const card = $(this).closest('.reja-card');
            const wrapper = $(this).closest('.darsSoatWrapper');
            const index = card.data('index');
            
            const darsTurlariOptions = `<?php foreach ($dars_soat_turlari as $d): ?>
                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>`;
            
            const newRow = $(`
                <div class="form-grid-2 dars-soat-row">
                    <div class="form-group">
                        <label>Dars turi</label>
                        <select class="form-control" name="dars_turi[${index}][]" required>
                            <option value="">Tanlang</option>
                            ${darsTurlariOptions}
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Dars soati</label>
                        <input type="number"
                            class="form-control"
                            name="dars_soati[${index}][]"
                            min="0"
                            required>
                    </div>
                </div>
            `);
            
            newRow.insertBefore(wrapper.find('.dars-soat-actions'));
        });

        $(document).on('click', '.removeDarsSoat', function() {
            const wrapper = $(this).closest('.darsSoatWrapper');
            const rows = wrapper.find('.dars-soat-row');
            
            if (rows.length > 1) {
                rows.last().remove();
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
        }

        function initializeSelect2(container) {
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

        const Toast = Swal.mixin({
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

        function renderCreatedRejaTable(rows, darsTurlari) {
            const tbody = $('#createdRejaTableBody');
            const countBadge = $('#createdRejaCount');
            countBadge.text(`${rows.length} ta`);

            if (!rows.length) {
                tbody.html('<tr><td colspan="7">Tanlangan filter bo‘yicha fan topilmadi</td></tr>');
                return;
            }

            let html = '';
            rows.forEach(row => {
                const fanTypeLabel = fanTypeLabels[parseInt(row.tanlov_fan || 0, 10)] || 'Noma\'lum';
                const semestrLabel = `${row.yonalish_name || '-'} - ${row.kirish_yili || '-'} / ${row.semestr_num || '-'}`;

                html += `
                    <tr>
                        <td>${escapeHtml(row.fan_code || '-')}</td>
                        <td>${escapeHtml(row.fan_name || '-')}</td>
                        <td>${escapeHtml(fanTypeLabel)}</td>
                        <td>${escapeHtml(row.kafedra_name || '-')}</td>
                        <td>${renderDarsSummary(row, darsTurlari)}</td>
                        <td>${escapeHtml(semestrLabel)}</td>
                        <td>
                            <button type="button" class="btn btn-outline btn-sm editCreatedRejaBtn" data-fan-id="${row.fan_id}">
                                <i class="fas fa-pen"></i> Tahrirlash
                            </button>
                        </td>
                    </tr>
                `;
            });

            tbody.html(html);
        }

        function loadCreatedRejaList() {
            const fakultetId = $('#fakultetFilter').val() || '';
            const semestrId = $('#semestrSelect').val() || '';
            const url = `api/get_oquv_reja_created_list.php?fakultet_id=${encodeURIComponent(fakultetId)}&semestr_id=${encodeURIComponent(semestrId)}`;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if (!data || !data.success) {
                        $('#createdRejaTableBody').html('<tr><td colspan="7">Ro\'yxatni yuklab bo\'lmadi</td></tr>');
                        $('#createdRejaCount').text('0 ta');
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

                    renderCreatedRejaTable(rows, darsTurlari);
                })
                .catch(() => {
                    $('#createdRejaTableBody').html('<tr><td colspan="7">Server bilan bog\'lanib bo\'lmadi</td></tr>');
                    $('#createdRejaCount').text('0 ta');
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
            Swal.fire({
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
                        Swal.showValidationMessage("Fan kodi va fan nomi to'ldirilishi shart");
                        return false;
                    }

                    const lockKafedra = parseInt(row.kafedra_lock || 0, 10) === 1;
                    const kafedraId = lockKafedra ? parseInt(row.kafedra_id || 0, 10) : parseInt(kafedraVal || 0, 10);
                    if (!lockKafedra && kafedraId <= 0) {
                        Swal.showValidationMessage("Kafedrani tanlang");
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
                        Swal.showValidationMessage("Kamida bitta dars soati 0 dan katta bo'lishi kerak");
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

        $('#oquvRejaForm').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const selectedFakultet = $('#fakultetFilter').val() || '';
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
                    filterSemestrByFakultet();
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
    </script>
</body>
</html>
