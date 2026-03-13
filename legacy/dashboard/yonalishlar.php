<?php

    include_once 'config.php';
    $db = new Database();
    $akademik_darajalar = $db->get_data_by_table_all('akademik_darajalar');
    $fakultetlar = $db->get_data_by_table_all('fakultetlar');
    $talim_shakllar = $db->get_data_by_table_all('talim_shakllar');
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yo'nalishlar - O'quv Qo'lanma</title>
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
                    <h1>Ta'lim Yo'nalishlari</h1>
                    <p class="navbar-subtitle">Yo'nalishlarni boshqarish bo'limi</p>
                </div>
                <div class="navbar-right">
                    <button class="btn btn-primary" id="addYonalishBtn">
                        <i class="fas fa-plus"></i> Yo'nalish qo'shish
                    </button>
                </div>
            </header>
            <div class="content-container">
                <!-- Yo'nalishlar jadvali -->
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Barcha Yo'nalishlar</h3>
                            <span class="badge" id="totalYonalishlar">0 ta</span>
                        </div>
                        <div class="table-actions">
                            <div class="search-box">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchYonalish" placeholder="Qidirish...">
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Yo'nalish Nomi</th>
                                    <th>Yo'nalish Kodi</th>
                                    <th>Ta'lim muddati (yil)</th>
                                    <th>Kirish yili</th>
                                    <th>Akademik daraja</th>
                                    <th>Ta'lim shakli</th>
                                    <th>Kvalifikatsiya</th>
                                    <th>Fakultet</th>
                                    <th>Yaratilgan sana</th>
                                    <th>Harakatlar</th>
                                </tr>
                            </thead>
                            <tbody id="yonalishlarTable">
                                <!-- JavaScript orqali to'ldiriladi -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Yo'nalishlar tahriri tarixi -->
                <div class="table-container mt-4">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Yo'nalish tahrirlar tarixi</h3>
                            <span class="badge" id="totalYonalishHistory">0 ta</span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Asl yo'nalish ID</th>
                                    <th>Yo'nalish nomi</th>
                                    <th>Yo'nalish kodi</th>
                                    <th>Patok</th>
                                    <th>Katta guruh</th>
                                    <th>Kichik guruh</th>
                                    <th>Akademik daraja</th>
                                    <th>Ta'lim shakli</th>
                                    <th>Fakultet</th>
                                    <th>Holat</th>
                                    <th>O'zgargan sana</th>
                                </tr>
                            </thead>
                            <tbody id="yonalishHistoryTable">
                                <!-- JavaScript orqali to'ldiriladi -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Yo'nalish qo'shish modal oynasi -->
    <div class="modal" id="yonalishModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Yo'nalish qo'shish</h3>
                <button class="modal-close" id="closeModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="yonalishForm">
                    <input type="hidden" id="yonalishEditId" value="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="yonalishNomi">
                                <i class="fas fa-compass"></i> Yo‘nalish nomi
                            </label>
                            <input type="text" id="yonalishNomi" placeholder="Dasturiy injiniring" required>
                        </div>
                        <div class="form-group">
                            <label for="yonalishCode">
                                <i class="fas fa-hashtag"></i> Yo‘nalish kodi
                            </label>
                            <input type="text" id="yonalishCode" placeholder="60610100" required>
                        </div>
                        <div class="form-group">
                            <label for="yonalishMuddati">
                                <i class="fas fa-clock"></i> Ta’lim muddati (yil)
                            </label>
                            <input type="number" id="yonalishMuddati" min="1" placeholder="4" required>
                        </div>
                        <div class="form-group">
                            <label for="kirishYili">
                                <i class="fas fa-calendar-plus"></i> Kirish yili
                            </label>
                            <input type="number" id="kirishYili" min="2000" max="<?= date('Y') ?>"
                                   placeholder="<?= date('Y') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="yonalishPatok">
                                <i class="fas fa-clock"></i> Patok soni
                            </label>
                            <input type="number" id="yonalishPatok" min="1" value='1' required>
                        </div>
                        <div class="form-group">
                            <label for="yonalishKattaguruh">
                                <i class="fas fa-clock"></i> Katta guruh soni
                            </label>
                            <input type="number" id="yonalishKattaguruh" min="1" placeholder="3" required>
                        </div>
                        <div class="form-group">
                            <label for="yonalishKichikguruh">
                                <i class="fas fa-clock"></i> Kichik guruh soni
                            </label>
                            <input type="number" id="yonalishKichikguruh" min="1" placeholder="2" required>
                        </div>
                        <div class="form-group">
                            <label for="akademikDarajaSelect">
                                <i class="fas fa-graduation-cap"></i> Akademik daraja
                            </label>
                            <select id="akademikDarajaSelect" required>
                                <option value="">Tanlang</option>
                                <?php foreach ($akademik_darajalar as $daraja): ?>
                                    <option value="<?= $daraja['id'] ?>">
                                        <?= htmlspecialchars($daraja['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="talimShakliSelect">
                                <i class="fas fa-chalkboard-user"></i> Ta’lim shakli
                            </label>
                            <select id="talimShakliSelect" required>
                                <option value="">Tanlang</option>
                                <?php foreach ($talim_shakllar as $shakl): ?>
                                    <option value="<?= $shakl['id'] ?>">
                                        <?= htmlspecialchars($shakl['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="kvalifikatsiya">
                                <i class="fas fa-user-graduate"></i> Kvalifikatsiya
                            </label>
                            <input type="text" id="kvalifikatsiya" placeholder="Bakalavr" required>
                        </div>
                        <div class="form-group">
                            <label for="fakultetSelect">
                                <i class="fas fa-building-columns"></i> Fakultet
                            </label>
                            <select id="fakultetSelect" required>
                                <option value="">Tanlang</option>
                                <?php foreach ($fakultetlar as $fakultet): ?>
                                    <option value="<?= $fakultet['id'] ?>">
                                        <?= htmlspecialchars($fakultet['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelBtn">
                    Bekor qilish
                </button>
                <button type="button" class="btn btn-primary" id="saveYonalishBtn">
                    Saqlash
                </button>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            setupModal();
            setupSearch();
            loadYonalishlar();
            loadYonalishlarHistory();
        });
        function loadYonalishlar() {
            fetch('get/yunalishlar_table.php')
                .then(res => res.text())
                .then(html => {
                    document.getElementById('yonalishlarTable').innerHTML = html;
                    const total = document.getElementById('yonalishlarTable').children.length;
                    document.getElementById('totalYonalishlar').textContent = total + ' ta';
                })
                .catch(() => {
                    document.getElementById('yonalishlarTable').innerHTML =
                        '<tr><td colspan="10">Xatolik yuz berdi</td></tr>';
                });
        }

        function loadYonalishlarHistory() {
            fetch('get/yonalishlar_history_table.php')
                .then(res => res.text())
                .then(html => {
                    const tbody = document.getElementById('yonalishHistoryTable');
                    const safeHtml = (html || '').trim();
                    const noDataFromServer = /ma.?lumot topilmadi/i.test(safeHtml);
                    if (safeHtml.length > 0 && !noDataFromServer) {
                        tbody.innerHTML = safeHtml;
                    } else {
                        tbody.innerHTML = '<tr class="empty-row"><td colspan="12">Ma\'lumot topilmadi</td></tr>';
                    }
                    const totalRows = Array.from(tbody.querySelectorAll('tr')).filter((row) => {
                        const text = (row.textContent || '').toLowerCase();
                        return !row.classList.contains('empty-row') && !text.includes("ma'lumot topilmadi");
                    }).length;
                    document.getElementById('totalYonalishHistory').textContent = totalRows + ' ta';
                })
                .catch(() => {
                    document.getElementById('yonalishHistoryTable').innerHTML =
                        '<tr class="empty-row"><td colspan="12">Xatolik yuz berdi</td></tr>';
                    document.getElementById('totalYonalishHistory').textContent = '0 ta';
                });
        }
        function setupModal() {
            const addBtn = document.getElementById('addYonalishBtn');
            const modal = document.getElementById('yonalishModal');
            const closeBtn = document.getElementById('closeModal');
            const cancelBtn = document.getElementById('cancelBtn');

            if (addBtn) {
                addBtn.addEventListener('click', () => {
                    document.getElementById('modalTitle').textContent = "Yo'nalish qo'shish";
                    document.getElementById('saveYonalishBtn').textContent = "Saqlash";
                    document.getElementById('yonalishEditId').value = '';
                    document.getElementById('yonalishForm').reset();
                    modal.classList.add('show');
                });
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    modal.classList.remove('show');
                });
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    modal.classList.remove('show');
                });
            }

            // Modal tashqarisiga bosilganda yopilishi
            window.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });

        }

        // Izoh: Jadvaldagi "Tahrirlash" tugmasi shu global funksiyani chaqiradi.
        function openYonalishEdit(id) {
            const modal = document.getElementById('yonalishModal');
            if (!id) return;

            fetch(`get/yonalish_one.php?id=${id}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success || !data.data) {
                        Toast.fire({ icon: 'error', title: data.message || "Yo'nalish topilmadi" });
                        return;
                    }

                    const row = data.data;
                    document.getElementById('yonalishEditId').value = row.id || '';
                    document.getElementById('yonalishNomi').value = row.name || '';
                    document.getElementById('yonalishCode').value = row.code || '';
                    document.getElementById('yonalishMuddati').value = row.muddati || '';
                    document.getElementById('kirishYili').value = row.kirish_yili || '';
                    document.getElementById('yonalishPatok').value = row.patok_soni || 1;
                    document.getElementById('yonalishKattaguruh').value = row.kattaguruh_soni || '';
                    document.getElementById('yonalishKichikguruh').value = row.kichikguruh_soni || '';
                    document.getElementById('akademikDarajaSelect').value = row.akademik_daraja_id || '';
                    document.getElementById('talimShakliSelect').value = row.talim_shakli_id || '';
                    document.getElementById('kvalifikatsiya').value = row.kvalifikatsiya || '';
                    document.getElementById('fakultetSelect').value = row.fakultet_id || '';

                    document.getElementById('modalTitle').textContent = "Yo'nalishni tahrirlash";
                    document.getElementById('saveYonalishBtn').textContent = "Yangilash";
                    modal.classList.add('show');
                })
                .catch(() => {
                    Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                });
        }
        window.openYonalishEdit = openYonalishEdit;

        document.addEventListener('click', (e) => {
            const deleteBtn = e.target.closest('.deleteYunalishBtn');
            if (!deleteBtn) return;

            const id = deleteBtn.dataset.id || '';
            if (!id) return;

            Swal.fire({
                title: "Ishonchingiz komilmi?",
                text: "Yo'nalish o'chiriladi",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ha, o'chirilsin",
                cancelButtonText: "Bekor qilish"
            }).then((result) => {
                if (!result.isConfirmed) return;

                const formData = new FormData();
                formData.append('id', id);

                fetch('insert/delete_yonalish.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    Toast.fire({
                        icon: data.success ? 'success' : 'error',
                        title: data.message || (data.success ? "Yo'nalish o'chirildi" : "Xatolik yuz berdi")
                    });
                    if (data.success) {
                        loadYonalishlar();
                        loadYonalishlarHistory();
                    }
                })
                .catch(() => {
                    Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                });
            });
        });

        function setupSearch() {
            const searchInput = document.getElementById('searchYonalish');

            if (!searchInput) return;

            searchInput.addEventListener('input', () => {
                const value = searchInput.value.toLowerCase();
                const rows = document.querySelectorAll('#yonalishlarTable tr');

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
            timerProgressBar: true
        });

        let isSavingYonalish = false;
        document.getElementById('saveYonalishBtn').addEventListener('click', async () => {
            if (isSavingYonalish) return;
            isSavingYonalish = true;
            const saveBtn = document.getElementById('saveYonalishBtn');
            const oldBtnText = saveBtn.textContent;
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saqlanmoqda...';

            const editId = document.getElementById('yonalishEditId').value;
            const yonalishNomi        = document.getElementById('yonalishNomi').value.trim();
            const yonalishCode        = document.getElementById('yonalishCode').value.trim();
            const yonalishMuddati     = document.getElementById('yonalishMuddati').value;
            const akademikDarajaId   = document.getElementById('akademikDarajaSelect').value;
            const talimShakliId      = document.getElementById('talimShakliSelect').value;
            const kvalifikatsiya     = document.getElementById('kvalifikatsiya').value.trim();
            const fakultetId         = document.getElementById('fakultetSelect').value;
            const kirishYili         = document.getElementById('kirishYili').value;
            const yonalishPatok       = document.getElementById('yonalishPatok').value;
            const yonalishKattaguruh  = document.getElementById('yonalishKattaguruh').value;
            const yonalishKichikguruh = document.getElementById('yonalishKichikguruh').value;

            const formData = new FormData();
            formData.append('nomi', yonalishNomi);
            formData.append('code', yonalishCode);
            formData.append('muddati', yonalishMuddati);
            formData.append('akademik_daraja_id', akademikDarajaId);
            formData.append('talim_shakli_id', talimShakliId);
            formData.append('kvalifikatsiya', kvalifikatsiya);
            formData.append('fakultet_id', fakultetId);
            formData.append('kirish_yili', kirishYili);
            formData.append('patok_soni', yonalishPatok);
            formData.append('kattaguruh_soni', yonalishKattaguruh);
            formData.append('kichikguruh_soni', yonalishKichikguruh);
            if (editId) {
                formData.append('id', editId);
            }

            if (editId) {
                const syncDecision = await Swal.fire({
                    title: "Sinxronlash holatini tanlang",
                    text: "Bu tahrir sinxronlangan holatda saqlansinmi?",
                    icon: "question",
                    showCancelButton: true,
                    showDenyButton: true,
                    confirmButtonText: "Ha, sinxronlansin",
                    denyButtonText: "Yo'q, sinxronlanmasin",
                    cancelButtonText: "Bekor qilish"
                });

                if (syncDecision.isDismissed) {
                    isSavingYonalish = false;
                    saveBtn.disabled = false;
                    saveBtn.textContent = oldBtnText;
                    return;
                }

                formData.append('sync_mode', syncDecision.isConfirmed ? 'sync' : 'nosync');
            }

            const endpoint = editId ? 'insert/update_yonalish.php' : 'insert/add_yonalish.php';

            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Toast.fire({
                        icon: 'success',
                        title: data.message || "Yo'nalish muvaffaqiyatli saqlandi"
                    });

                    document.getElementById('yonalishModal')?.classList.remove('show');

                    document.getElementById('yonalishForm').reset();
                    document.getElementById('yonalishEditId').value = '';
                    document.getElementById('modalTitle').textContent = "Yo'nalish qo'shish";
                    document.getElementById('saveYonalishBtn').textContent = "Saqlash";
                    loadYonalishlar();
                    loadYonalishlarHistory();

                } else {
                    Toast.fire({
                        icon: 'error',
                        title: data.message || "Xatolik yuz berdi"
                    });
                }
            })
            .catch(() => {
                Toast.fire({
                    icon: 'error',
                    title: "Server bilan bog'lanib bo'lmadi"
                });
            })
            .finally(() => {
                isSavingYonalish = false;
                saveBtn.disabled = false;
                saveBtn.textContent = oldBtnText;
            });
        });
        </script>

</body>
</html>
