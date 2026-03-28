<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once 'config.php';
$db = new Database();

// Izoh: Filtrlar (yo'nalish va semestr) uchun qiymatlar.
$filters = [];
if (!empty($_GET['yonalish_id'])) {
    $filters['yonalish_id'] = (int)$_GET['yonalish_id'];
}
if (!empty($_GET['semestr'])) {
    $filters['semestr'] = (int)$_GET['semestr'];
}

$yonalishlar = $db->get_data_by_table_all('yonalishlar');
$semestrNumbers = [];
$semestrRes = $db->query("SELECT DISTINCT semestr FROM semestrlar ORDER BY semestr");
if ($semestrRes) {
    while ($row = mysqli_fetch_assoc($semestrRes)) {
        $semestrNumbers[] = (int)$row['semestr'];
    }
}
$semestrPairs = [];
if (!empty($semestrNumbers)) {
    $maxSemestr = max($semestrNumbers);
    for ($i = 1; $i <= $maxSemestr; $i += 2) {
        if (in_array($i, $semestrNumbers, true) || in_array($i + 1, $semestrNumbers, true)) {
            $semestrPairs[] = $i;
        }
    }
}

$oquv_rejalar = $db->get_oquv_rejalar($filters);

// Izoh: O'quv rejada tanlov fan nechta variantga ega ekanini ishchi reja variantlaridan olamiz.
// Shu bilan bazaviy o'quv rejada ham "Tanlov fan" soni variantlar soniga mos chiqadi.
$where = [];
if (!empty($filters['yonalish_id'])) {
    $where[] = "y.id = " . (int)$filters['yonalish_id'];
}
if (!empty($filters['semestr'])) {
    $s = (int)$filters['semestr'];
    $pairStart = ($s % 2 === 0) ? $s - 1 : $s;
    $pairEnd = $pairStart + 1;
    $where[] = "s.semestr IN ($pairStart, $pairEnd)";
}
$whereSQL = '';
if (!empty($where)) {
    $whereSQL = 'WHERE ' . implode(' AND ', $where);
}

