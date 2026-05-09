<?php
include_once 'config.php';
$db = new Database();

$isKafedraMudiri = legacy_is_kafedra_mudiri();
$currentKafedraId = legacy_user_kafedra_id();
$semestrlar = $db->get_semestrlar();
$guruhlar = $db->get_guruhlar();
$fakultetlar = $db->get_data_by_table_all('fakultetlar');
$kafedralar = $isKafedraMudiri && $currentKafedraId > 0
    ? $db->get_data_by_table_all('kafedralar', 'WHERE id = ' . $currentKafedraId)
    : $db->get_data_by_table_all('kafedralar');
$darsSoatTurlari = $db->get_data_by_table_all('dars_soat_turlar');

$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Maxsus guruh uchun o'quv reja</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link href="/assets/vendor/select2/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .grid-4 { display:grid; grid-template-columns:repeat(4,minmax(220px,1fr)); gap:12px; }
        .grid-3 { display:grid; grid-template-columns:repeat(3,minmax(220px,1fr)); gap:12px; }
        .grid-2 { display:grid; grid-template-columns:repeat(2,minmax(220px,1fr)); gap:12px; }
        .reja-card { border:1px solid #e2e8f0; border-radius:12px; padding:14px; margin-top:14px; background:#f8fafc; }
        .card-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:10px; }
        .top-filter-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:12px; flex-wrap:wrap; }
        .table-actions { display:flex; gap:8px; justify-content:flex-end; margin-bottom:10px; }
        .badge-mini { display:inline-block; padding:3px 8px; border-radius:999px; background:#dcfce7; color:#15803d; font-size:12px; font-weight:700; }
        @media (max-width: 1200px){ .grid-4 { grid-template-columns:repeat(2,minmax(220px,1fr)); } }
        @media (max-width: 1000px){ .grid-4,.grid-3 { grid-template-columns:1fr; } .grid-2 { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="top-navbar">
            <h1><i class="fas fa-users-cog me-2"></i>Maxsus guruh uchun o'quv reja</h1>
        </header>
        <div class="content-container">
            <form id="maxsusRejaForm" class="card">
                <h3 class="section-title">Umumiy ma'lumot</h3>
                <div class="grid-4">
                    <div class="form-group">
                        <label>Fakultet</label>
                        <select class="form-control" id="fakultetFilter">
                            <option value="">Barcha fakultetlar</option>
                            <?php foreach ($fakultetlar as $f): ?>
                                <option value="<?= (int)$f['id'] ?>"><?= $h($f['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Fakultetning yo'nalishi</label>
                        <select class="form-control" id="yonalishId" name="yonalish_id" required>
                            <option value="">Tanlang</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Yo'nalishning guruhi</label>
                        <select class="form-control" id="guruhId" name="guruh_id" required>
                            <option value="">Tanlang</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Guruhning semestri</label>
                        <select class="form-control" id="semestrId" name="semestr_id" required>
                            <option value="">Tanlang</option>
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
                <div id="rejaWrapper"></div>
                <div class="card-actions">
                    <button type="button" class="btn btn-outline btn-sm" id="addRejaCardBtn"><i class="fas fa-plus"></i> Yana fan</button>
                </div>
                <div class="form-group mt-3">
                    <label>Izoh</label>
                    <textarea class="form-control" rows="3" name="izoh" placeholder="Ixtiyoriy izoh..."></textarea>
                </div>
                <div class="form-actions mt-3">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Saqlash</button>
                </div>
            </form>

            <div class="card mt-4">
                <div class="table-actions">
                    <button type="button" class="btn btn-outline btn-sm" id="refreshListBtn"><i class="fas fa-rotate"></i> Yangilash</button>
                </div>
                <h3 class="section-title">Yaratilgan maxsus reja <span class="badge-mini" id="listCount">0 ta</span></h3>
                <div class="table-responsive mt-2">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Fan kodi</th>
                            <th>Fan nomi</th>
                            <th>Kafedra</th>
                            <th>Yo'nalish</th>
                            <th>Guruh</th>
                            <th>Semestr</th>
                            <th>Dars soatlari</th>
                            <th>Harakat</th>
                        </tr>
                        </thead>
                        <tbody id="listBody">
                        <tr><td colspan="8">Yuklanmoqda...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/vendor/jquery/jquery-3.6.0.min.js"></script>
<script src="/assets/vendor/select2/js/select2.min.js"></script>
<script>if (window.jQuery && !window.jQuery.fn.select2) { document.write('<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"><\/script>'); }</script>
<script>
const semestrlar = <?php echo json_encode($semestrlar, $jsonFlags) ?: '[]'; ?>;
const guruhlar = <?php echo json_encode($guruhlar, $jsonFlags) ?: '[]'; ?>;
const fakultetlar = <?php echo json_encode($fakultetlar, $jsonFlags) ?: '[]'; ?>;
const kafedralar = <?php echo json_encode($kafedralar, $jsonFlags) ?: '[]'; ?>;
const darsTurlari = <?php echo json_encode($darsSoatTurlari, $jsonFlags) ?: '[]'; ?>;
const isKafedraMudiri = <?= $isKafedraMudiri ? 'true' : 'false' ?>;
const lockedKafedraId = <?= (int)$currentKafedraId ?>;
let cardIndex = 0;
let createdRowsById = {};

const SwalApi = window.Swal || {
    fire: () => Promise.resolve({ isConfirmed: false }),
    showValidationMessage: () => {},
    mixin: () => ({ fire: () => {} })
};

const Toast = SwalApi.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2200,
    timerProgressBar: true
});

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

function buildYonalishOptionsByFakultet(fakultetId = '') {
    const map = new Map();
    semestrlar.forEach(s => {
        if (fakultetId && String(s.yonalish_fakultet_id || s.fakultet_id || '') !== String(fakultetId)) return;
        const yid = Number(s.yonalish_id || 0);
        if (!yid || map.has(yid)) return;
        map.set(yid, {
            id: yid,
            label: `${s.yonalish_name || ''} - ${s.kirish_yili || ''}`,
            fakultet_id: Number(s.yonalish_fakultet_id || s.fakultet_id || 0),
        });
    });
    let html = '<option value="">Tanlang</option>';
    Array.from(map.values()).forEach(item => {
        html += `<option value="${item.id}" data-fakultet-id="${item.fakultet_id}">${esc(item.label)}</option>`;
    });
    $('#yonalishId').html(html);
    if ($.fn && typeof $.fn.select2 === 'function') {
        $('#yonalishId').trigger('change.select2');
    }
}

function buildGuruhOptions(yonalishId = '') {
    let html = '<option value="">Tanlang</option>';
    guruhlar.forEach(g => {
        if (yonalishId && String(g.yonalish_id) !== String(yonalishId)) return;
        html += `<option value="${Number(g.id || 0)}" data-yonalish-id="${Number(g.yonalish_id || 0)}">${esc(g.guruh_nomer || '')} (${Number(g.soni || 0)} ta)</option>`;
    });
    $('#guruhId').html(html);
    if ($.fn && typeof $.fn.select2 === 'function') {
        $('#guruhId').trigger('change.select2');
    }
}

function buildSemestrOptions(yonalishId = '') {
    let html = '<option value="">Tanlang</option>';
    semestrlar.forEach(s => {
        if (yonalishId && String(s.yonalish_id) !== String(yonalishId)) return;
        html += `<option value="${Number(s.id || 0)}" data-yonalish-id="${Number(s.yonalish_id || 0)}">${esc((s.yonalish_name || '') + ' - ' + (s.kirish_yili || '') + ' / ' + (s.semestr || ''))}</option>`;
    });
    $('#semestrId').html(html);
    if ($.fn && typeof $.fn.select2 === 'function') {
        $('#semestrId').trigger('change.select2');
    }
}

function buildKafedraOptions() {
    let html = '<option value="">Tanlang</option>';
    kafedralar.forEach(k => {
        const id = Number(k.id || 0);
        const selected = (isKafedraMudiri && id === lockedKafedraId) ? ' selected' : '';
        html += `<option value="${id}"${selected}>${esc(k.name || '')}</option>`;
    });
    return html;
}

function buildDarsRows(idx) {
    return darsTurlari.map(d => `
        <div class="grid-2">
            <div class="form-group">
                <label>Dars turi</label>
                <input type="text" class="form-control" value="${esc(d.name || '')}" readonly>
                <input type="hidden" name="dars_turi[${idx}][]" value="${Number(d.id || 0)}">
            </div>
            <div class="form-group">
                <label>Dars soati</label>
                <input type="number" class="form-control soat-input" name="dars_soati[${idx}][]" min="0" step="1" value="0">
            </div>
        </div>
    `).join('');
}

function initKafedraSelect2(context) {
    if (!($.fn && typeof $.fn.select2 === 'function')) return;
    const $root = context ? $(context) : $(document);
    $root.find('select[name^="kafedra_id["]').each(function () {
        const $el = $(this);
        if ($el.data('select2')) {
            $el.select2('destroy');
        }
        $el.select2({
            placeholder: 'Tanlang',
            allowClear: true,
            width: '100%'
        });
    });
}

function addCard() {
    const idx = cardIndex++;
    const cardHtml = `
        <div class="reja-card" data-card-index="${idx}">
            <div class="grid-3">
                <div class="form-group">
                    <label>Fan kodi</label>
                    <input type="text" class="form-control" name="fan_code[${idx}]" required>
                </div>
                <div class="form-group">
                    <label>Fan nomi</label>
                    <input type="text" class="form-control" name="fan_nomi[${idx}]" required>
                </div>
                <div class="form-group">
                    <label>Kafedra</label>
                    <select class="form-control" name="kafedra_id[${idx}]" required>
                        ${buildKafedraOptions()}
                    </select>
                </div>
            </div>
            ${buildDarsRows(idx)}
            <div class="card-actions">
                <button type="button" class="btn btn-danger btn-sm remove-card-btn"><i class="fas fa-times"></i> O'chirish</button>
            </div>
        </div>
    `;
    $('#rejaWrapper').append(cardHtml);
    const $newCard = $('#rejaWrapper .reja-card').last();
    initKafedraSelect2($newCard);
}

function ensureLockedKafedraValues() {
    if (!isKafedraMudiri) return;
    $('#rejaWrapper select[name^="kafedra_id["]').each(function () {
        $(this).val(String(lockedKafedraId)).trigger('change');
    });
}

function loadCreatedList() {
    const params = {
        semestr_id: $('#semestrId').val() || '',
        yonalish_id: $('#yonalishId').val() || '',
        guruh_id: $('#guruhId').val() || ''
    };
    if (!isKafedraMudiri) {
        const firstKafedra = $('#rejaWrapper select[name^="kafedra_id["]').first().val() || '';
        if (firstKafedra) params.kafedra_id = firstKafedra;
    } else if (lockedKafedraId > 0) {
        params.kafedra_id = lockedKafedraId;
    }

    $.getJSON('api/get_maxsus_oquv_reja_created_list.php', params, function (res) {
        if (!res || !res.success) {
            createdRowsById = {};
            $('#listBody').html('<tr><td colspan="8">Ro\'yxatni yuklab bo\'lmadi</td></tr>');
            return;
        }

        const rows = Array.isArray(res.rows) ? res.rows : [];
        createdRowsById = {};
        rows.forEach(r => {
            const id = Number(r.maxsus_reja_id || 0);
            if (id > 0) {
                createdRowsById[String(id)] = r;
            }
        });

        $('#listCount').text(rows.length + ' ta');
        if (!rows.length) {
            $('#listBody').html('<tr><td colspan="8">Ma\'lumot yo\'q</td></tr>');
            return;
        }

        const darsMap = new Map((res.dars_turlari || []).map(d => [String(d.id), d.name || ('Tur #' + d.id)]));
        const html = rows.map(r => {
            const dars = r.dars || {};
            const darsHtml = Object.keys(dars).sort((a, b) => Number(a) - Number(b)).map(k => {
                const v = Number(dars[k] || 0);
                if (v <= 0) return '';
                return `<div>${esc(darsMap.get(String(k)) || ('Tur #' + k))}: ${v}</div>`;
            }).join('');

            return `
                <tr>
                    <td>${esc(r.fan_code || '')}</td>
                    <td>${esc(r.fan_name || '')}</td>
                    <td>${esc(r.kafedra_name || '')}</td>
                    <td>${esc((r.yonalish_code || '') + ' - ' + (r.yonalish_name || ''))}</td>
                    <td>${esc(r.guruh_nomer || '')}</td>
                    <td>${esc(r.semestr || '')}</td>
                    <td>${darsHtml || '-'}</td>
                    <td>
                        <div class="table-actions">
                            <button type="button" class="btn btn-outline btn-sm editCreatedMaxsusBtn" data-maxsus-reja-id="${Number(r.maxsus_reja_id || 0)}">
                                <i class="fas fa-pen"></i> Tahrirlash
                            </button>
                            <button type="button" class="btn btn-danger btn-sm deleteCreatedMaxsusBtn" data-maxsus-reja-id="${Number(r.maxsus_reja_id || 0)}">
                                <i class="fas fa-trash-alt"></i> O'chirish
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        $('#listBody').html(html);
    }).fail(function () {
        createdRowsById = {};
        $('#listBody').html('<tr><td colspan="8">Ro\'yxatni yuklab bo\'lmadi</td></tr>');
    });
}

function buildEditKafedraOptionsHtml(selectedId) {
    let html = '<option value="">Tanlang</option>';
    kafedralar.forEach(k => {
        const id = Number(k.id || 0);
        if (id <= 0) return;
        const selected = String(id) === String(selectedId || '') ? ' selected' : '';
        html += `<option value="${id}"${selected}>${esc(k.name || '')}</option>`;
    });
    return html;
}

function buildEditMaxsusModalHtml(row) {
    const dars = row.dars || {};
    let darsRows = '';
    darsTurlari.forEach(tur => {
        const tid = String(tur.id || '');
        if (!tid) return;
        const soat = Number(dars[tid] || 0);
        darsRows += `
            <div style="display:flex;gap:8px;align-items:center;margin:6px 0;">
                <label style="flex:1;text-align:left;">${esc(tur.name || '')}</label>
                <input type="number" min="0" step="1" inputmode="numeric" class="swal2-input edit-dars-input" data-dars-tur-id="${tid}" value="${soat}" style="width:120px;margin:0;">
            </div>
        `;
    });

    return `
        <input type="text" id="editMaxsusFanCode" class="swal2-input" placeholder="Fan kodi" value="${esc(row.fan_code || '')}">
        <input type="text" id="editMaxsusFanName" class="swal2-input" placeholder="Fan nomi" value="${esc(row.fan_name || '')}">
        <div style="text-align:left;margin:8px 0 4px 0;font-size:13px;color:#64748b;">Kafedra</div>
        <select id="editMaxsusKafedraId" class="swal2-input" ${isKafedraMudiri ? 'disabled' : ''}>
            ${buildEditKafedraOptionsHtml(row.kafedra_id || '')}
        </select>
        <div style="text-align:left;margin:10px 0 4px 0;font-size:13px;color:#64748b;">Dars soatlari</div>
        <div style="max-height:220px;overflow:auto;padding-right:4px;">${darsRows}</div>
        <textarea id="editMaxsusIzoh" class="swal2-textarea" placeholder="Izoh">${esc(row.izoh || '')}</textarea>
    `;
}

$(document).on('click', '.editCreatedMaxsusBtn', function () {
    const rejaId = String($(this).data('maxsus-reja-id') || '');
    const row = createdRowsById[rejaId];
    if (!row) return;

    SwalApi.fire({
        title: "Maxsus reja tahrirlash",
        width: 860,
        html: buildEditMaxsusModalHtml(row),
        showCancelButton: true,
        confirmButtonText: "Saqlash",
        cancelButtonText: "Bekor qilish",
        preConfirm: () => {
            const fanCode = String($('#editMaxsusFanCode').val() || '').trim();
            const fanName = String($('#editMaxsusFanName').val() || '').trim();
            const izoh = String($('#editMaxsusIzoh').val() || '').trim();
            const kafedraId = isKafedraMudiri
                ? Number(lockedKafedraId || 0)
                : Number($('#editMaxsusKafedraId').val() || 0);

            if (fanCode === '' || fanName === '') {
                SwalApi.showValidationMessage("Fan kodi va fan nomi to'ldirilishi shart");
                return false;
            }
            if (kafedraId <= 0) {
                SwalApi.showValidationMessage("Kafedrani tanlang");
                return false;
            }

            const dars = {};
            let hasPositive = false;
            $('.edit-dars-input').each(function () {
                const turId = String($(this).data('dars-tur-id') || '');
                let soat = Number($(this).val() || 0);
                if (!Number.isFinite(soat) || soat < 0) soat = 0;
                dars[turId] = soat;
                if (soat > 0) hasPositive = true;
            });

            if (!hasPositive) {
                SwalApi.showValidationMessage("Kamida bitta dars soati 0 dan katta bo'lishi kerak");
                return false;
            }

            return {
                maxsus_reja_id: Number(row.maxsus_reja_id || 0),
                fan_code: fanCode,
                fan_name: fanName,
                kafedra_id: kafedraId,
                izoh: izoh,
                dars: dars
            };
        }
    }).then((result) => {
        if (!result.isConfirmed || !result.value) return;
        const payload = result.value;
        const formData = new FormData();
        formData.append('maxsus_reja_id', String(payload.maxsus_reja_id));
        formData.append('fan_code', payload.fan_code);
        formData.append('fan_name', payload.fan_name);
        formData.append('kafedra_id', String(payload.kafedra_id));
        formData.append('izoh', payload.izoh);
        formData.append('dars_json', JSON.stringify(payload.dars));

        fetch('insert/update_maxsus_oquv_reja_item.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                Toast.fire({ icon: 'success', title: data.message || "Yangilandi" });
                loadCreatedList();
            } else {
                Toast.fire({ icon: 'error', title: (data && data.message) || "Yangilashda xatolik" });
            }
        })
        .catch(() => {
            Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
        });
    });
});

$(document).on('click', '.deleteCreatedMaxsusBtn', function () {
    const rejaId = String($(this).data('maxsus-reja-id') || '');
    const row = createdRowsById[rejaId];
    if (!row) return;

    SwalApi.fire({
        title: "Maxsus reja fanini o'chirasizmi?",
        text: `${row.fan_code || ''} - ${row.fan_name || ''}`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Ha, o'chirilsin",
        cancelButtonText: "Bekor qilish"
    }).then((result) => {
        if (!result.isConfirmed) return;
        const formData = new FormData();
        formData.append('maxsus_reja_id', rejaId);

        fetch('insert/delete_maxsus_oquv_reja_item.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                Toast.fire({ icon: 'success', title: data.message || "O'chirildi" });
                loadCreatedList();
            } else {
                Toast.fire({ icon: 'error', title: (data && data.message) || "O'chirishda xatolik" });
            }
        })
        .catch(() => {
            Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
        });
    });
});

$(document).ready(function () {
    if ($.fn && typeof $.fn.select2 === 'function') {
        $('#fakultetFilter, #yonalishId, #guruhId, #semestrId').select2({
            placeholder: 'Tanlang',
            allowClear: true,
            width: '100%'
        });
    }

    addCard();
    ensureLockedKafedraValues();
    buildYonalishOptionsByFakultet('');
    buildGuruhOptions('');
    buildSemestrOptions('');
    initKafedraSelect2($('#rejaWrapper'));
    loadCreatedList();

    $('#fakultetFilter').on('change', function () {
        const fakultetId = $(this).val();
        buildYonalishOptionsByFakultet(fakultetId);
        $('#yonalishId').val('').trigger('change');
        buildGuruhOptions('');
        buildSemestrOptions('');
    });

    $('#yonalishId').on('change', function () {
        const yid = $(this).val();
        buildGuruhOptions(yid);
        buildSemestrOptions(yid);
        $('#guruhId').val('').trigger('change');
        $('#semestrId').val('').trigger('change');
    });

    $('#addRejaCardBtn').on('click', function () {
        addCard();
        ensureLockedKafedraValues();
    });

    $(document).on('click', '.remove-card-btn', function () {
        if ($('#rejaWrapper .reja-card').length <= 1) {
            Swal.fire({ icon: 'warning', title: 'Kamida bitta fan qolishi kerak' });
            return;
        }
        $(this).closest('.reja-card').remove();
    });

    $('#refreshListBtn').on('click', loadCreatedList);

    $('#applyTopFiltersBtn').on('click', function () {
        const yonalishId = $('#yonalishId').val();
        const guruhId = $('#guruhId').val();
        const semestrId = $('#semestrId').val();
        if (!yonalishId || !guruhId || !semestrId) {
            Swal.fire({
                icon: 'warning',
                title: "Filter to'liq emas",
                text: "Yo'nalish, guruh va semestrni tanlang."
            });
            return;
        }
        loadCreatedList();
    });

    $('#resetTopFiltersBtn').on('click', function () {
        $('#fakultetFilter').val('').trigger('change');
        buildYonalishOptionsByFakultet('');
        $('#yonalishId').val('').trigger('change');
        buildGuruhOptions('');
        buildSemestrOptions('');
        $('#guruhId').val('').trigger('change');
        $('#semestrId').val('').trigger('change');
        loadCreatedList();
    });

    $('#maxsusRejaForm').on('submit', function (e) {
        e.preventDefault();
        ensureLockedKafedraValues();
        const data = $(this).serialize();
        $.ajax({
            url: 'insert/add_maxsus_oquv_reja.php',
            method: 'POST',
            dataType: 'json',
            data,
            success: function (res) {
                if (res && res.success) {
                    Swal.fire({ icon: 'success', title: 'Saqlandi', text: res.message || '' });
                    loadCreatedList();
                } else {
                    Swal.fire({ icon: 'error', title: 'Xatolik', text: (res && res.message) ? res.message : "Saqlab bo'lmadi" });
                }
            },
            error: function () {
                Swal.fire({ icon: 'error', title: 'Xatolik', text: 'Server xatosi yuz berdi' });
            }
        });
    });
});
</script>
</body>
</html>
