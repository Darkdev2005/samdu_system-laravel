<?php
include_once 'config.php';
$db = new Database();
$semestrlar = $db->get_semestrlar();
$fakultetlar = $db->get_data_by_table_all('fakultetlar', 'ORDER BY name');
$yonalishlar = $db->get_data_by_table_all('yonalishlar');
$darsTurlari = $db->get_data_by_table_all('qoshimcha_dars_turlar', 'WHERE id IN (9, 10, 11, 12, 13, 14) ORDER BY id');
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
    if ($yId > 0) {
        $yonalishFakultetMap[$yId] = (int)($yRow['fakultet_id'] ?? 0);
    }
}

$filterYonalishlarMap = [];
foreach ($semestrlar as $s) {
    $yonalishId = (int)($s['yonalish_id'] ?? 0);
    if ($yonalishId <= 0 || isset($filterYonalishlarMap[$yonalishId])) {
        continue;
    }
    $fakultetId = (int)($yonalishFakultetMap[$yonalishId] ?? 0);
    if ($fakultetId <= 0) {
        $fakultetId = (int)($s['yonalish_fakultet_id'] ?? ($s['fakultet_id'] ?? 0));
    }
    $filterYonalishlarMap[$yonalishId] = [
        'id' => $yonalishId,
        'name' => (string)($s['yonalish_name'] ?? ''),
        'kirish_yili' => (string)($s['kirish_yili'] ?? ''),
        'fakultet_id' => $fakultetId,
    ];
}
$filterYonalishlar = array_values($filterYonalishlarMap);
usort($filterYonalishlar, static function (array $a, array $b): int {
    $nameCmp = strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
    return $nameCmp !== 0 ? $nameCmp : strcmp((string)($a['kirish_yili'] ?? ''), (string)($b['kirish_yili'] ?? ''));
});
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Magistr/Doktorant qo'shimcha reja</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .top-filters-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(220px, 1fr));
            gap: 12px;
        }
        .top-filter-actions,
        .table-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }
        .info-note {
            margin-top: 8px;
            color: #64748b;
            font-size: 13px;
        }
        @media (max-width: 900px) {
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
                <h1>Magistr/Doktorant qo'shimcha reja</h1>
            </header>
            <div class="content-container">
                <div class="card" style="display:none;">
                    <h3 class="section-title">Filter</h3>
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
                                    <option value="<?= (int)$y['id'] ?>" data-fakultet-id="<?= (int)$y['fakultet_id'] ?>">
                                        <?= $h((string)$y['name'] . (!empty($y['kirish_yili']) ? ' - ' . (string)$y['kirish_yili'] : '')) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Semestr</label>
                            <select class="form-control" id="semestrSelect" required>
                                <option value="">Semestrni tanlang</option>
                                <?php foreach ($semestrlar as $s):
                                    $short = $makeShortCode((string)($s['yonalish_name'] ?? ''));
                                    $darajaRaw = trim((string)($s['akademik_daraja_name'] ?? ''));
                                    $daraja = function_exists('mb_strtolower') ? (string)@mb_strtolower($darajaRaw, 'UTF-8') : strtolower($darajaRaw);
                                    $darajaPrefix = '';
                                    if (strpos($daraja, 'magistr') !== false) {
                                        $darajaPrefix = 'M ';
                                    } elseif (strpos($daraja, 'bakalavr') !== false) {
                                        $darajaPrefix = 'B ';
                                    }
                                    $fakultetId = (int)($s['yonalish_fakultet_id'] ?? ($s['fakultet_id'] ?? 0));
                                    $yonalishId = (int)($s['yonalish_id'] ?? 0);
                                ?>
                                    <option value="<?= (int)$s['id'] ?>" data-fakultet-id="<?= $fakultetId ?>" data-yonalish-id="<?= $yonalishId ?>">
                                        <?= $h($darajaPrefix . $short . '_' . ($s['kirish_yili'] ?? '') . ' - ' . ($s['semestr'] ?? '') . '-semestr') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="top-filter-actions mt-3">
                        <button type="button" class="btn btn-primary btn-sm" id="applyFiltersBtn">
                            <i class="fas fa-filter"></i> Filtrlash
                        </button>
                        <button type="button" class="btn btn-secondary btn-sm" id="resetFiltersBtn">
                            <i class="fas fa-rotate-left"></i> Tozalash
                        </button>
                    </div>
                </div>

                <div class="card mt-4">
                    <h3 class="section-title">Qo'shimcha reja kiritish</h3>
                    <div class="info-note">
                        Avval "Magistr/Doktorant kiritish" bo'limida shaxsni ro'yxatga oling. Bu yerda esa unga Magistratura/Doktorantura ustunlari uchun dars turi va soat beriladi.
                    </div>
                    <form id="magDokQoshimchaForm" class="mt-3">
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>Magistr/Doktorant</label>
                                <select class="form-control" name="person_id" id="personSelect" required>
                                    <option value="">Magistr/Doktorantni tanlang</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Dars turi</label>
                                <select class="form-control" name="qoshimcha_dars_id" id="darsTuriSelect" required>
                                    <option value="">Dars turini tanlang</option>
                                    <?php foreach ($darsTurlari as $tur): ?>
                                        <?php $turId = (int)$tur['id']; ?>
                                        <option value="<?= $turId ?>" data-turi="<?= $turId >= 12 ? 'doktorant' : 'magistr' ?>"><?= $h($tur['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Dars soati</label>
                                <input type="number" class="form-control" name="dars_soati" min="1" step="1" placeholder="Masalan: 25" required>
                            </div>
                            <div class="form-group">
                                <label>Izoh</label>
                                <input type="text" class="form-control" name="izoh" placeholder="Ixtiyoriy izoh">
                            </div>
                        </div>
                        <div class="form-actions mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Saqlash
                            </button>
                        </div>
                    </form>
                </div>

                <div class="card mt-4">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Kiritilgan qo'shimcha rejalar</h3>
                            <span class="badge" id="qoshimchaCount">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <button type="button" class="btn btn-outline btn-sm" id="refreshBtn">
                                <i class="fas fa-rotate"></i> Yangilash
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive mt-2">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Turi</th>
                                    <th>Dars turi</th>
                                    <th>Kod</th>
                                    <th>Ism familiyasi</th>
                                    <th>Kafedra</th>
                                    <th>Kirgan yili</th>
                                    <th>Dars soati</th>
                                    <th>Kurs</th>
                                    <th>Harakat</th>                  
      
                              </tr>
                            </thead>
                            <tbody id="qoshimchaTableBody">
                                <tr>
                                    <td colspan="9">Yuklanmoqda...</td>
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
    <script src="../assets/js/app.js"></script>
    <script>
        const allYonalishOptions = [];
        const allSemestrOptions = [];
        let qoshimchaRowsById = {};
        const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, timerProgressBar: true });

        function escapeHtml(value) {
            return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }
        function getKursLabel(row) {
            const kurs = parseInt(row.kurs || 0, 10) || 0;
            return kurs > 0 ? `${kurs}-kurs` : '-';
        }
        function getKirishYiliLabel(row) {
            const year = parseInt(row.kirish_yili || 0, 10) || 0;
            return year > 0 ? String(year) : '-';
        }
        function getFilterUrl(base) {
            return base;
        }
        function buildPersonOptionsHtml(selectedId) {
            const selected = String(selectedId || '');
            let html = '<option value="">Magistr/Doktorantni tanlang</option>';
            $('#personSelect option').each(function() {
                const value = String($(this).attr('value') || '');
                if (value === '') return;
                const selectedAttr = value === selected ? ' selected' : '';
                html += `<option value="${escapeHtml(value)}" data-turi="${escapeHtml(String($(this).data('turi') || ''))}"${selectedAttr}>${escapeHtml($(this).text())}</option>`;
            });
            return html;
        }
        function buildDarsTuriOptionsHtml(selectedId, personTuri = '') {
            const selected = String(selectedId || '');
            const turi = String(personTuri || '');
            let html = '<option value="">Dars turini tanlang</option>';
            $('#darsTuriSelect option').each(function() {
                const value = String($(this).attr('value') || '');
                if (value === '') return;
                const optionTuri = String($(this).data('turi') || '');
                if (turi !== '' && optionTuri !== '' && optionTuri !== turi) return;
                const selectedAttr = value === selected ? ' selected' : '';
                html += `<option value="${escapeHtml(value)}" data-turi="${escapeHtml(optionTuri)}"${selectedAttr}>${escapeHtml($(this).text())}</option>`;
            });
            return html;
        }
        function cacheFilterOptions() {
            $('#yonalishFilter option').each(function() {
                const value = String($(this).attr('value') || '');
                if (!value) return;
                allYonalishOptions.push({ value, label: $(this).text(), fakultetId: String($(this).data('fakultet-id') || '') });
            });
            $('#semestrSelect option').each(function() {
                const value = String($(this).attr('value') || '');
                if (!value) return;
                allSemestrOptions.push({
                    value,
                    label: $(this).text(),
                    fakultetId: String($(this).data('fakultet-id') || ''),
                    yonalishId: String($(this).data('yonalish-id') || '')
                });
            });
        }
        function rebuildYonalish(preferred = '') {
            const fakultetId = String($('#fakultetFilter').val() || '');
            const select = $('#yonalishFilter');
            const current = String(preferred || select.val() || '');
            select.empty().append('<option value="">Barcha yo\'nalishlar</option>');
            allYonalishOptions
                .filter(item => !fakultetId || item.fakultetId === fakultetId)
                .forEach(item => select.append($('<option>').val(item.value).attr('data-fakultet-id', item.fakultetId).text(item.label)));
            select.val(select.find(`option[value="${current}"]`).length ? current : '').trigger('change.select2');
        }
        function rebuildSemestr(preferred = '') {
            const fakultetId = String($('#fakultetFilter').val() || '');
            const yonalishId = String($('#yonalishFilter').val() || '');
            const select = $('#semestrSelect');
            const current = String(preferred || select.val() || '');
            select.empty().append('<option value="">Semestrni tanlang</option>');
            allSemestrOptions
                .filter(item => !fakultetId || item.fakultetId === fakultetId)
                .filter(item => !yonalishId || item.yonalishId === yonalishId)
                .forEach(item => select.append($('<option>').val(item.value).attr('data-fakultet-id', item.fakultetId).attr('data-yonalish-id', item.yonalishId).text(item.label)));
            select.val(select.find(`option[value="${current}"]`).length ? current : '').trigger('change.select2');
        }
        function loadPeople() {
            const select = $('#personSelect');
            select.empty().append('<option value="">Yuklanmoqda...</option>').trigger('change.select2');
            fetch(getFilterUrl('api/get_magistr_doktorant_yuklamalar.php'))
                .then(res => res.json())
                .then(data => {
                    const rows = data && data.success && Array.isArray(data.rows) ? data.rows : [];
                    select.empty().append('<option value="">Magistr/Doktorantni tanlang</option>');
                    rows.forEach(row => {
                        const turi = row.turi === 'doktorant' ? 'Doktorant' : 'Magistr';
                        select.append(
                            $('<option>')
                                .val(row.id)
                                .attr('data-turi', row.turi || '')
                                .text(`${turi}: ${row.kod || ''} - ${row.ism_familiya || ''} (${getKirishYiliLabel(row)}, ${getKursLabel(row)})`)
                        );
                    });
                    select.trigger('change.select2');
                    filterDarsTuriByPerson();
                })
                .catch(() => select.empty().append('<option value="">Ro\'yxat yuklanmadi</option>').trigger('change.select2'));
        }
        function filterDarsTuriByPerson() {
            const personTuri = String($('#personSelect option:selected').data('turi') || '');
            const darsSelect = $('#darsTuriSelect');
            darsSelect.find('option').each(function() {
                const optionTuri = String($(this).data('turi') || '');
                const visible = optionTuri === '' || personTuri === '' || optionTuri === personTuri;
                $(this).prop('disabled', !visible).toggle(visible);
            });
            const selectedTuri = String(darsSelect.find('option:selected').data('turi') || '');
            if (selectedTuri !== '' && personTuri !== '' && selectedTuri !== personTuri) {
                darsSelect.val('').trigger('change.select2');
            }
        }
        function renderTable(rows) {
            $('#qoshimchaCount').text(`${rows.length} ta`);
            const tbody = $('#qoshimchaTableBody');
            qoshimchaRowsById = {};
            rows.forEach(row => {
                const id = String(row.id || '');
                if (id !== '') {
                    qoshimchaRowsById[id] = row;
                }
            });
            if (!rows.length) {
                tbody.html('<tr><td colspan="9">Tanlangan filter bo\'yicha ma\'lumot yo\'q</td></tr>');
                return;
            }
            tbody.html(rows.map(row => `
                <tr>
                    <td>${escapeHtml(row.turi === 'doktorant' ? 'Doktorant' : 'Magistr')}</td>
                    <td>${escapeHtml(row.qoshimcha_dars_name || '-')}</td>
                    <td>${escapeHtml(row.kod || '-')}</td>
                    <td>${escapeHtml(row.ism_familiya || '-')}</td>
                    <td>${escapeHtml(row.kafedra_name || '-')}</td>
                    <td>${escapeHtml(getKirishYiliLabel(row))}</td>
                    <td>${escapeHtml(row.dars_soati || row.fan_soat || 0)}</td>
                    <td>${escapeHtml(getKursLabel(row))}</td>
                    <td>
                        <div class="table-actions">
                            <button type="button" class="btn btn-outline btn-sm editQoshimchaBtn" data-id="${escapeHtml(row.id || '')}">
                                <i class="fas fa-pen"></i> Tahrirlash
                            </button>
                            <button type="button" class="btn btn-danger btn-sm deleteQoshimchaBtn" data-id="${escapeHtml(row.id || '')}">
                                <i class="fas fa-trash-alt"></i> O'chirish
                            </button>
                        </div>
                    </td>
                </tr>
            `).join(''));
        }
        function loadList() {
            fetch(getFilterUrl('api/get_magistr_doktorant_qoshimcha_rejalar.php'))
                .then(res => res.json())
                .then(data => renderTable(data && data.success && Array.isArray(data.rows) ? data.rows : []))
                .catch(() => {
                    $('#qoshimchaTableBody').html('<tr><td colspan="9">Server bilan bog\'lanib bo\'lmadi</td></tr>');
                    $('#qoshimchaCount').text('0 ta');
                });
        }
        function refreshPageData() {
            loadPeople();
            loadList();
        }

        $(document).ready(function() {
            cacheFilterOptions();
            $('#fakultetFilter, #yonalishFilter, #semestrSelect, #personSelect, #darsTuriSelect').select2({ width: '100%', allowClear: true });
            rebuildYonalish();
            rebuildSemestr();
            refreshPageData();
        });
        $('#fakultetFilter').on('change', function() {
            rebuildYonalish('');
            rebuildSemestr('');
            refreshPageData();
        });
        $('#yonalishFilter').on('change', function() {
            rebuildSemestr('');
            refreshPageData();
        });
        $('#semestrSelect').on('change', refreshPageData);
        $('#personSelect').on('change', filterDarsTuriByPerson);
        $('#applyFiltersBtn, #refreshBtn').on('click', refreshPageData);
        $('#resetFiltersBtn').on('click', function() {
            $('#fakultetFilter').val('').trigger('change.select2');
            rebuildYonalish('');
            rebuildSemestr('');
            refreshPageData();
        });
        $('#magDokQoshimchaForm').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('insert/add_magistr_doktorant_qoshimcha_reja.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        Toast.fire({ icon: 'success', title: data.message || 'Saqlandi' });
                        this.reset();
                        $('#personSelect').val('').trigger('change.select2');
                        $('#darsTuriSelect').val('').trigger('change.select2');
                        loadList();
                    } else {
                        Toast.fire({ icon: 'error', title: (data && data.message) || 'Saqlashda xatolik' });
                    }
                })
                .catch(() => Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" }));
        });
        $(document).on('click', '.editQoshimchaBtn', function() {
            const id = String($(this).data('id') || '');
            const row = qoshimchaRowsById[id];
            if (!row) return;

            Swal.fire({
                title: "Qo'shimcha rejani tahrirlash",
                width: 760,
                html: `
                    <div style="text-align:left;">
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>Magistr/Doktorant</label>
                                <select class="form-control" id="editPersonSelect">
                                    ${buildPersonOptionsHtml(row.magistr_doktorant_id || '')}
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Dars turi</label>
                                <select class="form-control" id="editDarsTuriSelect">
                                    ${buildDarsTuriOptionsHtml(row.qoshimcha_dars_id || '', row.turi || '')}
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Dars soati</label>
                                <input type="number" class="form-control" id="editDarsSoati" min="1" step="1" value="${escapeHtml(row.dars_soati || row.fan_soat || '')}" placeholder="Masalan: 25">
                            </div>
                            <div class="form-group">
                                <label>Izoh</label>
                                <input type="text" class="form-control" id="editIzoh" value="${escapeHtml(row.izoh || '')}" placeholder="Ixtiyoriy izoh">
                            </div>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: "Saqlash",
                cancelButtonText: "Bekor qilish",
                didOpen: () => {
                    $('#editPersonSelect').on('change', function() {
                        const personTuri = String($('#editPersonSelect option:selected').data('turi') || '');
                        const selectedDarsId = String($('#editDarsTuriSelect').val() || '');
                        $('#editDarsTuriSelect').html(buildDarsTuriOptionsHtml(selectedDarsId, personTuri));
                    });
                },
                preConfirm: () => {
                    const payload = {
                        id,
                        person_id: String($('#editPersonSelect').val() || ''),
                        qoshimcha_dars_id: String($('#editDarsTuriSelect').val() || ''),
                        dars_soati: String($('#editDarsSoati').val() || '').trim(),
                        izoh: String($('#editIzoh').val() || '').trim()
                    };
                    if (!payload.person_id || !payload.qoshimcha_dars_id || !payload.dars_soati || parseFloat(payload.dars_soati) <= 0) {
                        Swal.showValidationMessage("Ma'lumotlarni to'liq kiriting");
                        return false;
                    }
                    return payload;
                }
            }).then(result => {
                if (!result.isConfirmed || !result.value) return;
                const formData = new FormData();
                Object.entries(result.value).forEach(([key, value]) => formData.append(key, value));
                fetch('insert/update_magistr_doktorant_qoshimcha_reja.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.success) {
                            Toast.fire({ icon: 'success', title: data.message || 'Yangilandi' });
                            refreshPageData();
                        } else {
                            Toast.fire({ icon: 'error', title: (data && data.message) || 'Yangilashda xatolik' });
                        }
                    })
                    .catch(() => Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" }));
            });
        });
        $(document).on('click', '.deleteQoshimchaBtn', function() {
            const id = String($(this).data('id') || '');
            if (!id) return;
            Swal.fire({
                title: "Yozuv o'chirilsinmi?",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ha, o'chirish",
                cancelButtonText: "Bekor qilish"
            }).then(result => {
                if (!result.isConfirmed) return;
                const formData = new FormData();
                formData.append('id', id);
                fetch('insert/delete_magistr_doktorant_qoshimcha_reja.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        Toast.fire({ icon: data && data.success ? 'success' : 'error', title: (data && data.message) || "Amal bajarilmadi" });
                        loadList();
                    })
                    .catch(() => Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" }));
            });
        });
    </script>
</body>
</html>
