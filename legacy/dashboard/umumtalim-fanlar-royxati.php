<?php
    // Izoh: Birlashtirilgan fanlar ro'yxati uchun alohida sahifa.
    include_once 'config.php';
    $db = new Database();
    $h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $kafedralar = $db->get_data_by_table_all('kafedralar');
    $semestrNums = [];
    $semestrRes = $db->query("SELECT DISTINCT semestr FROM semestrlar ORDER BY semestr");
    if ($semestrRes) {
        while ($row = mysqli_fetch_assoc($semestrRes)) {
            $semestrNums[] = (int) $row['semestr'];
        }
    }

    $umumtalimFanRows = [];
    $umumtalimFanResult = $db->query("
        SELECT
            uf.id,
            uf.fan_code,
            uf.fan_name,
            uf.semestr,
            uf.kafedra_id,
            uf.create_at,
            k.name AS kafedra_name,
            GROUP_CONCAT(
                DISTINCT CONCAT(
                    COALESCE(y.name, ''),
                    IF(COALESCE(y.kirish_yili, '') <> '', CONCAT(' - ', y.kirish_yili), ''),
                    ' - ',
                    COALESCE(s.semestr, ''),
                    '-semestr'
                )
                ORDER BY s.semestr SEPARATOR ' | '
            ) AS biriktirishlar
        FROM umumtalim_fanlar uf
        JOIN umumtalim_fan_biriktirish ub ON ub.umumtalim_fan_id = uf.id
        LEFT JOIN kafedralar k ON k.id = uf.kafedra_id
        LEFT JOIN semestrlar s ON s.id = ub.semestr_id
        LEFT JOIN yonalishlar y ON y.id = ub.yonalish_id
        GROUP BY uf.id
        HAVING COUNT(ub.id) > 0
        ORDER BY uf.id DESC
    ");
    if ($umumtalimFanResult) {
        while ($row = mysqli_fetch_assoc($umumtalimFanResult)) {
            $umumtalimFanRows[] = $row;
        }
    }
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>Birlashtirilgan fanlar ro'yxati</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-navbar">
                <h1>Birlashtirilgan fanlar ro'yxati</h1>
            </header>

            <div class="content-container">
                <div class="table-container">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Birlashtiriladigan fanlar ro'yxati</h3>
                            <span class="badge"><?php echo count($umumtalimFanRows); ?> ta</span>
                        </div>
                        <div class="table-actions">
                            <a href="umumtalim-fan-birlashtirish.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> Biriktirish sahifasi
                            </a>
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
                                    <th>Biriktirishlar</th>
                                    <th>Yaratilgan sana</th>
                                    <th>Harakatlar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($umumtalimFanRows) === 0): ?>
                                    <tr>
                                        <td colspan="8">Birlashtiriladigan fanlar topilmadi</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($umumtalimFanRows as $row): ?>
                                        <tr>
                                            <td><?php echo $h($row['id']); ?></td>
                                            <td><?php echo $h($row['fan_code']); ?></td>
                                            <td><?php echo $h($row['fan_name']); ?></td>
                                            <td><?php echo $h($row['semestr']); ?></td>
                                            <td><?php echo $h($row['kafedra_name'] ?? '-'); ?></td>
                                            <td><?php echo $h($row['biriktirishlar'] ?? '-'); ?></td>
                                            <td><?php echo $h($row['create_at']); ?></td>
                                            <td>
                                                <button
                                                    class="btn btn-sm btn-warning editUmumtalimFanBtn"
                                                    data-id="<?php echo (int)$row['id']; ?>"
                                                    data-code="<?php echo $h($row['fan_code']); ?>"
                                                    data-name="<?php echo $h($row['fan_name']); ?>"
                                                    data-semestr="<?php echo $h($row['semestr']); ?>"
                                                    data-kafedra="<?php echo (int)$row['kafedra_id']; ?>"
                                                >
                                                    <i class="fas fa-edit"></i> Tahrirlash
                                                </button>
                                                <button class="btn btn-sm btn-danger deleteUmumtalimFanBtn" data-id="<?php echo (int)$row['id']; ?>">
                                                    <i class="fas fa-trash-alt"></i> O'chirish
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="modal" id="umumtalimEditModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Birlashtiriladigan fanni tahrirlash</h3>
                <button class="modal-close" id="closeUmumtalimEditModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body">
                <form id="umumtalimEditForm">
                    <input type="hidden" id="editUmumtalimId">
                    <div class="form-group">
                        <label>Fan kodi</label>
                        <input type="text" id="editUmumtalimCode" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Fan nomi</label>
                        <input type="text" id="editUmumtalimName" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Semestr</label>
                        <select id="editUmumtalimSemestr" class="form-control" required>
                            <option value="">Tanlang</option>
                            <?php foreach ($semestrNums as $num): ?>
                                <option value="<?php echo (int)$num; ?>"><?php echo (int)$num; ?>-semestr</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kafedra</label>
                        <select id="editUmumtalimKafedra" class="form-control" required>
                            <option value="">Tanlang</option>
                            <?php foreach ($kafedralar as $k): ?>
                                <option value="<?php echo (int)$k['id']; ?>">
                                    <?php echo $h($k['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelUmumtalimEditBtn">Bekor qilish</button>
                <button class="btn btn-primary" id="saveUmumtalimEditBtn">Saqlash</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="/assets/vendor/jquery/jquery-3.6.0.min.js"></script>
    <script>window.jQuery || document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>')</script>
    <script>
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });

        const editModal = document.getElementById('umumtalimEditModal');
        const closeEditModal = () => editModal?.classList.remove('show');

        document.getElementById('closeUmumtalimEditModal')?.addEventListener('click', closeEditModal);
        document.getElementById('cancelUmumtalimEditBtn')?.addEventListener('click', closeEditModal);

        window.addEventListener('click', (e) => {
            if (e.target === editModal) {
                closeEditModal();
            }
        });

        $(document).on('click', '.editUmumtalimFanBtn', function() {
            const btn = $(this);
            $('#editUmumtalimId').val(btn.data('id'));
            $('#editUmumtalimCode').val(btn.data('code'));
            $('#editUmumtalimName').val(btn.data('name'));
            $('#editUmumtalimSemestr').val(btn.data('semestr'));
            $('#editUmumtalimKafedra').val(btn.data('kafedra'));
            editModal?.classList.add('show');
        });

        document.getElementById('saveUmumtalimEditBtn')?.addEventListener('click', () => {
            const id = document.getElementById('editUmumtalimId').value;
            const code = document.getElementById('editUmumtalimCode').value.trim();
            const name = document.getElementById('editUmumtalimName').value.trim();
            const semestr = document.getElementById('editUmumtalimSemestr').value;
            const kafedra = document.getElementById('editUmumtalimKafedra').value;

            if (!id || !code || !name || !semestr || !kafedra) {
                Toast.fire({ icon: 'error', title: "Barcha maydonlarni to'ldiring!" });
                return;
            }

            const formData = new FormData();
            formData.append('id', id);
            formData.append('fan_code', code);
            formData.append('fan_name', name);
            formData.append('semestr', semestr);
            formData.append('kafedra_id', kafedra);

            fetch('insert/update_umumtalim_fan.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Toast.fire({ icon: 'success', title: data.message || "Tahrirlandi" });
                    closeEditModal();
                    setTimeout(() => window.location.reload(), 300);
                } else {
                    Toast.fire({ icon: 'error', title: data.message || 'Xatolik yuz berdi' });
                }
            })
            .catch(() => {
                Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
            });
        });

        $(document).on('click', '.deleteUmumtalimFanBtn', function() {
            const id = $(this).data('id');
            if (!id) return;

            Swal.fire({
                title: "O'chirishni tasdiqlaysizmi?",
                text: "Bu amal orqaga qaytmaydi",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Ha, o'chirish",
                cancelButtonText: "Bekor qilish"
            }).then((result) => {
                if (!result.isConfirmed) return;

                const formData = new FormData();
                formData.append('id', id);

                fetch('insert/delete_umumtalim_fan.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Toast.fire({ icon: 'success', title: data.message || "O'chirildi" });
                        setTimeout(() => window.location.reload(), 300);
                    } else {
                        Toast.fire({ icon: 'error', title: data.message || 'Xatolik yuz berdi' });
                    }
                })
                .catch(() => {
                    Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                });
            });
        });
    </script>
    <script src="../assets/js/app.js"></script>
</body>
</html>
