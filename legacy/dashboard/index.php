<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - O'quv Qo'lanma</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php include_once 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navbar -->
            <header class="top-navbar">
                <div class="navbar-left">
                    <h1>O'quv jarayoni boshqaruvi</h1>
                </div>
                <div class="navbar-right">
                    <button class="btn-notification">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                    <div class="current-date">
                        <i class="fas fa-calendar-day"></i>
                        <span id="currentDate"></span>
                    </div>
                </div>
            </header>

            <!-- Statistics Cards -->
            <div class="content-container">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #d5f5e3;">
                            <i class="fas fa-compass" style="color: #27ae60;"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="yonalishlarSoni">0</h3>
                            <p>Ta'lim Yo'nalishlari</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #e8f6f3;">
                            <i class="fas fa-layer-group" style="color: #2ecc71;"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="kurslarSoni">4</h3>
                            <p>Kurslar</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #eafaf1;">
                            <i class="fas fa-calendar-alt" style="color: #27ae60;"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="rejalarSoni">0</h3>
                            <p>O'quv Rejalar</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #f0f9ff;">
                            <i class="fas fa-users" style="color: #2ecc71;"></i>
                        </div>
                        <div class="stat-info">
                            <h3>0</h3>
                            <p>Foydalanuvchilar</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-bolt me-2"></i>Tezkor harakatlar
                    </h2>
                    <div class="quick-actions">
                        <a href="yonalishlar.php" class="action-btn">
                            <i class="fas fa-plus-circle"></i>
                            <span>Yo'nalish qo'shish</span>
                        </a>
                        <a href="dasturlar.php" class="action-btn">
                            <i class="fas fa-book-medical"></i>
                            <span>Dastur qo'shish</span>
                        </a>
                        <a href="haftalik-reja.php" class="action-btn">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Reja tuzish</span>
                        </a>
                        <button class="action-btn secondary">
                            <i class="fas fa-download"></i>
                            <span>Hisobot yuklash</span>
                        </button>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-history me-2"></i>So'nggi faoliyat
                    </h2>
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="activity-content">
                                <p>Hozircha faoliyat yo'q. Yo'nalish, dastur yoki reja qo'shishni boshlang!</p>
                                <small class="activity-time">Bugun</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/app.js"></script>
</body>
</html>