<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ta'lim shakllari - O'quv Bo'limi</title>

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
                    <h1>Ta'lim shakllari</h1>
                    <p class="navbar-subtitle">Ta'lim shakllarini boshqarish bo‘limi</p>
                </div>
                <div class="navbar-right">
                    <button class="btn btn-primary" id="addTalimShakliBtn">
                        <i class="fas fa-plus"></i> Ta'lim shakli qo‘shish
                    </button>
                </div>
            </header>

            <div class="content-container">
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Barcha ta'lim shakllari</h3>
                            <span class="badge" id="totalTalimShakllar">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchTalimShakli" placeholder="Qidirish...">
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Ta'lim shakli nomi</th>
                                <th>Yaratilgan sana</th>
                                <th>Harakatlar</th>
                            </tr>
                            </thead>
                            <tbody id="talimShakllariTable">
                            <!-- AJAX orqali -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- MODAL -->
    <div class="modal" id="talimShakliModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="talimShakliModalTitle">Ta'lim shakli qo‘shish</h3>
                <button class="modal-close" id="closeTalimShakliModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <form id="talimShakliForm">
                    <input type="hidden" id="talimShakliEditId" value="">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-chalkboard-user"></i> Ta'lim shakli nomi
                        </label>
                        <input type="text" id="talimShakliName" required>
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelTalimShakliBtn">Bekor qilish</button>
                <button class="btn btn-primary" id="saveTalimShakliBtn">Saqlash</button>
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
            initTalimShakliModal();
            initTalimShakliSearch();
            loadTalimShakllari();
        });

        function loadTalimShakllari() {
            fetch('get/talim_shakllari_table.php')
                .then(res => res.text())
                .then(html => {
                    document.getElementById('talimShakllariTable').innerHTML = html;
                    const total = document.getElementById('talimShakllariTable').children.length;
                    document.getElementById('totalTalimShakllar').textContent = total + ' ta';

                })
                .catch(() => {
                    document.getElementById('talimShakllariTable').innerHTML =
                        '<tr><td colspan="4">Xatolik yuz berdi</td></tr>';
                });
        }

        function initTalimShakliModal() {
            const modal = document.getElementById('talimShakliModal');
            const modalTitle = document.getElementById('talimShakliModalTitle');
            const editIdInput = document.getElementById('talimShakliEditId');
            const saveBtn = document.getElementById('saveTalimShakliBtn');
            const form = document.getElementById('talimShakliForm');

            document.getElementById('addTalimShakliBtn').onclick = () => {
                modalTitle.textContent = "Ta'lim shakli qo‘shish";
                saveBtn.textContent = 'Saqlash';
                editIdInput.value = '';
                form.reset();
                modal.classList.add('show');
            };
            document.getElementById('closeTalimShakliModal').onclick = () => modal.classList.remove('show');
            document.getElementById('cancelTalimShakliBtn').onclick = () => modal.classList.remove('show');

            window.onclick = (e) => {
                if (e.target === modal) modal.classList.remove('show');
            };

            document.addEventListener('click', (e) => {
                const editBtn = e.target.closest('.editFakultetBtn');
                if (editBtn) {
                    editIdInput.value = editBtn.dataset.id || '';
                    document.getElementById('talimShakliName').value = editBtn.dataset.name || '';
                    modalTitle.textContent = "Ta'lim shaklini tahrirlash";
                    saveBtn.textContent = 'Yangilash';
                    modal.classList.add('show');
                    return;
                }

                const deleteBtn = e.target.closest('.deleteFakultetBtn');
                if (!deleteBtn) return;

                const id = deleteBtn.dataset.id || '';
                if (!id) return;

                Swal.fire({
                    title: "Ishonchingiz komilmi?",
                    text: "Ta'lim shakli o'chiriladi",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ha, o'chirilsin",
                    cancelButtonText: "Bekor qilish"
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const formData = new FormData();
                    formData.append('id', id);

                    fetch('insert/delete_talim_shakli.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        Toast.fire({
                            icon: data.success ? 'success' : 'error',
                            title: data.message || (data.success ? "Ta'lim shakli o'chirildi" : "Xatolik yuz berdi")
                        });
                        if (data.success) {
                            loadTalimShakllari();
                        }
                    })
                    .catch(() => {
                        Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                    });
                });
            });
        }

        function initTalimShakliSearch() {
            const input = document.getElementById('searchTalimShakli');
            const table = document.getElementById('talimShakllariTable');

            input.addEventListener('input', () => {
                const value = input.value.toLowerCase();
                table.querySelectorAll('tr').forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
                });
            });
        }

        document.getElementById('saveTalimShakliBtn').addEventListener('click', () => {
            const name = document.getElementById('talimShakliName').value.trim();
            const editId = document.getElementById('talimShakliEditId').value;

            if (!name) {
                Toast.fire({ icon: 'error', title: "Ta'lim shakli nomini kiriting!" });
                return;
            }

            const formData = new FormData();
            formData.append('nomi', name);
            if (editId) {
                formData.append('id', editId);
            }

            const endpoint = editId ? 'insert/update_talim_shakli.php' : 'insert/add_talim_shakli.php';

            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Toast.fire({ icon: 'success', title: data.message });
                    document.getElementById('talimShakliModal').classList.remove('show');
                    document.getElementById('talimShakliForm').reset();
                    document.getElementById('talimShakliEditId').value = '';
                    document.getElementById('talimShakliModalTitle').textContent = "Ta'lim shakli qo‘shish";
                    document.getElementById('saveTalimShakliBtn').textContent = 'Saqlash';
                    loadTalimShakllari();
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
