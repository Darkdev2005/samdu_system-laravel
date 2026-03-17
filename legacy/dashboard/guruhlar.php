<?php
include_once 'config.php';
$db = new Database();
$fakultetlar = $db->get_data_by_table_all('fakultetlar', 'ORDER BY name');
$yonalishlar = [];
$yonalishRes = $db->query("
    SELECT
        y.id,
        y.name,
        y.kirish_yili,
        MAX(s.fakultet_id) AS fakultet_id
    FROM yonalishlar y
    LEFT JOIN semestrlar s ON s.yonalish_id = y.id
    GROUP BY y.id, y.name, y.kirish_yili
    ORDER BY y.name, y.kirish_yili
");
if ($yonalishRes) {
    while ($row = mysqli_fetch_assoc($yonalishRes)) {
        $yonalishlar[] = $row;
    }
}
if (empty($yonalishlar)) {
    $yonalishlar = $db->get_data_by_table_all('yonalishlar', 'ORDER BY name, kirish_yili');
}
$yonalishFilterJson = json_encode(
    array_values(
        array_map(
            static fn(array $row): array => [
                'id' => (int)($row['id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'fakultet_id' => (int)($row['fakultet_id'] ?? 0),
                'kirish_yili' => (string)($row['kirish_yili'] ?? ''),
            ],
            $yonalishlar
        )
    ),
    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);
if ($yonalishFilterJson === false) {
    $yonalishFilterJson = '[]';
}
$initialGuruhlar = $db->get_guruhlar();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guruhlar - O‘quv Qo‘llanma</title>

    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/vendor/select2/css/select2.min.css" rel="stylesheet">
    <style>
        .filter-select {
            min-width: 220px;
            height: 44px;
            border: 1px solid #d8e2eb;
            border-radius: 12px;
            padding: 0 12px;
            font-size: 14px;
            background: #fff;
            color: #1f2937;
        }
        .filter-actions {
            display: flex;
            gap: 8px;
        }
        .filter-btn {
            height: 44px;
            border: none;
            border-radius: 10px;
            padding: 0 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .filter-btn.apply {
            background: #22c55e;
            color: #fff;
        }
        .filter-btn.reset {
            background: #eef2f7;
            color: #334155;
        }
        #guruhModal .select2-container {
            width: 100% !important;
        }
        #guruhModal .select2-container--default .select2-selection--single {
            height: 44px;
            border: 1px solid #d8e2eb;
            border-radius: 10px;
        }
        #guruhModal .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px;
            padding-left: 12px;
        }
        #guruhModal .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px;
            right: 8px;
        }
    </style>
