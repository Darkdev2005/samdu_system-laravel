<?php
include_once '../config.php';
$db = new Database();

$filters = [];
$rowLimit = 150;
if (isset($_POST['show_all']) && (int)$_POST['show_all'] === 1) {
    $filters['limit'] = 0;
} else {
    $filters['limit'] = $rowLimit;
}
if (isset($_POST['kafedra_id']) && !empty($_POST['kafedra_id'])) {
    $filters['kafedra_id'] = (int)$_POST['kafedra_id'];
}
if (isset($_POST['semestr']) && !empty($_POST['semestr'])) {
    $filters['semestr'] = (int)$_POST['semestr'];
}
legacy_apply_kafedra_scope($filters);

$oquv_taqsimotlar = $db->get_oquv_taqsimotlar($filters);
$maxsus_oquv_taqsimotlar = [];
if (empty($filters['limit']) || (int)$filters['limit'] === 0) {
    $maxsus_oquv_taqsimotlar = $db->get_maxsus_oquv_taqsimotlar($filters);
} else {
    $remainingLimitForMaxsus = max(0, (int)$filters['limit'] - count($oquv_taqsimotlar));
    if ($remainingLimitForMaxsus > 0) {
        $maxsus_oquv_taqsimotlar = $db->get_maxsus_oquv_taqsimotlar($filters + ['limit' => $remainingLimitForMaxsus]);
    }
}
$oquv_taqsimotlar = array_merge($oquv_taqsimotlar, $maxsus_oquv_taqsimotlar);
usort($oquv_taqsimotlar, static function (array $a, array $b): int {
    $aSem = (int)($a['semestr'] ?? 0);
    $bSem = (int)($b['semestr'] ?? 0);
    if ($aSem !== $bSem) {
        return $aSem <=> $bSem;
    }
    return strcmp((string)($a['fan_nomi'] ?? ''), (string)($b['fan_nomi'] ?? ''));
});
$qoshimcha_oquv_taqsimotlar = [];
if (empty($filters['limit']) || (int)$filters['limit'] === 0) {
    $qoshimcha_oquv_taqsimotlar = $db->get_qoshimcha_oquv_taqsimotlar($filters);
} else {
    $remainingLimit = max(0, (int)$filters['limit'] - count($oquv_taqsimotlar));
    if ($remainingLimit > 0) {
        $qoshimcha_oquv_taqsimotlar = $db->get_qoshimcha_oquv_taqsimotlar($filters + ['limit' => $remainingLimit]);
    }
}
$oquv_taqsimotlar = is_array($oquv_taqsimotlar) ? $oquv_taqsimotlar : [];
$qoshimcha_oquv_taqsimotlar = is_array($qoshimcha_oquv_taqsimotlar) ? $qoshimcha_oquv_taqsimotlar : [];

// Izoh: config.php dagi ayrim eski versiyalarda needs_resync SELECTda bo'lmasligi mumkin.
// Shu holatda fallback sifatida pending eventlarni yonalish_id bo'yicha shu faylda tekshiramiz.
$pendingYonalishMap = [];
$allYonalishIds = [];
foreach ([$oquv_taqsimotlar, $qoshimcha_oquv_taqsimotlar] as $rows) {
    foreach ($rows as $r) {
        if (!empty($r['yonalish_id'])) {
            $allYonalishIds[] = (int)$r['yonalish_id'];
        }
    }
}
$allYonalishIds = array_values(array_unique(array_filter($allYonalishIds)));
if (!empty($allYonalishIds)) {
    $pendingRows = $db->get_data_by_table_all(
        'taqsimot_resync_events',
        "WHERE status='pending' AND yonalish_id IN (" . implode(',', $allYonalishIds) . ")"
    );
    foreach ($pendingRows as $pr) {
        $pendingYonalishMap[(int)$pr['yonalish_id']] = true;
    }
}
?>
<style>
    .full-soat {
    background: #00f038 !important;   /* yashil */
    border: 2px solid #28a745;
}

.partial-soat {
    background: #ffc107ff !important;   /* sariq */
    border: 2px solid #ffc107;
}

.taqsim-info {
    font-size: 11px;
    font-weight: bold;
    margin-top: 4px;
}

