<?php
include_once '../config.php';
$db = new Database();

$filters = [];
if (isset($_POST['kafedra_id']) && !empty($_POST['kafedra_id'])) {
    $filters['kafedra_id'] = (int)$_POST['kafedra_id'];
}
if (isset($_POST['semestr']) && !empty($_POST['semestr'])) {
    $filters['semestr'] = (int)$_POST['semestr'];
}
if (isset($_POST['oquv_yil_start']) && !empty($_POST['oquv_yil_start'])) {
    $filters['oquv_yil_start'] = (int)$_POST['oquv_yil_start'];
}
if (isset($_POST['semestr_turi']) && !empty($_POST['semestr_turi'])) {
    $filters['semestr_turi'] = trim($_POST['semestr_turi']);
}
if (isset($_POST['yonalish_id']) && !empty($_POST['yonalish_id'])) {
    $filters['yonalish_id'] = (int)$_POST['yonalish_id'];
}

$oquv_yuklamalar = $db->get_oquv_yuklamalar($filters);
$qoshimcha_yuklamalar = $db->get_qoshimcha_oquv_yuklamalar($filters);
?>

<div class="table-container-wrapper">
    <div class="zoom-controls">
        <button class="zoom-btn" onclick="zoomOut()" title="Kichiklashtirish">-</button>
        <button class="zoom-btn" onclick="resetZoom()" title="Asl o'lcham">100%</button>
        <button class="zoom-btn" onclick="zoomIn()" title="Kattalashtirish">+</button>
        <div class="zoom-level" id="zoomLevel">100%</div>
    </div>
    
    <div class="table-title">
        O'ZBEKISTON RESPUBLIKASI OLIY TA'LIM MUASSASASI<br>
        O'QITUVCHILARNING O'QUV YUKLAMASI
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

                    <th colspan="8">O'quv soatlari</th>
                    <th colspan="2">Reyting nazorati</th>

                    <th rowspan="3" class="vertical">Kurs ishi va himoyasi</th>
                    <th rowspan="3" class="vertical">Kurs loyihasi va himoyasi</th>

                    <th colspan="5">Malakaviy amaliyot</th>

                    <th rowspan="3" class="vertical">BMI rahbarligi</th>

                    <th colspan="3">Magistratura</th>
                    <th colspan="3">Doktorantura</th>

                    <th rowspan="3" class="vertical">Ochiq dars</th>
                    <th rowspan="3" class="vertical">Yakuniy davlat attestatsiyasi</th>
                    <th rowspan="3" class="vertical">Boshqa soatlar</th>
                    <th rowspan="3" class="vertical">JAMI</th>
                    <th rowspan="3" class="vertical">Kafedra</th>
                </tr>

                <tr>
                    <th colspan="4">O'quv reja bo'yicha</th>
                    <th colspan="4">Amalda bajarilgan</th>

                    <th rowspan="2" class="vertical">Oraliq nazorat</th>
                    <th rowspan="2" class="vertical">Yakuniy nazorat</th>

                    <th rowspan="2" class="vertical">O'quv-pedagogik amaliyot</th>
                    <th rowspan="2" class="vertical">Uzluksiz malakaviy amaliyot</th>
                    <th rowspan="2" class="vertical">Dala amaliyoti</th>
                    <th rowspan="2" class="vertical">Dala amaliyoti (OTM)</th>
                    <th rowspan="2" class="vertical">Ishlab chiqarish amaliyoti</th>

                    <th rowspan="2" class="vertical">Ilmiy-tadqiqot ishi</th>
                    <th rowspan="2" class="vertical">Ilmiy-pedagogik ish</th>
                    <th rowspan="2" class="vertical">Ilmiy stajirovka</th>

                    <th rowspan="2" class="vertical">Tayanch doktorantura</th>
                    <th rowspan="2" class="vertical">Katta ilmiy tadqiqotchi</th>
                    <th rowspan="2" class="vertical">Stajyor-tadqiqotchi</th>
                </tr>

                <tr>
                    <th class="vertical">Ma'ruza</th>
                    <th class="vertical">Amaliy</th>
                    <th class="vertical">Laboratoriya</th>
                    <th class="vertical">Seminar</th>

                    <th class="vertical">Ma'ruza</th>
                    <th class="vertical">Amaliy</th>
                    <th class="vertical">Laboratoriya</th>
                    <th class="vertical">Seminar</th>
                </tr>
            </thead>
            
            <tbody>
                <?php 
                $counter = 1;
                $totals = [
                    'reja_maruza' => 0, 'reja_amaliy' => 0, 'reja_lab' => 0, 'reja_seminar' => 0,
                    'oraliq' => 0, 'yakuniy' => 0,
                    'kurs_ishi' => 0, 'kurs_loyiha' => 0,
                    'oquv_ped' => 0, 'uzluksiz' => 0, 'dala_otm' => 0, 'dala_tash' => 0, 'ishlab' => 0,
                    'bmi' => 0,
                    'mag_ilmiy' => 0, 'mag_ped' => 0, 'mag_staj' => 0,
                    'dok_tayanch' => 0, 'dok_katta' => 0, 'dok_staj' => 0,
                    'ochiq' => 0, 'yadak' => 0, 'boshqa' => 0,
                    'jami' => 0
                ];
                if (!empty($oquv_yuklamalar)):
                    foreach ($oquv_yuklamalar as $row): 
                        $talaba = (int)($row['talabalar_soni'] ?? 0);
                        $auditoriyaSoat = (float)($row['maruza_soat'] ?? 0)
                            + (float)($row['amaliy_soat'] ?? 0)
                            + (float)($row['laboratoriya_soat'] ?? 0)
                            + (float)($row['seminar_soat'] ?? 0);
                        $shakl = mb_strtolower(trim($row['oquv_shakli'] ?? ''), 'UTF-8');
                        $isExternal = (strpos($shakl, 'sirtqi') !== false) || (strpos($shakl, 'masof') !== false) || (strpos($shakl, 'kechki') !== false);

                        $oraliq = 0;
                        if (!$isExternal && $talaba > 0) {
                            if ($auditoriyaSoat >= 60) {
                                $oraliq = round($talaba * 0.4);
                            } elseif ($auditoriyaSoat >= 30) {
                                $oraliq = round($talaba * 0.2);
                            }
                        }
                        $yakuniy = (!$isExternal && $talaba > 0) ? round($talaba * 0.3) : 0;
                        $kursIshi = $talaba > 0 ? round($talaba * 2.4) : 0;
                        $kursLoyiha = $talaba > 0 ? round($talaba * 3.6) : 0;
                        $uzluksiz = $talaba > 0 ? round($talaba * ($isExternal ? 0.4 : 2)) : 0;

                        $jamiAll = (float)($row['jami_soat'] ?? 0) + $oraliq + $yakuniy + $kursIshi + $kursLoyiha + $uzluksiz;

                        $totals['reja_maruza'] += (float)($row['maruza_soat'] ?? 0);
                        $totals['reja_amaliy'] += (float)($row['amaliy_soat'] ?? 0);
                        $totals['reja_lab'] += (float)($row['laboratoriya_soat'] ?? 0);
                        $totals['reja_seminar'] += (float)($row['seminar_soat'] ?? 0);
                        $totals['oraliq'] += $oraliq;
                        $totals['yakuniy'] += $yakuniy;
                        $totals['kurs_ishi'] += $kursIshi;
                        $totals['kurs_loyiha'] += $kursLoyiha;
                        $totals['uzluksiz'] += $uzluksiz;
                        $totals['jami'] += $jamiAll;
                ?>
                <tr>
                    <td><?= $counter++ ?></td>
                    <td class="left"><?= htmlspecialchars($row['fan_name']) ?></td>
                    <td class="left">
                        <?php
                            $isBirlashtirilgan = !empty($row['is_birlashtirilgan']) && !empty($row['biriktirilgan_yonalishlar']);
                            if ($isBirlashtirilgan) {
                                $bCode = trim((string)($row['biriktirilgan_yonalish_code'] ?? ''));
                                $bName = trim((string)($row['biriktirilgan_yonalishlar'] ?? ''));
                                if ($bCode !== '' && $bName !== '') {
                                    $talimYonalishiText = $bCode . ' - ' . $bName;
                                } elseif ($bName !== '') {
                                    $talimYonalishiText = $bName;
                                } else {
                                    $talimYonalishiText = $bCode;
                                }
                            } else {
                                $talimYonalishiText = trim((string)($row['yonalish_code'] ?? '')) . ' - ' . trim((string)($row['talim_yonalishi'] ?? ''));
                            }
                        ?>
                        <?= htmlspecialchars($talimYonalishiText) ?>
                    </td>
                    <td><?= htmlspecialchars($row['guruh_raqami']) ?></td>
                    <td><?= $row['oquv_shakli'] ?></td>
                    <td><?= $row['kurs'] ?></td>
                    <td><?= $row['semestr'] ?></td>
                    <td><?= $row['talabalar_soni'] ?></td>
                    <td><?= $row['patok_soni'] ?></td>
                    <td><?= $row['kattaguruh_soni'] ?></td>
                    <td><?= $row['kichikguruh_soni'] ?></td>
                    <!-- O'quv reja bo'yicha -->
                    <td><?= $row['maruza_soat'] ?></td>
                    <td><?= $row['amaliy_soat'] ?></td>
                    <td><?= $row['laboratoriya_soat'] ?></td>
                    <td><?= $row['seminar_soat'] ?></td>
                    <!-- Amalda bajarilgan -->
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <!-- Reyting nazorati -->
                    <td><?= $oraliq ?: '' ?></td>
                    <td><?= $yakuniy ?: '' ?></td>
                    <!-- Kurs ishlari -->
                    <td><?= $kursIshi ?: '' ?></td>
                    <td><?= $kursLoyiha ?: '' ?></td>
                    <!-- Malakaviy amaliyot -->
                    <td></td>
                    <td><?= $uzluksiz ?: '' ?></td>
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
                    <td><?= $jamiAll ?></td>
                    <td class="left"><?= htmlspecialchars($row['kafedra_nomi'] ?? '') ?></td>
                </tr>
                <?php 
                    endforeach;
                    foreach ($qoshimcha_yuklamalar as $row):
                        $totals['oraliq'] += (float)($row['oraliq_nazorat'] ?? 0);
                        $totals['yakuniy'] += (float)($row['yakuniy_nazorat'] ?? 0);
                        $totals['kurs_ishi'] += (float)($row['kurs_ishi'] ?? 0);
                        $totals['kurs_loyiha'] += (float)($row['kurs_loyiha'] ?? 0);
                        $totals['oquv_ped'] += (float)($row['oquv_ped_amaliyot'] ?? 0);
                        $totals['uzluksiz'] += (float)($row['uzluksiz_malakaviy'] ?? 0);
                        $totals['dala_otm'] += (float)($row['dala_amaliyoti_otm'] ?? 0);
                        $totals['dala_tash'] += (float)($row['dala_amaliyoti_tashqarida'] ?? 0);
                        $totals['ishlab'] += (float)($row['ishlab_chiqarish'] ?? 0);
                        $totals['bmi'] += (float)($row['bmi_rahbarligi'] ?? 0);
                        $totals['mag_ilmiy'] += (float)($row['ilmiy_tadqiqot_ishi'] ?? 0);
                        $totals['mag_ped'] += (float)($row['ilmiy_pedagogik_ishi'] ?? 0);
                        $totals['mag_staj'] += (float)($row['ilmiy_stajirovka'] ?? 0);
                        $totals['dok_tayanch'] += (float)($row['tayanch_doktorantura'] ?? 0);
                        $totals['dok_katta'] += (float)($row['katta_ilmiy_tadqiqotchi'] ?? 0);
                        $totals['dok_staj'] += (float)($row['stajyor_tadqiqotchi'] ?? 0);
                        $totals['ochiq'] += (float)($row['ochiq_dars'] ?? 0);
                        $totals['yadak'] += (float)($row['yadak'] ?? 0);
                        $totals['boshqa'] += (float)($row['boshqa_soatlar'] ?? 0);
                        $totals['jami'] += (float)($row['jami_soat'] ?? 0);
                ?>
                <tr>
                    <td><?= $counter++ ?></td>
                    <td class="left"><?= htmlspecialchars($row['fan_nomi']) ?></td>
                    <td class="left"><?= htmlspecialchars($row['yonalish_code'] . ' - ' . $row['talim_yonalishi']) ?></td>
                    <td><?= htmlspecialchars($row['guruh_raqami']) ?></td>
                    <td><?= $row['oquv_shakli'] ?></td>
                    <td><?= $row['kurs'] ?></td>
                    <td><?= $row['semestr'] ?></td>
                    <td><?= $row['talabalar_soni'] ?></td>
                    <td><?= $row['patok_soni'] ?></td>
                    <td><?= $row['kattaguruh_soni'] ?></td>
                    <td><?= $row['kichikguruh_soni'] ?></td>
                    <!-- O'quv reja bo'yicha -->
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <!-- Amalda bajarilgan -->
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <!-- Reyting nazorati -->
                    <td><?= $row['oraliq_nazorat'] ?></td>
                    <td><?= $row['yakuniy_nazorat'] ?></td>
                    <!-- Kurs ishlari -->
                    <td><?= ($row['kurs_ishi'] ?? 0) ?: '' ?></td>
                    <td><?= ($row['kurs_loyiha'] ?? 0) ?: '' ?></td>
                    <!-- Malakaviy amaliyot -->
                    <td><?= $row['oquv_ped_amaliyot'] ?></td>
                    <td><?= $row['uzluksiz_malakaviy'] ?></td>
                    <td><?= $row['dala_amaliyoti_otm'] ?></td>
                    <td><?= $row['dala_amaliyoti_tashqarida'] ?></td>
                    <td><?= $row['ishlab_chiqarish'] ?></td>
                    <!-- BMI rahbarligi -->
                    <td><?= $row['bmi_rahbarligi'] ?></td>
                    <!-- Magistratura -->
                    <td><?= $row['ilmiy_tadqiqot_ishi'] ?></td>
                    <td><?= $row['ilmiy_pedagogik_ishi'] ?></td>
                    <td><?= $row['ilmiy_stajirovka'] ?></td>
                    <!-- Doktorantura -->
                    <td><?= $row['tayanch_doktorantura']  ?></td>
                    <td><?= $row['katta_ilmiy_tadqiqotchi']  ?></td>
                    <td><?= $row['stajyor_tadqiqotchi']  ?></td>
                    
                    <td><?= $row['ochiq_dars'] ?></td>
                    <td><?= $row['yadak'] ?></td>
                    <td><?= $row['boshqa_soatlar'] ?></td>
                    
                    <!-- JAMI soat -->
                    <td><?= $row['jami_soat'] ?></td>
                    <td class="left"><?= htmlspecialchars($row['kafedra_nomi'] ?? '') ?></td>
                </tr>
                <?php 
                    endforeach;
                ?>
                <tr class="total-row">
                    <td colspan="11" class="left"><strong>Jami</strong></td>
                    <!-- O'quv reja bo'yicha -->
                    <td><strong><?= $totals['reja_maruza'] ?></strong></td>
                    <td><strong><?= $totals['reja_amaliy'] ?></strong></td>
                    <td><strong><?= $totals['reja_lab'] ?></strong></td>
                    <td><strong><?= $totals['reja_seminar'] ?></strong></td>
                    <!-- Amalda bajarilgan -->
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <!-- Reyting nazorati -->
                    <td><strong><?= $totals['oraliq'] ?></strong></td>
                    <td><strong><?= $totals['yakuniy'] ?></strong></td>
                    <!-- Kurs ishlari -->
                    <td><strong><?= $totals['kurs_ishi'] ?></strong></td>
                    <td><strong><?= $totals['kurs_loyiha'] ?></strong></td>
                    <!-- Malakaviy amaliyot -->
                    <td><strong><?= $totals['oquv_ped'] ?></strong></td>
                    <td><strong><?= $totals['uzluksiz'] ?></strong></td>
                    <td><strong><?= $totals['dala_tash'] ?></strong></td>
                    <td><strong><?= $totals['dala_otm'] ?></strong></td>
                    <td><strong><?= $totals['ishlab'] ?></strong></td>
                    <!-- BMI rahbarligi -->
                    <td><strong><?= $totals['bmi'] ?></strong></td>
                    <!-- Magistratura -->
                    <td><strong><?= $totals['mag_ilmiy'] ?></strong></td>
                    <td><strong><?= $totals['mag_ped'] ?></strong></td>
                    <td><strong><?= $totals['mag_staj'] ?></strong></td>
                    <!-- Doktorantura -->
                    <td><strong><?= $totals['dok_tayanch'] ?></strong></td>
                    <td><strong><?= $totals['dok_katta'] ?></strong></td>
                    <td><strong><?= $totals['dok_staj'] ?></strong></td>
                    <!-- Qo'shimcha soatlar -->
                    <td><strong><?= $totals['ochiq'] ?></strong></td>
                    <td><strong><?= $totals['yadak'] ?></strong></td>
                    <td><strong><?= $totals['boshqa'] ?></strong></td>
                    <!-- JAMI soat -->
                    <td><strong><?= $totals['jami'] ?></strong></td>
                    <td></td>
                </tr>
                <?php else: ?>
                <tr>
                    <td colspan="40" style="text-align: center; padding: 20px;">
                        <i class="fas fa-info-circle"></i> Ma'lumotlar mavjud emas
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