</head>
<body>

    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-navbar">
                <div class="navbar-left">
                    <h1>Guruhlar</h1>
                    <p class="navbar-subtitle">Guruhlarni boshqarish bo‘limi</p>
                </div>
                <div class="navbar-right">
                    <button class="btn btn-primary" id="addGuruhBtn">
                        <i class="fas fa-plus"></i> Guruh qo‘shish
                    </button>
                </div>
            </header>

            <div class="content-container">
                <div class="table-container">

                    <div class="table-header">
                        <div class="table-title">
                            <h3>Barcha guruhlar</h3>
                            <span class="badge" id="totalGuruhlar"><?= count($initialGuruhlar) ?> ta</span>
                        </div>
                        <div class="table-actions">
                            <select id="fakultetFilter" class="filter-select">
                                <option value="">Barcha fakultetlar</option>
                                <?php foreach ($fakultetlar as $fakultet): ?>
                                    <option value="<?= (int)$fakultet['id'] ?>"><?= htmlspecialchars($fakultet['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select id="yonalishFilter" class="filter-select">
                                <option value="">Barcha yo'nalishlar</option>
                                <?php foreach ($yonalishlar as $y): ?>
                                    <option value="<?= (int)($y['id'] ?? 0) ?>">
                                        <?= htmlspecialchars((string)($y['name'] ?? '')) ?><?= !empty($y['kirish_yili']) ? ' - ' . htmlspecialchars((string)$y['kirish_yili']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="filter-actions">
                                <button type="button" class="filter-btn apply" id="applyFiltersBtn">Filtrlash</button>
                                <button type="button" class="filter-btn reset" id="resetFiltersBtn">Tozalash</button>
                            </div>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchGuruh" placeholder="Qidirish...">
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Yo‘nalish</th>
                                <th>Guruh nomeri</th>
                                <th>Talaba soni</th>
                                <th>Yaratilgan sana</th>
                                <th>Harakatlar</th>
                            </tr>
                            </thead>
                            <tbody id="guruhlarTable">
                                <!-- fetch orqali -->
                            </tbody>
                        </table>
                    </div>

                </div>

                <div class="table-container mt-4">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Guruh tahrirlar tarixi</h3>
                            <span class="badge" id="totalGuruhHistory">0 ta</span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Asl guruh ID</th>
                                    <th>Yo'nalish</th>
                                    <th>Guruh nomeri</th>
                                    <th>Talaba soni</th>
                                    <th>Holat</th>
                                    <th>O'zgargan sana</th>
                                </tr>
                            </thead>
                            <tbody id="guruhHistoryTable">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="modal" id="guruhModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="guruhModalTitle">Guruh qo'shish</h3>
                <button class="modal-close" id="closeGuruhModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <form id="guruhForm">
                    <input type="hidden" id="guruhEditId" value="">

                    <div class="form-group">
                        <label>
                            <i class="fas fa-graduation-cap"></i> Yo‘nalish
                        </label>
                        <select class="form-control" id="yonalishSelect" name="yonalish_id" required>
                            <option value="">Tanlang</option>
                            <?php foreach ($yonalishlar as $y): ?>
                                <option value="<?= $y['id'] ?>">
                                    <?= htmlspecialchars($y['name']) ?><?= !empty($y['kirish_yili']) ? ' - ' . htmlspecialchars((string)$y['kirish_yili']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-users"></i> Guruh nomeri
                        </label>
                        <input type="text" class="form-control" name="guruh_nomi" placeholder="DI-101" required>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-user-friends"></i> Talaba soni
                        </label>
                        <input type="number" class="form-control" name="talaba_soni" min="1" required>
                    </div>

                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelGuruhBtn">Bekor qilish</button>
                <button class="btn btn-primary" id="saveGuruhBtn">Saqlash</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/vendor/jquery/jquery-3.6.0.min.js"></script>
    <script src="../assets/vendor/select2/js/select2.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script id="yonalishFilterData" type="application/json"><?= json_encode(
        json_decode($yonalishFilterJson, true) ?? [],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?: '[]' ?></script>

    <script>
        const yonalishFilterDataEl = document.getElementById('yonalishFilterData');
        let allYonalishFilterItems = [];
        try {
            allYonalishFilterItems = JSON.parse(yonalishFilterDataEl ? yonalishFilterDataEl.textContent : '[]');
            if (!Array.isArray(allYonalishFilterItems)) {
                allYonalishFilterItems = [];
            }
        } catch (e) {
            allYonalishFilterItems = [];
        }

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });

        document.addEventListener('DOMContentLoaded', () => {
            initGuruhModal();
            initGuruhSearch();
            setupFilters();
            populateYonalishFilter();
            loadGuruhlar();
            loadGuruhlarHistory();
            initYonalishSelect();
        });

        function initYonalishSelect() {
            if (typeof window.jQuery === 'undefined') return;
            const $select = $('#yonalishSelect');
            const $modal = $('#guruhModal');
            if (!$select.length || !$modal.length || typeof $select.select2 !== 'function') return;

            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }

            $select.select2({
                placeholder: "Yo'nalishni qidiring...",
                allowClear: true,
                width: '100%',
                dropdownParent: $modal,
            });
        }

        function applyGuruhSearch() {
            const input = document.getElementById('searchGuruh');
            const table = document.getElementById('guruhlarTable');
            if (!input || !table) return;

            const val = (input.value || '').toLowerCase();
            table.querySelectorAll('tr').forEach((row) => {
                row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
            });
        }

        function loadGuruhlar() {
            const params = new URLSearchParams({
                fakultet_id: document.getElementById('fakultetFilter')?.value || '',
                yonalish_id: document.getElementById('yonalishFilter')?.value || ''
            });
            fetch(`get/guruhlar_table.php?${params.toString()}`)
                .then(res => res.text())
                .then(html => {
                    const tableBody = document.getElementById('guruhlarTable');
                    tableBody.innerHTML = html;

                    const rows = Array.from(tableBody.querySelectorAll('tr'));
                    const total = rows.filter((row) => {
                        const text = (row.textContent || '').toLowerCase();
                        return !text.includes("ma'lumot topilmadi");
                    }).length;
                    document.getElementById('totalGuruhlar').textContent = total + ' ta';
                    applyGuruhSearch();
                })
                .catch(() => {
                    document.getElementById('guruhlarTable').innerHTML =
                        '<tr><td colspan="6">Xatolik yuz berdi</td></tr>';
                });
        }

        function populateYonalishFilter() {
            const fakultetId = document.getElementById('fakultetFilter')?.value || '';
            const yonalishSelect = document.getElementById('yonalishFilter');
            if (!yonalishSelect) return;

            const currentValue = yonalishSelect.value;
            let options = "<option value=\"\">Barcha yo'nalishlar</option>";

            allYonalishFilterItems
                .filter((item) => !fakultetId || Number(item.fakultet_id) === Number(fakultetId))
                .forEach((item) => {
                    const label = item.kirish_yili ? `${item.name} - ${item.kirish_yili}` : item.name;
                    const selected = String(item.id) === String(currentValue) ? 'selected' : '';
                    options += `<option value=\"${item.id}\" ${selected}>${label}</option>`;
                });

            yonalishSelect.innerHTML = options;
        }

        function setupFilters() {
            const fakultetFilter = document.getElementById('fakultetFilter');
            const yonalishFilter = document.getElementById('yonalishFilter');
            const applyBtn = document.getElementById('applyFiltersBtn');
            const resetBtn = document.getElementById('resetFiltersBtn');

            if (fakultetFilter) {
                fakultetFilter.addEventListener('change', () => {
                    if (yonalishFilter) yonalishFilter.value = '';
                    populateYonalishFilter();
                });
            }

            if (applyBtn) {
                applyBtn.addEventListener('click', () => {
                    loadGuruhlar();
                });
            }

            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    if (fakultetFilter) fakultetFilter.value = '';
                    if (yonalishFilter) yonalishFilter.value = '';
                    populateYonalishFilter();
                    loadGuruhlar();
                });
            }
        }

        function loadGuruhlarHistory() {
            fetch('get/guruhlar_history_table.php')
                .then(res => res.text())
                .then(html => {
                    const tbody = document.getElementById('guruhHistoryTable');
                    tbody.innerHTML = html || '<tr><td colspan="7">Ma\'lumot topilmadi</td></tr>';
                    document.getElementById('totalGuruhHistory').textContent = tbody.children.length + ' ta';
                })
                .catch(() => {
                    document.getElementById('guruhHistoryTable').innerHTML =
                        '<tr><td colspan="7">Xatolik yuz berdi</td></tr>';
                });
        }

        function initGuruhModal() {
            const modal = document.getElementById('guruhModal');

            document.getElementById('addGuruhBtn').onclick = () => {
                document.getElementById('guruhModalTitle').textContent = "Guruh qo'shish";
                document.getElementById('saveGuruhBtn').textContent = "Saqlash";
                document.getElementById('guruhEditId').value = '';
                document.getElementById('guruhForm').reset();
                if (typeof window.jQuery !== 'undefined') {
                    $('#yonalishSelect').val('').trigger('change.select2');
                }
                modal.classList.add('show');
            };
            document.getElementById('closeGuruhModal').onclick =
            document.getElementById('cancelGuruhBtn').onclick = () => modal.classList.remove('show');

            window.onclick = e => { if (e.target === modal) modal.classList.remove('show'); };

            // Izoh: Guruhni tahrirlash uchun jadvaldan yozuvni modalga yuklaymiz.
            document.addEventListener('click', (e) => {
                const editBtn = e.target.closest('.editGuruhBtn');
                if (editBtn) {
                    const id = editBtn.dataset.id || '';
                    if (!id) return;

                    fetch(`get/guruh_one.php?id=${id}`)
                        .then(res => res.json())
                        .then(data => {
                            if (!data.success || !data.data) {
                                Toast.fire({ icon: 'error', title: data.message || "Guruh topilmadi" });
                                return;
                            }

                            const row = data.data;
                            document.getElementById('guruhEditId').value = row.id || '';
                            document.querySelector('select[name="yonalish_id"]').value = row.yonalish_id || '';
                            if (typeof window.jQuery !== 'undefined') {
                                $('#yonalishSelect').val(String(row.yonalish_id || '')).trigger('change.select2');
                            }
                            document.querySelector('input[name="guruh_nomi"]').value = row.guruh_nomer || '';
                            document.querySelector('input[name="talaba_soni"]').value = row.soni || '';

                            document.getElementById('guruhModalTitle').textContent = "Guruhni tahrirlash";
                            document.getElementById('saveGuruhBtn').textContent = "Yangilash";
                            modal.classList.add('show');
                        })
                        .catch(() => {
                            Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                        });
                    return;
                }

                const deleteBtn = e.target.closest('.deleteGuruhBtn');
                if (!deleteBtn) return;

                const id = deleteBtn.dataset.id || '';
                if (!id) return;

                Swal.fire({
                    title: "Ishonchingiz komilmi?",
                    text: "Guruh o'chiriladi",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ha, o'chirilsin",
                    cancelButtonText: "Bekor qilish"
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const formData = new FormData();
                    formData.append('id', id);

                    fetch('insert/delete_guruh.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        Toast.fire({
                            icon: data.success ? 'success' : 'error',
                            title: data.message || (data.success ? "Guruh o'chirildi" : "Xatolik yuz berdi")
                        });
                        if (data.success) {
                            loadGuruhlar();
                            loadGuruhlarHistory();
                        }
                    })
                    .catch(() => {
                        Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                    });
                });
            });
        }

        function initGuruhSearch() {
            const input = document.getElementById('searchGuruh');
            if (!input) return;

            input.addEventListener('input', () => {
                applyGuruhSearch();
            });
        }

        let isSavingGuruh = false;
        document.getElementById('saveGuruhBtn').addEventListener('click', async () => {
            if (isSavingGuruh) return;
            isSavingGuruh = true;

            const saveBtn = document.getElementById('saveGuruhBtn');
            const oldBtnText = saveBtn.textContent;
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saqlanmoqda...';

            const form = document.getElementById('guruhForm');
            const formData = new FormData(form);
            const editId = document.getElementById('guruhEditId').value;

            if (!form.checkValidity()) {
                Toast.fire({ icon: 'error', title: "Barcha maydonlarni to'ldiring!" });
                isSavingGuruh = false;
                saveBtn.disabled = false;
                saveBtn.textContent = oldBtnText;
                return;
            }

            if (editId) {
                formData.append('id', editId);
            }

            if (editId) {
                const syncDecision = await Swal.fire({
                    title: "Sinxronlash holatini tanlang",
                    text: "Bu tahrir sinxronlangan holatda saqlansinmi?",
                    icon: "question",
                    showCancelButton: true,
                    showDenyButton: true,
                    confirmButtonText: "Ha, sinxronlansin",
                    denyButtonText: "Yo'q, sinxronlanmasin",
                    cancelButtonText: "Bekor qilish"
                });

                if (syncDecision.isDismissed) {
                    isSavingGuruh = false;
                    saveBtn.disabled = false;
                    saveBtn.textContent = oldBtnText;
                    return;
                }

                formData.append('sync_mode', syncDecision.isConfirmed ? 'sync' : 'nosync');
            }

            const endpoint = editId ? 'insert/update_guruh.php' : 'insert/add_guruh.php';

            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Toast.fire({ icon: 'success', title: data.message });
                    form.reset();
                    if (typeof window.jQuery !== 'undefined') {
                        $('#yonalishSelect').val('').trigger('change.select2');
                    }
                    document.getElementById('guruhEditId').value = '';
                    document.getElementById('guruhModalTitle').textContent = "Guruh qo'shish";
                    document.getElementById('saveGuruhBtn').textContent = "Saqlash";
                    document.getElementById('guruhModal').classList.remove('show');
                    loadGuruhlar();
                    loadGuruhlarHistory();
                } else {
                    Toast.fire({ icon: 'error', title: data.message });
                }
            })
            .catch(() => {
                Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
            })
            .finally(() => {
                isSavingGuruh = false;
                saveBtn.disabled = false;
                saveBtn.textContent = oldBtnText;
            });
        });

    </script>

</body>
</html>
