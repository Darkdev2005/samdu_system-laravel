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
                <h1>Fanlardan nusxa olish</h1>
            </header>
            <div class="content-container">
                <div class="card">
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
                            <select class="form-control" id="targetSemestr">
                                <option value="">Semestrni tanlang</option>
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
                                    >
                                        <?= $h($darajaPrefix . $short . '_' . ($s['kirish_yili'] ?? '') . ' - ' . ($s['semestr'] ?? '') . '-semestr') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <h3 class="section-title mt-4">Manba semestr</h3>
                    <div class="created-list-note">
                        Manba sifatida boshqa fakultet, yo'nalish va semestrni tanlang.
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
                            <label>Manba semestr</label>
                            <select class="form-control" id="sourceSemestr">
                                <option value="">Semestrni tanlang</option>
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
                                    >
                                        <?= $h($darajaPrefix . $short . '_' . ($s['kirish_yili'] ?? '') . ' - ' . ($s['semestr'] ?? '') . '-semestr') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="top-filters-grid mt-2">
                        <div class="form-group">
                            <label>Nusxa olish rejimi</label>
                            <select class="form-control" id="copyScopeMode">
                                <option value="required_merged">Faqat majburiy + birlashtiriladigan (0,2)</option>
                                <option value="all">Tanlov + chet tili bilan birga (0,1,2,3)</option>
                            </select>
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

        const SwalApi = window.Swal || {
            mixin: () => ({ fire: () => {} }),
            fire: () => Promise.resolve({ isConfirmed: false }),
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
            let html = '<option value="">Semestrni tanlang</option>';

            allSemestrOptions.forEach(item => {
                if (fakultetValue !== '' && String(item.fakultetId) !== fakultetValue) {
                    return;
                }
                if (yonalishValue !== '' && String(item.yonalishId) !== yonalishValue) {
                    return;
                }

                const selected = String(item.id) === String(selectedValue) ? ' selected' : '';
                const fakultetAttr = item.fakultetId !== '' ? ` data-fakultet-id="${item.fakultetId}"` : '';
                const yonalishAttr = item.yonalishId !== '' ? ` data-yonalish-id="${item.yonalishId}"` : '';
                html += `<option value="${item.id}"${fakultetAttr}${yonalishAttr}${selected}>${escapeHtml(item.text)}</option>`;
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
                ? String(selectedValue || '')
                : String($('#' + prefix + 'Semestr').val() || '');

            rebuildSemestrOptions(prefix + 'Semestr', fakultet, yonalish, current);
            const hasCurrent = $('#' + prefix + 'Semestr option[value="' + current + '"]').length > 0;
            $('#' + prefix + 'Semestr').val(hasCurrent ? current : '').trigger('change.select2');
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
                '#sourceYonalish',
                '#sourceSemestr'
            ].forEach(selector => {
                $(selector).select2({
                    placeholder: "Tanlang",
                    allowClear: true,
                    width: '100%',
                });
            });
        }

        $(document).ready(function() {
            cacheOptions();
            initSelect2();

            $('#targetFakultet').on('change', function() {
                syncYonalish('target');
                syncSemestr('target');
            });
            $('#targetYonalish').on('change', function() {
                syncSemestr('target');
            });

            $('#sourceFakultet').on('change', function() {
                syncYonalish('source');
                syncSemestr('source');
            });
            $('#sourceYonalish').on('change', function() {
                syncSemestr('source');
            });

            $('#copyResetBtn').on('click', function() {
                $('#targetFakultet, #targetYonalish, #targetSemestr, #sourceFakultet, #sourceYonalish, #sourceSemestr').val('').trigger('change.select2');
                $('#copyScopeMode').val('required_merged');
                syncYonalish('target', '');
                syncSemestr('target', '');
                syncYonalish('source', '');
                syncSemestr('source', '');
            });

            $('#copyRunBtn').on('click', function() {
                const targetSemestrId = String($('#targetSemestr').val() || '');
                const sourceSemestrId = String($('#sourceSemestr').val() || '');
                const scopeMode = String($('#copyScopeMode').val() || 'required_merged');

                if (targetSemestrId === '') {
                    Toast.fire({ icon: 'error', title: "Maqsad semestrni tanlang" });
                    return;
                }
                if (sourceSemestrId === '') {
                    Toast.fire({ icon: 'error', title: "Manba semestrni tanlang" });
                    return;
                }
                if (targetSemestrId === sourceSemestrId) {
                    Toast.fire({ icon: 'error', title: "Manba va maqsad semestr bir xil bo'lmasligi kerak" });
                    return;
                }

                const targetText = String($('#targetSemestr option:selected').text() || '').trim();
                const sourceText = String($('#sourceSemestr option:selected').text() || '').trim();
                const scopeText = scopeMode === 'all'
                    ? 'Tanlov + Chet tili bilan birga (0,1,2,3)'
                    : 'Faqat majburiy + birlashtiriladigan (0,2)';

                SwalApi.fire({
                    title: "Fanlarni nusxalash",
                    icon: "question",
                    html: `
                        <div style="text-align:left;">
                            <div><b>Manba:</b> ${escapeHtml(sourceText)}</div>
                            <div style="margin-top:6px;"><b>Maqsad:</b> ${escapeHtml(targetText)}</div>
                            <div style="margin-top:6px;"><b>Rejim:</b> ${escapeHtml(scopeText)}</div>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: "Ha, nusxalash",
                    cancelButtonText: "Bekor qilish"
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const formData = new FormData();
                    formData.append('target_semestr_id', targetSemestrId);
                    formData.append('source_semestr_id', sourceSemestrId);
                    formData.append('scope_mode', scopeMode);

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
            syncSemestr('source');
        });
    </script>
</body>
</html>

