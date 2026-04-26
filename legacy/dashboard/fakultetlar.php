<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fakultetlar - O'quv Bo'limi</title>
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
                    <h1>Fakultetlar</h1>
                    <p class="navbar-subtitle">Fakultetlarni boshqarish bo'limi</p>
                </div>
                <div class="navbar-right">
                    <!-- Add button -->
                    <button class="btn btn-primary" id="addFakultetBtn">
                        <i class="fas fa-plus"></i> Fakultet qo'shish
                    </button>
                </div>
            </header>
            <div class="content-container">
                <!-- Fakultetlar jadvali -->
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Barcha Fakultetlar</h3>
                            <span class="badge" id="totalFakultetlar">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchFakultet" placeholder="Qidirish...">
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Fakultet nomi</th>
                                    <th>Yaratilgan sana</th>
                                    <th>Harakatlar</th>
                                </tr>
                            </thead>
                            <tbody id="fakultetlarTable">
                                <!-- JavaScript orqali to'ldiriladi -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Fakultet qo'shish modal oynasi -->
            <div class="modal" id="fakultetModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="fakultetModalTitle">Fakultet qo'shish</h3>
                <button class="modal-close" id="closeFakultetModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <form id="fakultetForm">
                    <input type="hidden" id="fakultetEditId" value="">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-building-columns"></i> Fakultet nomi
                        </label>
                        <input type="text" id="fakultetNomi" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelFakultetBtn">
                    Bekor qilish
                </button>
                <button class="btn btn-primary" id="saveFakultetBtn">
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
            initFakultetModal();
            initFakultetSearch();
            loadFakultetlar();
        });
        function loadFakultetlar() {
            fetch('get/fakultetlar_table.php')
                .then(res => res.text())
                .then(html => {
                    document.getElementById('fakultetlarTable').innerHTML = html;
                    const total = document.getElementById('fakultetlarTable').children.length;
                    document.getElementById('totalFakultetlar').textContent = total + ' ta';
                })
                .catch(() => {
                    document.getElementById('fakultetlarTable').innerHTML =
                        '<tr><td colspan="4">Xatolik yuz berdi</td></tr>';
                });
        }

        function initFakultetModal() {
            const addBtn = document.getElementById('addFakultetBtn');
            const modal = document.getElementById('fakultetModal');
            const closeBtn = document.getElementById('closeFakultetModal');
            const cancelBtn = document.getElementById('cancelFakultetBtn');
            const modalTitle = document.getElementById('fakultetModalTitle');
            const editIdInput = document.getElementById('fakultetEditId');
            const saveBtn = document.getElementById('saveFakultetBtn');
            const form = document.getElementById('fakultetForm');

            if (addBtn) {
                addBtn.addEventListener('click', () => {
                    modalTitle.textContent = "Fakultet qo'shish";
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
                    document.getElementById('fakultetNomi').value = editBtn.dataset.name || '';
                    modalTitle.textContent = "Fakultetni tahrirlash";
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
                    text: "Fakultet o'chiriladi",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ha, o'chirilsin",
                    cancelButtonText: "Bekor qilish"
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const formData = new FormData();
                    formData.append('id', id);

                    fetch('insert/delete_fakultet.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        Toast.fire({
                            icon: data.success ? 'success' : 'error',
                            title: data.message || (data.success ? "Fakultet o'chirildi" : "Xatolik yuz berdi")
                        });
                        if (data.success) {
                            loadFakultetlar();
                        }
                    })
                    .catch(() => {
                        Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                    });
                });
            });
        }

        function initFakultetSearch() {
            const searchInput = document.getElementById('searchFakultet');
            const table = document.getElementById('fakultetlarTable');

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
            const saveBtn = document.getElementById('saveFakultetBtn');

            if (!form || !saveBtn) return;

            saveBtn.addEventListener('click', () => {
                const fakultetNomi = document.getElementById('fakultetNomi').value.trim();
                const editId = document.getElementById('fakultetEditId').value;

                if (!fakultetNomi) {
                    Toast.fire({
                        icon: 'error',
                        title: 'Fakultet nomini kiriting!'
                    });
                    return;
                }

                const formData = new FormData();
                formData.append('nomi', fakultetNomi);
                if (editId) {
                    formData.append('id', editId);
                }

                const endpoint = editId ? 'insert/update_fakultet.php' : 'insert/add_fakultet.php';

                fetch(endpoint, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Toast.fire({
                            icon: 'success',
                            title: data.message || 'Fakultet muvaffaqiyatli saqlandi'
                        });

                        // modal yopish
                        document.getElementById('fakultetModal')?.classList.remove('show');

                        // formani tozalash
                        form.reset();
                        document.getElementById('fakultetEditId').value = '';
                        document.getElementById('fakultetModalTitle').textContent = "Fakultet qo'shish";
                        saveBtn.textContent = 'Saqlash';

                        // jadvalni yangilash
                        loadFakultetlar();
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
