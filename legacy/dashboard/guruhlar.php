<?php
include_once 'config.php';
$db = new Database();
$yonalishlar = $db->get_data_by_table_all('yonalishlar', 'ORDER BY name, kirish_yili');
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
                            <span class="badge" id="totalGuruhlar">0 ta</span>
                        </div>
                        <div class="table-actions">
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

    <script>
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

        function loadGuruhlar() {
            fetch('get/guruhlar_table.php')
                .then(res => res.text())
                .then(html => {
                    document.getElementById('guruhlarTable').innerHTML = html;
                    document.getElementById('totalGuruhlar').textContent =
                        document.getElementById('guruhlarTable').children.length + ' ta';
                })
                .catch(() => {
                    document.getElementById('guruhlarTable').innerHTML =
                        '<tr><td colspan="6">Xatolik yuz berdi</td></tr>';
                });
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
            const table = document.getElementById('guruhlarTable');

            input.addEventListener('input', () => {
                const val = input.value.toLowerCase();
                table.querySelectorAll('tr').forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
                });
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
