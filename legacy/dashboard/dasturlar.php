<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dasturlar - O'quv Qo'lanma</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="../assets/css/style2.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-navbar">
                <div class="navbar-left">
                    <h1>O'quv Dasturlari</h1>
                    <p class="navbar-subtitle">Dasturlarni boshqarish va yo'nalishlar bilan bog'lash</p>
                </div>
                <div class="navbar-right">
                    <button class="btn btn-primary" id="addDasturBtn">
                        <i class="fas fa-plus"></i> Dastur qo'shish
                    </button>
                </div>
            </header>

            <div class="content-container">
                <!-- Dasturlar jadvali -->
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Barcha Dasturlar</h3>
                            <span class="badge" id="totalDasturlar">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchDastur" placeholder="Dastur nomi bo'yicha qidirish...">
                            </div>
                            <select id="filterYonalish" class="filter-select">
                                <option value="">Barcha yo'nalishlar</option>
                                <!-- Yo'nalishlar JavaScript orqali to'ldiriladi -->
                            </select>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Dastur Nomi</th>
                                    <th>Tavsifi</th>
                                    <th>Yo'nalish</th>
                                    <th>Kurs</th>
                                    <th>Yaratilgan sana</th>
                                    <th>Harakatlar</th>
                                </tr>
                            </thead>
                            <tbody id="dasturlarTable">
                                <!-- JavaScript orqali to'ldiriladi -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="table-footer">
                        <div class="table-info">
                            <p>Jami: <span id="dasturCount">0</span> ta dastur</p>
                            <div class="filter-info" id="filterInfo"></div>
                        </div>
                    </div>
                </div>

                <!-- Yo'nalish bo'yicha dasturlar statistikasi -->
                <div class="section mt-4">
                    <h2 class="section-title">
                        <i class="fas fa-chart-pie me-2"></i>Yo'nalishlar bo'yicha dasturlar
                    </h2>
                    <div class="stats-cards" id="yonalishStats">
                        <!-- Statistikalar JavaScript orqali to'ldiriladi -->
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Dastur qo'shish modal oynasi -->
    <div class="modal" id="dasturModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="dasturModalTitle">Yangi dastur qo'shish</h3>
                <button class="modal-close" id="closeDasturModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="dasturForm">
                    <input type="hidden" id="dasturId">
                    
                    <div class="form-group">
                        <label for="dasturNomi">
                            <i class="fas fa-book"></i> Dastur nomi
                        </label>
                        <input type="text" id="dasturNomi" placeholder="Masalan: Algoritmlar va ma'lumotlar tuzilmasi" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="dasturYonalish">
                                <i class="fas fa-compass"></i> Yo'nalish
                            </label>
                            <select id="dasturYonalish" required>
                                <option value="">Yo'nalishni tanlang</option>
                                <!-- Yo'nalishlar JavaScript orqali to'ldiriladi -->
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="dasturKurs">
                                <i class="fas fa-layer-group"></i> Kurs
                            </label>
                            <select id="dasturKurs" required>
                                <option value="">Kursni tanlang</option>
                                <option value="1">1-kurs</option>
                                <option value="2">2-kurs</option>
                                <option value="3">3-kurs</option>
                                <option value="4">4-kurs</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="dasturTavsifi">
                            <i class="fas fa-file-alt"></i> Dastur tavsifi
                        </label>
                        <textarea id="dasturTavsifi" rows="4" placeholder="Dastur haqida batafsil ma'lumot..." required></textarea>
                        <small class="form-hint">Dasturning mazmuni, maqsadi va o'quv rejasini yozing</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="dasturKredit">
                            <i class="fas fa-weight-hanging"></i> Kreditlar soni
                        </label>
                        <input type="number" id="dasturKredit" min="1" max="10" placeholder="Masalan: 4" value="3">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelDasturBtn">
                    Bekor qilish
                </button>
                <button type="button" class="btn btn-primary" id="saveDasturBtn">
                    <i class="fas fa-save"></i> Saqlash
                </button>
            </div>
        </div>
    </div>

    <!-- Dastur ma'lumotlari modal oynasi -->
    <div class="modal" id="viewDasturModal">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h3 id="viewDasturTitle">Dastur ma'lumotlari</h3>
                <button class="modal-close" id="closeViewModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="dastur-detail" id="dasturDetailContent">
                    <!-- JavaScript orqali to'ldiriladi -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="closeViewBtn">
                    Yopish
                </button>
                <button type="button" class="btn btn-primary" id="editDasturBtn">
                    <i class="fas fa-edit"></i> Tahrirlash
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/dasturlar.js"></script>
</body>
</html>