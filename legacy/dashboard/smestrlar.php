<?php
include_once 'config.php';
$db = new Database();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Semestrlar - O‘quv Qo‘llanma</title>

    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-navbar">
                <div class="navbar-left">
                    <h1>Semestrlar</h1>
                    <p class="navbar-subtitle">
                        Yo‘nalishlar asosida semestrlarni avtomatik yaratish
                    </p>
                </div>
                <div class="navbar-right">
                    <button class="btn btn-primary" id="generateSmestrBtn">
                        <i class="fas fa-calendar-week"></i> Semestrlarni avtomatik yaratish
                    </button>
                </div>
            </header>

            <div class="content-container">
                <div class="table-container">

                    <div class="table-header">
                        <div class="table-title">
                            <h3>Barcha semestrlar</h3>
                            <span class="badge" id="totalSmestrlar">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchSemestr" placeholder="Qidirish...">
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fakultet</th>
                                <th>Yo‘nalish</th>
                                <th>Semestr</th>
                                <th>Yaratilgan sana</th>
                                <th>Harakatlar</th>
                            </tr>
                            </thead>
                            <tbody id="smestrlarTable">
                                <!-- fetch orqali -->
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true
        });

        document.addEventListener('DOMContentLoaded', () => {
            loadSmestrlar();
            initSearch();
            initRowActions();
        });

        function loadSmestrlar() {
            fetch('get/smestrlar_table.php')
                .then(res => res.text())
                .then(html => {
                    const tbody = document.getElementById('smestrlarTable');
                    tbody.innerHTML = html;

                    document.getElementById('totalSmestrlar').textContent =
                        tbody.children.length + ' ta';
                })
                .catch(() => {
                    document.getElementById('smestrlarTable').innerHTML =
                        '<tr><td colspan="5">Xatolik yuz berdi</td></tr>';
                });
        }

        function initSearch() {
            const input = document.getElementById('searchSemestr');
            const table = document.getElementById('smestrlarTable');

            input.addEventListener('input', () => {
                const value = input.value.toLowerCase();
                table.querySelectorAll('tr').forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(value)
                        ? ''
                        : 'none';
                });
            });
        }

        function initRowActions() {
            document.addEventListener('click', (e) => {
                const editBtn = e.target.closest('.editSmestrBtn');
                if (editBtn) {
                    const id = editBtn.dataset.id || '';
                    const currentSemestr = editBtn.dataset.semestr || '';
                    if (!id) return;

                    Swal.fire({
                        title: 'Semestrni tahrirlash',
                        input: 'number',
                        inputLabel: 'Yangi semestr raqami',
                        inputValue: currentSemestr,
                        inputAttributes: {
                            min: 1
                        },
                        showCancelButton: true,
                        confirmButtonText: 'Yangilash',
                        cancelButtonText: 'Bekor qilish',
                        inputValidator: (value) => {
                            if (!value || Number(value) <= 0) {
                                return 'Semestr raqami musbat bo‘lishi kerak';
                            }
                            return null;
                        }
                    }).then((result) => {
                        if (!result.isConfirmed) return;

                        const formData = new FormData();
                        formData.append('id', id);
                        formData.append('semestr', String(result.value));

                        fetch('insert/update_smestr.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            Toast.fire({
                                icon: data.success ? 'success' : 'error',
                                title: data.message || (data.success ? 'Semestr yangilandi' : 'Xatolik yuz berdi')
                            });
                            if (data.success) {
                                loadSmestrlar();
                            }
                        })
                        .catch(() => {
                            Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                        });
                    });
                    return;
                }

                const deleteBtn = e.target.closest('.deleteSmestrBtn');
                if (!deleteBtn) return;

                const id = deleteBtn.dataset.id || '';
                if (!id) return;

                Swal.fire({
                    title: "Ishonchingiz komilmi?",
                    text: "Semestr o'chiriladi",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Ha, o'chirilsin",
                    cancelButtonText: "Bekor qilish"
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const formData = new FormData();
                    formData.append('id', id);

                    fetch('insert/delete_smestr.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        Toast.fire({
                            icon: data.success ? 'success' : 'error',
                            title: data.message || (data.success ? "Semestr o'chirildi" : 'Xatolik yuz berdi')
                        });
                        if (data.success) {
                            loadSmestrlar();
                        }
                    })
                    .catch(() => {
                        Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                    });
                });
            });
        }

        document.getElementById('generateSmestrBtn').addEventListener('click', () => {

            Swal.fire({
                title: 'Tasdiqlaysizmi?',
                text: 'Barcha yo‘nalishlar uchun semestrlar avtomatik yaratiladi',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ha, yaratilsin',
                cancelButtonText: 'Bekor qilish'
            }).then(result => {

                if (!result.isConfirmed) return;

                fetch('insert/add_smestr.php')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            Toast.fire({
                                icon: 'success',
                                title: data.message
                            });
                            loadSmestrlar();
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
        });
    </script>

</body>
</html>