.needs-resync > td {
    background: #ffe8e8 !important;
}

.resync-badge {
    display: inline-block;
    margin-top: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    color: #fff;
    background: #dc3545;
}
.guruh-cell {
    white-space: normal;
    word-break: break-word;
}
.maxsus-badge {
    display: inline-block;
    margin-left: 8px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    color: #fff;
    background: #0ea5e9;
    vertical-align: middle;
    white-space: nowrap;
}

</style>
<div class="table-container-wrapper">
    <div class="zoom-controls">
        <button class="zoom-btn" onclick="zoomOut()" title="Kichiklashtirish">-</button>
        <button class="zoom-btn" onclick="resetZoom()" title="Asl o'lcham">100%</button>
        <button class="zoom-btn" onclick="zoomIn()" title="Kattalashtirish">+</button>
        <div class="zoom-level" id="zoomLevel">100%</div>
    </div>
    
    <div class="table-title">
        O'ZBEKISTON RESPUBLIKASI OLIY TA'LIM MUASSASASI<br>
        O'QITUVCHILARNING O'QUV YUKLAMASI TAQSIMOTI
    </div>
    
    <div class="table-wrapper">
        <table id="yuklamaTable">
            <thead>
                <tr>
                    <th rowspan="3">№</th>
                    <th rowspan="3">O'qitiladigan fan va boshqa turdagi o'quv ishlari</th>
                    <th rowspan="3">Ta'lim yo'nalishi</th>
                    <th rowspan="3" class="vertical">Guruh raqami</th>
                    <th rowspan="3" class="vertical">O'quv shakli</th>
                    <th rowspan="3" class="vertical">Kurs</th>
                    <th rowspan="3" class="vertical">Semestr</th>
                    <th rowspan="3" class="vertical">Talabalar soni</th>
                    <th rowspan="3" class="vertical">Potoklar soni</th>
                    <th rowspan="3" class="vertical">Guruhlar soni</th>
                    <th rowspan="3" class="vertical">Kichik guruhlar soni</th>

                    <th colspan="4">O'quv soatlari</th>

                    <th colspan="2">Reyting nazorati</th>

                    <th rowspan="3" class="vertical">Kurs ishi va himoyasi</th>
                    <th rowspan="3" class="vertical">Kurs loyihasi va himoyasi</th>

                    <th rowspan="3" class="vertical">O'quv-pedagogik amaliyot</th>
                    <th rowspan="3" class="vertical">Uzluksiz malakaviy amaliyot</th>
                    <th rowspan="3" class="vertical">Dala amaliyoti</th>
                    <th rowspan="3" class="vertical">Dala amaliyoti (OTM)</th>
                    <th rowspan="3" class="vertical">Ishlab chiqarish amaliyoti</th>

                    <th rowspan="3" class="vertical">BMI rahbarligi</th>

                    <th colspan="3">Magistratura</th>
                    <th colspan="3">Doktorantura</th>

                    <th rowspan="3" class="vertical">Ochiq dars</th>
                    <th rowspan="3" class="vertical">Yakuniy davlat attestatsiyasi</th>
                    <th rowspan="3" class="vertical">Boshqa soatlar</th>
                    <th rowspan="3" class="vertical">JAMI</th>
                </tr>

                <tr>
                    <th rowspan="2" class="vertical">Ma'ruza</th>
                    <th rowspan="2" class="vertical">Amaliy</th>
                    <th rowspan="2" class="vertical">Laboratoriya</th>
                    <th rowspan="2" class="vertical">Seminar</th>
                    <!-- Reyting -->
                    <th rowspan="2" class="vertical">Oraliq nazorat</th>
                    <th rowspan="2" class="vertical">Yakuniy nazorat</th>
                    <!-- Magistratura -->
                    <th rowspan="2" class="vertical">Ilmiy-tadqiqot ishi</th>
                    <th rowspan="2" class="vertical">Ilmiy-pedagogik ish</th>
                    <th rowspan="2" class="vertical">Ilmiy stajirovka</th>
                    <!-- Doktorantura -->
                    <th rowspan="2" class="vertical">Tayanch doktorantura</th>
                    <th rowspan="2" class="vertical">Katta ilmiy tadqiqotchi</th>
                    <th rowspan="2" class="vertical">Stajyor-tadqiqotchi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $counter = 1;
                function buildTaqsimotSoatMap($db, $rows): array {
                    if (!is_array($rows) || empty($rows)) {
                        return [];
                    }

                    $rejaIdsByType = [];

                    foreach ($rows as $row) {
                        $rowType = trim((string)($row['taqsimot_type'] ?? 'A'));
                        if (!in_array($rowType, ['A', 'Q', 'M'], true)) {
                            $rowType = 'A';
                        }
                        foreach (['maruza_reja_id', 'amaliy_reja_id', 'laboratoriya_reja_id', 'seminar_reja_id'] as $field) {
                            $rejaId = (int)($row[$field] ?? 0);
                            if ($rejaId > 0) {
                                if (!isset($rejaIdsByType[$rowType])) {
                                    $rejaIdsByType[$rowType] = [];
                                }
                                $rejaIdsByType[$rowType][$rejaId] = true;
                            }
                        }
                    }

                    if (empty($rejaIdsByType)) {
                        return [];
                    }

                    $resultMap = [];
                    foreach ($rejaIdsByType as $rowType => $ids) {
                        $rejaIds = array_keys($ids);
                        foreach (array_chunk($rejaIds, 500) as $idChunk) {
                            $sql = "
                                SELECT oquv_reja_id, SUM(soat) AS jami_soat
                                FROM taqsimotlar
                                WHERE type = '{$rowType}'
                                  AND oquv_reja_id IN (" . implode(',', array_map('intval', $idChunk)) . ")
                                GROUP BY oquv_reja_id
                            ";
                            $queryResult = $db->query($sql);
                            if ($queryResult === false) {
                                continue;
                            }

                            while ($taqsimotRow = mysqli_fetch_assoc($queryResult)) {
                                $rid = (int)($taqsimotRow['oquv_reja_id'] ?? 0);
                                $resultMap[$rowType . ':' . $rid] = (float)($taqsimotRow['jami_soat'] ?? 0);
                            }
                        }
                    }

                    return $resultMap;
                }
                function getMappedTaqsimotSoat($db, array &$taqsimotSoatMap, int $rejaId, string $type = 'A'): float {
                    if ($rejaId <= 0) {
                        return 0.0;
                    }

                    $type = trim($type);
                    if (!in_array($type, ['A', 'Q', 'M'], true)) {
                        $type = 'A';
                    }
                    $cacheKey = $type . ':' . $rejaId;

                    if (array_key_exists($cacheKey, $taqsimotSoatMap)) {
                        return (float)$taqsimotSoatMap[$cacheKey];
                    }

                    $sql = "
                        SELECT SUM(soat) AS jami_soat
                        FROM taqsimotlar
                        WHERE type = '{$type}' AND oquv_reja_id = {$rejaId}
                    ";
                    $queryResult = $db->query($sql);
                    $jamiSoat = 0.0;
                    if ($queryResult !== false) {
                        $taqsimotRow = mysqli_fetch_assoc($queryResult);
                        $jamiSoat = (float)($taqsimotRow['jami_soat'] ?? 0);
                    }

                    $taqsimotSoatMap[$cacheKey] = $jamiSoat;
                    return $jamiSoat;
                }
                function getCellClass($jami, $max) {
                    if ($max <= 0) return '';
                    if ($jami == $max) return 'full-soat';     
                    if ($jami < $max && $jami > 0)  return 'partial-soat';  
                    return '';
                }
                $taqsimotSoatMap = buildTaqsimotSoatMap($db, $oquv_taqsimotlar);
                if (!empty($oquv_taqsimotlar) || !empty($qoshimcha_oquv_taqsimotlar)):
                    foreach ($oquv_taqsimotlar as $row): 
                        $needsResync = !empty($row['needs_resync']);
                        $rowYonalishId = !empty($row['yonalish_id']) ? (int)$row['yonalish_id'] : 0;
                        if (!$needsResync && $rowYonalishId > 0 && !empty($pendingYonalishMap[$rowYonalishId])) {
                            $needsResync = true;
                        }
                        $maruzaRejaId = (int)($row['maruza_reja_id'] ?? 0);
                        $amaliyRejaId = (int)($row['amaliy_reja_id'] ?? 0);
                        $labRejaId = (int)($row['laboratoriya_reja_id'] ?? 0);
                        $seminarRejaId = (int)($row['seminar_reja_id'] ?? 0);
                        $rowType = trim((string)($row['taqsimot_type'] ?? 'A'));
                        if ($rowType === '') {
                            $rowType = 'A';
                        }
                        $maruzaJami = getMappedTaqsimotSoat($db, $taqsimotSoatMap, $maruzaRejaId, $rowType);
                        $amaliyJami = getMappedTaqsimotSoat($db, $taqsimotSoatMap, $amaliyRejaId, $rowType);
                        $labJami = getMappedTaqsimotSoat($db, $taqsimotSoatMap, $labRejaId, $rowType);
                        $seminarJami = getMappedTaqsimotSoat($db, $taqsimotSoatMap, $seminarRejaId, $rowType);

                ?>
                <tr class="<?= $needsResync ? 'needs-resync' : '' ?>">
                    <td><?= $counter++ ?></td>
                    <td class="left fan-nomi">
                        <?= htmlspecialchars($row['fan_nomi']) ?>
                        <?php if ($rowType === 'M' || !empty($row['is_maxsus'])): ?>
                            <span class="maxsus-badge">Maxsus guruh</span>
                        <?php endif; ?>
                    </td>
                    <td class="left">
                        <?= htmlspecialchars($row['yonalish_code'] . ' - ' . $row['talim_yonalishi']) ?>
                        <?php if ($needsResync): ?>
                            <div class="resync-badge">Qayta taqsimot kerak</div>
                        <?php endif; ?>
                    </td>
                    <td class="guruh-cell"><?= htmlspecialchars($row['guruh_raqami']) ?></td>
                    <td><?= $row['oquv_shakli'] ?></td>
                    <td><?= $row['kurs'] ?></td>
                    <td><?= $row['semestr'] ?></td>
                    <td><?= $row['talabalar_soni'] ?></td>
                    <td><?= $row['patok_soni'] ?></td>
                    <td><?= $row['kattaguruh_soni'] ?></td>
                    <td><?= $row['kichikguruh_soni'] ?></td>
                    <td class="soat-cell  <?= getCellClass($maruzaJami, $row['amalda_maruz']) ?>"
                        data-type="<?= htmlspecialchars($rowType) ?>"
                        data-yuklama-id="<?= $row['maruza_reja_id'] ?: 0 ?>"
                        data-soat-turi="amalda_maruz"
                        data-max-soat="<?= $row['amalda_maruz'] ?>">
                        <?= $row['amalda_maruz'] ?: '' ?>
                        
                    </td>
                    <!-- 🔥 AMALIY -->
                    <td class="soat-cell <?= getCellClass($amaliyJami, $row['amalda_amaliy']) ?>"
                        data-type="<?= htmlspecialchars($rowType) ?>"
                        data-yuklama-id="<?= $row['amaliy_reja_id'] ?: 0 ?>"
                        data-soat-turi="amalda_amaliy"
                        data-max-soat="<?= $row['amalda_amaliy'] ?: 0 ?>">
                        <?= $row['amalda_amaliy'] ?: '' ?>
                        
                    </td>
                    <!-- 🔥 LAB -->
                    <td class="soat-cell <?= getCellClass($labJami, $row['amalda_laboratoriya']) ?>"
                        data-type="<?= htmlspecialchars($rowType) ?>"
                        data-yuklama-id="<?= $row['laboratoriya_reja_id'] ?: 0 ?>"
                        data-soat-turi="amalda_laboratoriya"
                        data-max-soat="<?= $row['amalda_laboratoriya'] ?: 0 ?>">
                        <?= $row['amalda_laboratoriya'] ?: '' ?>
                        
                    </td>
                    <!-- 🔥 SEMINAR -->
                    <td class="soat-cell <?= getCellClass($seminarJami, $row['amalda_seminar']) ?>"
                        data-type="<?= htmlspecialchars($rowType) ?>"
                        data-yuklama-id="<?= $row['seminar_reja_id'] ?: 0 ?>"
                        data-soat-turi="amalda_seminar"
                        data-max-soat="<?= $row['amalda_seminar'] ?: 0 ?>">
                        <?= $row['amalda_seminar'] ?: '' ?>
                        
                    </td>
                    <!-- Reyting -->
                    <td></td>
                    <td></td>
                    <!-- Kurs ishlari -->
                    <td></td>
                    <td></td>
                    <!-- Malakaviy amaliyot -->
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <!-- BMI rahbarligi -->
                    <td></td>
                    
                    <!-- Magistratura -->
                    <td></td>
                    <td></td>
                    <td></td>
                    
                    <!-- Doktorantura -->
                    <td></td>
                    <td></td>
                    <td></td>
                    
                    <!-- Qo'shimcha soatlar -->
                    <td></td>
                    <td></td>
                    <td></td>
                    
                    <!-- JAMI soat -->
                    <td class="total-cell">
                        <?= $row['jami_soat'] ?> 
                    </td>
                </tr>
                <?php 
                    endforeach;
                    foreach ($qoshimcha_oquv_taqsimotlar as $row):
                        $needsResync = !empty($row['needs_resync']);
                        $rowYonalishId = !empty($row['yonalish_id']) ? (int)$row['yonalish_id'] : 0;
                        if (!$needsResync && $rowYonalishId > 0 && !empty($pendingYonalishMap[$rowYonalishId])) {
                            $needsResync = true;
                        }
                        $qoshimchaBaseName = (int)($row['qoshimcha_dars_id'] ?? 0) === 16
                            ? 'YADAK'
                            : (string)($row['fan_nomi'] ?? '');
                        $qoshimchaFanNomi = legacy_qoshimcha_display_name(
                            $qoshimchaBaseName,
                            (int)($row['qoshimcha_dars_id'] ?? 0),
                            (string)($row['subtype_code'] ?? '')
                        );
                ?>
                <tr class="<?= $needsResync ? 'needs-resync' : '' ?>">
                    <td><?= $counter++ ?></td>
                    <td class="left fan-nomi"><?= htmlspecialchars($qoshimchaFanNomi) ?></td>
                    <td class="left">
                        <?= htmlspecialchars($row['yonalish_code'] . ' - ' . $row['talim_yonalishi']) ?>
                        <?php if ($needsResync): ?>
                            <div class="resync-badge">Qayta taqsimot kerak</div>
                        <?php endif; ?>
                    </td>
                    <td class="guruh-cell"><?= htmlspecialchars($row['guruh_raqami']) ?></td>
                    <td><?= $row['oquv_shakli'] ?></td>
                    <td><?= $row['kurs'] ?></td>
                    <td><?= $row['semestr'] ?></td>
                    <td><?= $row['talabalar_soni'] ?></td>
                    <td><?= $row['patok_soni'] ?></td>
                    <td><?= $row['kattaguruh_soni'] ?></td>
                    <td><?= $row['kichikguruh_soni'] ?></td>
                    
                    <!-- Amalda bajarilgan -->
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <!-- Reyting nazorati -->
                    <td class="soat-cell"
                        data-type="Q"
                        data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?: 0?>"
                        data-soat-turi="oraliq_nazorat"
                        data-max-soat="<?= $row['oraliq_nazorat'] ?: 0 ?>">
                        <?= $row['oraliq_nazorat'] ?: '' ?>
                    </td>
                    <td class="soat-cell"
                        data-type="Q"
                        data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?:0 ?>"
                        data-soat-turi="yakuniy_nazorat"
                        data-max-soat="<?= $row['yakuniy_nazorat'] ?: 0 ?>">
                        <?= $row['yakuniy_nazorat'] ?: '' ?>
                    </td>
                     <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="kurs_ishi" data-max-soat="<?= $row['kurs_ishi'] ?>">
                        <?= $row['kurs_ishi'] > 0 ? $row['kurs_ishi'] : '' ?>
                    </td>
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="kurs_loyiha" data-max-soat="<?= $row['kurs_loyiha'] ?>">
                        <?= $row['kurs_loyiha'] > 0 ? $row['kurs_loyiha'] : '' ?>
                    </td>
                    <!-- Malakaviy amaliyot -->
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="oquv_ped_amaliyot" data-max-soat="<?= $row['oquv_ped_amaliyot'] ?>">
                        <?= $row['oquv_ped_amaliyot'] > 0 ? $row['oquv_ped_amaliyot'] : '' ?>
                    </td>
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="uzluksiz_malakaviy" data-max-soat="<?= $row['uzluksiz_malakaviy'] ?>">
                        <?= $row['uzluksiz_malakaviy'] > 0 ? $row['uzluksiz_malakaviy'] : '' ?>
                    </td>
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="dala_amaliyoti_otm" data-max-soat="<?= $row['dala_amaliyoti_otm'] ?>">
                        <?= $row['dala_amaliyoti_otm'] > 0 ? $row['dala_amaliyoti_otm'] : '' ?>
                    </td>
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="dala_amaliyoti_tashqarida" data-max-soat="<?= $row['dala_amaliyoti_tashqarida'] ?>">
                        <?= $row['dala_amaliyoti_tashqarida'] > 0 ? $row['dala_amaliyoti_tashqarida'] : '' ?>
                    </td>
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="ishlab_chiqarish" data-max-soat="<?= $row['ishlab_chiqarish'] ?>">
                        <?= $row['ishlab_chiqarish'] > 0 ? $row['ishlab_chiqarish'] : '' ?>
                    </td>
                    <!-- BMI rahbarligi -->
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="bmi_rahbarligi" data-max-soat="<?= $row['bmi_rahbarligi'] ?>">
                        <?= $row['bmi_rahbarligi'] > 0 ? $row['bmi_rahbarligi'] : '' ?>
                    </td>
                    <!-- Magistratura -->
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="mag_ilmiy_tadqiqot" data-max-soat="0">
                        
                    </td>
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="mag_ilmiy_pedagogik" data-max-soat="0">
                    </td>
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="mag_ilmiy_stajirovka" data-max-soat="0">
                    </td>
                    <!-- Doktorantura -->
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="tayanch_doktorantura" data-max-soat="<?= $row['tayanch_doktorantura'] ?? 0 ?>">
                        <?= ($row['tayanch_doktorantura'] ?? 0) > 0 ? ($row['tayanch_doktorantura'] ?? 0) : '' ?>
                    </td>
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="katta_ilmiy_tadqiqotchi" data-max-soat="<?= $row['katta_ilmiy_tadqiqotchi'] ?? 0 ?>">
                        <?= ($row['katta_ilmiy_tadqiqotchi'] ?? 0) > 0 ? ($row['katta_ilmiy_tadqiqotchi'] ?? 0) : '' ?>
                    </td>
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="stajyor_tadqiqotchi" data-max-soat="<?= $row['stajyor_tadqiqotchi'] ?? 0 ?>">
                        <?= ($row['stajyor_tadqiqotchi'] ?? 0) > 0 ? ($row['stajyor_tadqiqotchi'] ?? 0) : '' ?>
                    </td>
                    
                    <!-- Qo'shimcha soatlar -->
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="ochiq_dars" data-max-soat="<?= $row['ochiq_dars'] ?? 0 ?>">
                        <?= ($row['ochiq_dars'] ?? 0) > 0 ? ($row['ochiq_dars'] ?? 0) : '' ?>
                    </td>
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="yadak" data-max-soat="<?= $row['yadak'] ?? 0 ?>">
                        <?= ($row['yadak'] ?? 0) > 0 ? ($row['yadak'] ?? 0) : '' ?>
                    </td>
                    <td class="soat-cell" data-type='Q' data-yuklama-id="<?= $row['qoshimcha_reja_id'] ?>" data-soat-turi="boshqa_soatlar" data-max-soat="<?= $row['boshqa_soatlar'] ?? 0 ?>">
                        <?= ($row['boshqa_soatlar'] ?? 0) > 0 ? ($row['boshqa_soatlar'] ?? 0) : '' ?>
                    </td>
                    <!-- JAMI soat -->
                    <td class="total-cell">
                        <?= $row['jami_soat'] ?> 
                    </td>
                </tr>
                <?php 
                    endforeach;
                else: ?>
                <tr>
                    <td colspan="37" style="text-align: center; padding: 20px;">
                        <i class="fas fa-info-circle"></i> Ma'lumotlar mavjud emas
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
