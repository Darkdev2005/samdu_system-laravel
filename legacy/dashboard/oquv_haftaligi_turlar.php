<?php
include_once 'config.php';
$db = new Database();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O‘quv haftaligi turlari - O‘quv Bo‘limi</title>

    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-navbar">
                <div class="navbar-left">
                    <h1>O‘quv haftaligi turlari</h1>
                    <p class="navbar-subtitle">O‘quv haftaligi turlarini boshqarish</p>
                </div>
                <div class="navbar-right">
                    <button class="btn btn-primary" id="addHaftalikBtn">
                        <i class="fas fa-plus"></i> Qo‘shish
                    </button>
                </div>
            </header>

            <div class="content-container">
                <div class="table-container">

                    <div class="table-header">
                        <div class="table-title">
                            <h3>Barcha o‘quv haftaligi turlari</h3>
                            <span class="badge" id="totalHaftalik">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchHaftalik" placeholder="Qidirish...">
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nomi</th>
                                <th>Qisqartma</th>
                                <th>Yaratilgan sana</th>
                                <th>Harakatlar</th>
                            </tr>
                            </thead>
                            <tbody id="haftalikTable">
                                <!-- fetch orqali -->
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <div class="modal" id="haftalikModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="haftalikModalTitle">O‘quv haftaligi turi qo‘shish</h3>
                <button class="modal-close" id="closeHaftalikModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <form id="haftalikForm">
                    <input type="hidden" id="haftalikEditId" value="">

                    <div class="form-group">
                        <label>
                            <i class="fas fa-calendar-days"></i> Nomi
                        </label>
                        <input type="text" class="form-control" name="name" required>
                    </div>

                    <div class="form-group">
                        <label>
                            <i class="fas fa-font"></i> Qisqartma nomi
                        </label>
                        <input type="text"
                            class="form-control"
                            name="short_name"
                            placeholder="Masalan: OHT"
                            required>
                    </div>

                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelHaftalikBtn">Bekor qilish</button>
                <button class="btn btn-primary" id="saveHaftalikBtn">Saqlash</button>
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

        document.addEventListener('DOMContentLoaded', () => {
            initHaftalikModal();
            initHaftalikSearch();
            loadHaftaliklar();
        });

        function loadHaftaliklar() {
            fetch('get/oquv_haftalik_turlar_table.php')
                .then(res => res.text())
                .then(html => {
                    document.getElementById('haftalikTable').innerHTML = html;
                    document.getElementById('totalHaftalik').textContent =
                        document.getElementById('haftalikTable').children.length + ' ta';
                })
                .catch(() => {
                    document.getElementById('haftalikTable').innerHTML =
                        '<tr><td colspan="5">Xatolik yuz berdi</td></tr>';
                });
        }

        function initHaftalikModal() {
            const modal = document.getElementById('haftalikModal');
            const modalTitle = document.getElementById('haftalikModalTitle');
            const saveBtn = document.getElementById('saveHaftalikBtn');
            const editIdInput = document.getElementById('haftalikEditId');
            const form = document.getElementById('haftalikForm');

            document.getElementById('addHaftalikBtn').onclick = () => {
                modalTitle.textContent = 'O‘quv haftaligi turi qo‘shish';
                saveBtn.textContent = 'Saqlash';
                editIdInput.value = '';
                form.reset();
                modal.classList.add('show');
            };
            document.getElementById('closeHaftalikModal').onclick =
            document.getElementById('cancelHaftalikBtn').onclick =
                () => modal.classList.remove('show');

            window.onclick = e => { if (e.target === modal) modal.classList.remove('show'); };

            document.addEventListener('click', (e) => {
                const editBtn = e.target.closest('.editHaftalikBtn');
                if (editBtn) {
                    editIdInput.value = editBtn.dataset.id || '';
                    form.querySelector('[name="name"]').value = editBtn.dataset.name || '';
                    form.querySelector('[name="short_name"]').value = editBtn.dataset.shortName || '';
                    modalTitle.textContent = 'O‘quv haftaligi turini tahrirlash';
                    saveBtn.textContent = 'Yangilash';
                    modal.classList.add('show');
                    return;
                }

                const deleteBtn = e.target.closest('.deleteHaftalikBtn');
                if (!deleteBtn) return;

                const id = deleteBtn.dataset.id || '';
                if (!id) return;

                Swal.fire({
                    title: "Ishonchingiz komilmi?",
                    text: "O‘quv haftalik turi o'chiriladi",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ha, o'chirilsin",
                    cancelButtonText: "Bekor qilish"
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const formData = new FormData();
                    formData.append('id', id);

                    fetch('insert/delete_oquv_haftaligi_turlar.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        Toast.fire({
                            icon: data.success ? 'success' : 'error',
                            title: data.message || (data.success ? "O‘quv haftalik turi o'chirildi" : "Xatolik yuz berdi")
                        });
                        if (data.success) {
                            loadHaftaliklar();
                        }
                    })
                    .catch(() => {
                        Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                    });
                });
            });
        }

        function initHaftalikSearch() {
            const input = document.getElementById('searchHaftalik');
            const table = document.getElementById('haftalikTable');

            input.addEventListener('input', () => {
                const val = input.value.toLowerCase();
                table.querySelectorAll('tr').forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
                });
            });
        }

        document.getElementById('saveHaftalikBtn').addEventListener('click', () => {
            const form = document.getElementById('haftalikForm');
            const editId = document.getElementById('haftalikEditId').value;

            if (!form.checkValidity()) {
                Toast.fire({ icon: 'error', title: 'Barcha maydonlarni to‘ldiring!' });
                return;
            }

            const formData = new FormData(form);
            if (editId) {
                formData.append('id', editId);
            }

            const endpoint = editId ? 'insert/update_oquv_haftaligi_turlar.php' : 'insert/add_oquv_haftaligi_turlar.php';

            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Toast.fire({ icon: 'success', title: data.message });
                    form.reset();
                    document.getElementById('haftalikModal').classList.remove('show');
                    document.getElementById('haftalikEditId').value = '';
                    document.getElementById('haftalikModalTitle').textContent = 'O‘quv haftaligi turi qo‘shish';
                    document.getElementById('saveHaftalikBtn').textContent = 'Saqlash';
                    loadHaftaliklar();
                } else {
                    Toast.fire({ icon: 'error', title: data.message });
                }
            })
            .catch(() => {
                Toast.fire({ icon: 'error', title: 'Server bilan bog‘lanib bo‘lmadi' });
            });
        });
    </script>

</body>
</html>
