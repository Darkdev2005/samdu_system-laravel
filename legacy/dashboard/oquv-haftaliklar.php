<?php
    include_once 'config.php';
    $db = new Database();
    $yonalishlar = $db->get_data_by_table_all('yonalishlar');
    $oquv_haftalik_turlari = $db->get_data_by_table_all('oquv_haftalik_turlar');
        
    $oquv_haftaliklar = $db->get_oquv_haftaliklar();
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <title>O'quv haftaliklari ro'yxati</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="../assets/css/oquv_haftaliklar_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-navbar">
                <div class="navbar-left">
                    <h1>O'quv haftaliklari</h1>
                    <div class="navbar-subtitle">Yo'nalishlar bo'yicha o'quv rejalari</div>
                </div>
                <div class="navbar-right">
                    <div class="current-date">
                        <i class="far fa-calendar-alt"></i>
                        <span id="currentDate"></span>
                    </div>
                </div>
            </header>
            
            <div class="content-container">
                <!-- Filter paneli -->
                <div class="filter-panel">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label><i class="fas fa-filter"></i> Yo'nalish bo'yicha filtrlash</label>
                            <select class="form-control" id="yonalishFilter">
                                <option value="0">Barcha yo'nalishlar</option>
                                <?php foreach($yonalishlar as $yonalish): ?>
                                    <option value="<?= $yonalish['id'] ?>">
                                        <?= htmlspecialchars($yonalish['name']) ?> 
                                        (<?= $yonalish['code'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Tezkor filter tugmalari -->
                    <div class="filter-buttons">
                        <button class="filter-btn active" data-yonalish="0">
                            <i class="fas fa-globe"></i> Barchasi
                        </button>
                        <?php foreach($yonalishlar as $yonalish): ?>
                            <button class="filter-btn" data-yonalish="<?= $yonalish['id'] ?>">
                                <i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($yonalish['code']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Natijalar soni -->
                    <div class="results-count" id="resultsCount">
                        Jami: <?= count($oquv_haftaliklar) ?> ta o'quv haftaligi
                    </div>
                    
                    <!-- Hafta turi afishasi -->
                    <div class="legend-container">
                        <h4 style="width: 100%; margin-bottom: 10px; color: #333;">
                            <i class="fas fa-key"></i> Hafta turlari kaliti
                        </h4>
                        <?php foreach($oquv_haftalik_turlari as $tur): 
                            $color = '#f5f5f5';
                            if(strpos($tur['short_name'], 'A') !== false) $color = '#d5f5e3';
                            elseif(strpos($tur['short_name'], 'T') !== false) $color = '#ffebee';
                            elseif(strpos($tur['short_name'], 'M') !== false) $color = '#e3f2fd';
                            elseif(strpos($tur['short_name'], 'AT') !== false) $color = '#fff3e0';
                            elseif(strpos($tur['short_name'], 'D') !== false) $color = '#f3e5f5';
                            elseif(strpos($tur['short_name'], 'FT') !== false) $color = '#e8f5e9';
                            elseif(strpos($tur['short_name'], 'T/Y') !== false) $color = '#f5f5f5';
                        ?>
                            <div class="legend-item">
                                <div class="legend-color" style="background-color: <?= $color ?>"></div>
                                <span><?= htmlspecialchars($tur['short_name']) ?> - <?= htmlspecialchars($tur['name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Kalendar jadvallari -->
                <div id="calendarContainer">
                    <?php if(empty($oquv_haftaliklar)): ?>
                        <div class="no-results" id="noResults">
                            <i class="far fa-calendar-times"></i>
                            <h3>O'quv haftaligi topilmadi</h3>
                            <p>Hozircha o'quv haftaligi mavjud emas. Yangi o'quv haftaligi yaratish uchun <a href="oquv_haftaligi_yaratish.php">bu yerga</a> boring.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($oquv_haftaliklar as $item): ?>
                            <div class="calendar-view" 
                                 data-yonalish-id="<?= $item['yonalish_id'] ?>">
                                <?php 
                                    // Kalendar HTML yaratish
                                    echo createCalendarHTML($item, $oquv_haftalik_turlari);
                                ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Hozirgi sanani ko'rsatish
            function updateCurrentDate() {
                const now = new Date();
                const options = { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                };
                const dateString = now.toLocaleDateString('uz-UZ', options);
                $('#currentDate').text(dateString);
            }
            updateCurrentDate();
            
            // Filter tugmalari
            $('.filter-btn').on('click', function() {
                const yonalishId = $(this).data('yonalish');
                
                // Filter tugmalarini yangilash
                $('.filter-btn').removeClass('active');
                $(this).addClass('active');
                
                // Select'ni yangilash
                $('#yonalishFilter').val(yonalishId);
                
                // Filter qo'llash
                filterCalendars();
            });
            
            // Select filter o'zgarganida
            $('#yonalishFilter').on('change', function() {
                const yonalishId = $(this).val();
                
                // Filter tugmalarini yangilash
                $('.filter-btn').removeClass('active');
                if (yonalishId == 0) {
                    $('.filter-btn[data-yonalish="0"]').addClass('active');
                } else {
                    $(`.filter-btn[data-yonalish="${yonalishId}"]`).addClass('active');
                }
                
                // Filter qo'llash
                filterCalendars();
            });
            
            // Kalendarlarni filter qilish
            function filterCalendars() {
                const selectedYonalishId = $('#yonalishFilter').val();
                let visibleCount = 0;
                
                $('.calendar-view').each(function() {
                    const yonalishId = $(this).data('yonalish-id');
                    
                    if (selectedYonalishId == 0 || yonalishId == selectedYonalishId) {
                        $(this).removeClass('hidden');
                        visibleCount++;
                    } else {
                        $(this).addClass('hidden');
                    }
                });
                
                // Natijalar sonini yangilash
                updateResultsCount(visibleCount);
            }
            
            // Natijalar sonini yangilash
            function updateResultsCount(visibleCount) {
                const totalCount = $('.calendar-view').length;
                
                if (visibleCount === 0) {
                    $('#noResults').show();
                } else {
                    $('#noResults').hide();
                }
                
                // Natijalar sonini ko'rsatish
                const selectedYonalish = $('#yonalishFilter option:selected').text();
                let countText = `${visibleCount} ta o'quv haftaligi`;
                
                if (selectedYonalish !== 'Barcha yo\'nalishlar') {
                    countText += ` (${selectedYonalish})`;
                }
                
                $('#resultsCount').text(countText);
            }
            
            // Dastlabki natijalar sonini ko'rsatish
            const initialCount = $('.calendar-view').length;
            updateResultsCount(initialCount);
        });
    </script>
    <script src="../assets/js/app.js"></script>
</body>
</html>

<?php
function getColorClass($shortName) {
    if (strpos($shortName, 'A') !== false) return 'theory';
    if (strpos($shortName, 'T') !== false) return 'holiday';
    if (strpos($shortName, 'M') !== false) return 'practice';
    if (strpos($shortName, 'AT') !== false) return 'attestation';
    if (strpos($shortName, 'D') !== false) return 'exam';
    if (strpos($shortName, 'FT') !== false) return 'theory';
    if (strpos($shortName, 'T/Y') !== false) return 'rest';
    return 'rest';
}

function createCalendarHTML($item, $oquv_haftalik_turlari) {
    // Hafta turlarini map qilish
    $weekTypes = [];
    foreach ($oquv_haftalik_turlari as $tur) {
        $weekTypes[$tur['id']] = [
            'short_name' => $tur['short_name'],
            'name' => $tur['name'],
            'color_class' => getColorClass($tur['short_name'])
        ];
    }
    
    try {
        $oquvData = json_decode($item['oquv_data'], true);
        $muddati = $item['muddati'] ?: 4;
        
        $html = '
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <div>
                    <h3 style="margin: 0; color: #333;">
                        <i class="fas fa-graduation-cap text-primary"></i> 
                        ' . htmlspecialchars($item['yonalish_nomi']) . ' (' . htmlspecialchars($item['yonalish_code']) . ')
                    </h3>
                    <p style="margin: 5px 0 0 0; color: #6c757d;">
                        <i class="far fa-calendar"></i> 
                        ' . formatDate($item['create_at']) . ' | 
                        <span class="info-badge">' . $muddati . ' yillik</span>
                    </p>
                </div>
            </div>';
        
        // Oy va haftalar ma'lumotlari
        $months = [
            ['name' => 'Sentyabr', 'weeks' => 4],
            ['name' => 'Oktyabr', 'weeks' => 5],
            ['name' => 'Noyabr', 'weeks' => 4],
            ['name' => 'Dekabr', 'weeks' => 5],
            ['name' => 'Yanvar', 'weeks' => 4],
            ['name' => 'Fevral', 'weeks' => 4],
            ['name' => 'Mart', 'weeks' => 4],
            ['name' => 'Aprel', 'weeks' => 5],
            ['name' => 'May', 'weeks' => 4],
            ['name' => 'Iyun', 'weeks' => 4],
            ['name' => 'Iyul', 'weeks' => 5],
            ['name' => 'Avgust', 'weeks' => 4]
        ];
        
        $html .= '<table class="calendar-table">';
        
        // Oy nomlari qatori
        $html .= '<thead><tr class="month-row"><th class="course-number">Kurs</th>';
        foreach ($months as $month) {
            $html .= '<th colspan="' . $month['weeks'] . '">' . $month['name'] . '</th>';
        }
        $html .= '</tr>';
        
        // Hafta raqamlari qatori
        $html .= '<tr class="week-row"><th></th>';
        $weekNumber = 1;
        foreach ($months as $month) {
            for ($w = 1; $w <= $month['weeks']; $w++) {
                $html .= '<th>' . $weekNumber . '</th>';
                $weekNumber++;
            }
        }
        $html .= '</tr></thead><tbody>';
        
        // Har bir kurs uchun qatorlar
        for ($course = 1; $course <= $muddati; $course++) {
            $html .= '<tr data-course="' . $course . '">';
            $html .= '<td class="course-number">' . $course . '-kurs</td>';
            
            $weekNumber = 1;
            foreach ($months as $month) {
                for ($w = 1; $w <= $month['weeks']; $w++) {
                    $fieldName = 'hafta[' . $course . '][' . $weekNumber . ']';
                    $weekValue = $oquvData[$fieldName] ?? null;
                    
                    $cellClass = 'week-cell';
                    $weekInfo = '';
                    $weekTitle = '';
                    
                    if ($weekValue && isset($weekTypes[$weekValue])) {
                        $weekType = $weekTypes[$weekValue];
                        $cellClass .= ' ' . $weekType['color_class'];
                        $weekInfo = $weekType['short_name'];
                        $weekTitle = $weekType['name'];
                    }
                    
                    $html .= '<td class="' . $cellClass . '" title="' . htmlspecialchars($weekTitle) . '">';
                    if ($weekInfo) {
                        $html .= '<div class="week-info">' . $weekInfo . '</div>';
                    }
                    $html .= '</td>';
                    $weekNumber++;
                }
            }
            
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
        
    } catch (Exception $e) {
        return '
            <div style="color: #e74c3c; padding: 20px; text-align: center;">
                <i class="fas fa-exclamation-triangle"></i> 
                Kalendarni yuklashda xatolik yuz berdi
            </div>';
    }
}

function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('d.m.Y H:i');
}
?>
