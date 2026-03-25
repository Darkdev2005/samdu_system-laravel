<?php

    include_once 'config.php';
    $db = new Database();
    $akademik_darajalar = $db->get_data_by_table_all('akademik_darajalar');
    $fakultetlar = $db->get_data_by_table_all('fakultetlar');
    $talim_shakllar = $db->get_data_by_table_all('talim_shakllar');
    $yonalishlarForFilter = $db->get_data_by_table_all('yonalishlar', 'ORDER BY name');
    $initialYunalishlar = $db->get_yunalishlar_with_details();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yo'nalishlar - n </title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .filter-select {
            min-width: 220px;
            max-width: 340px;
            flex: 1 1 220px;
            height: 44px;
            border: 1px solid #d8e2eb;
            border-radius: 12px;
            padding: 0 12px;
            font-size: 14px;
            background: #fff;
            color: #1f2937;
        }
        .table-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
            flex: 1 1 780px;
            min-width: 300px;
        }
        .filter-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filter-btn {
            height: 44px;
            border: none;
            border-radius: 10px;
            padding: 0 16px;
            font-weight: 600;
            cursor: pointer;
            white-space: nowrap;
        }
        .filter-btn.apply {
            background: #22c55e;
            color: #fff;
        }
        .filter-btn.reset {
            background: #eef2f7;
            color: #334155;
        }
        .search-box {
            flex: 1 1 240px;
            min-width: 220px;
            max-width: 320px;
        }
        .search-box input {
            width: 100%;
        }
        @media (max-width: 1200px) {
            .table-actions {
                flex: 1 1 100%;
                justify-content: flex-start;
            }
            .search-box {
                max-width: 100%;
            }
        }
        @media (max-width: 768px) {
            .filter-select {
                min-width: 100%;
                max-width: 100%;
                flex: 1 1 100%;
            }
            .filter-actions {
                width: 100%;
            }
            .filter-btn {
                flex: 1 1 140px;
            }
            .search-box {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-navbar">
                <div class="navbar-left">
                    <h1>Ta'lim Yo'nalishlari</h1>
                    <p class="navbar-subtitle">Yo'nalishlarni boshqarish bo'limi</p>
                </div>
                <div class="navbar-right">
                    <button class="btn btn-primary" id="addYonalishBtn">
                        <i class="fas fa-plus"></i> Yo'nalish qo'shish
                    </button>
                </div>
            </header>
            <div class="content-container">
                <!-- Yo'nalishlar jadvali -->
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Barcha Yo'nalishlar</h3>
                            <span class="badge" id="totalYonalishlar"><?= count($initialYunalishlar) ?> ta</span>
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
                                <?php foreach ($yonalishlarForFilter as $y): ?>
                                    <option value="<?= (int)($y['id'] ?? 0) ?>">
                                        <?= htmlspecialchars((string)($y['name'] ?? '')) ?><?= !empty($y['kirish_yili']) ? ' - '.htmlspecialchars((string)$y['kirish_yili']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="filter-actions">
                                <button type="button" class="filter-btn apply" id="applyFiltersBtn">Filtrlash</button>
                                <button type="button" class="filter-btn reset" id="resetFiltersBtn">Tozalash</button>
                            </div>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchYonalish" placeholder="Qidirish...">
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Yo'nalish Nomi</th>
                                    <th>Yo'nalish Kodi</th>
                                    <th>Ta'lim muddati (yil)</th>
                                    <th>Kirish yili</th>
                                    <th>Akademik daraja</th>
                                    <th>Ta'lim shakli</th>
                                    <th>Kvalifikatsiya</th>
                                    <th>Fakultet</th>
                                    <th>Yaratilgan sana</th>
                                    <th>Harakatlar</th>
                                </tr>
                            </thead>
                            <tbody id="yonalishlarTable">
                                <?php foreach ($initialYunalishlar as $yunalish): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($yunalish['id']); ?></td>
                                        <td><?php echo htmlspecialchars($yunalish['yonalish_nomi']); ?></td>
                                        <td><?php echo htmlspecialchars($yunalish['yonalish_kodi']); ?></td>
                                        <td><?php echo htmlspecialchars($yunalish['talim_muddati']); ?></td>
                                        <td><?php echo htmlspecialchars($yunalish['kirish_yili']); ?></td>
                                        <td><?php echo htmlspecialchars($yunalish['akademik_daraja']); ?></td>
                                        <td><?php echo htmlspecialchars($yunalish['talim_shakli']); ?></td>
                                        <td><?php echo htmlspecialchars($yunalish['kvalifikatsiya']); ?></td>
                                        <td><?php echo htmlspecialchars($yunalish['fakultet']); ?></td>
                                        <td><?php echo htmlspecialchars($yunalish['create_at']); ?></td>
                                        <td>
                                            <button
                                                class="btn btn-sm btn-warning editYonalishBtn"
                                                data-id="<?php echo $yunalish['id']; ?>"
                                                onclick="openYonalishEdit(<?php echo (int)$yunalish['id']; ?>)"
                                            >
                                                <i class="fas fa-edit"></i> Tahrirlash
                                            </button>
                                            <button class="btn btn-sm btn-danger deleteYunalishBtn" data-id="<?php echo $yunalish['id']; ?>">
                                                <i class="fas fa-trash-alt"></i> O'chirish
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($initialYunalishlar)): ?>
                                    <tr>
                                        <td colspan="11" style="text-align:center; color:#64748b;">Ma'lumot topilmadi</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Yo'nalishlar tahriri tarixi -->
                <div class="table-container mt-4">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Yo'nalish tahrirlar tarixi</h3>
                            <span class="badge" id="totalYonalishHistory">0 ta</span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Asl yo'nalish ID</th>
                                    <th>Yo'nalish nomi</th>
                                    <th>Yo'nalish kodi</th>
                                    <th>Patok</th>
                                    <th>Katta guruh</th>
                                    <th>Kichik guruh</th>
                                    <th>Akademik daraja</th>
                                    <th>Ta'lim shakli</th>
                                    <th>Fakultet</th>
                                    <th>Holat</th>
                                    <th>O'zgargan sana</th>
                                </tr>
                            </thead>
                            <tbody id="yonalishHistoryTable">
                                <!-- JavaScript orqali to'ldiriladi -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Yo'nalish qo'shish modal oynasi -->
    <div class="modal" id="yonalishModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Yo'nalish qo'shish</h3>
                <button class="modal-close" id="closeModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="yonalishForm">
                    <input type="hidden" id="yonalishEditId" value="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="yonalishNomi">
                                <i class="fas fa-compass"></i> Yo‘nalish nomi
                            </label>
                            <input type="text" id="yonalishNomi" placeholder="Dasturiy injiniring" required>
                        </div>
                        <div class="form-group">
                            <label for="yonalishCode">
                                <i class="fas fa-hashtag"></i> Yo‘nalish kodi
                            </label>
                            <input type="text" id="yonalishCode" placeholder="60610100" required>
                        </div>
                        <div class="form-group">
                            <label for="yonalishMuddati">
                                <i class="fas fa-clock"></i> Ta’lim muddati (yil)
                            </label>
                            <input type="number" id="yonalishMuddati" min="1" placeholder="4" required>
                        </div>
                        <div class="form-group">
                            <label for="kirishYili">
                                <i class="fas fa-calendar-plus"></i> Kirish yili
                            </label>
                            <input type="number" id="kirishYili" min="2000" max="<?= date('Y') ?>"
                                   placeholder="<?= date('Y') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="yonalishPatok">
                                <i class="fas fa-clock"></i> Patok soni
                            </label>
                            <input type="number" id="yonalishPatok" min="1" value='1' required>
                        </div>
                        <div class="form-group">
                            <label for="yonalishKattaguruh">
                                <i class="fas fa-clock"></i> Katta guruh soni
                            </label>
                            <input type="number" id="yonalishKattaguruh" min="1" placeholder="3" required>
                        </div>
                        <div class="form-group">
                            <label for="yonalishKichikguruh">
                                <i class="fas fa-clock"></i> Kichik guruh soni
                            </label>
                            <input type="number" id="yonalishKichikguruh" min="1" placeholder="2" required>
                        </div>
                        <div class="form-group">
                            <label for="akademikDarajaSelect">
                                <i class="fas fa-graduation-cap"></i> Akademik daraja
                            </label>
                            <select id="akademikDarajaSelect" required>
                                <option value="">Tanlang</option>
                                <?php foreach ($akademik_darajalar as $daraja): ?>
                                    <option value="<?= $daraja['id'] ?>">
                                        <?= htmlspecialchars($daraja['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="talimShakliSelect">
                                <i class="fas fa-chalkboard-user"></i> Ta’lim shakli
                            </label>
                            <select id="talimShakliSelect" required>
                                <option value="">Tanlang</option>
                                <?php foreach ($talim_shakllar as $shakl): ?>
                                    <option value="<?= $shakl['id'] ?>">
                                        <?= htmlspecialchars($shakl['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="kvalifikatsiya">
                                <i class="fas fa-user-graduate"></i> Kvalifikatsiya
                            </label>
                            <input type="text" id="kvalifikatsiya" placeholder="Bakalavr" required>
                        </div>
                        <div class="form-group">
                            <label for="fakultetSelect">
                                <i class="fas fa-building-columns"></i> Fakultet
                            </label>
                            <select id="fakultetSelect" required>
                                <option value="">Tanlang</option>
                                <?php foreach ($fakultetlar as $fakultet): ?>
                                    <option value="<?= $fakultet['id'] ?>">
                                        <?= htmlspecialchars($fakultet['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelBtn">
                    Bekor qilish
                </button>
                <button type="button" class="btn btn-primary" id="saveYonalishBtn">
                    Saqlash
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script id="yonalishFilterData" type="application/json"><?= json_encode(array_map(static fn($y) => [
        'id' => (int)($y['id'] ?? 0),
        'name' => (string)($y['name'] ?? ''),
        'fakultet_id' => (int)($y['fakultet_id'] ?? 0),
        'kirish_yili' => (string)($y['kirish_yili'] ?? ''),
    ], $yonalishlarForFilter), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]' ?></script>
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
        const hasSwal = typeof window.Swal !== 'undefined';
        const Toast = hasSwal
            ? Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true
            })
            : {
                fire({ title }) {
                    console.warn(title || 'Xabar');
                }
            };

        document.addEventListener('DOMContentLoaded', () => {
            setupModal();
            setupSearch();
            setupFilters();
            populateYonalishFilter();
            loadYonalishlarHistory();
        });
        function loadYonalishlar() {
            const params = new URLSearchParams({
                fakultet_id: document.getElementById('fakultetFilter')?.value || '',
                yonalish_id: document.getElementById('yonalishFilter')?.value || ''
            });
            fetch(`get/yunalishlar_table.php?${params.toString()}`)
                .then(res => res.text())
                .then(html => {
                    if (!/<tr[\s>]/i.test(html)) {
                        throw new Error('Invalid table response');
                    }
                    document.getElementById('yonalishlarTable').innerHTML = html;
                    const rows = Array.from(document.querySelectorAll('#yonalishlarTable tr'));
                    const total = rows.filter((row) => {
                        const text = (row.textContent || '').toLowerCase();
                        return !text.includes("ma'lumot topilmadi");
                    }).length;
                    document.getElementById('totalYonalishlar').textContent = total + ' ta';
                })
                .catch((err) => {
                    console.error(err);
                    Toast.fire({ icon: 'error', title: "Yo'nalishlar ro'yxatini yuklashda xatolik" });
                });
        }

        function populateYonalishFilter() {
            const fakultetId = document.getElementById('fakultetFilter')?.value || '';
            const yonalishSelect = document.getElementById('yonalishFilter');
            if (!yonalishSelect) return;

            const currentValue = yonalishSelect.value;
            let options = "<option value=\"\">Barcha yo'nalishlar</option>";
            allYonalishFilterItems
                .filter(item => !fakultetId || Number(item.fakultet_id) === Number(fakultetId))
                .forEach(item => {
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
                    if (yonalishFilter) {
                        yonalishFilter.value = '';
                    }
                    populateYonalishFilter();
                });
            }

            if (applyBtn) {
                applyBtn.addEventListener('click', () => {
                    loadYonalishlar();
                });
            }

            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    if (fakultetFilter) fakultetFilter.value = '';
                    if (yonalishFilter) yonalishFilter.value = '';
                    populateYonalishFilter();
                    loadYonalishlar();
                });
            }
        }

        function loadYonalishlarHistory() {
            fetch('get/yonalishlar_history_table.php')
                .then(res => res.text())
                .then(html => {
                    const tbody = document.getElementById('yonalishHistoryTable');
                    const safeHtml = (html || '').trim();
                    const noDataFromServer = /ma.?lumot topilmadi/i.test(safeHtml);
                    if (safeHtml.length > 0 && !noDataFromServer) {
                        tbody.innerHTML = safeHtml;
                    } else {
                        tbody.innerHTML = '<tr class="empty-row"><td colspan="12">Ma\'lumot topilmadi</td></tr>';
                    }
                    const totalRows = Array.from(tbody.querySelectorAll('tr')).filter((row) => {
                        const text = (row.textContent || '').toLowerCase();
                        return !row.classList.contains('empty-row') && !text.includes("ma'lumot topilmadi");
                    }).length;
                    document.getElementById('totalYonalishHistory').textContent = totalRows + ' ta';
                })
                .catch(() => {
                    document.getElementById('yonalishHistoryTable').innerHTML =
                        '<tr class="empty-row"><td colspan="12">Xatolik yuz berdi</td></tr>';
                    document.getElementById('totalYonalishHistory').textContent = '0 ta';
                });
        }
        function setupModal() {
            const addBtn = document.getElementById('addYonalishBtn');
            const modal = document.getElementById('yonalishModal');
            const closeBtn = document.getElementById('closeModal');
            const cancelBtn = document.getElementById('cancelBtn');

            if (addBtn) {
                addBtn.addEventListener('click', () => {
                    document.getElementById('modalTitle').textContent = "Yo'nalish qo'shish";
                    document.getElementById('saveYonalishBtn').textContent = "Saqlash";
                    document.getElementById('yonalishEditId').value = '';
                    document.getElementById('yonalishForm').reset();
                    modal.classList.add('show');
                });
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    modal.classList.remove('show');
                });
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    modal.classList.remove('show');
                });
            }

            // Modal tashqarisiga bosilganda yopilishi
            window.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });

        }

        // Izoh: Jadvaldagi "Tahrirlash" tugmasi shu global funksiyani chaqiradi.
        function openYonalishEdit(id) {
            const modal = document.getElementById('yonalishModal');
            if (!id) return;

            fetch(`get/yonalish_one.php?id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success || !data.data) {
                        Toast.fire({ icon: 'error', title: data.message || "Yo'nalish topilmadi" });
                        return;
                    }

                    const row = data.data;
                    document.getElementById('yonalishEditId').value = row.id || '';
                    document.getElementById('yonalishNomi').value = row.name || '';
                    document.getElementById('yonalishCode').value = row.code || '';
                    document.getElementById('yonalishMuddati').value = row.muddati || '';
                    document.getElementById('kirishYili').value = row.kirish_yili || '';
                    document.getElementById('yonalishPatok').value = row.patok_soni || 1;
                    document.getElementById('yonalishKattaguruh').value = row.kattaguruh_soni || '';
                    document.getElementById('yonalishKichikguruh').value = row.kichikguruh_soni || '';
                    document.getElementById('akademikDarajaSelect').value = row.akademik_daraja_id || '';
                    document.getElementById('talimShakliSelect').value = row.talim_shakli_id || '';
                    document.getElementById('kvalifikatsiya').value = row.kvalifikatsiya || '';
                    document.getElementById('fakultetSelect').value = row.fakultet_id || '';

                    document.getElementById('modalTitle').textContent = "Yo'nalishni tahrirlash";
                    document.getElementById('saveYonalishBtn').textContent = "Yangilash";
                    modal.classList.add('show');
                })
                .catch(() => {
                    Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                });
        }
        window.openYonalishEdit = openYonalishEdit;

        document.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.editYonalishBtn, .editYunalishBtn');
            if (editBtn) {
                const id = editBtn.dataset.id || '';
                if (id) {
                    openYonalishEdit(Number(id));
                }
                return;
            }

            const deleteBtn = e.target.closest('.deleteYunalishBtn');
            if (!deleteBtn) return;

            const id = deleteBtn.dataset.id || '';
            if (!id) return;

            const doDelete = () => {
                const formData = new FormData();
                formData.append('id', id);

                fetch('insert/delete_yonalish.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    Toast.fire({
                        icon: data.success ? 'success' : 'error',
                        title: data.message || (data.success ? "Yo'nalish o'chirildi" : "Xatolik yuz berdi")
                    });
                    if (data.success) {
                        loadYonalishlar();
                        loadYonalishlarHistory();
                    }
                })
                .catch(() => {
                    Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                });
            };

            if (hasSwal) {
                Swal.fire({
                    title: "Ishonchingiz komilmi?",
                    text: "Yo'nalish o'chiriladi",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ha, o'chirilsin",
                    cancelButtonText: "Bekor qilish"
                }).then((result) => {
                    if (!result.isConfirmed) return;
                    doDelete();
                });
            } else if (window.confirm("Yo'nalish o'chiriladi. Davom etilsinmi?")) {
                doDelete();
            }
        });

        function setupSearch() {
            const searchInput = document.getElementById('searchYonalish');

            if (!searchInput) return;

            searchInput.addEventListener('input', () => {
                const value = searchInput.value.toLowerCase();
                const rows = document.querySelectorAll('#yonalishlarTable tr');

                rows.forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(value)
                        ? ''
                        : 'none';
                });
            });
        }

        let isSavingYonalish = false;
        document.getElementById('saveYonalishBtn').addEventListener('click', async () => {
            if (isSavingYonalish) return;
            isSavingYonalish = true;
            const saveBtn = document.getElementById('saveYonalishBtn');
            const oldBtnText = saveBtn.textContent;
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saqlanmoqda...';

            const editId = document.getElementById('yonalishEditId').value;
            const yonalishNomi        = document.getElementById('yonalishNomi').value.trim();
            const yonalishCode        = document.getElementById('yonalishCode').value.trim();
            const yonalishMuddati     = document.getElementById('yonalishMuddati').value;
            const akademikDarajaId   = document.getElementById('akademikDarajaSelect').value;
            const talimShakliId      = document.getElementById('talimShakliSelect').value;
            const kvalifikatsiya     = document.getElementById('kvalifikatsiya').value.trim();
            const fakultetId         = document.getElementById('fakultetSelect').value;
            const kirishYili         = document.getElementById('kirishYili').value;
            const yonalishPatok       = document.getElementById('yonalishPatok').value;
            const yonalishKattaguruh  = document.getElementById('yonalishKattaguruh').value;
            const yonalishKichikguruh = document.getElementById('yonalishKichikguruh').value;

            const formData = new FormData();
            formData.append('nomi', yonalishNomi);
            formData.append('code', yonalishCode);
            formData.append('muddati', yonalishMuddati);
            formData.append('akademik_daraja_id', akademikDarajaId);
            formData.append('talim_shakli_id', talimShakliId);
            formData.append('kvalifikatsiya', kvalifikatsiya);
            formData.append('fakultet_id', fakultetId);
            formData.append('kirish_yili', kirishYili);
            formData.append('patok_soni', yonalishPatok);
            formData.append('kattaguruh_soni', yonalishKattaguruh);
            formData.append('kichikguruh_soni', yonalishKichikguruh);
            if (editId) {
                formData.append('id', editId);
            }

            if (editId) {
                if (hasSwal) {
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
                        isSavingYonalish = false;
                        saveBtn.disabled = false;
                        saveBtn.textContent = oldBtnText;
                        return;
                    }

                    formData.append('sync_mode', syncDecision.isConfirmed ? 'sync' : 'nosync');
                } else {
                    const syncMode = window.confirm("Tahrir sinxronlangan holatda saqlansinmi?")
                        ? 'sync'
                        : 'nosync';
                    formData.append('sync_mode', syncMode);
                }
            }

            const endpoint = editId ? 'insert/update_yonalish.php' : 'insert/add_yonalish.php';

            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Toast.fire({
                        icon: 'success',
                        title: data.message || "Yo'nalish muvaffaqiyatli saqlandi"
                    });

                    document.getElementById('yonalishModal')?.classList.remove('show');

                    document.getElementById('yonalishForm').reset();
                    document.getElementById('yonalishEditId').value = '';
                    document.getElementById('modalTitle').textContent = "Yo'nalish qo'shish";
                    document.getElementById('saveYonalishBtn').textContent = "Saqlash";
                    loadYonalishlar();
                    loadYonalishlarHistory();

                } else {
                    Toast.fire({
                        icon: 'error',
                        title: data.message || "Xatolik yuz berdi"
                    });
                }
            })
            .catch(() => {
                Toast.fire({
                    icon: 'error',
                    title: "Server bilan bog'lanib bo'lmadi"
                });
            })
            .finally(() => {
                isSavingYonalish = false;
                saveBtn.disabled = false;
                saveBtn.textContent = oldBtnText;
            });
        });
        </script>

</body>
</html>
