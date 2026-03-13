<?php
    // Izoh: Birlashtiriladigan fanlar va yo'nalish+semestrlar ro'yxatini olish.
    include_once 'config.php';
    $db = new Database();

    $fanOptionsBySemestr = [];
    // Izoh: Fanlar ro'yxati fanlar jadvalidan olinadi, lekin faqat umumtalim_fanlar bilan mos tushadiganlari chiqariladi.
    $fanResult = $db->query("
        SELECT
            f.id,
            f.fan_name,
            f.fan_code,
            f.semestr_id,
            f.kafedra_id,
            k.name AS kafedra_name
        FROM fanlar f
        JOIN semestrlar s ON s.id = f.semestr_id
        JOIN umumtalim_fanlar uf ON
            uf.fan_code = f.fan_code
            AND uf.fan_name = f.fan_name
            AND uf.semestr = s.semestr
        LEFT JOIN kafedralar k ON k.id = f.kafedra_id
        WHERE f.tanlov_fan IN (0, 2)
        ORDER BY f.semestr_id, f.fan_name, f.id DESC
    ");
    if ($fanResult) {
        $seenFanKeys = [];
        while ($row = mysqli_fetch_assoc($fanResult)) {
            $semestrId = (int) ($row['semestr_id'] ?? 0);
            if ($semestrId <= 0) {
                continue;
            }
            // Izoh: Bir semestrda bir xil fan (kod+nom) dublikati bo'lsa, faqat bittasini qoldiramiz.
            $fanKey = $semestrId . '|' . ($row['fan_code'] ?? '') . '|' . ($row['fan_name'] ?? '');
            if (isset($seenFanKeys[$fanKey])) {
                continue;
            }
            $seenFanKeys[$fanKey] = true;
            // Izoh: Birlashtiriladigan fan selectda faqat fan nomi ko'rsatiladi (kod ko'rsatilmaydi).
            $label = trim($row['fan_name']);
            if (!empty($row['kafedra_name'])) {
                $label .= ' (' . $row['kafedra_name'] . ')';
            }
            if (!isset($fanOptionsBySemestr[$semestrId])) {
                $fanOptionsBySemestr[$semestrId] = '';
            }
            $fanOptionsBySemestr[$semestrId] .= '<option value="' . (int)$row['id'] . '">' . htmlspecialchars($label) . '</option>';
        }
    }

    $semestrlar = $db->get_semestrlar();
    $kafedralar = $db->get_data_by_table_all('kafedralar');
    $semestrNums = [];
    $semestrRes = $db->query("SELECT DISTINCT semestr FROM semestrlar ORDER BY semestr");
    if ($semestrRes) {
        while ($row = mysqli_fetch_assoc($semestrRes)) {
            $semestrNums[] = (int) $row['semestr'];
        }
    }
    $semestrOptions = '';
    foreach ($semestrlar as $s) {
        $yonalishName = trim($s['yonalish_name'] ?? '');
        $kirishYili = trim($s['kirish_yili'] ?? '');
        $semestrNum = trim($s['semestr'] ?? '');
        $daraja = mb_strtolower(trim($s['akademik_daraja_name'] ?? ''), 'UTF-8');
        $darajaPrefix = '';
        if (strpos($daraja, 'magistr') !== false) {
            $darajaPrefix = 'M ';
        } elseif (strpos($daraja, 'bakalavr') !== false) {
            $darajaPrefix = 'B ';
        }

        // Izoh: Yo'nalish nomini to'liq ko'rsatamiz.
        $labelParts = [];
        if ($yonalishName !== '') {
            $labelParts[] = $yonalishName;
        }
        if ($kirishYili !== '') {
            $labelParts[] = $kirishYili;
        }
        $label = implode(' - ', $labelParts);
        if ($semestrNum !== '') {
            $label = ($label !== '' ? $label . ' - ' : '') . $semestrNum . '-semestr';
        }
        if ($label === '') {
            $label = 'Semestr: ' . (int)$s['id'];
        }
        $label = $darajaPrefix . $label;
        $semestrOptions .= '<option value="' . (int)$s['id'] . '">' . htmlspecialchars($label) . '</option>';
    }
    if ($semestrOptions === '') {
        $semestrOptions = '<option value="" disabled>Semestr topilmadi</option>';
    }

    // Izoh: Birlashtiriladigan fanlar ro'yxatini (biriktirishlar bilan) shu sahifaga chiqarish.
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
        LEFT JOIN kafedralar k ON k.id = uf.kafedra_id
        LEFT JOIN umumtalim_fan_biriktirish ub ON ub.umumtalim_fan_id = uf.id
        LEFT JOIN semestrlar s ON s.id = ub.semestr_id
        LEFT JOIN yonalishlar y ON y.id = ub.yonalish_id
        GROUP BY uf.id
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
    <title>Birlashtiriladigan fanlarni biriktirish</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-navbar">
                <h1>Birlashtiriladigan fanlarni biriktirish</h1>
            </header>
            <div class="content-container">
                <form id="birlashtirishForm" class="card">
                    <h3 class="section-title">Umumiy ma'lumot</h3>
                    <input type="hidden" id="masterFanId" name="master_fan_id" value="">
                    <div class="form-group">
                        <label>Yo'nalish + semestr va fan (biriktirish uchun)</label>
                        <div id="yonalishWrapper">
                            <div class="yonalish-item">
                                <div class="form-grid-2">
                                    <div class="form-group">
                                        <select class="form-control yonalish-select" name="semestr_ids[]" required>
                                            <option value="">Tanlang</option>
                                            <?php echo $semestrOptions; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <select class="form-control fan-select" name="fan_ids[]" required>
                                            <option value="">Tanlang</option>
                                        </select>
                                    </div>
                                    <div class="dars-soat-actions">
                                        <button type="button" class="btn btn-outline btn-sm addYonalish">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm removeYonalish">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Birlashtirish
                        </button>
                    </div>
                </form>

                <!-- Izoh: Birlashtiriladigan fanlar ro'yxati (bitta jadval). -->
                <div class="table-container mt-4">
                    <div class="table-header">
                        <div class="table-title">
                            <h3>Birlashtiriladigan fanlar ro'yxati</h3>
                            <span class="badge"><?php echo count($umumtalimFanRows); ?> ta</span>
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
                                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                                            <td><?php echo htmlspecialchars($row['fan_code']); ?></td>
                                            <td><?php echo htmlspecialchars($row['fan_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['semestr']); ?></td>
                                            <td><?php echo htmlspecialchars($row['kafedra_name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($row['biriktirishlar'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($row['create_at']); ?></td>
                                            <td>
                                                <button
                                                    class="btn btn-sm btn-warning editUmumtalimFanBtn"
                                                    data-id="<?php echo (int)$row['id']; ?>"
                                                    data-code="<?php echo htmlspecialchars($row['fan_code']); ?>"
                                                    data-name="<?php echo htmlspecialchars($row['fan_name']); ?>"
                                                    data-semestr="<?php echo htmlspecialchars($row['semestr']); ?>"
                                                    data-kafedra="<?php echo htmlspecialchars($row['kafedra_id']); ?>"
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

    <!-- Izoh: Birlashtiriladigan fanni tahrirlash modal oynasi. -->
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
                                    <?php echo htmlspecialchars($k['name']); ?>
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
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        const semestrOptions = `<?php echo $semestrOptions; ?>`;
        const fanOptionsBySemestr = <?php echo json_encode($fanOptionsBySemestr, JSON_UNESCAPED_UNICODE); ?>;

        $(document).ready(function() {
            // Izoh: Yo'nalish selectlariga select2 qo'llash.
            $('.yonalish-select').select2({
                placeholder: "Yo'nalishni tanlang",
                allowClear: true,
                width: '100%',
            });

            // Izoh: Fan selectlariga select2 qo'llash.
            $('.fan-select').select2({
                placeholder: "Birlashtiriladigan fanni tanlang",
                allowClear: true,
                width: '100%',
            });
        });

        // Izoh: Master fan doim birinchi qatordagi fan bo'ladi (submit paytida qayta hisoblanadi).

        // Izoh: Yo'nalish+semestr selectini to'ldirish.
        function fillYonalishOptions(select) {
            select.empty().append(new Option('Tanlang', '', false, false));
            select.append(semestrOptions);
        }

        // Izoh: Semestr bo'yicha birlashtiriladigan fanlarni chiqarish.
        function renderFanOptionsBySemestr(select, semestrId) {
            select.empty().append(new Option('Tanlang', '', false, false));

            if (!semestrId) {
                select.val(null).trigger('change');
                return;
            }
            if (fanOptionsBySemestr[semestrId]) {
                select.append(fanOptionsBySemestr[semestrId]);
            } else {
                select.append(new Option("Birlashtiriladigan fan topilmadi", "", false, false));
            }

            select.val(null).trigger('change');
        }

        // Izoh: Yo'nalish selectini + bilan ko'paytirish.
        $(document).on('click', '.addYonalish', function() {
            const wrapper = $('#yonalishWrapper');
            const newItem = $(`
                <div class="yonalish-item mt-2">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <select class="form-control yonalish-select" name="semestr_ids[]" required>
                                <option value="">Tanlang</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <select class="form-control fan-select" name="fan_ids[]" required>
                                <option value="">Tanlang</option>
                            </select>
                        </div>
                        <div class="dars-soat-actions">
                            <button type="button" class="btn btn-outline btn-sm addYonalish">
                                <i class="fas fa-plus"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm removeYonalish">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `);

            wrapper.append(newItem);
            const newSemestr = newItem.find('.yonalish-select');
            const newFan = newItem.find('.fan-select');

            fillYonalishOptions(newSemestr);
            newSemestr.select2({
                placeholder: "Yo'nalishni tanlang",
                allowClear: true,
                width: '100%',
            });

            newFan.select2({
                placeholder: "Birlashtiriladigan fanni tanlang",
                allowClear: true,
                width: '100%',
            });
        });

        // Izoh: Yo'nalish+semestr tanlanganda fan ro'yxatini yangilash.
        $(document).on('change', '.yonalish-select', function() {
            const semestrId = $(this).val();
            const row = $(this).closest('.yonalish-item');
            const fanSelect = row.find('.fan-select');
            if (semestrId) {
                renderFanOptionsBySemestr(fanSelect, semestrId);
            } else {
                renderFanOptionsBySemestr(fanSelect, null);
            }
        });

        // Izoh: Master fan tenglashtirish mantiqi yo'q (har rowda fan alohida).

        // Izoh: Yo'nalish selectini olib tashlash (kamida 1 ta qoladi).
        $(document).on('click', '.removeYonalish', function() {
            const items = $('#yonalishWrapper .yonalish-item');
            if (items.length > 1) {
                const item = $(this).closest('.yonalish-item');
                item.find('select').each(function() {
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2('destroy');
                    }
                });
                item.remove();
            }
        });

        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2000,
            timerProgressBar: true
        });

        // Izoh: Birlashtiriladigan fan tahrirlash modali uchun handlerlar.
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

        // Izoh: Birlashtiriladigan fanini o'chirish.
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

        $('#birlashtirishForm').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData();
            const masterInput = $('#masterFanId');
            const rows = $('#yonalishWrapper .yonalish-item');
            const firstFanId = rows.first().find('.fan-select').val();
            masterInput.val(firstFanId || '');

            let hasError = false;
            $('#yonalishWrapper .yonalish-item').each(function() {
                const semestrId = $(this).find('.yonalish-select').val();
                const fanId = $(this).find('.fan-select').val();

                if (!semestrId || !fanId) {
                    hasError = true;
                    return false;
                }

                formData.append('semestr_ids[]', semestrId);
                formData.append('fan_ids[]', fanId);
            });

            if (hasError) {
                Toast.fire({ icon: 'error', title: "Barcha maydonlarni to'ldiring!" });
                return;
            }

            formData.append('master_fan_id', masterInput.val());

            // Izoh: Birlashtiriladigan fanlarni biriktirish ma'lumotini serverga yuborish.
            fetch('insert/add_umumtalim_birlashtirish.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Toast.fire({
                        icon: 'success',
                        title: data.message || "Birlashtiriladigan fan biriktirildi"
                    });

                    this.reset();
                    $('#yonalishWrapper .yonalish-item:gt(0)').remove();
                    $('#yonalishWrapper .yonalish-select').val(null).trigger('change');
                    $('.fan-select').val(null).trigger('change');
                    $('#masterFanId').val('');
                    // Izoh: Biriktirilgan fanlar ro'yxati ko'rinishi uchun sahifani yangilaymiz.
                    setTimeout(() => {
                        window.location.reload();
                    }, 400);
                } else {
                    Toast.fire({
                        icon: 'error',
                        title: data.message || 'Xatolik yuz berdi'
                    });
                }
            })
            .catch(() => {
                Toast.fire({
                    icon: 'error',
                    title: "Server bilan bog'lanib bo'lmadi"
                });
            });
        });
    </script>
    <script src="../assets/js/app.js"></script>
</body>
</html>
