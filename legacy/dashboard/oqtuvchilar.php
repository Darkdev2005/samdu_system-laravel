<?php 
    include_once 'config.php';
    $db = new Database();
    $isKafedraMudiri = legacy_is_kafedra_mudiri();
    $currentKafedraId = legacy_user_kafedra_id();
    $currentKafedra = $isKafedraMudiri ? legacy_current_kafedra_row($db) : null;
    $currentFakultetId = (int)($currentKafedra['fakultet_id'] ?? 0);
    $fakultetlar = $isKafedraMudiri && $currentFakultetId > 0
        ? $db->get_data_by_table_all('fakultetlar', 'WHERE id = ' . $currentFakultetId)
        : $db->get_data_by_table_all('fakultetlar');
    $kafedralar = $isKafedraMudiri && $currentKafedraId > 0
        ? $db->get_data_by_table_all('kafedralar', 'WHERE id = ' . $currentKafedraId)
        : $db->get_data_by_table_all('kafedralar');
    $ilmiy_unvonlar = $db->get_data_by_table_all('ilmiy_unvonlar');
    $ilmiy_darajalar = $db->get_data_by_table_all('ilmiy_darajalar');

    $normalizeIshturName = static function (string $value): string {
        $value = trim($value);
        $value = str_replace(['’', '‘', 'ʼ', 'ʻ', '`'], "'", $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    };

    $classifyIshtur = static function (string $normalized): string {
        if ($normalized === '' || $normalized === 'tanlang') {
            return 'skip';
        }

        $isOrindosh = strpos($normalized, 'rindosh') !== false || strpos($normalized, 'orindosh') !== false;
        if ($isOrindosh && strpos($normalized, 'ichki') !== false) {
            if (strpos($normalized, 'qoshimcha') !== false || strpos($normalized, "qo'shimcha") !== false) {
                return 'ichki_qoshimcha';
            }
            return 'ichki_asosiy';
        }
        if ($isOrindosh && strpos($normalized, 'tashqi') !== false) {
            return 'tashqi';
        }
        return 'other';
    };

    // Izoh: Yangi "ichki-qo'shimcha" shtat turi bo'lmasa avtomatik qo'shamiz.
    $ishTurQoshimchaLabel = "O'rindosh (ichki-qo'shimcha)";
    $ishTurRowsBefore = $db->get_data_by_table_all('ish_turlar');
    $hasIchkiQoshimcha = false;
    foreach ($ishTurRowsBefore as $row) {
        $normalized = $normalizeIshturName((string)($row['name'] ?? ''));
        if ($classifyIshtur($normalized) === 'ichki_qoshimcha') {
            $hasIchkiQoshimcha = true;
            break;
        }
    }
    if (!$hasIchkiQoshimcha) {
        $insertData = ['name' => $ishTurQoshimchaLabel];
        $columnsRes = $db->query('SHOW COLUMNS FROM ish_turlar');
        if ($columnsRes) {
            while ($column = mysqli_fetch_assoc($columnsRes)) {
                $field = (string)($column['Field'] ?? '');
                if ($field === '' || $field === 'id' || $field === 'name') {
                    continue;
                }

                if ($field === 'short_name') {
                    $insertData[$field] = 'orindosh_ichki_qoshimcha';
                    continue;
                }

                $isRequiredNoDefault = (($column['Null'] ?? 'YES') === 'NO') && (($column['Default'] ?? null) === null);
                $isAutoIncrement = strpos((string)($column['Extra'] ?? ''), 'auto_increment') !== false;
                if ($isRequiredNoDefault && !$isAutoIncrement) {
                    $type = (string)($column['Type'] ?? '');
                    $insertData[$field] = preg_match('/int|decimal|float|double|bit/i', $type) ? 0 : '';
                }
            }
        }
        $db->insert('ish_turlar', $insertData);
    }

    $ishTurDisplayOrder = [
        "Asosiy shtatda" => 10,
        "O'rindosh (ichki-asosiy)" => 20,
        "O'rindosh (ichki-qo'shimcha)" => 30,
        "O'rindosh (tashqi)" => 40,
        "Soatbay" => 50,
        "Vakant" => 60,
    ];

    $ishTurByDisplayName = [];
    foreach ($db->get_data_by_table_all('ish_turlar') as $row) {
        $name = trim((string)($row['name'] ?? ''));
        $normalized = $normalizeIshturName($name);
        $kind = $classifyIshtur($normalized);
        if ($kind === 'skip') {
            continue;
        }

        $displayName = $name;
        if ($kind === 'ichki_asosiy') {
            $displayName = "O'rindosh (ichki-asosiy)";
        } elseif ($kind === 'ichki_qoshimcha') {
            $displayName = "O'rindosh (ichki-qo'shimcha)";
        } elseif ($kind === 'tashqi') {
            $displayName = "O'rindosh (tashqi)";
        }

        $row['display_name'] = $displayName;
        $displayName = (string)$displayName;
        $existing = $ishTurByDisplayName[$displayName] ?? null;
        if ($existing === null || (int)($row['id'] ?? 0) < (int)($existing['id'] ?? PHP_INT_MAX)) {
            $ishTurByDisplayName[$displayName] = $row;
        }
    }
    $ish_turlar = array_values($ishTurByDisplayName);
    usort($ish_turlar, static function (array $a, array $b) use ($ishTurDisplayOrder): int {
        $nameA = (string)($a['display_name'] ?? $a['name'] ?? '');
        $nameB = (string)($b['display_name'] ?? $b['name'] ?? '');
        $orderA = $ishTurDisplayOrder[$nameA] ?? (1000 + (int)($a['id'] ?? 0));
        $orderB = $ishTurDisplayOrder[$nameB] ?? (1000 + (int)($b['id'] ?? 0));
        return $orderA <=> $orderB;
    });
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O'qituvchilar - O'quv Bo'limi</title>

    <link rel="stylesheet" href="../assets/css/oquv_yuklama_style.css">
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
</head>
<body>

    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <header class="top-navbar">
                <div class="navbar-left">
                    <h1>O'qituvchilar</h1>
                    <p class="navbar-subtitle">O'qituvchilarni boshqarish bo'limi</p>
                </div>
                <div class="navbar-right">
                    <button class="btn btn-primary" id="addOqituvchiBtn">
                        <i class="fas fa-plus"></i> O'qituvchi qo'shish
                    </button>
                </div>
            </header>

	            <div class="content-container">
                    <?php if ($isKafedraMudiri && !empty($currentKafedra['name'])): ?>
                        <div class="alert alert-info" style="margin-bottom: 16px;">
                            <strong>Sizning kafedra:</strong> <?= htmlspecialchars($currentKafedra['name']) ?>
                        </div>
                    <?php endif; ?>
	                <div class="filter-container">
	                    <div class="filter-grid">
	                        <div class="form-group">
	                            <label><i class="fas fa-building-columns me-2"></i>Fakultet</label>
	                            <select class="form-control" id="fakultetFilter"<?= $isKafedraMudiri ? ' disabled' : '' ?>>
	                                <option value="">Barcha fakultetlar</option>
	                                <?php foreach ($fakultetlar as $f): ?>
	                                    <option value="<?= $f['id'] ?>"<?= $currentFakultetId === (int)$f['id'] ? ' selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
	                                <?php endforeach; ?>
	                            </select>
	                        </div>
	                        <div class="form-group">
	                            <label><i class="fas fa-building me-2"></i>Kafedra</label>
	                            <select class="form-control" id="kafedraFilter"<?= $isKafedraMudiri ? ' disabled' : '' ?>>
	                                <?php if ($isKafedraMudiri && !empty($currentKafedra)): ?>
	                                    <option value="<?= (int)$currentKafedra['id'] ?>" selected><?= htmlspecialchars($currentKafedra['name']) ?></option>
	                                <?php else: ?>
	                                    <option value="">Avval fakultetni tanlang</option>
	                                <?php endif; ?>
	                            </select>
	                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button class="btn btn-primary" onclick="applyFilters()">
                            <i class="fas fa-filter me-2"></i>Filtrlash
                        </button>
                        <button class="btn btn-secondary" onclick="resetFilters()">
                            <i class="fas fa-redo me-2"></i>Tozalash
                        </button>
                    </div>
                </div>
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Barcha o'qituvchilar</h3>
                            <span class="badge" id="totalOqituvchilar">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchOqituvchi" placeholder="Qidirish...">
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>F.I.O</th>
                                    <th>Fakultet</th>
                                    <th>Kafedra</th>
                                    <th>Lavozim</th>
                                    <th>Stavka</th>
                                    <th>Ish tur nomi</th>
                                    <th>Ilmiy unvon</th>
                                    <th>Ilmiy daraja</th>
                                    <th>Harakatlar</th>
                                </tr>
                            </thead>
                            <tbody id="oqituvchilarTable">
                                <!-- AJAX orqali to'ldiriladi -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <!-- MODAL -->
    <div class="modal" id="oqituvchiModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="oqituvchiModalTitle">O'qituvchi qo'shish</h3>
                <button class="modal-close" id="closeOqituvchiModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <form id="oqituvchiForm">
                    <input type="hidden" id="oqituvchiEditId" value="">

                    <div class="form-group">
                        <label>Fakultet</label>
                        <select class="form-control" name="fakultet_id" id="fakultetSelect" required>
                            <option value="">Tanlang</option>
	                            <?php foreach ($fakultetlar as $f): ?>
	                                <option value="<?= $f['id'] ?>"<?= $currentFakultetId === (int)$f['id'] ? ' selected' : '' ?>>
	                                    <?= htmlspecialchars($f['name']) ?>
	                                </option>
	                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Kafedra</label>
	                        <select class="form-control" name="kafedra_id" id="kafedraSelect" required>
	                            <?php if ($isKafedraMudiri && !empty($currentKafedra)): ?>
	                                <option value="<?= (int)$currentKafedra['id'] ?>" selected><?= htmlspecialchars($currentKafedra['name']) ?></option>
	                            <?php else: ?>
	                                <option value="">Avval fakultetni tanlang</option>
	                            <?php endif; ?>
	                        </select>
	                    </div>

                    <div class="form-group">
                        <label>F.I.O</label>
                        <input type="text" class="form-control" name="fio" required>
                    </div>
                    <div class="form-group">
                        <label>Lavozimi</label>
                        <input type="text" class="form-control" name="lavozim" required>
                    </div>
                    <div class="form-group">
                        <label>Shtat birligi</label>
                        <select class="form-control" name="stavka" id="stavkaSelect">
                            <option value="">Tanlang</option>
                            <option value="0.25">0.25</option>
                            <option value="0.5">0.5</option>
                            <option value="0.75">0.75</option>
                            <option value="1.0">1.0</option>
                            <option value="1.25">1.25</option>
                            <option value="1.5">1.5</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Shtat turi</label>
                        <select class="form-control" name="ishtur_id" id="ishturSelect" required>
                            <option value="">Tanlang</option>
                            <?php foreach ($ish_turlar as $t): ?>
                                <?php if ($normalizeIshturName((string)($t['name'] ?? '')) === 'tanlang') { continue; } ?>
                                <option value="<?= $t['id'] ?>">
                                    <?= htmlspecialchars((string)($t['display_name'] ?? $t['name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Ilmiy unvon</label>
                        <select class="form-control" name="ilmiy_unvon_id">
                            <option value="">Tanlang</option>
                            <?php foreach ($ilmiy_unvonlar as $u): ?>
                                <option value="<?= $u['id'] ?>">
                                    <?= htmlspecialchars($u['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Ilmiy daraja</label>
                        <select class="form-control" name="ilmiy_daraja_id">
                            <option value="">Tanlang</option>
                            <?php foreach ($ilmiy_darajalar as $d): ?>
                                <option value="<?= $d['id'] ?>">
                                    <?= htmlspecialchars($d['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelOqituvchiBtn">Bekor qilish</button>
                <button class="btn btn-primary" id="saveOqituvchiBtn">Saqlash</button>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/vendor/xlsx/xlsx.full.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="../assets/js/app.js"></script>

	    <script>
            const scopeLocked = <?= $isKafedraMudiri ? 'true' : 'false' ?>;
            const lockedFakultetId = <?= (int)$currentFakultetId ?>;
            const lockedKafedraId = <?= (int)$currentKafedraId ?>;
	        const allKafedralar = <?php echo json_encode($kafedralar, JSON_UNESCAPED_UNICODE); ?>;

	        $(document).ready(function() {
            $('#kafedraFilter, #fakultetFilter, #kafedraSelect, #fakultetSelect').select2({
                placeholder: "Tanlang",
                allowClear: true,
                width: '100%'
            });

	            toggleStavkaRequired();
	            $('#ishturSelect').on('change', toggleStavkaRequired);

                if (scopeLocked) {
                    $('#fakultetFilter').val(String(lockedFakultetId)).trigger('change');
                    $('#kafedraFilter').val(String(lockedKafedraId)).trigger('change');
                    $('#fakultetSelect').val(String(lockedFakultetId)).trigger('change');
                    populateKafedraOptions(lockedFakultetId, lockedKafedraId);
                }
	        });

        function toggleStavkaRequired() {
            const selectedText = $('#ishturSelect option:selected').text().trim().toLowerCase();
            const isSoatbay = selectedText.includes('soatbay');
            const stavkaSelect = $('#stavkaSelect');
            if (isSoatbay) {
                stavkaSelect.prop('required', false);
            } else {
                stavkaSelect.prop('required', true);
            }
        }

        function populateKafedraOptions(fakultetId, selectedKafedraId = '') {
            const kafedraSelect = $('#kafedraSelect');
            kafedraSelect.empty().append('<option value="">Tanlang</option>');

            const filteredKafedralar = allKafedralar.filter(k => String(k.fakultet_id) === String(fakultetId));
            filteredKafedralar.forEach(k => {
                const selected = String(k.id) === String(selectedKafedraId) ? ' selected' : '';
                kafedraSelect.append(`<option value="${k.id}"${selected}>${k.name}</option>`);
            });

            kafedraSelect.trigger('change.select2');
        }

        document.addEventListener('DOMContentLoaded', () => {
            initModal();
            loadOqituvchilar();
            initSearch();
        });
        $('#fakultetSelect').on('change', function() {
            const fakultetId = $(this).val();
            populateKafedraOptions(fakultetId);
        });
        $('#fakultetFilter').on('change', function() {
            const fakultetId = $(this).val();
            const kafedraFilter = $('#kafedraFilter');
            kafedraFilter.empty().append('<option value="">Barcha kafedralar</option>');

            const filteredKafedralar = allKafedralar.filter(k => k.fakultet_id == fakultetId);
            filteredKafedralar.forEach(k => {
                kafedraFilter.append(
                    `<option value="${k.id}">${k.name}</option>`
                );
            });
        });
        function applyFilters() {
            const fakultetId = $('#fakultetFilter').val();
            const kafedraId  = $('#kafedraFilter').val();

            $('#oqituvchilarTable tr').each(function () {
                const row = $(this);

                const rowFakultet = row.find('td:eq(2)').text().trim();
                const rowKafedra  = row.find('td:eq(3)').text().trim();

                let show = true;

                if (fakultetId) {
                    const selectedFakultetText =
                        $('#fakultetFilter option:selected').text().trim();
                    if (rowFakultet !== selectedFakultetText) {
                        show = false;
                    }
                }

                if (kafedraId) {
                    const selectedKafedraText =
                        $('#kafedraFilter option:selected').text().trim();
                    if (rowKafedra !== selectedKafedraText) {
                        show = false;
                    }
                }

                row.toggle(show);
            });
        }
	        function resetFilters() {
	            if (scopeLocked) {
	                $('#fakultetFilter').val(String(lockedFakultetId)).trigger('change');
	                $('#kafedraFilter').val(String(lockedKafedraId)).trigger('change');
	            } else {
	                $('#fakultetFilter').val(null).trigger('change');
	                $('#kafedraFilter').val(null).trigger('change');
	            }
	            $('#oqituvchilarTable tr').show();
	        }
        function loadOqituvchilar() {
            fetch('get/oqituvchilar_table.php')
                .then(res => res.text())
                .then(html => {
                    const table = document.getElementById('oqituvchilarTable');
                    table.innerHTML = html;
                    document.getElementById('totalOqituvchilar').textContent =
                        table.children.length + ' ta';
                });
        }

        function initModal() {
            const modal = document.getElementById('oqituvchiModal');
            const modalTitle = document.getElementById('oqituvchiModalTitle');
            const saveBtn = document.getElementById('saveOqituvchiBtn');
            const form = document.getElementById('oqituvchiForm');
            const editIdInput = document.getElementById('oqituvchiEditId');

	            document.getElementById('addOqituvchiBtn').onclick = () => {
	                modalTitle.textContent = "O'qituvchi qo'shish";
	                saveBtn.textContent = 'Saqlash';
	                editIdInput.value = '';
	                form.reset();
	                if (scopeLocked) {
	                    $('#fakultetSelect').val(String(lockedFakultetId)).trigger('change');
	                    populateKafedraOptions(lockedFakultetId, lockedKafedraId);
	                } else {
	                    $('#fakultetSelect').val('').trigger('change');
	                    $('#kafedraSelect').val('').trigger('change');
	                }
	                $('#ishturSelect').val('').trigger('change');
	                $('#stavkaSelect').val('').trigger('change');
                $('[name="ilmiy_unvon_id"]').val('');
                $('[name="ilmiy_daraja_id"]').val('');
                modal.classList.add('show');
            };
            document.getElementById('closeOqituvchiModal').onclick = () => modal.classList.remove('show');
            document.getElementById('cancelOqituvchiBtn').onclick = () => modal.classList.remove('show');

            document.addEventListener('click', (e) => {
                const editBtn = e.target.closest('.editOqituvchiBtn');
                if (editBtn) {
                    const id = editBtn.dataset.id || '';
                    const fakultetId = editBtn.dataset.fakultetId || '';
                    const kafedraId = editBtn.dataset.kafedraId || '';

                    editIdInput.value = id;
                    $('#fakultetSelect').val(fakultetId).trigger('change');
                    populateKafedraOptions(fakultetId, kafedraId);

                    form.querySelector('[name="fio"]').value = editBtn.dataset.fio || '';
                    form.querySelector('[name="lavozim"]').value = editBtn.dataset.lavozim || '';
                    $('#stavkaSelect').val(editBtn.dataset.stavka || '').trigger('change');
                    $('#ishturSelect').val(editBtn.dataset.ishturId || '').trigger('change');
                    form.querySelector('[name="ilmiy_unvon_id"]').value = editBtn.dataset.ilmiyUnvonId || '';
                    form.querySelector('[name="ilmiy_daraja_id"]').value = editBtn.dataset.ilmiyDarajaId || '';
                    toggleStavkaRequired();

                    modalTitle.textContent = "O'qituvchini tahrirlash";
                    saveBtn.textContent = 'Yangilash';
                    modal.classList.add('show');
                    return;
                }

                const deleteBtn = e.target.closest('.deleteOqituvchiBtn');
                if (!deleteBtn) return;

                const id = deleteBtn.dataset.id || '';
                if (!id) return;

                Swal.fire({
                    title: "Ishonchingiz komilmi?",
                    text: "O'qituvchi o'chiriladi",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ha, o'chirilsin",
                    cancelButtonText: "Bekor qilish"
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const formData = new FormData();
                    formData.append('id', id);

                    fetch('insert/delete_oqituvchi.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        Toast.fire({
                            icon: data.success ? 'success' : 'error',
                            title: data.message || (data.success ? "O'qituvchi o'chirildi" : "Xatolik yuz berdi")
                        });
                        if (data.success) {
                            loadOqituvchilar();
                        }
                    })
                    .catch(() => {
                        Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                    });
                });
            });
        }

        function initSearch() {
            const input = document.getElementById('searchOqituvchi');
            const table = document.getElementById('oqituvchilarTable');

            input.addEventListener('input', () => {
                const val = input.value.toLowerCase();
                table.querySelectorAll('tr').forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
                });
            });
        }

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000
        });

        document.getElementById('saveOqituvchiBtn').addEventListener('click', () => {
            const form = document.getElementById('oqituvchiForm');
            const editId = document.getElementById('oqituvchiEditId').value;

            if (!form.checkValidity()) {
                Toast.fire({ icon: 'error', title: "Barcha maydonlarni to'ldiring" });
                return;
            }

            const data = new FormData(form);
            if (editId) {
                data.append('id', editId);
            }

            const endpoint = editId ? 'insert/update_oqituvchi.php' : 'insert/add_oqituvchi.php';

            fetch(endpoint, {
                method: 'POST',
                body: data
            })
            .then(res => res.json())
            .then(r => {
                if (r.success) {
                    Toast.fire({ icon: 'success', title: r.message || "O‘qituvchi saqlandi" });
                    form.reset();
	                    document.getElementById('oqituvchiEditId').value = '';
	                    document.getElementById('oqituvchiModalTitle').textContent = "O‘qituvchi qo‘shish";
	                    document.getElementById('saveOqituvchiBtn').textContent = 'Saqlash';
	                    if (scopeLocked) {
	                        $('#fakultetSelect').val(String(lockedFakultetId)).trigger('change');
	                        populateKafedraOptions(lockedFakultetId, lockedKafedraId);
	                    } else {
	                        $('#fakultetSelect').val('').trigger('change');
	                        $('#kafedraSelect').val('').trigger('change');
	                    }
	                    $('#ishturSelect').val('').trigger('change');
                    $('#stavkaSelect').val('').trigger('change');
                    $('[name="ilmiy_unvon_id"]').val('');
                    $('[name="ilmiy_daraja_id"]').val('');
                    document.getElementById('oqituvchiModal').classList.remove('show');
                    loadOqituvchilar();
                } else {
                    Toast.fire({ icon: 'error', title: r.message });
                }
            })
            .catch(() => {
                Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
            });
        });
    </script>
</body>
</html>
