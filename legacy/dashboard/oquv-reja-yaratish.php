<?php
    // Izoh: Bu sahifada Majburiy, Tanlov, Chet tili va Birlashtiriladigan fanlar yaratiladi.
    // Izoh: Majburiy/Birlashtiriladigan fanlarda fan kodi va fan nomi inputdan kiritiladi.

    include_once 'config.php';
    $db = new Database();
    $semestrlar = $db->get_semestrlar();
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
                                    ?>
                                    <option value="<?= $s['id'] ?>">
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
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        let fanIndex = 0;

        $(document).ready(function() {
            $('#semestrSelect').select2({
                placeholder: "Semestrni tanlang",
                allowClear: true,
                width: '100%',
            });
            
            initializeSelect2($('.reja-card:first'));
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

        $('#oquvRejaForm').on('submit', function(e) {
            e.preventDefault();
            
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
                        title: data.message || 'Oquv reja muvaffaqiyatli saqlandi'
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
                    switchToMandatory(firstCard, 0, 0);
                    initializeSelect2(firstCard);
                    
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
