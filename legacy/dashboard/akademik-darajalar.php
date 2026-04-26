<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akademik daraja - O'quv Bo'limi</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        <!-- Main Content -->
        <main class="main-content">
            <header class="top-navbar">
                <div class="navbar-left">
                    <h1>Akademik darajalar</h1>
                    <p class="navbar-subtitle">Akademik darajalarni boshqarish bo'limi</p>
                </div>
                <div class="navbar-right">
                    <!-- Add button -->
                    <button class="btn btn-primary" id="addakademikDarajaBtn">
                        <i class="fas fa-plus"></i> Akademik daraja qo'shish
                    </button>
                </div>
            </header>
            <div class="content-container">
                <!-- Akademik darajalar jadvali -->
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Barcha akademik darajalar</h3>
                            <span class="badge" id="totalAkademikdaraja">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchAkademikdaraja" placeholder="Qidirish...">
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Akademik daraja nomi</th>
                                    <th>Yaratilgan sana</th>
                                    <th>Harakatlar</th>
                                </tr>
                            </thead>
                            <tbody id="akademikdarajaTable">
                                <!-- JavaScript orqali to'ldiriladi -->
                            </tbody>
                        </table>
                    </div>
                    
                </div>
            </div>
        </main>
    </div>

    <!-- Akademik darajalar qo'shish modal oynasi -->
    <div class="modal" id="akademikDarajaModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="akademikDarajaModalTitle">Akademik daraja qo'shish</h3>
                <button class="modal-close" id="closeakademikDarajaModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <form id="fakultetForm">
                    <input type="hidden" id="akademikDarajaEditId" value="">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-graduation-cap"></i> Akademik daraja nomi
                        </label>
                        <input type="text" id="akademikdarajaName" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelakademikDarajaBtn">
                    Bekor qilish
                </button>
                <button class="btn btn-primary" id="saveakademikDarajaBtn">
                    Saqlash
                </button>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <script src="../assets/js/app.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            initakademikDarajaModal();
            initFakultetSearch();
            loadakademikDaraja();
        });
        function loadakademikDaraja() {
            fetch('get/akademikdarajalar_table.php')
                .then(res => res.text())
                .then(html => {
                    document.getElementById('akademikdarajaTable').innerHTML = html;
                    const total = document.getElementById('akademikdarajaTable').children.length;
                    document.getElementById('totalAkademikdaraja').textContent = total + ' ta';
                })
                .catch(() => {
                    document.getElementById('akademikdarajaTable').innerHTML =
                        '<tr><td colspan="4">Xatolik yuz berdi</td></tr>';
                });
        }

        function initakademikDarajaModal() {
            const addBtn = document.getElementById('addakademikDarajaBtn');
            const modal = document.getElementById('akademikDarajaModal');
            const closeBtn = document.getElementById('closeakademikDarajaModal');
            const cancelBtn = document.getElementById('cancelakademikDarajaBtn');
            const modalTitle = document.getElementById('akademikDarajaModalTitle');
            const editIdInput = document.getElementById('akademikDarajaEditId');
            const saveBtn = document.getElementById('saveakademikDarajaBtn');
            const form = document.getElementById('fakultetForm');

            if (addBtn) {
                addBtn.addEventListener('click', () => {
                    modalTitle.textContent = "Akademik daraja qo'shish";
                    saveBtn.textContent = 'Saqlash';
                    editIdInput.value = '';
                    form.reset();
                    modal.classList.add('show');
                });
            }

            [closeBtn, cancelBtn].forEach(btn => {
                if (btn) {
                    btn.addEventListener('click', () => {
                        modal.classList.remove('show');
                    });
                }
            });

            window.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });

            document.addEventListener('click', (e) => {
                const editBtn = e.target.closest('.editFakultetBtn');
                if (editBtn) {
                    editIdInput.value = editBtn.dataset.id || '';
                    document.getElementById('akademikdarajaName').value = editBtn.dataset.name || '';
                    modalTitle.textContent = "Akademik darajani tahrirlash";
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
                    text: "Akademik daraja o'chiriladi",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ha, o'chirilsin",
                    cancelButtonText: "Bekor qilish"
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const formData = new FormData();
                    formData.append('id', id);

                    fetch('insert/delete_akademikdaraja.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        Toast.fire({
                            icon: data.success ? 'success' : 'error',
                            title: data.message || (data.success ? "Akademik daraja o'chirildi" : "Xatolik yuz berdi")
                        });
                        if (data.success) {
                            loadakademikDaraja();
                        }
                    })
                    .catch(() => {
                        Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                    });
                });
            });
        }

        function initFakultetSearch() {
            const searchInput = document.getElementById('searchAkademikdaraja');
            const table = document.getElementById('akademikdarajaTable');

            if (!searchInput || !table) return;

            searchInput.addEventListener('input', () => {
                const value = searchInput.value.toLowerCase();
                const rows = table.querySelectorAll('tr');

                rows.forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(value)
                        ? ''
                        : 'none';
                });
            });
        }
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('fakultetForm');
            const saveBtn = document.getElementById('saveakademikDarajaBtn');

            if (!form || !saveBtn) return;

            saveBtn.addEventListener('click', () => {
                const akademikdarajaName = document.getElementById('akademikdarajaName').value.trim();
                const editId = document.getElementById('akademikDarajaEditId').value;

                if (!akademikdarajaName) {
                    Toast.fire({
                        icon: 'error',
                        title: 'Fakultet nomini kiriting!'
                    });
                    return;
                }

                const formData = new FormData();
                formData.append('nomi', akademikdarajaName);
                if (editId) {
                    formData.append('id', editId);
                }

                const endpoint = editId ? 'insert/update_akademikdaraja.php' : 'insert/add_akademikdaraja.php';

                fetch(endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Toast.fire({
                            icon: 'success',
                            title: data.message || 'Akademik daraja muvaffaqiyatli saqlandi'
                        });

                        // modal yopish
                        document.getElementById('akademikDarajaModal')?.classList.remove('show');

                        // formani tozalash
                        form.reset();
                        document.getElementById('akademikDarajaEditId').value = '';
                        document.getElementById('akademikDarajaModalTitle').textContent = "Akademik daraja qo'shish";
                        saveBtn.textContent = 'Saqlash';

                        // jadvalni yangilash
                        loadakademikDaraja();
                    } else {
                        Toast.fire({
                            icon: 'error',
                            title: data.message || 'Xatolik yuz berdi'
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
        });

    </script>
</body>
</html>
