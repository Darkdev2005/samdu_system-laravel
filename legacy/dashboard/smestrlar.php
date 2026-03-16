<?php
include_once 'config.php';
$db = new Database();
$fakultetlar = $db->get_data_by_table_all('fakultetlar');
$yonalishlar = [];
$yonalishRes = $db->query("
    SELECT DISTINCT
        y.id,
        y.name,
        y.kirish_yili,
        s.fakultet_id
    FROM semestrlar s
    JOIN yonalishlar y ON y.id = s.yonalish_id
    WHERE y.id IS NOT NULL
    ORDER BY y.name, y.kirish_yili
");
if ($yonalishRes) {
    while ($row = mysqli_fetch_assoc($yonalishRes)) {
        $yonalishlar[] = $row;
    }
}
if (empty($yonalishlar)) {
    $yonalishlar = $db->get_data_by_table_all('yonalishlar');
}

$yonalishJson = json_encode(
    array_values(
        array_map(
            static function (array $row): array {
                return [
                    'id' => (int)($row['id'] ?? 0),
                    'name' => (string)($row['name'] ?? ''),
                    'fakultet_id' => (int)($row['fakultet_id'] ?? 0),
                    'kirish_yili' => (string)($row['kirish_yili'] ?? ''),
                ];
            },
            $yonalishlar
        )
    ),
    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);
if ($yonalishJson === false) {
    $yonalishJson = '[]';
}
$semestrValues = [];
$semestrRes = $db->query("SELECT DISTINCT semestr FROM semestrlar ORDER BY semestr");
if ($semestrRes) {
    while ($row = mysqli_fetch_assoc($semestrRes)) {
        $semestrValues[] = (int)$row['semestr'];
    }
}
$initialSemestrlar = $db->get_semestrlar();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Semestrlar - O‘quv Qo‘llanma</title>

    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .filters-panel {
            display: grid;
            grid-template-columns: repeat(3, minmax(180px, 1fr)) auto auto;
            gap: 12px;
            align-items: end;
            margin-bottom: 20px;
        }
        .filter-field label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #2c3e50;
        }
        .filter-field select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d8e2eb;
            border-radius: 12px;
            background: #fff;
            font-size: 14px;
        }
        .filter-btn {
            height: 46px;
            padding: 0 18px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .filter-btn.apply {
            background: #22c55e;
            color: #fff;
        }
        .filter-btn.reset {
            background: #eef2f7;
            color: #334155;
        }
        @media (max-width: 1200px) {
            .filters-panel {
                grid-template-columns: repeat(2, minmax(180px, 1fr));
            }
        }
        @media (max-width: 768px) {
            .filters-panel {
                grid-template-columns: 1fr;
            }
        }
    </style>
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
                    <div class="filters-panel">
                        <div class="filter-field">
                            <label for="fakultetFilter">Fakultet</label>
                            <select id="fakultetFilter">
                                <option value="">Barcha fakultetlar</option>
                                <?php foreach ($fakultetlar as $f): ?>
                                    <option value="<?= (int)$f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-field">
                            <label for="yonalishFilter">Yo'nalish</label>
                            <select id="yonalishFilter">
                                <option value="">Barcha yo'nalishlar</option>
                                <?php foreach ($yonalishlar as $y): ?>
                                    <option value="<?= (int)($y['id'] ?? 0) ?>">
                                        <?= htmlspecialchars((string)($y['name'] ?? '')) ?><?= !empty($y['kirish_yili']) ? ' - '.htmlspecialchars((string)$y['kirish_yili']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-field">
                            <label for="semestrFilter">Semestr</label>
                            <select id="semestrFilter">
                                <option value="">Barcha semestrlar</option>
                                <?php foreach ($semestrValues as $value): ?>
                                    <option value="<?= $value ?>"><?= $value ?>-semestr</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button class="filter-btn apply" id="applyFiltersBtn">Filtrlash</button>
                        <button class="filter-btn reset" id="resetFiltersBtn">Tozalash</button>
                    </div>

                    <div class="table-header">
                        <div class="table-title">
                            <h3>Barcha semestrlar</h3>
                            <span class="badge" id="totalSmestrlar"><?= count($initialSemestrlar) ?> ta</span>
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
                                <?php foreach ($initialSemestrlar as $semestr): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($semestr['id']); ?></td>
                                        <td><?php echo htmlspecialchars($semestr['fakultet_name']); ?></td>
                                        <td><?= implode('', array_map(fn($w)=>mb_strtoupper(mb_substr($w,0,1,'UTF-8'),'UTF-8'), preg_split('/\s+/u', trim($semestr['yonalish_name'])))).'_'.$semestr['kirish_yili']; ?></td>
                                        <td><?php echo htmlspecialchars($semestr['semestr']); ?></td>
                                        <td><?php echo htmlspecialchars($semestr['create_at']); ?></td>
                                        <td>
                                            <button
                                                class="btn btn-sm btn-warning editSmestrBtn"
                                                data-id="<?php echo $semestr['id']; ?>"
                                                data-semestr="<?php echo (int) $semestr['semestr']; ?>"
                                            >
                                                <i class="fas fa-edit"></i> Tahrirlash
                                            </button>
                                            <button class="btn btn-sm btn-danger deleteSmestrBtn" data-id="<?php echo $semestr['id']; ?>">
                                                <i class="fas fa-trash-alt"></i> O'chirish
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($initialSemestrlar)): ?>
                                    <tr>
                                        <td colspan="6" style="text-align:center; color:#64748b;">Ma'lumot topilmadi</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../assets/js/app.js"></script>
    <script id="yonalishData" type="application/json"><?= json_encode(
        json_decode($yonalishJson, true) ?? [],
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) ?: '[]' ?></script>
    <script>
        const yonalishDataEl = document.getElementById('yonalishData');
        let allYonalishlar = [];
        try {
            allYonalishlar = JSON.parse(yonalishDataEl ? yonalishDataEl.textContent : '[]');
            if (!Array.isArray(allYonalishlar)) {
                allYonalishlar = [];
            }
        } catch (e) {
            allYonalishlar = [];
        }
        const hasSwal = typeof window.Swal !== 'undefined';

        const Toast = hasSwal
            ? Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2500,
                timerProgressBar: true
            })
            : {
                fire({ title }) {
                    console.warn(title || 'Xabar');
                }
            };

        document.addEventListener('DOMContentLoaded', () => {
            populateYonalishFilter();
            loadSmestrlar();
            initSearch();
            initRowActions();
            initFilters();
        });

        function loadSmestrlar() {
            const params = new URLSearchParams({
                fakultet_id: document.getElementById('fakultetFilter').value || '',
                yonalish_id: document.getElementById('yonalishFilter').value || '',
                semestr: document.getElementById('semestrFilter').value || ''
            });

            fetch(`get/smestrlar_table.php?${params.toString()}`, { method: 'GET' })
                .then(res => res.text())
                .then(html => {
                    const tbody = document.getElementById('smestrlarTable');
                    tbody.innerHTML = html;
                    const rowCount = Math.max(0, tbody.querySelectorAll('tr').length - (tbody.textContent.includes("Ma'lumot topilmadi") ? 1 : 0));
                    document.getElementById('totalSmestrlar').textContent = rowCount + ' ta';
                })
                .catch(() => {
                    document.getElementById('smestrlarTable').innerHTML =
                        '<tr><td colspan="6">Xatolik yuz berdi</td></tr>';
                });
        }

        function populateYonalishFilter() {
            const fakultetId = document.getElementById('fakultetFilter').value || '';
            const yonalishSelect = document.getElementById('yonalishFilter');
            const currentValue = yonalishSelect.value;
            let options = "<option value=\"\">Barcha yo'nalishlar</option>";
            const fakultetIdNum = Number(fakultetId);
            const filteredYonalishlar = allYonalishlar.filter(item => {
                if (!fakultetId) return true;
                return Number(item.fakultet_id) === fakultetIdNum;
            });

            filteredYonalishlar.forEach(item => {
                const label = item.kirish_yili ? `${item.name} - ${item.kirish_yili}` : item.name;
                const selected = String(item.id) === String(currentValue) ? 'selected' : '';
                options += `<option value=\"${item.id}\" ${selected}>${label}</option>`;
            });

            if (fakultetId && filteredYonalishlar.length === 0) {
                options += "<option value=\"\" disabled>Yo'nalish topilmadi</option>";
            }

            yonalishSelect.innerHTML = options;
        }

        function initFilters() {
            document.getElementById('fakultetFilter').addEventListener('change', () => {
                document.getElementById('yonalishFilter').value = '';
                populateYonalishFilter();
            });

            document.getElementById('applyFiltersBtn').addEventListener('click', () => {
                loadSmestrlar();
            });

            document.getElementById('resetFiltersBtn').addEventListener('click', () => {
                document.getElementById('fakultetFilter').value = '';
                document.getElementById('yonalishFilter').value = '';
                document.getElementById('semestrFilter').value = '';
                populateYonalishFilter();
                loadSmestrlar();
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
