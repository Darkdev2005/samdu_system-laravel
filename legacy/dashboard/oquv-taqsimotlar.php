<?php
include_once 'config.php';
$db = new Database();
$kafedralar = $db->get_data_by_table_all('kafedralar');

$oqtuvchilar = $db->get_data_by_table_all('oqituvchilar');
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>O'quv yuklama taqsimoti</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="../assets/css/oquv_yuklama_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-navbar">
                <h1><i class="fas fa-chalkboard-teacher me-2"></i>O'quv yuklama taqsimoti</h1>
                <div class="current-date">
                    <i class="fas fa-calendar-alt"></i>
                    <span><?php echo date('d.m.Y'); ?></span>
                </div>
            </header>
            
            <div class="content-container">
                <div class="filter-container">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label><i class="fas fa-building me-2"></i>Kafedra</label>
                            <select class="form-control" id="kafedraFilter">
                                <option value="">Barcha kafedralar</option>
                                <?php foreach ($kafedralar as $k): ?>
                                    <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-calendar me-2"></i>Semestr</label>
                            <select class="form-control" id="semestrFilter">
                                <option value="">Barcha semestrlar</option>
                                <?php for($i=1; $i<=10; $i+=2): ?>
                                    <option value="<?= $i ?>">
                                        <?= $i ?>-<?= $i+1 ?>-semestr
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button class="btn btn-primary" onclick="applyFilters()">
                            <i class="fas fa-filter me-2"></i>Filtrlash
                        </button>
                        <button class="btn btn-secondary" onclick="resetFilters()">
                            <i class="fas fa-redo me-2"></i>Tozalash
                        </button>
                        <button class="btn btn-success" onclick="printTable()">
                            <i class="fas fa-print me-2"></i>Chop etish
                        </button>
                        <button class="btn btn-info" onclick="exportToExcel()">
                            <i class="fas fa-file-excel me-2"></i>Excel
                        </button>
                    </div>
                </div>
                
                <div id="yuklamaTableContainer">
                </div>
            </div>
        </main>
    </div>
    <div class="modal" id="taqsimotModal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h3>O'qituvchi biriktirish</h3>
                <button class="modal-close" onclick="closeTaqsimotModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-info">
                    <div style="display:flex; gap:30px; align-items:center; flex-wrap:wrap;">
                        <h4 style="margin:0;"><i class="fas fa-book"></i> <span id="modalFanNomi"></span></h4>           
                        <p style="margin:0;"><strong>Soat turi:</strong> <span id="modalSoatTuri"></span></p>
                        <p style="margin:0;"><strong>Maksimal soat:</strong> <span id="modalMaxSoat"></span> soat</p>
                    </div>
                    <!-- 3 qatorli ma'lumotlar -->
                    <div class="info-grid" id="oquvRejaInfo" style="display: none; margin-top: 15px; background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <!-- 1-qator -->
                        <div class="grid-row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 10px;">
                            <div class="grid-col">
                                <small style="color: #666; display: block;">Talim yo'nalishi</small>
                                <strong id="infoTalimYonalishi">-</strong>
                            </div>
                            <div class="grid-col">
                                <small style="color: #666; display: block;">Kafedra</small>
                                <strong id="infoKafedra">-</strong>
                            </div>
                            <div class="grid-col">
                                <small style="color: #666; display: block;">O'quv shakli</small>
                                <strong id="infoOquvShakli">-</strong>
                            </div>
                        </div>
                        
                        <!-- 2-qator -->
                        <div class="grid-row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 10px;">
                            <div class="grid-col">
                                <small style="color: #666; display: block;">Kurs</small>
                                <strong id="infoKurs">-</strong>
                            </div>
                            <div class="grid-col">
                                <small style="color: #666; display: block;">Guruh</small>
                                <strong id="infoGuruh">-</strong>
                            </div>
                            <div class="grid-col">
                                <small style="color: #666; display: block;">Semestr</small>
                                <strong id="infoSemestr">-</strong>
                            </div>
                        </div>
                        
                        <!-- 3-qator -->
                        <div class="grid-row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                            <div class="grid-col">
                                <small style="color: #666; display: block;">Talabalar soni</small>
                                <strong id="infoTalabalar">-</strong>
                            </div>
                            <div class="grid-col">
                                <small style="color: #666; display: block;">Guruhlar soni</small>
                                <strong id="infoGuruhlarSoni">-</strong>
                            </div>
                            
                        </div>
                    </div>
                    <!-- taqsimotlar -->
                    <div class="taqsimot-section" style="margin-top: 20px;">
                        <h5 style="color: #333; margin-bottom: 10px;"><i class="fas fa-tasks"></i> Taqsimotlar</h5>
                        <div class="taqsimot-list" id="taqsimotList" style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px; background: #f8f9fa;">
                            <!-- Taqsimotlar shu yerda paydo bo'ladi -->
                        </div>
                    </div>

                    <div class="hours-control" style="margin-top: 15px;">
                        <div class="hours-stats" style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                            <span>Ajratilgan: <strong id="totalAssignedHours" style="color: #28a745;">0</strong> soat</span>
                            <span>Qolgan: <strong id="remainingHours" style="color: #ffc107;">0</strong> soat</span>
                            <span>Maksimal: <strong id="maxHoursTotal">0</strong> soat</span>
                        </div>
                        <div class="progress" style="height: 10px; background: #e9ecef; border-radius: 5px; overflow: hidden;">
                            <div id="hoursProgressBar" class="progress-bar" style="height: 100%; width: 0%;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="multiple-teachers-section" style="margin-top: 20px;">
                    <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h5 style="margin: 0; color: #333;"><i class="fas fa-users"></i> O'qituvchilar</h5>
                        <button type="button" class="btn btn-primary" onclick="addTeacherRow()" style="background: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-plus"></i> Qo'shish
                        </button>
                    </div>
                    
                    <div class="teachers-table" style="border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="border: 1px solid #dee2e6; padding: 10px; text-align: center; font-weight: bold;">#</th>
                                    <th style="border: 1px solid #dee2e6; padding: 10px; text-align: center; font-weight: bold;">O'qituvchi</th>
                                    <th style="border: 1px solid #dee2e6; padding: 10px; text-align: center; font-weight: bold;">Soat soni</th>
                                    <th style="border: 1px solid #dee2e6; padding: 10px; text-align: center; font-weight: bold;"></th>
                                </tr>
                            </thead>
                            <tbody id="teachersTableBody">
                                <!-- Bu yerda qatorlar qo'shiladi -->
                            </tbody>
                            <tfoot style="background: #f8f9fa;">
                                <tr>
                                    <td colspan="2" style="border: 1px solid #dee2e6; padding: 10px; text-align: right; font-weight: bold;">Jami soat:</td>
                                    <td style="border: 1px solid #dee2e6; padding: 10px; text-align: center; font-weight: bold;"><span id="totalHoursSum">0</span> soat</td>
                                    <td style="border: 1px solid #dee2e6; padding: 10px;"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeTaqsimotModal()" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                    Bekor qilish
                </button>
                <button class="btn btn-primary" onclick="saveTaqsimot()" style="background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                    Saqlash
                </button>
            </div>
        </div>
    </div>
                               
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="../assets/js/app.js"></script>
    
    <script>
        let currentZoom = 1;
        let currentCell = null;
        let currentYuklamaId = null;
        let currentSoatTuri = null;
        let currentMaxSoat = 0;
        let currentFanNomi = '';
        let currentType = '';
        let teacherRows = [];
        let teacherRowCounter = 0;

        $(document).ready(function() {
            $('#kafedraFilter, #semestrFilter, #oqituvchiSelect').select2({
                placeholder: "Tanlang",
                allowClear: true,
                width: '100%'
            });
            
            loadTableData();
            
            $(document).on('wheel', function(e) {
                if (e.ctrlKey) {
                    e.preventDefault();
                    if (e.originalEvent.deltaY < 0) {
                        zoomIn();
                    } else {
                        zoomOut();
                    }
                }
            });
            
            // Modal yopish uchun
            $('#taqsimotModal .modal-close, #taqsimotModal .btn-secondary').click(function(e) {
                e.stopPropagation();
                closeTaqsimotModal();
            });
            
            $(document).on('click', function(e) {
                if ($(e.target).hasClass('modal')) {
                    closeTaqsimotModal();
                }
            });

            // Soat inputi o'zgarganda
            $(document).on('input', '.teacher-hours', function() {
                calculateTotalHours();
            });
        });

        function loadTableData(kafedraId = '', semestrId = '') {
            const container = $('#yuklamaTableContainer');
            
            $.ajax({
                url: 'get/oquv_taqsimoti_table.php',
                type: 'POST',
                data: {
                    kafedra_id: kafedraId,
                    semestr: semestrId
                },
                success: function(response) {
                    container.html(response);
                    updateZoom();
                                        
                    // Click eventlarni qo'shish
                    attachCellClickEvents();
                },
                error: function(xhr, status, error) {
                    container.html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Ma'lumotlarni yuklab bo'lmadi: ${error}
                        </div>
                    `);
                }
            });
        }
        function attachCellClickEvents() {
            $('.soat-cell').off('click').on('click', function(e) {
                e.stopPropagation();
                
                const $cell = $(this);
                
                // Agar disabled bo'lsa, click qilish mumkin emas
                if ($cell.hasClass('disabled-cell')) {
                    return;
                }
                
                const yuklamaId = $cell.data('yuklama-id');
                const soatTuri = $cell.data('soat-turi');
                const fanNomi = $cell.closest('tr').find('.fan-nomi').text();
                const maxSoat = parseFloat($cell.data('max-soat')) || 0;
                const type = $cell.data('type');
                const text = $cell.text().trim();
                
                // Agar soat mavjud bo'lmasa yoki to'liq taqsimlangan bo'lsa
                if (maxSoat <= 0 || text === "" || text === "0") {
                    return;
                }
                
                // Modalni ochish
                openTaqsimotModal(this, yuklamaId, soatTuri, maxSoat, fanNomi, type);
            });
        }
        function calculateAssignedHours(htmlText) {
            if (!htmlText || htmlText === '') {
                return 0;
            }
            
            let totalHours = 0;
            const regex = /\((\d+(?:\.\d+)?)\s*soat\)/gi;
            let match;
            
            while ((match = regex.exec(htmlText)) !== null) {
                totalHours += parseFloat(match[1]);
            }
            
            return totalHours;
        }

        function openTaqsimotModal(cell, yuklamaId, soatTuri, maxSoat, fanNomi, type) {
            currentCell = cell;
            currentYuklamaId = yuklamaId;
            currentSoatTuri = soatTuri;
            currentFanNomi = fanNomi;
            currentType = type;

            teacherRows = [];
            teacherRowCounter = 0;
            existingAssignedHours = 0; // 🔥 YANGI

            $('#modalFanNomi').text(fanNomi);
            $('#modalSoatTuri').text(getSoatTuriName(soatTuri));

            $('#teachersTableBody').empty();
            $('#taqsimotList').empty();
            $('#oquvRejaInfo').hide();

            $.ajax({
                url: 'api/get_oquv_reja_by_yuklama.php',
                type: 'POST',
                data: { yuklama_id: yuklamaId, type: type },
                success: function(response) {
                    try {
                        const result = JSON.parse(response);
                        if (!(result.success && result.data)) return;

                        const data = result.data;
                        const taqsimotlar = result.taqsimotlar || [];
                        $('#oquvRejaInfo').show();

                        $('#infoTalimYonalishi').text(data.talim_yonalishi || '-');
                        $('#infoKafedra').text(data.kafedra_nomi || '-');
                        $('#infoOquvShakli').text(data.oquv_shakli || '-');
                        $('#infoKurs').text(data.kurs || '-');
                        $('#infoGuruh').text(data.guruh_raqami || '-');
                        $('#infoSemestr').text(data.semestr || '-');
                        $('#infoTalabalar').text(data.talabalar_soni ? data.talabalar_soni + ' ta' : '-');
                        $('#infoGuruhlarSoni').text(data.guruhlar_soni || '-');

                        taqsimotlar.forEach(t => {
                            const soat = parseFloat(t.soat_soni || 0);
                            existingAssignedHours += soat;

                            $('#taqsimotList').append(`
                                <div style="padding:6px;border-bottom:1px solid #dee2e6">
                                    <strong>${t.fio}</strong> — ${soat} soat
                                </div>
                            `);
                        });

                        currentMaxSoat = maxSoat - existingAssignedHours;
                        $('#modalMaxSoat').text(currentMaxSoat.toFixed(1));
                        updateHoursControl(0);

                        if (currentMaxSoat <= 0) {
                            Swal.fire({
                                icon: 'info',
                                title: 'Barcha soatlar taqsimlangan',
                                text: 'Yangi taqsimot kiritib bo‘lmaydi'
                            });
                            $('#taqsimotModal').addClass('show');
                            return;
                        }

                        addTeacherRow();

                    } catch (e) {
                        console.error('JSON xato:', e);
                    }
                }
            });

            $('#taqsimotModal').addClass('show');
        }



        function addTeacherRow(teacherId = '', hours = 0) {

            if (currentMaxSoat <= 0) return; // soat qolmagan bo‘lsa chiqmaydi

            teacherRowCounter++;
            const rowId = 'teacherRow_' + teacherRowCounter;

            const row = {
                id: rowId,
                teacherId: teacherId,
                hours: parseFloat(hours) || 0
            };

            teacherRows.push(row);

            const rowHtml = `
                <tr id="${rowId}" style="border-bottom: 1px solid #dee2e6;">
                    <td style="border: 1px solid #dee2e6; padding: 10px; text-align: center;">
                        ${teacherRowCounter}
                    </td>

                    <td style="border: 1px solid #dee2e6; padding: 10px;">
                        <select class="form-control teacher-select"
                                onchange="updateTeacherHours('${rowId}'); showTeacherPreviousHours(this.value)"
                                style="width: 100%; padding: 6px; border: 1px solid #ced4da; border-radius: 4px;">
                            <option value="">Tanlang</option>
                            <?php foreach ($oqtuvchilar as $oqtuvchi): ?>
                                <option value="<?= $oqtuvchi['id'] ?>" ${teacherId == '<?= $oqtuvchi['id'] ?>' ? 'selected' : ''}>
                                    <?= htmlspecialchars($oqtuvchi['fio'])?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>

                    <td style="border: 1px solid #dee2e6; padding: 10px;">
                        <input type="number" class="teacher-hours"
                            value="${hours}"
                            min="0"
                            max="${currentMaxSoat}"
                            step="0.5"
                            onchange="updateTeacherHours('${rowId}')"
                            style="width: 80px; padding: 6px; border: 1px solid #ced4da; border-radius: 4px; display: inline-block;">
                        <span> soat</span>
                    </td>

                    <td style="border: 1px solid #dee2e6; padding: 10px; text-align: center;">
                        <button type="button" class="btn btn-danger"
                                onclick="removeTeacherRow('${rowId}')"
                                style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer;"
                                ${teacherRowCounter === 1 ? 'disabled' : ''}>
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>
            `;

            $('#teachersTableBody').append(rowHtml);

            $('.teacher-select').select2({
                width: '100%',
                dropdownParent: $('#taqsimotModal')
            });

            calculateTotalHours();
        }


        function updateTeacherHours(rowId) {
            const row = document.getElementById(rowId);
            if (!row) return;
            
            const teacherId = row.querySelector('.teacher-select').value;
            const hoursInput = row.querySelector('.teacher-hours');
            let hours = parseFloat(hoursInput.value) || 0;
            
            // Maksimal chegarani tekshirish
            if (hours > currentMaxSoat) {
                hours = currentMaxSoat;
                hoursInput.value = currentMaxSoat;
            }
            
            // Massivni yangilash
            const rowIndex = teacherRows.findIndex(r => r.id === rowId);
            if (rowIndex !== -1) {
                teacherRows[rowIndex].teacherId = teacherId;
                teacherRows[rowIndex].hours = hours;
            }
            
            calculateTotalHours();
        }

        function removeTeacherRow(rowId) {
            teacherRows = teacherRows.filter(row => row.id !== rowId);
            
            $(`#${rowId}`).remove();
            
            $('#teachersTableBody tr').each(function(index) {
                $(this).find('td:first').text(index + 1);
            });
            
            teacherRowCounter = teacherRows.length;
            calculateTotalHours();
        }

        function calculateTotalHours() {
            let totalHours = 0;
            let hasError = false;
            
            $('#teachersTableBody tr').each(function() {
                const hoursInput = $(this).find('.teacher-hours');
                const hours = parseFloat(hoursInput.val()) || 0;
                
                if (hours > currentMaxSoat) {
                    hoursInput.css('border-color', '#dc3545');
                    hasError = true;
                } else {
                    hoursInput.css('border-color', '#ced4da');
                    totalHours += hours;
                }
            });
            
            $('#totalHoursSum').text(totalHours.toFixed(1));
            
            updateHoursControl(totalHours);
            
            return { total: totalHours, hasError: hasError };
        }

        // Soat nazoratini yangilash
        function updateHoursControl(totalHours) {
            const remaining = currentMaxSoat - totalHours;
            const percentage = currentMaxSoat > 0 ? (totalHours / currentMaxSoat * 100) : 0;
            
            $('#totalAssignedHours').text(totalHours.toFixed(1));
            $('#remainingHours').text(remaining.toFixed(1));
            $('#maxHoursTotal').text(currentMaxSoat);
            
            // Progress bar
            const progressBar = $('#hoursProgressBar');
            progressBar.css('width', percentage + '%');
            
            // Rangni o'zgartirish
            if (remaining >= currentMaxSoat * 0.5) {
                progressBar.css('background-color', '#28a745');
                $('#remainingHours').css('color', '#28a745');
            } else if (remaining >= currentMaxSoat * 0.2) {
                progressBar.css('background-color', '#ffc107');
                $('#remainingHours').css('color', '#ffc107');
            } else {
                progressBar.css('background-color', '#dc3545');
                $('#remainingHours').css('color', '#dc3545');
            }
        }

        function getSoatTuriName(soatTuri) {
            const soatTurlari = {
                'reja_maruz': "Ma'ruza (reja)",
                'reja_amaliy': "Amaliy (reja)",
                'reja_laboratoriya': "Laboratoriya (reja)",
                'reja_seminar': "Seminar (reja)",
                'amalda_maruz': "Ma'ruza (amalda)",
                'amalda_amaliy': "Amaliy (amalda)",
                'amalda_laboratoriya': "Laboratoriya (amalda)",
                'amalda_seminar': "Seminar (amalda)",
                'oraliq_nazorat': "Oraliq nazorat",
                'yakuniy_nazorat': "Yakuniy nazorat",
                'kurs_ishi': "Kurs ishi va himoyasi",
                'kurs_loyiha': "Kurs loyihasi va himoyasi",
                'oquv_ped_amaliyot': "O'quv-pedagogik amaliyot",
                'uzluksiz_malakaviy': "Uzluksiz malakaviy amaliyot",
                'dala_amaliyoti': "Dala amaliyoti",
                'dala_amaliyoti_otm': "Dala amaliyoti (OTM)",
                'ishlab_chiqarish': "Ishlab chiqarish amaliyot",
                'bmi_rahbarligi': "BMI rahbarligi",
                'mag_ilmiy_tadqiqot': "Ilmiy-tadqiqot ishi (Magistratura)",
                'mag_ilmiy_pedagogik': "Ilmiy-pedagogik ish (Magistratura)",
                'mag_ilmiy_stajirovka': "Ilmiy stajirovka (Magistratura)",
                'tayanch_doktorantura': "Tayanch doktorantura",
                'katta_ilmiy_tadqiqotchi': "Katta ilmiy tadqiqotchi",
                'stajyor_tadqiqotchi': "Stajyor-tadqiqotchi",
                'ochiq_dars': "Ochiq dars",
                'yakuniy_attestatsiya': "Yakuniy attestatsiya",
                'boshqa_soatlar': "Boshqa soatlar"
            };
            
            return soatTurlari[soatTuri] || soatTuri;
        }
        function showTeacherPreviousHours(teacherId) {
            if (!teacherId) return;

            $.post('api/get_teacher_total_hours.php', { teacher_id: teacherId }, function(res) {
                const result = JSON.parse(res);
                if (!result.success) return;

                const d = result.data;

                let detailsHtml = '';
                d.details.forEach(item => {
                    detailsHtml += `
                        <div style="text-align:left;border-bottom:1px solid #eee;padding:4px 0">
                            <b>${item.fan_code}</b> — ${item.fan_name}<br>
                            ${item.dars_turi} | ${item.soat} soat
                        </div>
                    `;
                });

                Swal.fire({
                    icon: 'info',
                    title: d.fio + ' (' + d.lavozim + ')',
                    html: `
                        <div><b>Jami yuklama:</b> ${d.total_hours} soat</div>
                        <hr>
                        <div style="max-height:200px; overflow:auto">
                            ${detailsHtml || 'Ma\'lumot yo‘q'}
                        </div>
                    `
                });
            });
        }

        function saveTaqsimot() {
            const result = calculateTotalHours();
            const totalHours = result.total;
            
            if (result.hasError) {
                Swal.fire({
                    icon: 'error',
                    title: 'Xatolik!',
                    text: 'Soat soni maksimal qiymatdan oshib ketdi!'
                });
                return;
            }
            if (totalHours > currentMaxSoat) {
                Swal.fire({
                    icon: 'error',
                    title: 'Xatolik!',
                    text: `Jami soat ${currentMaxSoat} dan ko'p bo'lishi mumkin emas!`
                });
                return;
            }
            
            const incompleteRows = [];
            
            $('#teachersTableBody tr').each(function(index) {
                const teacherId = $(this).find('.teacher-select').val();
                if (!teacherId) {
                    incompleteRows.push(index + 1);
                }
            });
            if (incompleteRows.length > 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Diqqat!',
                    text: `${incompleteRows.join(', ')}-qatorlarda o'qituvchi tanlanmagan!`
                });
                return;
            }
            
            // Taqsimotlar massivini tayyorlash
            const taqsimotlar = [];
            $('#teachersTableBody tr').each(function() {
                const teacherId = $(this).find('.teacher-select').val();
                const hours = parseFloat($(this).find('.teacher-hours').val()) || 0;
                
                if (teacherId && hours > 0) {
                    taqsimotlar.push({
                        oqituvchi_id: teacherId,
                        soat_soni: hours
                    });
                }
            });
            
            $.ajax({
                url: 'insert/add_oquv_taqsimot.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    yuklama_id: currentYuklamaId,
                    soat_turi: currentSoatTuri,
                    type: currentType,
                    taqsimotlar: JSON.stringify(taqsimotlar)
                },
                success: function(response) {
                    if (response.success) {
                        closeTaqsimotModal();
                        loadTableData();

                        Swal.fire({
                            icon: 'success',
                            title: 'Muvaffaqiyatli!',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Xatolik!',
                            text: response.message
                        });
                    }
                },
                error: function(xhr) {
                    console.log(xhr.responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'Server xatosi'
                    });
                }
            });
        }

        function closeTaqsimotModal() {
            $('#taqsimotModal').removeClass('show');
            currentCell = null;
            currentYuklamaId = null;
            currentSoatTuri = null;
            currentMaxSoat = 0;
            currentFanNomi = '';
            currentType = '';
            teacherRows = [];
            teacherRowCounter = 0;
        }

        // Qolgan funksiyalar o'zgartirilmagan
        function applyFilters() {
            const kafedraId = $('#kafedraFilter').val();
            const semestrId = $('#semestrFilter').val();
            
            const filterBtn = $('.filter-actions .btn-primary');
            const originalText = filterBtn.html();
            filterBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Filtrlash...');
            filterBtn.prop('disabled', true);
            
            loadTableData(kafedraId, semestrId);
            
            setTimeout(() => {
                filterBtn.html(originalText);
                filterBtn.prop('disabled', false);
                
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1500
                });
                
                Toast.fire({
                    icon: 'success',
                    title: 'Filterlar qo\'llandi'
                });
            }, 1000);
        }

        function resetFilters() {
            $('#kafedraFilter').val(null).trigger('change');
            $('#semestrFilter').val(null).trigger('change');
            
            loadTableData();
            
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500
            });
            
            Toast.fire({
                icon: 'info',
                title: 'Filterlar tozalandi'
            });
        }

        function zoomIn() {
            if (currentZoom < 1.5) {
                currentZoom += 0.05;
                updateZoom();
            }
        }

        function zoomOut() {
            if (currentZoom > 0.5) {
                currentZoom -= 0.05;
                updateZoom();
            }
        }

        function resetZoom() {
            currentZoom = 1;
            updateZoom();
        }

        function updateZoom() {
            const table = document.getElementById('yuklamaTable');
            if (table) {
                table.style.transform = `scale(${currentZoom})`;
                table.style.transformOrigin = 'top left';
                document.getElementById('zoomLevel').textContent = `${Math.round(currentZoom * 100)}%`;
            }
        }

        function printTable() {
            const originalZoom = currentZoom;
            resetZoom();
            
            setTimeout(() => {
                window.print();
                setTimeout(() => {
                    currentZoom = originalZoom;
                    updateZoom();
                }, 500);
            }, 100);
        }

        function exportToExcel() {
            const table = document.getElementById('yuklamaTable');
            if (table) {
                const wb = XLSX.utils.table_to_book(table, {sheet: "O'quv taqsimoti"});
                XLSX.writeFile(wb, "oquv_taqsimoti.xlsx");
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Jadval mavjud emas',
                    text: 'Iltimos, avval ma\'lumotlarni yuklang'
                });
            }
        }

        $(document).on('keydown', function(e) {
            if (e.ctrlKey && e.key === '=') {
                e.preventDefault();
                zoomIn();
            }
            if (e.ctrlKey && e.key === '-') {
                e.preventDefault();
                zoomOut();
            }
            if (e.ctrlKey && e.key === '0') {
                e.preventDefault();
                resetZoom();
            }
        });
    </script>
</body>
</html>
