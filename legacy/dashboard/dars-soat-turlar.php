<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dars soat turlari - O'quv Qo'lanma</title>

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
                    <h1>Dars soat turlari</h1>
                    <p class="navbar-subtitle">Dars soat turlarini boshqarish bo‘limi</p>
                </div>
                <div class="navbar-right">
                    <button class="btn btn-primary" id="addDarsSoatTuriBtn">
                        <i class="fas fa-plus"></i> Dars soat turi qo‘shish
                    </button>
                </div>
            </header>

            <div class="content-container">
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Barcha dars soat turlari</h3>
                            <span class="badge" id="totalDarsSoatTurlari">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchDarsSoatTuri" placeholder="Qidirish...">
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Dars soat turi nomi</th>
                                <th>Yaratilgan sana</th>
                                <th>Harakatlar</th>
                            </tr>
                            </thead>
                            <tbody id="darsSoatTurlariTable">
                            <!-- AJAX orqali -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <!-- MODAL -->
    <div class="modal" id="darsSoatTuriModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="darsSoatTuriModalTitle">Dars soat turi qo‘shish</h3>
                <button class="modal-close" id="closeDarsSoatTuriModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <form id="darsSoatTuriForm">
                    <input type="hidden" id="darsSoatTuriEditId" value="">
                    <div class="form-group">
                        <label>
                            <i class="fas fa-clock"></i> Dars soat turi nomi
                        </label>
                        <input type="text" id="darsSoatTuriName" placeholder="Masalan: Ma’ruza" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelDarsSoatTuriBtn">Bekor qilish</button>
                <button class="btn btn-primary" id="saveDarsSoatTuriBtn">Saqlash</button>
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
            initDarsSoatTuriModal();
            initDarsSoatTuriSearch();
            loadDarsSoatTurlari();
        });

       
        function loadDarsSoatTurlari() {
            fetch('get/dars_soat_turlari_table.php')
                .then(res => res.text())
                .then(html => {
                    const tbody = document.getElementById('darsSoatTurlariTable');
                    tbody.innerHTML = html;
                    document.getElementById('totalDarsSoatTurlari').textContent =
                        tbody.children.length + ' ta';
                })
                .catch(() => {
                    document.getElementById('darsSoatTurlariTable').innerHTML =
                        '<tr><td colspan="4">Xatolik yuz berdi</td></tr>';
                });
        }

       
        function initDarsSoatTuriModal() {
            const modal = document.getElementById('darsSoatTuriModal');
            const modalTitle = document.getElementById('darsSoatTuriModalTitle');
            const editIdInput = document.getElementById('darsSoatTuriEditId');
            const saveBtn = document.getElementById('saveDarsSoatTuriBtn');
            const form = document.getElementById('darsSoatTuriForm');

            document.getElementById('addDarsSoatTuriBtn').onclick = () => {
                modalTitle.textContent = 'Dars soat turi qo‘shish';
                saveBtn.textContent = 'Saqlash';
                editIdInput.value = '';
                form.reset();
                modal.classList.add('show');
            };
            document.getElementById('closeDarsSoatTuriModal').onclick = () => modal.classList.remove('show');
            document.getElementById('cancelDarsSoatTuriBtn').onclick = () => modal.classList.remove('show');

            window.onclick = (e) => {
                if (e.target === modal) modal.classList.remove('show');
            };

            document.addEventListener('click', (e) => {
                const editBtn = e.target.closest('.editDarsSoatTuriBtn');
                if (editBtn) {
                    editIdInput.value = editBtn.dataset.id || '';
                    document.getElementById('darsSoatTuriName').value = editBtn.dataset.name || '';
                    modalTitle.textContent = 'Dars soat turini tahrirlash';
                    saveBtn.textContent = 'Yangilash';
                    modal.classList.add('show');
                    return;
                }

                const deleteBtn = e.target.closest('.deleteDarsSoatTuriBtn');
                if (!deleteBtn) return;

                const id = deleteBtn.dataset.id || '';
                if (!id) return;

                Swal.fire({
                    title: "Ishonchingiz komilmi?",
                    text: "Dars soat turi o'chiriladi",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ha, o'chirilsin",
                    cancelButtonText: "Bekor qilish"
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const formData = new FormData();
                    formData.append('id', id);

                    fetch('insert/delete_dars_soat_turi.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        Toast.fire({
                            icon: data.success ? 'success' : 'error',
                            title: data.message || (data.success ? "Dars soat turi o'chirildi" : "Xatolik yuz berdi")
                        });
                        if (data.success) {
                            loadDarsSoatTurlari();
                        }
                    })
                    .catch(() => {
                        Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                    });
                });
            });
        }

       
        function initDarsSoatTuriSearch() {
            const input = document.getElementById('searchDarsSoatTuri');
            const table = document.getElementById('darsSoatTurlariTable');

            input.addEventListener('input', () => {
                const value = input.value.toLowerCase();
                table.querySelectorAll('tr').forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
                });
            });
        }

        document.getElementById('saveDarsSoatTuriBtn').addEventListener('click', () => {
            const name = document.getElementById('darsSoatTuriName').value.trim();
            const editId = document.getElementById('darsSoatTuriEditId').value;

            if (!name) {
                Toast.fire({ icon: 'error', title: "Dars soat turi nomini kiriting!" });
                return;
            }

            const formData = new FormData();
            formData.append('nomi', name);
            if (editId) {
                formData.append('id', editId);
            }

            const endpoint = editId ? 'insert/update_dars_soat_turi.php' : 'insert/add_dars_soat_turi.php';

            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Toast.fire({ icon: 'success', title: data.message });
                    document.getElementById('darsSoatTuriModal').classList.remove('show');
                    document.getElementById('darsSoatTuriForm').reset();
                    document.getElementById('darsSoatTuriEditId').value = '';
                    document.getElementById('darsSoatTuriModalTitle').textContent = 'Dars soat turi qo‘shish';
                    document.getElementById('saveDarsSoatTuriBtn').textContent = 'Saqlash';
                    loadDarsSoatTurlari();
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
