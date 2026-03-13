<?php
    include_once 'config.php';
    $db = new Database();
    $kafedralar = $db->get_data_by_table_all('kafedralar');
    $oqtuvchilar = $db->get_data_by_table_all('oqituvchilar');
    $ishturlar = $db->get_data_by_table_all('ish_turlar');

    // Izoh: Shtat turlarini kerakli tartibda chiqarish (Asosiy -> Ichki -> Tashqi -> Soatbay).
    usort($ishturlar, function($a, $b) {
        $order = function($name) {
            $n = mb_strtolower(trim($name));
            if (strpos($n, 'asosiy') !== false) return 1;
            if (strpos($n, 'ichki') !== false) return 2;
            if (strpos($n, 'tashqi') !== false) return 3;
            if (strpos($n, 'soatbay') !== false) return 4;
            if (strpos($n, 'vakant') !== false) return 5;
            return 9;
        };
        $oa = $order($a['name'] ?? '');
        $ob = $order($b['name'] ?? '');
        if ($oa === $ob) {
            return strcmp($a['name'] ?? '', $b['name'] ?? '');
        }
        return $oa <=> $ob;
    });
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>O'qituvchilar bildirgisi</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="../assets/css/oquv_yuklama_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-navbar">
                <h1><i class="fas fa-file-signature me-2"></i>O'qituvchilar bildirgisi</h1>
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

                        <div class="form-group">
                            <label><i class="fas fa-user me-2"></i>O'qituvchi</label>
                            <select class="form-control" id="oqituvchiFilter">
                                <option value="">Barcha o'qituvchilar</option>
                                <?php foreach ($oqtuvchilar as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['fio']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-briefcase me-2"></i>Shtat turi</label>
                            <select class="form-control" id="shtatFilter">
                                <option value="">Barcha shtat turlari</option>
                                <?php foreach ($ishturlar as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?></option>
                                <?php endforeach; ?>
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
                
                <div id="bildirgiTableContainer"></div>
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
        $(document).ready(function() {
            $('#kafedraFilter, #semestrFilter, #oqituvchiFilter, #shtatFilter').select2({
                placeholder: "Tanlang",
                allowClear: true,
                width: '100%'
            });
            
            loadTableData();
        });

        function loadTableData(kafedraId = '', semestrId = '', oqituvchiId = '', shtatTurId = '') {
            const container = $('#bildirgiTableContainer');
            container.html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Yuklanmoqda...</span>
                    </div>
                    <p class="mt-2">Ma'lumotlar yuklanmoqda...</p>
                </div>
            `);
            
            $.ajax({
                url: 'get/oqituvchi_taqsimoti_table.php',
                type: 'POST',
                data: {
                    mode: 'report',
                    kafedra_id: kafedraId,
                    semestr: semestrId,
                    oqituvchi_id: oqituvchiId,
                    shtat_turi_id: shtatTurId
                },
                success: function(response) {
                    container.html(response);
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

        function applyFilters() {
            const kafedraId = $('#kafedraFilter').val();
            const semestrId = $('#semestrFilter').val();
            const oqituvchiId = $('#oqituvchiFilter').val();
            const shtatTurId = $('#shtatFilter').val();
            
            const filterBtn = $('.filter-actions .btn-primary');
            const originalText = filterBtn.html();
            filterBtn.html('<i class="fas fa-spinner fa-spin me-2"></i>Filtrlash...');
            filterBtn.prop('disabled', true);
            
            loadTableData(kafedraId, semestrId, oqituvchiId, shtatTurId);
            
            setTimeout(() => {
                filterBtn.html(originalText);
                filterBtn.prop('disabled', false);
                
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
            }, 600);
        }

        function resetFilters() {
            $('#kafedraFilter').val(null).trigger('change');
            $('#semestrFilter').val(null).trigger('change');
            $('#oqituvchiFilter').val(null).trigger('change');
            $('#shtatFilter').val(null).trigger('change');
            
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

        function printTable() {
            window.print();
        }

        function exportToExcel() {
            const table = document.getElementById('oqituvchiBildirgiTable');
            if (table) {
                const wb = XLSX.utils.table_to_book(table, {sheet: "Bildirgi"});
                XLSX.writeFile(wb, "oqituvchi_bildirgi.xlsx");
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Jadval mavjud emas',
                    text: 'Iltimos, avval ma\'lumotlarni yuklang'
                });
            }
        }
    </script>
</body>
</html>
