<?php
    // Izoh: Birlashtiriladigan fanlar jadvali (alohida katalog).
    include_once 'config.php';
    $db = new Database();
    // Izoh: Qo'shish funksiyasi olib tashlanganligi sababli qo'shimcha ma'lumotlar kerak emas.
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Birlashtiriladigan fanlar - O'quv Qo'lanma</title>

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
                    <h1>Birlashtiriladigan fanlar</h1>
                    <p class="navbar-subtitle">Birlashtiriladigan fanlarni boshqarish bo'limi</p>
                </div>
                <!-- Izoh: Birlashtiriladigan fan qo'shish tugmasi olib tashlandi (faqat ro'yxat). -->
                <div class="navbar-right"></div>
            </header>

            <div class="content-container">
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Barcha umumta'lim fanlar</h3>
                            <span class="badge" id="totalUmumtalimFanlar">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchUmumtalimFan" placeholder="Qidirish...">
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fan kodi</th>
                                <th>Fan nomi</th>
                                <th>Semestr</th>
                                <th>Kafedra</th>
                                <th>Yaratilgan sana</th>
                                <th>Harakatlar</th>
                            </tr>
                            </thead>
                            <tbody id="umumtalimFanlarTable">
                            <!-- AJAX orqali -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Izoh: Birlashtiriladigan fan qo'shish modal oynasi olib tashlandi (faqat ro'yxat). -->

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
            initUmumtalimFanSearch();
            loadUmumtalimFanlar();
        });

        function loadUmumtalimFanlar() {
            fetch('get/umumtalim_fanlar_table.php')
                .then(res => res.text())
                .then(html => {
                    const tbody = document.getElementById('umumtalimFanlarTable');
                    tbody.innerHTML = html;
                    document.getElementById('totalUmumtalimFanlar').textContent =
                        tbody.children.length + ' ta';
                })
                .catch(() => {
                    document.getElementById('umumtalimFanlarTable').innerHTML =
                        '<tr><td colspan="6">Xatolik yuz berdi</td></tr>';
                });
        }

        function initUmumtalimFanSearch() {
            const input = document.getElementById('searchUmumtalimFan');
            const table = document.getElementById('umumtalimFanlarTable');

            input.addEventListener('input', () => {
                const value = input.value.toLowerCase();
                table.querySelectorAll('tr').forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(value) ? '' : 'none';
                });
            });
        }

        // Izoh: Birlashtiriladigan fan qo'shish funksiyasi olib tashlandi (faqat ro'yxat).
    </script>

</body>
</html>
