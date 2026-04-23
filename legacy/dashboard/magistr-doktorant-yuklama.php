<?php
include_once 'config.php';
$db = new Database();
$semestrlar = $db->get_semestrlar();
$fakultetlar = $db->get_data_by_table_all('fakultetlar', 'ORDER BY name');
$yonalishlar = $db->get_data_by_table_all('yonalishlar');
$kafedralar = $db->get_data_by_table_all('kafedralar', 'ORDER BY name');
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
    <title>Magistr/Doktorant kiritish</title>
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
                <h1>Magistr/Doktorant kiritish</h1>
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
                    <h3 class="section-title">Kiritish</h3>
                    <div class="info-note">
                        Bu sahifa faqat magistr va doktorantlarni ro'yxatga oladi. Soat kiritish alohida "Magistr/Doktorant qo'shimcha reja" bo'limida bajariladi.
                    </div>
                    <form id="magDokForm" class="mt-3">
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>Turi</label>
                                <select class="form-control" name="turi" id="magDokTuri" required>
                                    <option value="magistr">Magistr</option>
                                    <option value="doktorant">Doktorant</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Kurs</label>
                                <select class="form-control" name="kurs" id="magDokKurs" required>
                                    <option value="1">1-kurs</option>
                                    <option value="2">2-kurs</option>
                                    <option value="3">3-kurs</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Kirgan yili</label>
                                <select class="form-control" name="kirish_yili" id="magDokKirishYili" required>
                                    <?php
                                        $currentYear = (int)date('Y');
                                        for ($year = $currentYear + 1; $year >= $currentYear - 10; $year--):
                                    ?>
                                        <option value="<?= $year ?>"><?= $year ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Kod</label>
                                <input type="text" class="form-control" name="kod" placeholder="Masalan: MAG-001" required>
                            </div>
                            <div class="form-group">
                                <label>Ism familiyasi</label>
                                <input type="text" class="form-control" name="ism_familiya" placeholder="Masalan: Aliyev Ali" required>
                            </div>
                            <div class="form-group">
                                <label>Kafedra</label>
                                <select class="form-control" name="kafedra_id" id="magDokKafedra" required>
                                    <option value="">Kafedrani tanlang</option>
                                    <?php foreach ($kafedralar as $k): ?>
                                        <option value="<?= (int)$k['id'] ?>"><?= $h($k['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                            <h3>Kiritilgan magistr/doktorantlar</h3>
                            <span class="badge" id="magDokCount">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <button type="button" class="btn btn-outline btn-sm" id="refreshMagDokBtn">
                                <i class="fas fa-rotate"></i> Yangilash
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive mt-2">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Turi</th>
                                    <th>Kod</th>
                                    <th>Ism familiyasi</th>
                                    <th>Kafedra</th>
                                    <th>Kurs</th>
                                    <th>Kirgan yili</th>
                                    <th>Harakat</th>
                                </tr>
                            </thead>
                            <tbody id="magDokTableBody">
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
    <script src="../assets/js/app.js"></script>
    <script>
        const allYonalishOptions = [];
        const allSemestrOptions = [];
        let magDokRowsById = {};
        const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000, timerProgressBar: true });

        function escapeHtml(value) {
            return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
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

        function getKursLabel(row) {
            const kurs = parseInt(row.kurs || 0, 10) || 0;
            return kurs > 0 ? `${kurs}-kurs` : '-';
        }
        function getKirishYiliLabel(row) {
            const year = parseInt(row.kirish_yili || 0, 10) || 0;
            return year > 0 ? String(year) : '-';
        }
        function buildKafedraOptionsHtml(selectedId) {
            const selected = String(selectedId || '');
            let html = '<option value="">Kafedrani tanlang</option>';
            $('#magDokKafedra option').each(function() {
                const value = String($(this).attr('value') || '');
                if (value === '') return;
                const selectedAttr = value === selected ? ' selected' : '';
                html += `<option value="${escapeHtml(value)}"${selectedAttr}>${escapeHtml($(this).text())}</option>`;
            });
            return html;
        }
        function buildKirishYiliOptionsHtml(selectedYear) {
            const selected = String(selectedYear || '');
            let html = '';
            let hasSelected = false;
            $('#magDokKirishYili option').each(function() {
                const value = String($(this).attr('value') || '');
                if (value === '') return;
                const selectedAttr = value === selected ? ' selected' : '';
                if (value === selected) {
                    hasSelected = true;
                }
                html += `<option value="${escapeHtml(value)}"${selectedAttr}>${escapeHtml($(this).text())}</option>`;
            });
            if (selected !== '' && !hasSelected) {
                html = `<option value="${escapeHtml(selected)}" selected>${escapeHtml(selected)}</option>` + html;
            }
            return html;
        }

        function renderTable(rows) {
            $('#magDokCount').text(`${rows.length} ta`);
            const tbody = $('#magDokTableBody');
            magDokRowsById = {};
            rows.forEach(row => {
                const id = String(row.id || '');
                if (id !== '') {
                    magDokRowsById[id] = row;
                }
            });
            if (!rows.length) {
                tbody.html('<tr><td colspan="7">Tanlangan filter bo\'yicha ma\'lumot yo\'q</td></tr>');
                return;
            }
            tbody.html(rows.map(row => `
                <tr>
                    <td>${escapeHtml(row.turi === 'doktorant' ? 'Doktorant' : 'Magistr')}</td>
                    <td>${escapeHtml(row.kod || '-')}</td>
                    <td>${escapeHtml(row.ism_familiya || '-')}</td>
                    <td>${escapeHtml(row.kafedra_name || '-')}</td>
                    <td>${escapeHtml(getKursLabel(row))}</td>
                    <td>${escapeHtml(getKirishYiliLabel(row))}</td>
                    <td>
                        <div class="table-actions">
                            <button type="button" class="btn btn-outline btn-sm editMagDokBtn" data-id="${escapeHtml(row.id || '')}">
                                <i class="fas fa-pen"></i> Tahrirlash
                            </button>
                            <button type="button" class="btn btn-danger btn-sm deleteMagDokBtn" data-id="${escapeHtml(row.id || '')}">
                                <i class="fas fa-trash-alt"></i> O'chirish
                            </button>
                        </div>
                    </td>
                </tr>
            `).join(''));
        }

        function loadList() {
            const url = 'api/get_magistr_doktorant_yuklamalar.php';
            fetch(url)
                .then(res => res.json())
                .then(data => renderTable(data && data.success && Array.isArray(data.rows) ? data.rows : []))
                .catch(() => {
                    $('#magDokTableBody').html('<tr><td colspan="7">Server bilan bog\'lanib bo\'lmadi</td></tr>');
                    $('#magDokCount').text('0 ta');
                });
        }

        $(document).ready(function() {
            cacheFilterOptions();
            $('#fakultetFilter, #yonalishFilter, #semestrSelect, #magDokKafedra').select2({ width: '100%', allowClear: true });
            $('#magDokTuri').select2({ width: '100%', minimumResultsForSearch: Infinity });
            $('#magDokKurs').select2({ width: '100%', minimumResultsForSearch: Infinity });
            $('#magDokKirishYili').select2({ width: '100%', minimumResultsForSearch: Infinity });
            rebuildYonalish();
            rebuildSemestr();
            loadList();
        });

        $('#fakultetFilter').on('change', function() {
            rebuildYonalish('');
            rebuildSemestr('');
        });
        $('#yonalishFilter').on('change', function() {
            rebuildSemestr('');
        });
        $('#applyFiltersBtn, #refreshMagDokBtn').on('click', loadList);
        $('#resetFiltersBtn').on('click', function() {
            $('#fakultetFilter').val('').trigger('change.select2');
            rebuildYonalish('');
            rebuildSemestr('');
            loadList();
        });

        $('#magDokForm').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('insert/add_magistr_doktorant_yuklama.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        Toast.fire({ icon: 'success', title: data.message || 'Saqlandi' });
                        this.reset();
                        $('#magDokTuri').val('magistr').trigger('change.select2');
                        $('#magDokKurs').val('1').trigger('change.select2');
                        $('#magDokKirishYili').val(String(new Date().getFullYear())).trigger('change.select2');
                        $('#magDokKafedra').val('').trigger('change.select2');
                        loadList();
                    } else {
                        Toast.fire({ icon: 'error', title: (data && data.message) || 'Saqlashda xatolik' });
                    }
                })
                .catch(() => Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" }));
        });

        $(document).on('click', '.editMagDokBtn', function() {
            const id = String($(this).data('id') || '');
            const row = magDokRowsById[id];
            if (!row) return;

            Swal.fire({
                title: "Magistr/Doktorantni tahrirlash",
                width: 760,
                html: `
                    <div style="text-align:left;">
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label>Turi</label>
                                <select class="form-control" id="editMagDokTuri">
                                    <option value="magistr"${row.turi === 'doktorant' ? '' : ' selected'}>Magistr</option>
                                    <option value="doktorant"${row.turi === 'doktorant' ? ' selected' : ''}>Doktorant</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Kurs</label>
                                <select class="form-control" id="editMagDokKurs">
                                    <option value="1"${String(row.kurs || '') === '1' ? ' selected' : ''}>1-kurs</option>
                                    <option value="2"${String(row.kurs || '') === '2' ? ' selected' : ''}>2-kurs</option>
                                    <option value="3"${String(row.kurs || '') === '3' ? ' selected' : ''}>3-kurs</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Kirgan yili</label>
                                <select class="form-control" id="editMagDokKirishYili">
                                    ${buildKirishYiliOptionsHtml(row.kirish_yili || '')}
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Kod</label>
                                <input type="text" class="form-control" id="editMagDokKod" value="${escapeHtml(row.kod || '')}" placeholder="Masalan: MAG-001">
                            </div>
                            <div class="form-group">
                                <label>Ism familiyasi</label>
                                <input type="text" class="form-control" id="editMagDokIsmFamiliya" value="${escapeHtml(row.ism_familiya || '')}" placeholder="Masalan: Aliyev Ali">
                            </div>
                            <div class="form-group">
                                <label>Kafedra</label>
                                <select class="form-control" id="editMagDokKafedra">
                                    ${buildKafedraOptionsHtml(row.kafedra_id || '')}
                                </select>
                            </div>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: "Saqlash",
                cancelButtonText: "Bekor qilish",
                preConfirm: () => {
                    const payload = {
                        id,
                        turi: String($('#editMagDokTuri').val() || ''),
                        kurs: String($('#editMagDokKurs').val() || ''),
                        kirish_yili: String($('#editMagDokKirishYili').val() || ''),
                        kod: String($('#editMagDokKod').val() || '').trim(),
                        ism_familiya: String($('#editMagDokIsmFamiliya').val() || '').trim(),
                        kafedra_id: String($('#editMagDokKafedra').val() || '')
                    };
                    if (!payload.turi || !payload.kurs || !payload.kirish_yili || !payload.kod || !payload.ism_familiya || !payload.kafedra_id) {
                        Swal.showValidationMessage("Ma'lumotlarni to'liq kiriting");
                        return false;
                    }
                    return payload;
                }
            }).then(result => {
                if (!result.isConfirmed || !result.value) return;
                const formData = new FormData();
                Object.entries(result.value).forEach(([key, value]) => formData.append(key, value));
                fetch('insert/update_magistr_doktorant_yuklama.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.success) {
                            Toast.fire({ icon: 'success', title: data.message || 'Yangilandi' });
                            loadList();
                        } else {
                            Toast.fire({ icon: 'error', title: (data && data.message) || 'Yangilashda xatolik' });
                        }
                    })
                    .catch(() => Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" }));
            });
        });

        $(document).on('click', '.deleteMagDokBtn', function() {
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
                fetch('insert/delete_magistr_doktorant_yuklama.php', { method: 'POST', body: formData })
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
