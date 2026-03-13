<?php
    include_once 'config.php';
    $db = new Database();
    $kafedralar = $db->get_data_by_table_all('kafedralar');
    $yonalishlar = $db->get_data_by_table_all('yonalishlar');
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>O'quv yuklama jadvali</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="../assets/css/oquv_yuklama_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-navbar">
                <h1><i class="fas fa-chart-bar me-2"></i>O'quv yuklama jadvali</h1>
                <div class="current-date">
                    <i class="fas fa-calendar-alt"></i>
                    <span><?php echo date('d.m.Y'); ?></span>
                </div>
            </header>
            
            <div class="content-container">
                <!-- Filter qismi -->
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
                            <label><i class="fas fa-compass me-2"></i>Yo'nalish</label>
                            <select class="form-control" id="yonalishFilter">
                                <option value="">Barcha yo'nalishlar</option>
                                <?php foreach ($yonalishlar as $y): ?>
                                    <option value="<?= $y['id'] ?>" data-kirish-yili="<?= (int)$y['kirish_yili'] ?>">
                                        <?= htmlspecialchars($y['name'] . ' - ' . $y['kirish_yili']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-calendar me-2"></i>O'quv yili</label>
                            <select class="form-control" id="semestrFilter">
                                <option value="">Barcha o'quv yillari</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-calendar-check me-2"></i>Semestr turi</label>
                            <select class="form-control" id="semestrTypeFilter">
                                <option value="">Barcha semestr turlari</option>
                                <option value="fall">Kuzgi (1,3,5,7,9)</option>
                                <option value="spring">Bahorgi (2,4,6,8,10)</option>
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

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="../assets/js/app.js"></script>
    
    
    <script>
        let currentZoom = 1;
        $(document).ready(function() {
            $('#kafedraFilter, #semestrFilter, #semestrTypeFilter, #yonalishFilter').select2({
                placeholder: "Tanlang",
                allowClear: true,
                width: '100%'
            });

            function computeCurrentSemestr(kirishYili, type) {
                if (!kirishYili) return null;
                const now = new Date();
                const month = now.getMonth() + 1;
                const year = now.getFullYear();
                const isFall = (month >= 9 || month === 1);
                const academicYearStart = isFall ? year : (year - 1);
                const parityAdd = type === 'fall' ? 1 : 2;
                const sem = ((academicYearStart - kirishYili) * 2) + parityAdd;
                if (sem < 1 || sem > 10) return null;
                return sem;
            }

            function updateSemestrOptions() {
                const yonalishOption = $('#yonalishFilter option:selected');
                const kirishYili = parseInt(yonalishOption.data('kirish-yili'), 10) || 0;
                const semestrType = $('#semestrTypeFilter').val();
                const prevSelected = $('#semestrFilter').val();
                const now = new Date();
                const month = now.getMonth() + 1;
                const year = now.getFullYear();
                const isFall = (month >= 9 || month === 1);
                const currentAcademicStart = isFall ? year : (year - 1);

                let options = '<option value="">Tanlang</option>';
                const buildYearOptions = (startYearFrom, startYearTo) => {
                    for (let y = startYearFrom; y <= startYearTo; y++) {
                        options += `<option value="${y}">${y}-${y + 1} o'quv yili</option>`;
                    }
                };

                if (kirishYili) {
                    buildYearOptions(kirishYili, currentAcademicStart);
                } else {
                    buildYearOptions(currentAcademicStart - 5, currentAcademicStart);
                }

                $('#semestrFilter').html(options);

                const hasPrev = prevSelected && $(`#semestrFilter option[value="${prevSelected}"]`).length;
                if (hasPrev) {
                    $('#semestrFilter').val(prevSelected).trigger('change');
                } else if (kirishYili) {
                    $('#semestrFilter').val(String(currentAcademicStart)).trigger('change');
                } else {
                    $('#semestrFilter').val(null).trigger('change');
                }

                // Izoh: Yo'nalish tanlansa, semestr turi tanlanmagan bo'lsa joriy turini qo'yamiz.
                if (kirishYili && !semestrType) {
                    $('#semestrTypeFilter').val(isFall ? 'fall' : 'spring').trigger('change');
                }
            }

            $('#yonalishFilter, #semestrTypeFilter').on('change', updateSemestrOptions);
            updateSemestrOptions();

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
        });

        function loadTableData(kafedraId = '', oquvYilStart = '', yonalishId = '', semestrType = '') {
            // Loading ko'rsatish
            const container = $('#yuklamaTableContainer');
            container.html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Yuklanmoqda...</span>
                    </div>
                    <p class="mt-2">Ma'lumotlar yuklanmoqda...</p>
                </div>
            `);
            
            $.ajax({
                url: 'get/oquv_yuklama_table.php',
                type: 'POST',
                data: {
                    kafedra_id: kafedraId,
                    oquv_yil_start: oquvYilStart,
                    yonalish_id: yonalishId,
                    semestr_turi: semestrType
                },
                success: function(response) {
                    container.html(response);
                    
                    // Zoom qayta o'rnatish
                    updateZoom();
                },
                error: function(xhr, status, error) {
                    container.html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Ma'lumotlarni yuklab bo'lmadi: ${error}
                        </div>
                    `);
                    console.error('Xatolik:', error);
                }
            });
        }

        function applyFilters() {
            const kafedraId = $('#kafedraFilter').val();
            const oquvYilStart = $('#semestrFilter').val();
            const yonalishId = $('#yonalishFilter').val();
            const semestrType = $('#semestrTypeFilter').val();
            
            // Loading ko'rsatish
            const filterBtn = $('.filter-actions .btn-primary');
            const originalText = filterBtn.html();
            filterBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Filtrlash...');
            filterBtn.prop('disabled', true);
            
            loadTableData(kafedraId, oquvYilStart, yonalishId, semestrType);
            
            setTimeout(() => {
                filterBtn.html(originalText);
                filterBtn.prop('disabled', false);
                
                // Muvaffaqiyatli xabar
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
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
            $('#yonalishFilter').val(null).trigger('change');
            $('#semestrTypeFilter').val(null).trigger('change');
            
            loadTableData();
            
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2000
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
                const wb = XLSX.utils.table_to_book(table, {sheet: "O'quv yuklamasi"});
                XLSX.writeFile(wb, "oquv_yuklamasi.xlsx");
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
