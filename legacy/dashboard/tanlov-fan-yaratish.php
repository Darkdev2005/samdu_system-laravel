<?php
    // Izoh: Tanlov fan yaratish - o'quv reja sahifasida yaratilgan tanlov fan (kod + nom) selectda chiqadi.
    // Izoh: Selectdan olingan kod hidden `tanlov_fan_code` ga yoziladi, nomi esa `tanlov_fan_base_nomi` ga.
    include_once 'config.php';
    $db = new Database();
    $semestrlar = $db->get_semestrlar();
    $dars_soat_turlari = $db->get_data_by_table_all('dars_soat_turlar');
    $kafedralar = $db->get_data_by_table_all('kafedralar');
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
            const semestrId = $('#semestrSelect').val();
            renderTanlovOptions($('.reja-card:first').find('.tanlov-fan-select'), semestrId);
        });

        // Izoh: Semestr tanlanganda tanlov fanlar ro'yxatini yangilash.
        $('#semestrSelect').on('change', function() {
            const semestrId = $(this).val();
            $('.tanlov-fan-select').each(function() {
                renderTanlovOptions($(this), semestrId);
            });
        });

        const kafedralarOptions = `<?php foreach ($kafedralar as $k): ?>
            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['name']) ?></option>
        <?php endforeach; ?>`;

        const darsTurlariOptions = `<?php foreach ($dars_soat_turlari as $d): ?>
            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
        <?php endforeach; ?>`;
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
                                ${kafedralarOptions}
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
            const semestrId = $('#semestrSelect').val();
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
                                ${kafedralarOptions}
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

        $(document).on('click', '.addDarsSoat', function() {
            const card = $(this).closest('.reja-card');
            const wrapper = $(this).closest('.darsSoatWrapper');
            const index = card.data('index');
            
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
                $(this).data('index', newIndex);
                const card = $(this);
                
                card.find('input[name^="tanlov_fan["]').attr('name', `tanlov_fan[${newIndex}]`);
                // Izoh: Tanlov fan select va input nomlarini indeks bo'yicha yangilash.
                card.find('input[name^="tanlov_fan_code["]').attr('name', `tanlov_fan_code[${newIndex}]`);
                card.find('input[name^="tanlov_fan_base_nomi["]').attr('name', `tanlov_fan_base_nomi[${newIndex}]`);
                card.find('select[name^="tanlov_fan_base["]').attr('name', `tanlov_fan_base[${newIndex}]`);
                card.find('input[name^="tanlov_fan_nomi["]').attr('name', `tanlov_fan_nomi[${newIndex}][]`);
                card.find('select[name^="tanlov_kafedra_id["]').attr('name', `tanlov_kafedra_id[${newIndex}][]`);
                card.find('select[name^="dars_turi["]').attr('name', `dars_turi[${newIndex}][]`);
                card.find('input[name^="dars_soati["]').attr('name', `dars_soati[${newIndex}][]`);
            });
        }

        function initializeSelect2(container) {
            setTimeout(() => {
                container.find('select').each(function() {
                    const name = $(this).attr('name') || '';

                    if (name.startsWith('dars_turi')) return;

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

