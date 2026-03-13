<?php
    include_once 'config.php';
    $db = new Database();
    $yonalishlar = $db->get_data_by_table_all('yonalishlar');
    $oquv_haftalik_turlari = $db->get_data_by_table_all('oquv_haftalik_turlar');
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>O'quv haftaligi yaratish</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="../assets/css/oquv_haftalik_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-navbar">
                <h1>O'quv haftaligi yaratish</h1>
            </header>
            <div class="content-container">
                <div class="card">
                    <div class="controls-panel">
                        <div class="form-grid-2">
                            <div class="form-group">
                                <label><i class="fas fa-graduation-cap"></i> Yo'nalish</label>
                                <select class="form-control" id="yonalishSelect" required>
                                    <option value="">Tanlang</option>
                                    <?php foreach($yonalishlar as $yonalish): ?>
                                        <option value="<?= $yonalish['id'] ?>" 
                                                data-muddati="<?= $yonalish['muddati'] ?>"
                                                data-kirish-yili="<?= $yonalish['kirish_yili'] ?>">
                                            <?= htmlspecialchars($yonalish['name']) ?> 
                                            (<?= $yonalish['code'] ?>, <?= $yonalish['muddati'] ?> yillik)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="quick-toolbar">
                            <button type="button" class="quick-btn" id="rangeSelectBtn">
                                <i class="fas fa-vector-square"></i> So'ra tanlash
                            </button>
                            <button type="button" class="quick-btn" id="clearAllBtn">
                                <i class="fas fa-trash-alt"></i> Hammasini tozalash
                            </button>
                            
                            <div class="bulk-control">
                                <select class="bulk-select" id="bulkSelect">
                                    <option value="">Hammasi uchun tanlash</option>
                                    <?php foreach($oquv_haftalik_turlari as $tur): ?>
                                        <option value="<?= $tur['id'] ?>">
                                            <?= htmlspecialchars($tur['short_name']) ?> - 
                                            <?= htmlspecialchars($tur['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="bulk-apply" id="applyBulk">
                                    <i class="fas fa-check"></i> Qo'llash
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="yearDisplay" class="year-display" style="display: none;">
                        <i class="fas fa-calendar-week"></i> O'quv yillari:
                    </div>

                    <div id="calendarContainer" class="academic-calendar"></div>

                    <!-- Range selection modal -->
                    <div id="rangeModal" class="range-modal">
                        <div class="range-modal-content">
                            <div class="range-header">
                                <h3><i class="fas fa-layer-group"></i> So'ra uchun hafta turi</h3>
                                <button class="close-modal">&times;</button>
                            </div>
                            
                            <p style="color: var(--text-light); font-size: 13px; margin-bottom: 12px;">
                                Tanlangan <span id="selectedCount">0</span> ta katakcha uchun hafta turini tanlang:
                            </p>
                            
                            <div class="range-options" id="rangeOptions">
                                <?php foreach($oquv_haftalik_turlari as $tur): ?>
                                    <div class="range-option" data-value="<?= $tur['id'] ?>">
                                        <?= htmlspecialchars($tur['short_name']) ?> - 
                                        <?= htmlspecialchars($tur['name']) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button class="range-apply" id="applyRange">
                                <i class="fas fa-check"></i> Tanlashni qo'llash
                            </button>
                        </div>
                    </div>

                    <!-- Selection status bar -->
                    <div class="selection-status" id="selectionStatus">
                        <div class="status-count" id="statusCount">0 katakcha tanlandi</div>
                        <div class="status-actions">
                            <button class="status-btn apply" id="applySelection">Tanlash</button>
                            <button class="status-btn cancel" id="cancelSelection">Bekor qilish</button>
                        </div>
                    </div>

                    <div class="form-actions mt-4">
                        <button type="button" class="btn btn-primary" id="saveBtn">
                            <i class="fas fa-save"></i> Saqlash
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            const oquvHaftalikTurlari = <?php echo json_encode($oquv_haftalik_turlari); ?>;
            let selectedCells = [];
            let isRangeSelecting = false;
            let rangeStart = null;
            let rangeEnd = null;
            let weekData = {}; 
            $('#yonalishSelect').on('change', function() {
                const selected = $(this).find('option:selected');
                const muddati = selected.data('muddati');
                const kirishYili = selected.data('kirish-yili');
                
                if (muddati && kirishYili) {
                    // O'quv yillarini ko'rsatish
                    let yearsHtml = '<i class="fas fa-calendar-week"></i> O\'quv yillari: ';
                    for(let i = 0; i < muddati; i++) {
                        const startYear = parseInt(kirishYili) + i;
                        const endYear = startYear + 1;
                        yearsHtml += `<span class="year-item">${i+1}-kurs: ${startYear}-${endYear}</span>`;
                    }
                    $('#yearDisplay').html(yearsHtml).show();
                    
                    // Kalendar jadvalini yaratish
                    createCalendar(muddati);
                } else {
                    $('#yearDisplay').hide();
                    $('#calendarContainer').empty();
                }
            });
            
            function createCalendar(muddati) {
                const months = [
                    { name: 'Sentyabr', weeks: 4 },
                    { name: 'Oktyabr', weeks: 5 },
                    { name: 'Noyabr', weeks: 4 },
                    { name: 'Dekabr', weeks: 5 },
                    { name: 'Yanvar', weeks: 4 },
                    { name: 'Fevral', weeks: 4 },
                    { name: 'Mart', weeks: 4 },
                    { name: 'Aprel', weeks: 5 },
                    { name: 'May', weeks: 4 },
                    { name: 'Iyun', weeks: 4 },
                    { name: 'Iyul', weeks: 5 },
                    { name: 'Avgust', weeks: 4 }
                ];
                
                let html = '<table class="calendar-table">';
                
                // Oy nomlari qatori
                html += '<thead><tr class="month-row">';
                html += '<th class="course-number">Kurs</th>';
                
                months.forEach(month => {
                    html += `<th colspan="${month.weeks}">${month.name}</th>`;
                });
                
                html += '</tr>';
                
                html += '<tr class="week-row">';
                html += '<th></th>';
                
                let weekNumber = 1;
                months.forEach(month => {
                    for(let w = 1; w <= month.weeks; w++) {
                        html += `<th>${weekNumber}</th>`;
                        weekNumber++;
                    }
                });
                
                html += '</tr></thead><tbody>';
                
                // Har bir kurs uchun qatorlar
                for(let course = 1; course <= muddati; course++) {
                    html += `<tr data-course="${course}">`;
                    html += `<td class="course-number">${course}-kurs</td>`;
                    
                    weekNumber = 1;
                    months.forEach(month => {
                        for(let w = 1; w <= month.weeks; w++) {
                            const fieldName = `hafta[${course}][${weekNumber}]`;
                            const selectOptions = oquvHaftalikTurlari.map(tur => 
                                `<option value="${tur.id}">${tur.short_name}</option>`
                            ).join('');
                            
                            html += `
                                <td class="week-cell" 
                                    data-course="${course}" 
                                    data-week="${weekNumber}"
                                    data-selected="false">
                                    <select class="week-select" data-field="${fieldName}">
                                        <option value="">-</option>
                                        ${selectOptions}
                                    </select>
                                </td>
                            `;
                            weekNumber++;
                        }
                    });
                    
                    html += '</tr>';
                }
                
                html += '</tbody></table>';
                $('#calendarContainer').html(html);
                
                // Individual select'lar uchun event listener
                $('.week-select').on('change', function() {
                    updateCellStyle($(this).closest('.week-cell'));
                    updateWeekData();
                });
                
                setupRangeSelection();
                
                // Ma'lumotlarni tozalash
                weekData = {};
            }
            
            function updateCellStyle(cell) {
                const select = cell.find('.week-select');
                const value = select.val();
                const text = select.find('option:selected').text();
                
                // Avvalgi klasslarni olib tashlash
                cell.removeClass('selected holiday practice theory');
                
                if (value) {
                    cell.addClass('selected');
                    
                    // Maxsus turlar uchun klasslar
                    if (text.includes('T')) {
                        cell.addClass('holiday');
                    } else if (text.includes('M')) {
                        cell.addClass('practice');
                    } else if (text.includes('A')) {
                        cell.addClass('theory');
                    }
                }
            }
            
            function updateWeekData() {
                weekData = {};
                $('.week-select').each(function() {
                    const value = $(this).val();
                    if (value) {
                        const field = $(this).data('field');
                        weekData[field] = value;
                    }
                });
            }
            
            // RANGE SELECTION FUNCTIONS
            function setupRangeSelection() {
                let isSelecting = false;
                let startCell = null;
                
                // So'ra tanlash boshlanishi
                $('.week-cell').on('mousedown', function(e) {
                    if (!isRangeSelecting) return;
                    
                    e.preventDefault();
                    isSelecting = true;
                    startCell = $(this);
                    
                    // Barcha katakchalardan selecting klassini olib tashlash
                    $('.week-cell').removeClass('selecting');
                    
                    // Boshlang'ich katakchani belgilash
                    startCell.addClass('selecting');
                    rangeStart = {
                        course: startCell.data('course'),
                        week: startCell.data('week')
                    };
                    
                    $(document).on('mousemove.rangeSelect', handleMouseMove);
                    $(document).on('mouseup.rangeSelect', handleMouseUp);
                });
                
                function handleMouseMove(e) {
                    if (!isSelecting || !startCell) return;
                    
                    // Sichqoncha ostidagi katakchani topish
                    const target = $(document.elementFromPoint(e.clientX, e.clientY));
                    const cell = target.closest('.week-cell');
                    
                    if (cell.length && cell[0] !== startCell[0]) {
                        // Hozirgi katakchani belgilash
                        cell.addClass('selecting');
                        rangeEnd = {
                            course: cell.data('course'),
                            week: cell.data('week')
                        };
                        
                        // Oraliqdagi barcha katakchalarni belgilash
                        selectRangeCells(rangeStart, rangeEnd);
                    }
                }
                
                function handleMouseUp() {
                    if (!isSelecting) return;
                    
                    isSelecting = false;
                    $(document).off('mousemove.rangeSelect');
                    $(document).off('mouseup.rangeSelect');
                    
                    // Tanlangan katakchalarni saqlash
                    updateSelectedCells();
                    
                    // Agar kamida 1 ta katakcha tanlangan bo'lsa, modalni ko'rsatish
                    if (selectedCells.length >= 1) {
                        showRangeModal();
                    }
                    
                    // Selecting klassini olib tashlash
                    $('.week-cell').removeClass('selecting');
                    startCell = null;
                }
            }
            
            function selectRangeCells(start, end) {
                // Katakchalar koordinatalarini hisoblash
                const minCourse = Math.min(start.course, end.course);
                const maxCourse = Math.max(start.course, end.course);
                const minWeek = Math.min(start.week, end.week);
                const maxWeek = Math.max(start.week, end.week);
                
                // Barcha katakchalarni aylantirish
                $('.week-cell').removeClass('selecting');
                
                for(let course = minCourse; course <= maxCourse; course++) {
                    for(let week = minWeek; week <= maxWeek; week++) {
                        $(`.week-cell[data-course="${course}"][data-week="${week}"]`)
                            .addClass('selecting');
                    }
                }
            }
            
            function updateSelectedCells() {
                selectedCells = [];
                $('.week-cell.selecting').each(function() {
                    const course = $(this).data('course');
                    const week = $(this).data('week');
                    const fieldName = `hafta[${course}][${week}]`;
                    selectedCells.push({
                        course, 
                        week, 
                        element: $(this),
                        field: fieldName
                    });
                });
                
                updateStatusBar();
            }
            
            function updateStatusBar() {
                const count = selectedCells.length;
                if (count > 0) {
                    $('#statusCount').text(`${count} katakcha tanlandi`);
                    $('#selectedCount').text(count);
                    $('#selectionStatus').fadeIn();
                } else {
                    $('#selectionStatus').fadeOut();
                }
            }
            
            function showRangeModal() {
                $('#rangeModal').fadeIn();
            }
            
            function hideRangeModal() {
                $('#rangeModal').fadeOut();
                clearSelection();
            }
            
            function clearSelection() {
                selectedCells = [];
                $('.week-cell').removeClass('selecting');
                updateStatusBar();
            }
            
            // Range selection modal boshqaruvi
            $('.close-modal').on('click', hideRangeModal);
            $('#rangeModal').on('click', function(e) {
                if (e.target === this) hideRangeModal();
            });
            
            // Range variantlarini tanlash
            $('.range-option').on('click', function() {
                $(this).parent().find('.range-option').removeClass('selected');
                $(this).addClass('selected');
            });
            
            // Range tanlashni qo'llash
            $('#applyRange').on('click', function() {
                const selectedOption = $('#rangeOptions .range-option.selected');
                if (!selectedOption.length) {
                    Swal.fire('Xatolik', 'Iltimos, hafta turini tanlang!', 'warning');
                    return;
                }
                
                const value = selectedOption.data('value');
                const optionData = oquvHaftalikTurlari.find(t => t.id == value);
                
                // Tanlangan barcha katakchalarga qo'llash
                selectedCells.forEach(({element, field}) => {
                    element.find('.week-select').val(value).trigger('change');
                    weekData[field] = value; // weekData ni yangilash
                });
                
                Swal.fire({
                    icon: 'success',
                    title: 'Muvaffaqiyatli!',
                    text: `${selectedCells.length} ta katakcha belgilandi`,
                    timer: 1500,
                    showConfirmButton: false
                });
                
                hideRangeModal();
            });
            
            // Tanlashni bekor qilish
            $('#cancelSelection').on('click', function() {
                clearSelection();
                hideRangeModal();
            });
            
            // Tanlashni qo'llash
            $('#applySelection').on('click', function() {
                if (selectedCells.length > 0) {
                    showRangeModal();
                }
            });
            
            // So'ra tanlash rejimini yoqish/o'chirish
            $('#rangeSelectBtn').on('click', function(e) {
                e.preventDefault(); // URLga ketishni oldini olish
                isRangeSelecting = !isRangeSelecting;
                $(this).toggleClass('active', isRangeSelecting);
                
                if (isRangeSelecting) {
                    Swal.fire({
                        title: 'So\'ra tanlash rejimi',
                        text: 'Endi sichqoncha bilan so\'ra tanlashingiz mumkin. Sichqonchani bosib turib, katakchalarni aylantiring.',
                        icon: 'info',
                        confirmButtonText: 'Tushundim',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            });
            
            // Barcha katakchalar uchun tanlash
            $('#applyBulk').on('click', function(e) {
                e.preventDefault(); // URLga ketishni oldini olish
                const value = $('#bulkSelect').val();
                if (!value) {
                    Swal.fire('Xatolik', 'Iltimos, hafta turini tanlang!', 'warning');
                    return;
                }
                
                const optionData = oquvHaftalikTurlari.find(t => t.id == value);
                
                // Barcha katakchalarga qo'llash
                $('.week-select').val(value).trigger('change');
                updateWeekData(); // weekData ni yangilash
                
                Swal.fire({
                    icon: 'success',
                    title: 'Muvaffaqiyatli!',
                    text: `Barcha katakchalar ${optionData.short_name} bilan belgilandi`,
                    timer: 1500,
                    showConfirmButton: false
                });
                
                // Selectni tozalash
                $('#bulkSelect').val('');
            });
            
            // Hammasini tozalash
            $('#clearAllBtn').on('click', function(e) {
                e.preventDefault(); // URLga ketishni oldini olish
                Swal.fire({
                    title: 'Barchasini tozalash?',
                    text: 'Barcha tanlangan ma\'lumotlar o\'chiriladi',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    confirmButtonText: 'Ha, tozalash',
                    cancelButtonText: 'Bekor qilish'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('.week-select').val('').trigger('change');
                        weekData = {}; // weekData ni tozalash
                        clearSelection();
                        Swal.fire('Tozalandi!', 'Barcha ma\'lumotlar tozalandi', 'success');
                    }
                });
            });
            
            // Saqlash tugmasi
            $('#saveBtn').on('click', function(e) {
                e.preventDefault(); // URLga ketishni oldini olish
                const yonalishId = $('#yonalishSelect').val();
                
                if (!yonalishId) {
                    Swal.fire('Xatolik', 'Iltimos, yo\'nalishni tanlang!', 'error');
                    return;
                }
                
                // Ma'lumotlarni tekshirish
                if (Object.keys(weekData).length === 0) {
                    Swal.fire({
                        title: 'Ogohlantirish',
                        text: 'Hech qanday hafta turi tanlanmagan. Saqlashni davom ettirishni hohlaysizmi?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#10b981',
                        confirmButtonText: 'Ha, saqlash',
                        cancelButtonText: 'Bekor qilish'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            submitToServer(yonalishId, weekData);
                        }
                    });
                } else {
                    submitToServer(yonalishId, weekData);
                }
            });
            
            function submitToServer(yonalishId, oquvData) {
                // JSON formatga o'tkazish
                const oquvDataJson = JSON.stringify(oquvData);
                
                Swal.fire({
                    title: 'Saqlashni tasdiqlang',
                    text: 'O\'quv haftaligini saqlashni hohlaysizmi?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#10b981',
                    confirmButtonText: 'Ha, saqlash',
                    cancelButtonText: 'Bekor qilish'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'insert/add_oquv_haftaligi.php',
                            type: 'POST',
                            data: {
                                yonalish_id: yonalishId,
                                oquv_data: oquvDataJson
                            },
                            dataType: 'json',
                            beforeSend: function() {
                                Swal.fire({
                                    title: 'Saqlanmoqda...',
                                    text: 'Iltimos kuting',
                                    allowOutsideClick: false,
                                    didOpen: () => {
                                        Swal.showLoading();
                                    }
                                });
                            },
                            success: function(response) {
                                Swal.close();
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Muvaffaqiyatli!',
                                        text: response.message,
                                        timer: 2000,
                                        showConfirmButton: false
                                    }).then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire('Xatolik', response.message, 'error');
                                }
                            },
                            error: function() {
                                Swal.close();
                                Swal.fire('Xatolik', 'Server bilan bog\'lanishda xatolik!', 'error');
                            }
                        });
                    }
                });
            }
        });
    </script>
    <script src="../assets/js/app.js"></script>
</body>
</html>
