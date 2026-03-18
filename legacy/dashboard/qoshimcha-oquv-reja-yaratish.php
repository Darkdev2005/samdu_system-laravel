<?php

    include_once 'config.php';
    $db = new Database();
    $semestrlar = $db->get_semestrlar();
    $fakultetlar = $db->get_data_by_table_all('fakultetlar', 'ORDER BY name');
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
    // Izoh: Fanlar ro'yxati (kod + nom) va auditoriya soatlari selectda chiqishi uchun.
    $fanOptions = [];
    $fanSql = "
        SELECT
            f.id,
            f.semestr_id,
            f.fan_code,
            f.fan_name,
            COALESCE(SUM(CASE WHEN dst.id IN (1,2,3,4) THEN o.dars_soat ELSE 0 END), 0) AS auditoriya_soat
        FROM fanlar f
        LEFT JOIN oquv_rejalar o ON o.fan_id = f.id
        LEFT JOIN dars_soat_turlar dst ON dst.id = o.dars_tur_id
        GROUP BY f.id, f.semestr_id, f.fan_code, f.fan_name
    ";
    $fanResult = $db->query($fanSql);
    while ($row = mysqli_fetch_assoc($fanResult)) {
        $semestrId = (int) ($row['semestr_id'] ?? 0);
        $code = trim($row['fan_code'] ?? '');
        $name = trim($row['fan_name'] ?? '');
        if ($semestrId <= 0 || $name === '') {
            continue;
        }
        $label = $code !== '' ? ($code . ' - ' . $name) : $name;
        $fanOptions[] = [
            'semestr_id' => $semestrId,
            'value' => (int) $row['id'],
            'label' => $label,
            'auditoriya_soat' => (float) $row['auditoriya_soat']
        ];
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
                                <option value="">Barcha yo'nalishlar</option>
                                <?php foreach ($filterYonalishlar as $y): ?>
                                    <option
                                        value="<?= (int)$y['id'] ?>"
                                        data-fakultet-id="<?= (int)$y['fakultet_id'] ?>"
                                    >
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
                                    $fakultetId = (int)($s['yonalish_fakultet_id'] ?? ($s['fakultet_id'] ?? 0));
                                    $yonalishId = (int)($s['yonalish_id'] ?? 0);
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

        let fanIndex = 0;
        const fanOptions = <?php echo json_encode($fanOptions, JSON_UNESCAPED_UNICODE); ?>;
        const fanMap = {};
        fanOptions.forEach(f => {
            fanMap[String(f.value)] = f;
        });

        let allSemestrOptions = [];
        let allYonalishOptions = [];

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

        function escapeOptionText(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function setSelectValueAndSync($select, value) {
            $select.val(value);
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.trigger('change.select2');
            } else {
                $select.trigger('change');
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
            const selectedFakultet = String($('#fakultetFilter').val() || '');
            const select = $('#yonalishFilter');
            let html = "<option value=\"\">Barcha yo'nalishlar</option>";

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
            const currentYonalish = selectedValue !== null
                ? String(selectedValue || '')
                : String($('#yonalishFilter').val() || '');

            rebuildYonalishOptions(currentYonalish);
            const hasCurrent = $('#yonalishFilter option[value="' + currentYonalish + '"]').length > 0;

            if (!hasCurrent) {
                setSelectValueAndSync($('#yonalishFilter'), '');
                return;
            }
            setSelectValueAndSync($('#yonalishFilter'), currentYonalish);
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
                html += `<option value="${item.id}" data-fakultet-id="${item.fakultetId}" data-yonalish-id="${item.yonalishId}" data-talaba="${escapeOptionText(item.talaba)}" data-talim-shakli="${escapeOptionText(item.talimShakli)}" data-talim-shakli-id="${escapeOptionText(item.talimShakliId)}" data-daraja="${escapeOptionText(item.daraja)}" data-patok="${escapeOptionText(item.patok)}" data-guruh="${escapeOptionText(item.guruh)}"${selected}>${escapeOptionText(item.text)}</option>`;
            });

            select.html(html);
        }

        function filterSemestrByFilters(selectedValue = null) {
            const currentSemestr = String($('#semestrSelect').val() || '');
            const targetSemestr = selectedValue !== null ? String(selectedValue || '') : currentSemestr;

            rebuildSemestrOptions(targetSemestr);

            const hasCurrent = $('#semestrSelect option[value="' + targetSemestr + '"]').length > 0;
            if (!hasCurrent) {
                setSelectValueAndSync($('#semestrSelect'), '');
                return;
            }
            setSelectValueAndSync($('#semestrSelect'), targetSemestr);
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

            switch (qoshimchaId) {
                case QOSHIMCHA_IDS.ORALIQ:
                    if (isExternal) {
                        fanSoat = 0;
                        hintText = "Sirtqi/masofaviy/kechki: 0";
                    } else if (auditoriyaSoat >= 60) {
                        fanSoat = Math.round(semestrMeta.talaba * 0.4);
                        hintText = `${semestrMeta.talaba} × 0.4 = ${fanSoat}`;
                    } else if (auditoriyaSoat >= 30) {
                        fanSoat = Math.round(semestrMeta.talaba * 0.2);
                        hintText = `${semestrMeta.talaba} × 0.2 = ${fanSoat}`;
                    } else {
                        fanSoat = 0;
                        hintText = "Auditoriya soat 30 dan kam: 0";
                    }
                    break;
                case QOSHIMCHA_IDS.YAKUNIY: {
                    const isTest = card.find('.yakuniy-test').is(':checked');
                    fanSoat = isTest ? 0 : Math.round(semestrMeta.talaba * 0.3);
                    hintText = isTest ? "Yakuniy test: 0" : `${semestrMeta.talaba} × 0.3 = ${fanSoat}`;
                    break;
                }
                case QOSHIMCHA_IDS.KURS_ISHI:
                    fanSoat = Math.round(semestrMeta.talaba * 2.4);
                    hintText = `${semestrMeta.talaba} × 2.4 = ${fanSoat}`;
                    break;
                case QOSHIMCHA_IDS.KURS_LOYIHA:
                    fanSoat = Math.round(semestrMeta.talaba * 3.6);
                    hintText = `${semestrMeta.talaba} × 3.6 = ${fanSoat}`;
                    break;
                case QOSHIMCHA_IDS.UZLUKSIZ_MALAKA:
                    fanSoat = Math.round(semestrMeta.talaba * (isExternal ? 0.4 : 2));
                    hintText = `${semestrMeta.talaba} × ${isExternal ? 0.4 : 2} = ${fanSoat}`;
                    break;
                case QOSHIMCHA_IDS.OQUV_PED:            
                case QOSHIMCHA_IDS.DALA_OTM:
                case QOSHIMCHA_IDS.DALA_TASH:
                case QOSHIMCHA_IDS.ISHLAB_CHIQARISH: {
                    const hafta = parseFloat(card.find('.hafta-input').val()) || 0;
                    const guruh = semestrMeta.guruh || 0;
                    const perWeek = qoshimchaId === QOSHIMCHA_IDS.DALA_TASH ? 30 : 18;
                    fanSoat = Math.round(guruh * hafta * perWeek);
                    hintText = `${guruh} × ${hafta} × ${perWeek} = ${fanSoat}`;
                    break;
                }
                case QOSHIMCHA_IDS.BMI: {
                    const isTech = card.find('.bmi-tech').is(':checked');
                    const per = isTech ? 30 : 25;
                    fanSoat = Math.round(semestrMeta.talaba * per);
                    hintText = `${semestrMeta.talaba} × ${per} = ${fanSoat}`;
                    break;
                }
                case QOSHIMCHA_IDS.OCHIQ_DARS: {
                    const count = parseInt(card.find('.ochiq-count').val(), 10) || 1;
                    fanSoat = count * 10;
                    hintText = `${count} × 10 = ${fanSoat}`;
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
                fanSoatInput.val('');
                fanSoatInput.prop('readonly', !auto ? false : true);
                setCalcHint(card, '');
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

            initSelect2Safe($('#fakultetFilter'), "Fakultetni tanlang");
            initSelect2Safe($('#yonalishFilter'), "Yo'nalishni tanlang");
            initSelect2Safe($('#semestrSelect'), "Semestrni tanlang");
            
            initInitialSelect2();

            // Dastlabki holatni to'g'ri bog'lash
            filterYonalishByFakultet($('#yonalishFilter').val() || '');
            filterSemestrByFilters($('#semestrSelect').val() || '');

            $('#fakultetFilter').on('change', function() {
                filterYonalishByFakultet();
                filterSemestrByFilters('');
            });

            $('#yonalishFilter').on('change', function() {
                filterSemestrByFilters('');
            });

            $('#applyFiltersBtn').on('click', function() {
                filterYonalishByFakultet();
                filterSemestrByFilters();
            });

            $('#resetFiltersBtn').on('click', function() {
                setSelectValueAndSync($('#fakultetFilter'), '');
                setSelectValueAndSync($('#yonalishFilter'), '');
                setSelectValueAndSync($('#semestrSelect'), '');
                filterYonalishByFakultet('');
                filterSemestrByFilters('');
            });
        });

        $('#semestrSelect').on('change', function() {
            $('.reja-card').each(function() {
                renderFanOptions($(this));
                calculateForSingleCard($(this));
            });
        });
        
        function initInitialSelect2() {
            initSelect2Safe($('select[name="qoshimcha_dars_id[]"]'), "Qo'shimcha dars turini tanlang");
            initSelect2Safe($('select[name="fan_nomi[]"]'), "Fan (kod + nomi) tanlang");
            initSelect2Safe($('select[name="kafedra_id[0][]"]'), "Kafedrani tanlang");

            renderFanOptions($('.reja-card:first'));
        }

        function renderFanOptions(card) {
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

            const items = fanOptions.filter(f => String(f.semestr_id) === String(semestrId));
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
                                <select class="form-control kafedra-select" name="kafedra_id[${fanIndex}][]" required>
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
                            <?php foreach ($kafedralar as $k): ?>
                                <option value="<?= $k['id'] ?>">
                                    <?= htmlspecialchars($k['name']) ?>
                                </option>
                            <?php endforeach; ?>
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
                Swal.fire({
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
        
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
    </script>
</body>
</html>