$selectedVariants = [];
$variantResult = $db->query("
    SELECT
        fb.fan_code,
        fb.semestr_id,
        fv.fan_name
    FROM ishchi_oquv_reja ior
    JOIN fanlar fb ON fb.id = ior.base_fan_id
    JOIN ishchi_oquv_reja_variants iv ON iv.ishchi_reja_id = ior.id
    JOIN fanlar fv ON fv.id = iv.fan_id
    JOIN semestrlar s ON s.id = fb.semestr_id
    JOIN yonalishlar y ON y.id = s.yonalish_id
    $whereSQL
    ORDER BY fv.fan_name
");
if ($variantResult) {
    while ($row = mysqli_fetch_assoc($variantResult)) {
        $key = $row['fan_code'] . '|' . $row['semestr_id'];
        $selectedVariants[$key][] = [
            'name' => $row['fan_name']
        ];
    }
}

function process_data_for_template(array $data, array $selectedVariants = []): array{
    $semesters = [];

    foreach ($data as $row) {
        $semestrNum = (int)$row['semestr'];
        $fanCode    = $row['fan_code'];
        $tanlovFan  = (int)$row['tanlov_fan'];
        $semestrId  = (int)($row['semestr_id'] ?? 0);
        $fanId      = (int)($row['fan_id'] ?? 0);
        $variantKey = $fanCode . '|' . $semestrId;
        $hasSelectedVariants = isset($selectedVariants[$variantKey]) && count($selectedVariants[$variantKey]) > 0;
        $nonVariantSuffix = $fanId > 0
            ? (string)$fanId
            : trim((string)($row['fan_name'] ?? ''));
        $subjectKey = ($tanlovFan == 1)
            ? $fanCode
            : ($fanCode . '|' . $nonVariantSuffix . '|' . $semestrId);

        $lecture   = (int)$row['lecture'];
        $practical = (int)$row['practical'];
        $lab       = (int)$row['lab'];
        $seminar   = (int)$row['seminar'];
        $mustaqil  = (int)$row['mustaqilTalim'];
        $kursIshi  = (int)$row['kursIshi'];
        $kursIshiFlag = (int)($row['kursIshiFlag'] ?? 0);
        $kursIshiExtraFlag = (int)($row['kursIshiExtraFlag'] ?? 0);
        $malaka    = (int)$row['malakaAmaliyot'];

        $audTotal = $lecture + $practical + $lab + $seminar;
        $totalSoat = $audTotal + $mustaqil + $kursIshi + $malaka;

        if (!isset($semesters[$semestrNum])) {
            $semesters[$semestrNum] = [
                'id' => $row['semestr_id'] ?? null,
                'name' => $semestrNum . '-SEMESTR',
                'subjects' => [],
                'totals' => [
                    'credit' => 0,
                    'totalHours' => 0,
                    'auditoriya' => [
                        'total' => 0,
                        'lecture' => 0,
                        'practical' => 0,
                        'lab' => 0,
                        'seminar' => 0
                    ],
                    'malakaAmaliyot' => 0,
                    'kursIshi' => 0,
                    'mustaqilTalim' => 0
                ]
            ];
        }

        if ($tanlovFan == 1) {
            if (isset($semesters[$semestrNum]['subjects'][$subjectKey])) {
                // Izoh: Ishchi variantlar mavjud bo'lsa, bu yerda qayta append qilmaymiz.
                if (empty($semesters[$semestrNum]['subjects'][$subjectKey]['variants_locked'])) {
                    $semesters[$semestrNum]['subjects'][$subjectKey]['variants'][] = [
                        'name' => $row['fan_name'],
                        'department' => $row['kafedra_name']
                    ];
                }
            } else {
                $variants = [];
                if ($hasSelectedVariants) {
                    $variants = $selectedVariants[$variantKey];
                } else {
                    $variants[] = [
                        'name' => $row['fan_name'],
                        'department' => $row['kafedra_name']
                    ];
                }
                $semesters[$semestrNum]['subjects'][$subjectKey] = [
                    'code' => $fanCode,
                    'name' => $row['fan_name'],
                    'isTanlovFan' => true,
                    'variants' => $variants,
                    'variants_locked' => $hasSelectedVariants,
                    'examType' => 'I',
                    'credit' => round($totalSoat / 30),
                    'totalHours' => $totalSoat,
                    'auditoriya' => [
                        'total' => $audTotal,
                        'lecture' => $lecture,
                        'practical' => $practical,
                        'lab' => $lab,
                        'seminar' => $seminar
                    ],
                    'malakaAmaliyot' => $malaka,
                    'kursIshi' => $kursIshi,
                    'kursIshiFlag' => $kursIshiFlag,
                    'kursIshiExtraFlag' => $kursIshiExtraFlag,
                    'mustaqilTalim' => $mustaqil,
                    'department' => $row['kafedra_name']
                ];
            }
        } else {
            $semesters[$semestrNum]['subjects'][$subjectKey] = [
                'code' => $fanCode,
                'name' => $row['fan_name'],
                'isTanlovFan' => false,
                'examType' => 'I',
                'credit' => round($totalSoat / 30),
                'totalHours' => $totalSoat,
                'auditoriya' => [
                    'total' => $audTotal,
                    'lecture' => $lecture,
                    'practical' => $practical,
                    'lab' => $lab,
                    'seminar' => $seminar
                ],
                'malakaAmaliyot' => $malaka,
                'kursIshi' => $kursIshi,
                'kursIshiFlag' => $kursIshiFlag,
                'kursIshiExtraFlag' => $kursIshiExtraFlag,
                'mustaqilTalim' => $mustaqil,
                'department' => $row['kafedra_name']
            ];
        }

        if ($tanlovFan != 1 || !isset($semesters[$semestrNum]['subjects'][$subjectKey]['totals_calculated'])) {
            $semesters[$semestrNum]['totals']['totalHours'] += $totalSoat;
            $semesters[$semestrNum]['totals']['credit'] += round($totalSoat / 30);

            $semesters[$semestrNum]['totals']['auditoriya']['total'] += $audTotal;
            $semesters[$semestrNum]['totals']['auditoriya']['lecture'] += $lecture;
            $semesters[$semestrNum]['totals']['auditoriya']['practical'] += $practical;
            $semesters[$semestrNum]['totals']['auditoriya']['lab'] += $lab;
            $semesters[$semestrNum]['totals']['auditoriya']['seminar'] += $seminar;

            $semesters[$semestrNum]['totals']['mustaqilTalim'] += $mustaqil;
            $semesters[$semestrNum]['totals']['kursIshi'] += $kursIshi;
            $semesters[$semestrNum]['totals']['malakaAmaliyot'] += $malaka;
            
            if ($tanlovFan == 1) {
                $semesters[$semestrNum]['subjects'][$subjectKey]['totals_calculated'] = true;
            }
        }
    }

    ksort($semesters);

    foreach ($semesters as &$semester) {
        ksort($semester['subjects']);
        foreach ($semester['subjects'] as &$subject) {
            unset($subject['totals_calculated']);
            unset($subject['variants_locked']);
        }
        $semester['subjects'] = array_values($semester['subjects']);
    }

    $yearlyTotal = [
        'credit' => 0,
        'totalHours' => 0,
        'auditoriya' => ['total'=>0,'lecture'=>0,'practical'=>0,'lab'=>0,'seminar'=>0],
        'malakaAmaliyot' => 0,
        'kursIshi' => 0,
        'mustaqilTalim' => 0
    ];

    foreach ($semesters as $s) {
        foreach ($yearlyTotal as $k => &$v) {
            if (is_array($v)) {
                foreach ($v as $kk => &$vv) {
                    $vv += $s['totals'][$k][$kk];
                }
            } else {
                $v += $s['totals'][$k];
            }
        }
    }

    return [
        'academicYear' => '2025-2026',
        'semesters' => array_values($semesters),
        'yearlyTotal' => $yearlyTotal
    ];
}

$data = process_data_for_template($oquv_rejalar, $selectedVariants);

function renderSubjectCells($subject, $side = 'left') {
    if (!$subject) {
        return '
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
            <td>-</td>
        ';
    }
    
    $calculatedCredit = round($subject['totalHours'] / 30);
    
    $fanNomiHtml = '';
    
    if (isset($subject['isTanlovFan']) && $subject['isTanlovFan'] && isset($subject['variants'])) {
        $fanNomiHtml = '<ol class="tanlov-fan-list">';

        // Izoh: O'quv rejada tanlov fan variant nomlari ko'rsatilmaydi, har biri "Tanlov fan" deb chiqadi.
        // Bu yerda nechta variant bo'lsa, shuncha band chiqaramiz.
        foreach ($subject['variants'] as $variant) {
            $fanNomiHtml .= '<li>Tanlov fan</li>';
        }

        $fanNomiHtml .= '</ol>';
    } else {
        $fanNomiHtml = htmlspecialchars($subject['name']);
    }
    
    return '
        <td>' . htmlspecialchars($subject['code']) . '</td>
        <td style="text-align: left;">' . $fanNomiHtml . '</td>
        <td><span class="exam-type exam-' . strtolower($subject['examType']) . '">' . $subject['examType'] . '</span></td>
        <td>' . $calculatedCredit . '</td>
        <td>' . $subject['totalHours'] . '</td>
        <td>' . $subject['auditoriya']['total'] . '</td>
        <td>' . $subject['auditoriya']['lecture'] . '</td>
        <td>' . $subject['auditoriya']['practical'] . '</td>
        <td>' . $subject['auditoriya']['lab'] . '</td>
        <td>' . $subject['auditoriya']['seminar'] . '</td>
        <td>' . ($subject['malakaAmaliyot'] ?? 0) . '</td>
        <td>' . (((($subject['kursIshi'] ?? 0) > 0) || (($subject['kursIshiFlag'] ?? 0) > 0) || (($subject['kursIshiExtraFlag'] ?? 0) > 0)) ? 'K' : '') . '</td>
        <td>' . $subject['mustaqilTalim'] . '</td>
    ';
}
?>

<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>O'quv Rejalari - O'quv Qo'lanma</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="../assets/css/oquv_reja_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>
    <div class="app-container">
        <?php include_once 'includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-navbar">
                <div class="navbar-left">
                    <h1>O'quv Rejalari</h1>
                    <p class="navbar-subtitle">O'quv reja jadvali</p>
                </div>
                <div class="navbar-right">
                    <div class="current-date">
                        <i class="fas fa-calendar-day"></i>
                        <span id="currentDate"></span>
                    </div>
                </div>
            </header>

            <div class="content-container">
                <div class="controls-panel">
                    <form class="filter-form" method="get" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                        <select class="form-control" name="yonalish_id" style="min-width:200px;" data-placeholder="Barcha yo'nalishlar">
                            <option value="">Barcha yo'nalishlar</option>
                            <?php foreach ($yonalishlar as $y): ?>
                                <option value="<?= (int)$y['id'] ?>" <?= (!empty($filters['yonalish_id']) && (int)$filters['yonalish_id'] === (int)$y['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($y['name']) ?> - <?= htmlspecialchars($y['kirish_yili']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select class="form-control" name="semestr" style="min-width:150px;" data-placeholder="Barcha semestrlar">
                            <option value="">Barcha semestrlar</option>
                            <?php foreach ($semestrPairs as $pairStart): ?>
                                <option value="<?= $pairStart ?>" <?= (!empty($filters['semestr']) && (int)$filters['semestr'] === (int)$pairStart) ? 'selected' : '' ?>>
                                    <?= $pairStart ?>-<?= $pairStart + 1 ?>-semestr
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-filter"></i> Filtrlash
                        </button>
                        <a class="btn btn-secondary" href="oquv-rejalar.php">
                            <i class="fas fa-rotate-left"></i> Tozalash
                        </a>
                    </form>
                    <div class="action-buttons" style="display: flex; gap: 10px;">
                        <button class="btn btn-success" id="exportExcel">
                            <i class="fas fa-file-excel"></i> Excel ga eksport
                        </button>
                        <button class="btn btn-info" id="printTable">
                            <i class="fas fa-print"></i> Chop etish
                        </button>
                    </div>
                </div>

                <div class="stats-cards">
                    <div class="stat-card-excel">
                        <h4>Jami Fanlar</h4>
                        <div class="value"><?php echo array_sum(array_map(function($sem) { return count($sem['subjects']); }, $data['semesters'])); ?></div>
                        <div class="label">Barcha semestrlar bo'yicha</div>
                    </div>
                    
                    <div class="stat-card-excel">
                        <h4>Umumiy Kreditlar</h4>
                        <div class="value"><?php echo $data['yearlyTotal']['credit']; ?></div>
                        <div class="label">Jami kredit soatlari</div>
                    </div>
                    
                    <div class="stat-card-excel">
                        <h4>Jami Soatlar</h4>
                        <div class="value"><?php echo $data['yearlyTotal']['totalHours']; ?></div>
                        <div class="label">Barcha fanlar uchun</div>
                    </div>
                    
                    <div class="stat-card-excel">
                        <h4>Semestrlar</h4>
                        <div class="value"><?php echo count($data['semesters']); ?></div>
                        <div class="label">Umumiy semestrlar soni</div>
                    </div>
                </div>

                <div class="excel-view-container">
                    <div class="excel-header">
                        <h2>O'QUV REJA JADVALI</h2>
                        <div class="academic-year"><?php echo $data['academicYear']; ?> O'quv yili</div>
                    </div>

                    <div class="excel-table-container">
                        <table class="excel-table">
                            <tbody>
                                <?php
                                $semestersCount = count($data['semesters']);
                                
                                for ($i = 0; $i < $semestersCount; $i += 2) {
                                    $leftSemester = $data['semesters'][$i];
                                    $rightSemester = ($i + 1 < $semestersCount) ? $data['semesters'][$i + 1] : null;
                                ?>
                                    <!-- Semestr sarlavhasi -->
                                    <tr class="semester-header-row">
                                        <td colspan="13" class="semester-header"><?php echo $leftSemester['name']; ?></td>
                                        <td colspan="13" class="semester-header"><?php echo $rightSemester ? $rightSemester['name'] : '-'; ?></td>
                                    </tr>
                                    
                                    <!-- Ustun sarlavhalari -->
                                    <tr>
                                        <!-- Chap semestr -->
                                        <th rowspan="2">Kod</th>
                                        <th rowspan="2">Fan nomi</th>
                                        <th rowspan="2">S/I</th>
                                        <th rowspan="2">Kredit</th>
                                        <th rowspan="2">Soat</th>
                                        <th colspan="5">Auditoriya soatlari</th>
                                        <th rowspan="2">Malaka amaliyot</th>
                                        <th rowspan="2">Kurs ishi</th>
                                        <th rowspan="2">Mustaqil ta'lim</th>
                                        
                                        <!-- O'ng semestr -->
                                        <th rowspan="2">Kod</th>
                                        <th rowspan="2">Fan nomi</th>
                                        <th rowspan="2">S/I</th>
                                        <th rowspan="2">Kredit</th>
                                        <th rowspan="2">Soat</th>
                                        <th colspan="5">Auditoriya soatlari</th>
                                        <th rowspan="2">Malaka amaliyot</th>
                                        <th rowspan="2">Kurs ishi</th>
                                        <th rowspan="2">Mustaqil ta'lim</th>
                                    </tr>
                                    <tr>
                                        <!-- Chap auditoriya -->
                                        <th>Jami</th>
                                        <th>Ma'ruza</th>
                                        <th>Amaliy</th>
                                        <th>Lab</th>
                                        <th>Seminar</th>
                                        
                                        <!-- O'ng auditoriya -->
                                        <th>Jami</th>
                                        <th>Ma'ruza</th>
                                        <th>Amaliy</th>
                                        <th>Lab</th>
                                        <th>Seminar</th>
                                    </tr>
                                    
                                    <!-- Fanlar -->
                                    <?php
                                    $leftSubjects = $leftSemester['subjects'];
                                    $rightSubjects = $rightSemester ? $rightSemester['subjects'] : [];
                                    $maxRows = max(count($leftSubjects), count($rightSubjects));
                                    
                                    for ($j = 0; $j < $maxRows; $j++) {
                                        $leftSubject = isset($leftSubjects[$j]) ? $leftSubjects[$j] : null;
                                        $rightSubject = isset($rightSubjects[$j]) ? $rightSubjects[$j] : null;
                                    ?>
                                        <tr class="subject-row">
                                            <?php echo renderSubjectCells($leftSubject, 'left'); ?>
                                            <?php echo renderSubjectCells($rightSubject, 'right'); ?>
                                        </tr>
                                    <?php } ?>
                                    
                                    <!-- Semestr jami -->
                                    <tr class="total-row">
                                        <td colspan="2" class="total-cell"><strong>Jami semestrda</strong></td>
                                        <td class="total-cell"></td>
                                        <td class="total-cell semester-total"><?php echo round($leftSemester['totals']['totalHours'] / 30); ?></td>
                                        <td class="total-cell semester-total"><?php echo $leftSemester['totals']['totalHours']; ?></td>
                                        <td class="total-cell semester-total"><?php echo $leftSemester['totals']['auditoriya']['total']; ?></td>
                                        <td class="total-cell semester-total"><?php echo $leftSemester['totals']['auditoriya']['lecture']; ?></td>
                                        <td class="total-cell semester-total"><?php echo $leftSemester['totals']['auditoriya']['practical']; ?></td>
                                        <td class="total-cell semester-total"><?php echo $leftSemester['totals']['auditoriya']['lab']; ?></td>
                                        <td class="total-cell semester-total"><?php echo $leftSemester['totals']['auditoriya']['seminar']; ?></td>
                                        <td class="total-cell semester-total"><?php echo $leftSemester['totals']['malakaAmaliyot'] ?? 0; ?></td>
                                        <td class="total-cell semester-total"><?php echo $leftSemester['totals']['kursIshi'] ?? 0; ?></td>
                                        <td class="total-cell semester-total"><?php echo $leftSemester['totals']['mustaqilTalim']; ?></td>
                                        
                                        <?php if ($rightSemester): ?>
                                            <td colspan="2" class="total-cell"><strong>Jami semestrda</strong></td>
                                            <td class="total-cell"></td>
                                            <td class="total-cell semester-total"><?php echo round($rightSemester['totals']['totalHours'] / 30); ?></td>
                                            <td class="total-cell semester-total"><?php echo $rightSemester['totals']['totalHours']; ?></td>
                                            <td class="total-cell semester-total"><?php echo $rightSemester['totals']['auditoriya']['total']; ?></td>
                                            <td class="total-cell semester-total"><?php echo $rightSemester['totals']['auditoriya']['lecture']; ?></td>
                                            <td class="total-cell semester-total"><?php echo $rightSemester['totals']['auditoriya']['practical']; ?></td>
                                            <td class="total-cell semester-total"><?php echo $rightSemester['totals']['auditoriya']['lab']; ?></td>
                                            <td class="total-cell semester-total"><?php echo $rightSemester['totals']['auditoriya']['seminar']; ?></td>
                                            <td class="total-cell semester-total"><?php echo $rightSemester['totals']['malakaAmaliyot'] ?? 0; ?></td>
                                            <td class="total-cell semester-total"><?php echo $rightSemester['totals']['kursIshi'] ?? 0; ?></td>
                                            <td class="total-cell semester-total"><?php echo $rightSemester['totals']['mustaqilTalim']; ?></td>
                                        <?php else: ?>
                                            <td colspan="13" class="total-cell"></td>
                                        <?php endif; ?>
                                    </tr>
                                    
                                <?php } ?>
                                
                                <!-- Yillik jami -->
                                <tr class="year-total-row">
                                    <td colspan="2" class="total-cell"><strong>Jami yillik</strong></td>
                                    <td class="total-cell"></td>
                                    <td class="total-cell year-total"><?php echo round($data['yearlyTotal']['totalHours'] / 30); ?></td>
                                    <td class="total-cell year-total"><?php echo $data['yearlyTotal']['totalHours']; ?></td>
                                    <td class="total-cell year-total"><?php echo $data['yearlyTotal']['auditoriya']['total']; ?></td>
                                    <td class="total-cell year-total"><?php echo $data['yearlyTotal']['auditoriya']['lecture']; ?></td>
                                    <td class="total-cell year-total"><?php echo $data['yearlyTotal']['auditoriya']['practical']; ?></td>
                                    <td class="total-cell year-total"><?php echo $data['yearlyTotal']['auditoriya']['lab']; ?></td>
                                    <td class="total-cell year-total"><?php echo $data['yearlyTotal']['auditoriya']['seminar']; ?></td>
                                    <td class="total-cell year-total"><?php echo $data['yearlyTotal']['malakaAmaliyot']; ?></td>
                                    <td class="total-cell year-total"><?php echo $data['yearlyTotal']['kursIshi']; ?></td>
                                    <td class="total-cell year-total"><?php echo $data['yearlyTotal']['mustaqilTalim']; ?></td>
                                    <td colspan="13" class="total-cell"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="legend mt-4">
                    <h6><i class="fas fa-info-circle me-2"></i>Belgilar:</h6>
                    <div class="legend-items">
                        <div class="legend-item">
                            <span class="exam-type exam-s">S</span>
                            <span>Sinov (Test/Quiz)</span>
                        </div>
                        <div class="legend-item">
                            <span class="exam-type exam-i">I</span>
                            <span>Imtihon (Exam)</span>
                        </div>
                        
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../assets/vendor/xlsx/xlsx.full.min.js"></script>
    <script src="../assets/js/app.js"></script>
    <script>
        function updateCurrentDate() {
            const dateElement = document.getElementById('currentDate');
            if (dateElement) {
                const now = new Date();
                const options = { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                };
                dateElement.textContent = now.toLocaleDateString('uz-UZ', options);
            }
        }

        function exportToExcel() {
            if (typeof XLSX === 'undefined') {
                alert("Excel kutubxonasi yuklanmadi. Sahifani Ctrl+F5 bilan yangilang.");
                return;
            }

            const table = document.querySelector('.excel-table');
            if (!table) {
                alert("Eksport uchun jadval topilmadi.");
                return;
            }

            const wb = XLSX.utils.table_to_book(table, { sheet: "O'quv rejalar" });
            XLSX.writeFile(wb, "oquv_rejalar.xlsx");
        }

        function printTable() {
            window.print();
        }

        document.addEventListener('DOMContentLoaded', function() {
            updateCurrentDate();
            
            document.getElementById('exportExcel')?.addEventListener('click', exportToExcel);
            document.getElementById('printTable')?.addEventListener('click', printTable);
        });

        $(document).ready(function() {
            $('select[name="yonalish_id"], select[name="semestr"]').each(function() {
                const placeholder = $(this).data('placeholder') || 'Tanlang';
                $(this).select2({
                    placeholder: placeholder,
                    allowClear: true,
                    width: 'style'
                });
            });
        });
    </script>
</body>
</html>
