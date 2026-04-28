<?php
$isKafedraMudiri = function_exists('legacy_is_kafedra_mudiri') && legacy_is_kafedra_mudiri();
$displayName = function_exists('legacy_user_display_name') ? legacy_user_display_name() : 'Foydalanuvchi';
$roleLabel = function_exists('legacy_user_role_label') ? legacy_user_role_label() : 'Admin';
?>
<link rel="stylesheet" href="../assets/css/sidebar_style.css">
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <h2>O'quv Bo'limi</h2>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Menyuni ochish yoki yig'ish" aria-expanded="true">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <li class="nav-item">
                <a href="index.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li class="nav-item">
                <details class="nav-details">
                    <summary class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Sozlamalar</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </summary>
                    <ul class="submenu">
                        <?php if (!$isKafedraMudiri): ?>
                            <li>
                                <a href="foydalanuvchilar.php">
                                    <i class="fas fa-user-shield"></i>
                                    <span>Foydalanuvchilar</span>
                                </a>
                            </li>
                            <li>
                                <a href="fakultetlar.php">
                                    <i class="fas fa-building-columns"></i>
                                    <span>Fakultetlar</span>
                                </a>
                            </li>
                            <li>
                                <a href="akademik-darajalar.php">
                                    <i class="fas fa-graduation-cap"></i>
                                    <span>Akademik darajalar</span>
                                </a>
                            </li>
                            <li>
                                <a href="talim-shakllari.php">
                                    <i class="fas fa-chalkboard-user"></i>
                                    <span>Ta'lim shakllar</span>
                                </a>
                            </li>
                            <li>
                                <a href="yonalishlar.php">
                                    <i class="fas fa-compass"></i>
                                    <span>Yo'nalishlar</span>
                                </a>
                            </li>
                            <li>
                                <a href="kafedralar.php">
                                    <i class="fas fa-sitemap"></i>
                                    <span>Kafedralar</span>
                                </a>
                            </li>
                            <li>
                                <a href="oquv-shakllar.php">
                                    <i class="fas fa-layer-group"></i>
                                    <span>O‘quv shakllar</span>
                                </a>
                            </li>
                            <li>
                                <a href="dars-soat-turlar.php">
                                    <i class="fas fa-clock"></i>
                                    <span>Dars soat turlar</span>
                                </a>
                            </li>
                            <li>
                                <a href="smestrlar.php">
                                    <i class="fas fa-calendar-week"></i>
                                    <span>Semestrlar</span>
                                </a>
                            </li>
                            <li>
                                <a href="guruhlar.php">
                                    <i class="fas fa-users"></i>
                                    <span>Guruhlar</span>
                                </a>
                            </li>
                            <li>
                                <a href="oquv_haftaligi_turlar.php">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>O‘quv haftalik turlar</span>
                                </a>
                            </li>
                            <li>
                                <a href="qoshimcha_dars_turlar.php">
                                    <i class="fas fa-book-open"></i>
                                    <span>Qo‘shimcha dars turlar</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li>
                            <a href="oqtuvchilar.php">
                                <i class="fas fa-user-tie"></i>
                                <span>O‘qituvchilar</span>
                            </a>
                        </li>
                    </ul>
                </details>
            </li>

            <li class="nav-item">
                <details class="nav-details">
                    <summary class="nav-link">
                        <i class="fas fa-clipboard-list"></i>
                        <span>O‘quv reja</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </summary>
                    <ul class="submenu">
                        <?php if (!$isKafedraMudiri): ?>
                            <li>
                                <a href="oquv-reja-yaratish.php">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>O‘quv reja yaratish</span>
                                </a>
                            </li>
                            <li>
                                <a href="fanlardan-nusxa-olish.php">
                                    <i class="fas fa-copy"></i>
                                    <span>Fanlardan nusxa olish</span>
                                </a>
                            </li>
                            <li>
                                <a href="tanlov-fan-yaratish.php">
                                    <i class="fas fa-list-check"></i>
                                    <span>Tanlov fan yaratish</span>
                                </a>
                            </li>
                            <li>
                                <a href="umumtalim-fan-birlashtirish.php">
                                    <i class="fas fa-layer-group"></i>
                                    <span>Birlashtiriladigan fanlarni biriktirish</span>
                                </a>
                            </li>
                            <li>
                                <a href="umumtalim-fanlar-royxati.php">
                                    <i class="fas fa-list-ul"></i>
                                    <span>Birlashtirilgan fanlar ro'yxati</span>
                                </a>
                            </li>
                            <li>
                                <a href="chet-tili-biriktirish.php">
                                    <i class="fas fa-language"></i>
                                    <span>Chet tilini biriktirish</span>
                                </a>
                            </li>
                            <li>
                                <a href="qoshimcha-oquv-reja-yaratish.php">
                                    <i class="fas fa-calendar-plus"></i>
                                    <span>Qo‘shimcha reja yaratish</span>
                                </a>
                            </li>
                            <li>
                                <a href="magistr-doktorant-yuklama.php">
                                    <i class="fas fa-user-graduate"></i>
                                    <span>Magistr/Doktorant kiritish</span>
                                </a>
                            </li>
                            <li>
                                <a href="magistr-doktorant-qoshimcha-reja.php">
                                    <i class="fas fa-user-clock"></i>
                                    <span>Magistr/Doktorant qo‘shimcha reja</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li>
                            <a href="oquv-rejalar.php">
                                <i class="fas fa-list"></i>
                                <span>Barcha o‘quv rejalar</span>
                            </a>
                        </li>
                        <li>
                            <a href="ishchi-oquv-rejalar.php">
                                <i class="fas fa-clipboard-check"></i>
                                <span>Barcha ishchi o‘quv rejalar</span>
                            </a>
                        </li>
                        <?php if (!$isKafedraMudiri): ?>
                            <li>
                                <a href="oquv-haftalik-yaratish.php">
                                    <i class="fas fa-plus"></i>
                                    <span>O‘quv haftaligini yaratish</span>
                                </a>
                            </li>
                            <li>
                                <a href="oquv-haftaliklar.php">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Barcha o‘quv haftaliklar</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </details>
            </li>

            <li class="nav-item">
                <details class="nav-details">
                    <summary class="nav-link">
                        <i class="fas fa-briefcase"></i>
                        <span>O‘quv yuklama</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </summary>
                    <ul class="submenu">
                        <li>
                            <a href="oquv-yuklamalar.php">
                                <i class="fas fa-tasks"></i>
                                <span>Barcha o'quv yuklamalar</span>
                            </a>
                        </li>
                        <li>
                            <a href="magistr-doktorant-yuklamalar.php">
                                <i class="fas fa-table"></i>
                                <span>Magistr/Doktorant yuklama jadvali</span>
                            </a>
                        </li>
                    </ul>
                </details>
            </li>

            <li class="nav-item">
                <details class="nav-details">
                    <summary class="nav-link">
                        <i class="fas fa-chalkboard"></i>
                        <span>O‘quv taqsimot</span>
                        <i class="fas fa-chevron-down arrow"></i>
                    </summary>
                    <ul class="submenu">
                        <li>
                            <a href="oquv-taqsimotlar.php">
                                <i class="fas fa-project-diagram"></i>
                                <span>Barcha o'quv taqsimotlar</span>
                            </a>
                        </li>
                        <li>
                            <a href="oqituvchi-taqsimotlar.php">
                                <i class="fas fa-user-clock"></i>
                                <span>O'qituvchilar soat taqsimoti</span>
                            </a>
                        </li>
                        <li>
                            <a href="oqituvchi-bildirgi.php">
                                <i class="fas fa-file-signature"></i>
                                <span>O'qituvchilar bildirgisi</span>
                            </a>
                        </li>
                    </ul>
                </details>
            </li>
        </ul>
        <div class="sidebar-footer">
            <div class="user-profile">
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?></strong>
                    <small><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></small>
                </div>
            </div>
            <a href="/logout" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Chiqish</span>
            </a>
        </div>
    </nav>
</aside>
