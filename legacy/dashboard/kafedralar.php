<?php

include_once 'config.php';
$db = new Database();
$fakultetlar = $db->get_data_by_table_all('fakultetlar');
?>
<!DOCTYPE html>
<html lang="uz">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kafedralar - O'quv Qo'lanma</title>

    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .table-header .table-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            min-width: 0;
        }
        .filter-select {
            min-width: 220px;
            max-width: 320px;
            flex: 1 1 240px;
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
            flex: 0 0 auto;
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
        .table-header .search-box {
            min-width: 220px;
            flex: 1 1 240px;
            max-width: 320px;
        }
        .table-header .search-box input {
            width: 100%;
        }
        @media (max-width: 1200px) {
            .table-header .table-actions {
                justify-content: flex-start;
                width: 100%;
            }
            .filter-select {
                max-width: none;
            }
        }
        @media (max-width: 768px) {
            .filter-select,
            .table-header .search-box {
                min-width: 100%;
                flex-basis: 100%;
            }
            .filter-actions {
                width: 100%;
            }
            .filter-actions .filter-btn {
                flex: 1 1 calc(50% - 4px);
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
                    <h1>Kafedralar</h1>
                    <p class="navbar-subtitle">Kafedralarni boshqarish bo‘limi</p>
                </div>
                <div class="navbar-right">
                    <button class="btn btn-primary" id="addKafedraBtn">
                        <i class="fas fa-plus"></i> Kafedra qo‘shish
                    </button>
                </div>
            </header>

            <div class="content-container">
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Barcha kafedralar</h3>
                            <span class="badge" id="totalKafedralar">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <select id="filterFakultet" class="filter-select">
                                <option value="">Barcha fakultetlar</option>
                                <?php foreach ($fakultetlar as $fakultet): ?>
                                    <option value="<?= (int)$fakultet['id'] ?>">
                                        <?= htmlspecialchars($fakultet['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="filter-actions">
                                <button type="button" class="filter-btn apply" id="applyKafedraFilterBtn">Filtrlash</button>
                                <button type="button" class="filter-btn reset" id="resetKafedraFilterBtn">Tozalash</button>
                            </div>
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchKafedra" placeholder="Qidirish...">
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Kafedra nomi</th>
                                    <th>Fakultet nomi</th>
                                    <th>Yaratilgan sana</th>
                                    <th>Harakatlar</th>
                                </tr>
                            </thead>
                            <tbody id="kafedralarTable">
                                <!-- AJAX orqali -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL -->
    <div class="modal" id="kafedraModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="kafedraModalTitle">Kafedra qo‘shish</h3>
                <button class="modal-close" id="closeKafedraModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <form id="kafedraForm">
                    <input type="hidden" id="kafedraEditId" value="">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-sitemap"></i> Kafedra nomi
                        </label>
                        <input type="text" id="kafedraName" placeholder="Masalan: Axborot texnologiyalari" required>
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
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelKafedraBtn">Bekor qilish</button>
                <button class="btn btn-primary" id="saveKafedraBtn">Saqlash</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/app.js"></script>

    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });
        let currentKafedraSearch = '';

        document.addEventListener('DOMContentLoaded', () => {
            initKafedraModal();
            initKafedraSearch();
            initKafedraFilter();
            loadKafedralar();
        });

        function isNoDataRow(row) {
            return !!row.querySelector('td[colspan]');
        }

        function updateKafedraCount() {
            const rows = Array.from(document.querySelectorAll('#kafedralarTable tr'));
            const visibleRows = rows.filter((row) => !isNoDataRow(row) && row.style.display !== 'none').length;
            document.getElementById('totalKafedralar').textContent = visibleRows + ' ta';
        }

        function applyKafedraSearch() {
            const table = document.getElementById('kafedralarTable');
            if (!table) return;

            const value = (currentKafedraSearch || '').trim().toLowerCase();
            const rows = Array.from(table.querySelectorAll('tr'));

            rows.forEach((row) => {
                if (isNoDataRow(row)) {
                    row.style.display = '';
                    return;
                }
                row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
            });

            const hasVisibleData = rows.some((row) => !isNoDataRow(row) && row.style.display !== 'none');
            rows.forEach((row) => {
                if (isNoDataRow(row)) {
                    row.style.display = hasVisibleData ? 'none' : '';
                }
            });

            updateKafedraCount();
        }

        function loadKafedralar() {
            const fakultetId = document.getElementById('filterFakultet')?.value || '';
            const url = fakultetId ?
                `get/kafedralar_table.php?fakultet_id=${encodeURIComponent(fakultetId)}` :
                'get/kafedralar_table.php';

            fetch(url)
                .then(res => res.text())
                .then(html => {
                    document.getElementById('kafedralarTable').innerHTML = html;
                    applyKafedraSearch();
                })
                .catch(() => {
                    document.getElementById('kafedralarTable').innerHTML =
                        '<tr><td colspan="5">Xatolik yuz berdi</td></tr>';
                    updateKafedraCount();
                });
        }

       
        function initKafedraModal() {
            const modal = document.getElementById('kafedraModal');
            const modalTitle = document.getElementById('kafedraModalTitle');
            const editIdInput = document.getElementById('kafedraEditId');
            const saveBtn = document.getElementById('saveKafedraBtn');
            const form = document.getElementById('kafedraForm');

            document.getElementById('addKafedraBtn').onclick = () => {
                modalTitle.textContent = 'Kafedra qo‘shish';
                saveBtn.textContent = 'Saqlash';
                editIdInput.value = '';
                form.reset();
                modal.classList.add('show');
            };
            document.getElementById('closeKafedraModal').onclick = () => modal.classList.remove('show');
            document.getElementById('cancelKafedraBtn').onclick = () => modal.classList.remove('show');

            window.onclick = (e) => {
                if (e.target === modal) modal.classList.remove('show');
            };

            document.addEventListener('click', (e) => {
                const editBtn = e.target.closest('.editKafedraBtn');
                if (editBtn) {
                    editIdInput.value = editBtn.dataset.id || '';
                    document.getElementById('kafedraName').value = editBtn.dataset.name || '';
                    document.getElementById('fakultetSelect').value = editBtn.dataset.fakultetId || '';
                    modalTitle.textContent = 'Kafedrani tahrirlash';
                    saveBtn.textContent = 'Yangilash';
                    modal.classList.add('show');
                    return;
                }

                const deleteBtn = e.target.closest('.deleteKafedraBtn');
                if (!deleteBtn) return;

                const id = deleteBtn.dataset.id || '';
                if (!id) return;

                Swal.fire({
                    title: "Ishonchingiz komilmi?",
                    text: "Kafedra o'chiriladi",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ha, o'chirilsin",
                    cancelButtonText: "Bekor qilish"
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const formData = new FormData();
                    formData.append('id', id);

                    fetch('insert/delete_kafedra.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            Toast.fire({
                                icon: data.success ? 'success' : 'error',
                                title: data.message || (data.success ? "Kafedra o'chirildi" : "Xatolik yuz berdi")
                            });
                            if (data.success) {
                                loadKafedralar();
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
        }

        function initKafedraFilter() {
            const filter = document.getElementById('filterFakultet');
            const applyBtn = document.getElementById('applyKafedraFilterBtn');
            const resetBtn = document.getElementById('resetKafedraFilterBtn');
            const searchInput = document.getElementById('searchKafedra');
            if (!filter) return;

            filter.addEventListener('change', () => {
                loadKafedralar();
            });

            if (applyBtn) {
                applyBtn.addEventListener('click', () => {
                    loadKafedralar();
                });
            }

            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    filter.value = '';
                    currentKafedraSearch = '';
                    if (searchInput) {
                        searchInput.value = '';
                    }
                    loadKafedralar();
                });
            }
        }

        function initKafedraSearch() {
            const input = document.getElementById('searchKafedra');
            input.addEventListener('input', () => {
                currentKafedraSearch = input.value || '';
                applyKafedraSearch();
            });
        }

        document.getElementById('saveKafedraBtn').addEventListener('click', () => {
            const name = document.getElementById('kafedraName').value.trim();
            const fakultetId = document.getElementById('fakultetSelect').value;
            const editId = document.getElementById('kafedraEditId').value;

            if (!name || !fakultetId) {
                Toast.fire({
                    icon: 'error',
                    title: "Kafedra nomi va fakultetni kiriting!"
                });
                return;
            }

            const formData = new FormData();
            formData.append('nomi', name);
            formData.append('fakultet_id', fakultetId);
            if (editId) {
                formData.append('id', editId);
            }

            const endpoint = editId ? 'insert/update_kafedra.php' : 'insert/add_kafedra.php';

            fetch(endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Toast.fire({
                            icon: 'success',
                            title: data.message
                        });
                        document.getElementById('kafedraModal').classList.remove('show');
                        document.getElementById('kafedraForm').reset();
                        document.getElementById('kafedraEditId').value = '';
                        document.getElementById('kafedraModalTitle').textContent = 'Kafedra qo‘shish';
                        document.getElementById('saveKafedraBtn').textContent = 'Saqlash';
                        loadKafedralar();
                    } else {
                        Toast.fire({
                            icon: 'error',
                            title: data.message
                        });
                    }
                })
                .catch(() => {
                    Toast.fire({
                        icon: 'error',
                        title: 'Server bilan bog‘lanib bo‘lmadi'
                    });
                });
        });
    </script>

</body>

</html>
