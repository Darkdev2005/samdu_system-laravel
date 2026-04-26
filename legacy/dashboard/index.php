<?php
include_once 'config.php';

$db = new Database();

function tableExists(Database $db, string $table): bool
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safeTable === '') {
        return false;
    }
    $res = $db->query("SHOW TABLES LIKE '{$safeTable}'");
    return $res && mysqli_num_rows($res) > 0;
}

function countRows(Database $db, string $table, string $where = '1=1'): int
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($safeTable === '') {
        return 0;
    }

    $res = $db->query("SELECT COUNT(*) AS total FROM {$safeTable} WHERE {$where}");
    if (!$res) {
        return 0;
    }

    $row = mysqli_fetch_assoc($res);
    return isset($row['total']) ? (int)$row['total'] : 0;
}

$yonalishlarSoni = tableExists($db, 'yonalishlar') ? countRows($db, 'yonalishlar') : 0;
$rejalarSoni = tableExists($db, 'oquv_rejalar') ? countRows($db, 'oquv_rejalar') : 0;

$kurslarSoni = 0;
if (tableExists($db, 'semestrlar')) {
    $kursRes = $db->query("
        SELECT COUNT(DISTINCT FLOOR((s.semestr + 1) / 2)) AS total
        FROM semestrlar s
        WHERE s.semestr IS NOT NULL AND s.semestr > 0
    ");
    if ($kursRes) {
        $kursRow = mysqli_fetch_assoc($kursRes);
        $kurslarSoni = isset($kursRow['total']) ? (int)$kursRow['total'] : 0;
    }
}
if ($kurslarSoni === 0 && tableExists($db, 'yonalishlar')) {
    $kursFallbackRes = $db->query("SELECT MAX(muddati) AS total FROM yonalishlar");
    if ($kursFallbackRes) {
        $kursFallbackRow = mysqli_fetch_assoc($kursFallbackRes);
        $kurslarSoni = isset($kursFallbackRow['total']) ? (int)$kursFallbackRow['total'] : 0;
    }
}

$foydalanuvchilarSoni = 0;
if (tableExists($db, 'users')) {
    $foydalanuvchilarSoni = countRows($db, 'users');
} elseif (tableExists($db, 'oqituvchilar')) {
    $foydalanuvchilarSoni = countRows($db, 'oqituvchilar');
}
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - O'quv Bo'limi</title>
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
                        <?php if ($rejalarSoni === 0): ?>
                            <span class="notification-badge">1</span>
                        <?php endif; ?>
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
                            <h3 id="yonalishlarSoni"><?= $yonalishlarSoni ?></h3>
                            <p>Ta'lim Yo'nalishlari</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #e8f6f3;">
                            <i class="fas fa-layer-group" style="color: #2ecc71;"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="kurslarSoni"><?= $kurslarSoni ?></h3>
                            <p>Kurslar</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #eafaf1;">
                            <i class="fas fa-calendar-alt" style="color: #27ae60;"></i>
                        </div>
                        <div class="stat-info">
                            <h3 id="rejalarSoni"><?= $rejalarSoni ?></h3>
                            <p>O'quv Rejalar</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="background-color: #f0f9ff;">
                            <i class="fas fa-users" style="color: #2ecc71;"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= $foydalanuvchilarSoni ?></h3>
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
                        <a href="oquv-haftalik-yaratish.php" class="action-btn">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Reja tuzish</span>
                        </a>
                        <a href="oquv-yuklamalar.php" class="action-btn secondary">
                            <i class="fas fa-download"></i>
                            <span>Hisobot yuklash</span>
                        </a>
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
                                <p>Yo'nalishlar: <?= $yonalishlarSoni ?> ta, o'quv rejalar: <?= $rejalarSoni ?> ta, kurslar: <?= $kurslarSoni ?> ta.</p>
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
