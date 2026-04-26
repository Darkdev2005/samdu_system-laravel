<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O‘quv shakllar - O'quv Bo'limi</title>

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
                    <h1>O‘quv shakllar</h1>
                    <p class="navbar-subtitle">O‘quv shakllarini boshqarish bo‘limi</p>
                </div>
                <div class="navbar-right">
                    <button class="btn btn-primary" id="addOquvShakliBtn">
                        <i class="fas fa-plus"></i> O‘quv shakli qo‘shish
                    </button>
                </div>
            </header>
            <div class="content-container">
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Barcha o‘quv shakllar</h3>
                            <span class="badge" id="totalOquvShakllar">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchOquvShakli" placeholder="Qidirish...">
                            </div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>O‘quv shakli nomi</th>
                                <th>Yaratilgan sana</th>
                                <th>Harakatlar</th>
                            </tr>
                            </thead>
                            <tbody id="oquvShakllariTable">
                            <!-- AJAX orqali -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL -->
    <div class="modal" id="oquvShakliModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="oquvShakliModalTitle">O‘quv shakli qo‘shish</h3>
                <button class="modal-close" id="closeOquvShakliModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <form id="oquvShakliForm">
                    <input type="hidden" id="oquvShakliEditId" value="">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-chalkboard-user"></i> O‘quv shakli nomi
                        </label>
                        <input type="text" id="oquvShakliName" placeholder="Masalan: Kunduzgi" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelOquvShakliBtn">Bekor qilish</button>
                <button class="btn btn-primary" id="saveOquvShakliBtn">Saqlash</button>
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
            initOquvShakliModal();
            initOquvShakliSearch();
            loadOquvShakllari();
        });

        function loadOquvShakllari() {
            fetch('get/oquv_shakllari_table.php')
                .then(res => res.text())
                .then(html => {
                    const tbody = document.getElementById('oquvShakllariTable');
                    tbody.innerHTML = html;
                    document.getElementById('totalOquvShakllar').textContent =
                        tbody.children.length + ' ta';
                })
                .catch(() => {
                    document.getElementById('oquvShakllariTable').innerHTML =
                        '<tr><td colspan="4">Xatolik yuz berdi</td></tr>';
                });
        }

        function initOquvShakliModal() {
            const modal = document.getElementById('oquvShakliModal');
            const modalTitle = document.getElementById('oquvShakliModalTitle');
            const editIdInput = document.getElementById('oquvShakliEditId');
            const saveBtn = document.getElementById('saveOquvShakliBtn');
            const form = document.getElementById('oquvShakliForm');

            document.getElementById('addOquvShakliBtn').onclick = () => {
                modalTitle.textContent = 'O‘quv shakli qo‘shish';
                saveBtn.textContent = 'Saqlash';
                editIdInput.value = '';
                form.reset();
                modal.classList.add('show');
            };
            document.getElementById('closeOquvShakliModal').onclick = () => modal.classList.remove('show');
            document.getElementById('cancelOquvShakliBtn').onclick = () => modal.classList.remove('show');

            window.onclick = (e) => {
                if (e.target === modal) modal.classList.remove('show');
            };

            document.addEventListener('click', (e) => {
                const editBtn = e.target.closest('.editOquvShakliBtn');
                if (editBtn) {
                    editIdInput.value = editBtn.dataset.id || '';
                    document.getElementById('oquvShakliName').value = editBtn.dataset.name || '';
                    modalTitle.textContent = 'O‘quv shaklini tahrirlash';
                    saveBtn.textContent = 'Yangilash';
                    modal.classList.add('show');
                    return;
                }

                const deleteBtn = e.target.closest('.deleteOquvShakliBtn');
                if (!deleteBtn) return;

                const id = deleteBtn.dataset.id || '';
                if (!id) return;

                Swal.fire({
                    title: "Ishonchingiz komilmi?",
                    text: "O‘quv shakli o'chiriladi",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ha, o'chirilsin",
                    cancelButtonText: "Bekor qilish"
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const formData = new FormData();
                    formData.append('id', id);

                    fetch('insert/delete_oquv_shakli.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        Toast.fire({
                            icon: data.success ? 'success' : 'error',
                            title: data.message || (data.success ? "O‘quv shakli o'chirildi" : "Xatolik yuz berdi")
                        });
                        if (data.success) {
                            loadOquvShakllari();
                        }
                    })
                    .catch(() => {
                        Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                    });
                });
            });
        }

        function initOquvShakliSearch() {
            const input = document.getElementById('searchOquvShakli');
            const table = document.getElementById('oquvShakllariTable');

            input.addEventListener('input', () => {
                const value = input.value.toLowerCase();
                table.querySelectorAll('tr').forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
                });
            });
        }

        document.getElementById('saveOquvShakliBtn').addEventListener('click', () => {
            const name = document.getElementById('oquvShakliName').value.trim();
            const editId = document.getElementById('oquvShakliEditId').value;

            if (!name) {
                Toast.fire({ icon: 'error', title: "O‘quv shakli nomini kiriting!" });
                return;
            }

            const formData = new FormData();
            formData.append('nomi', name);
            if (editId) {
                formData.append('id', editId);
            }

            const endpoint = editId ? 'insert/update_oquv_shakli.php' : 'insert/add_oquv_shakli.php';

            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Toast.fire({ icon: 'success', title: data.message });
                    document.getElementById('oquvShakliModal').classList.remove('show');
                    document.getElementById('oquvShakliForm').reset();
                    document.getElementById('oquvShakliEditId').value = '';
                    document.getElementById('oquvShakliModalTitle').textContent = 'O‘quv shakli qo‘shish';
                    document.getElementById('saveOquvShakliBtn').textContent = 'Saqlash';
                    loadOquvShakllari();
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
