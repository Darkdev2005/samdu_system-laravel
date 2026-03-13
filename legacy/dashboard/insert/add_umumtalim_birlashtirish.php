<?php
    // Izoh: Birlashtiriladigan fanini yo'nalish+semestrga biriktirish yozuvini saqlash.
    include_once '../config.php';
    header('Content-Type: application/json');
    $db = new Database();

    // Izoh: fan_ids[] = fanlar jadvalidagi IDlar (source_fan_id bo'lib yoziladi).
    $fanIdsRaw = $_POST['fan_ids'] ?? [];
    $masterFanId = (int) ($_POST['master_fan_id'] ?? 0);
    $semestrIdsRaw = $_POST['semestr_ids'] ?? [];

    if (!is_array($fanIdsRaw) || !is_array($semestrIdsRaw) || count($fanIdsRaw) === 0 || count($semestrIdsRaw) === 0) {
        echo json_encode(['success' => false, 'message' => 'Yo\'nalish+semestr va fan tanlanmagan']);
        return;
    }

    if (count($fanIdsRaw) !== count($semestrIdsRaw)) {
        echo json_encode(['success' => false, 'message' => 'Biriktirish ma\'lumotlari to\'liq emas']);
        return;
    }

    // Izoh: Birinchi tanlangan fan (fanlar jadvalidagi ID) master bo'ladi.
    $rows = [];
    $seenSemestrIds = [];

    for ($i = 0; $i < count($semestrIdsRaw); $i++) {
        $semestrId = (int) ($semestrIdsRaw[$i] ?? 0);
        $sourceFanId = (int) ($fanIdsRaw[$i] ?? 0);

        if ($semestrId <= 0 && $sourceFanId <= 0) {
            continue;
        }

        if ($semestrId <= 0 || $sourceFanId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Yo\'nalish+semestr va fan tanlanishi shart']);
            return;
        }

        if (isset($seenSemestrIds[$semestrId])) {
            echo json_encode(['success' => false, 'message' => 'Bir xil yo\'nalish+semestr qayta tanlangan']);
            return;
        }
        $seenSemestrIds[$semestrId] = true;

        if ($masterFanId <= 0) {
            $masterFanId = $sourceFanId;
        }

        $rows[] = [
            'semestr_id' => $semestrId,
            'source_fan_id' => $sourceFanId
        ];
    }

    if ($masterFanId <= 0 || count($rows) === 0) {
        echo json_encode(['success' => false, 'message' => 'Biriktirish ma\'lumotlari to\'liq emas']);
        return;
    }

    // Izoh: Master fan ID fanlar jadvalidan keladi. Uni umumtalim_fanlar jadvalidagi IDga map qilamiz.
    $masterFanRow = $db->get_data_by_table('fanlar', [
        'id' => (int) $masterFanId
    ]);

    if (!$masterFanRow) {
        echo json_encode(['success' => false, 'message' => 'Master fan topilmadi']);
        return;
    }

    $semestrRow = $db->get_data_by_table('semestrlar', [
        'id' => (int) ($masterFanRow['semestr_id'] ?? 0)
    ]);
    $semestrNum = (int) ($semestrRow['semestr'] ?? 0);

    $masterUmumtalim = null;
    if ($semestrNum > 0) {
        // Izoh: Umumta'lim fanlar bazasida (fan_code+fan_name+semestr) bo'yicha topamiz.
        $masterUmumtalim = $db->get_data_by_table('umumtalim_fanlar', [
            'fan_code'   => $masterFanRow['fan_code'],
            'fan_name'   => $masterFanRow['fan_name'],
            'semestr'    => $semestrNum
        ]);
    }

    if (!$masterUmumtalim) {
        echo json_encode(['success' => false, 'message' => 'Birlashtiriladigan fan topilmadi']);
        return;
    }
    $masterUmumtalimId = (int) ($masterUmumtalim['id'] ?? 0);
    if ($masterUmumtalimId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Birlashtiriladigan fan topilmadi']);
        return;
    }

    $masterFanCode = trim((string) ($masterFanRow['fan_code'] ?? ''));
    $masterFanName = trim((string) ($masterFanRow['fan_name'] ?? ''));

    if ($masterFanCode === '' || $masterFanName === '') {
        echo json_encode(['success' => false, 'message' => 'Master fan ma\'lumoti to\'liq emas']);
        return;
    }

    foreach ($rows as $row) {
        $semestrId = (int) $row['semestr_id'];
        $semestr = $db->get_data_by_table('semestrlar', [
            'id' => (int) $semestrId
        ]);

        if (!$semestr) {
            continue;
        }
        $semestrNumForRow = (int) ($semestr['semestr'] ?? 0);
        if ($semestrNumForRow !== $semestrNum) {
            echo json_encode(['success' => false, 'message' => 'Faqat bir xil semestr raqami ichida biriktirish mumkin']);
            return;
        }

        $yonalishId = (int) ($semestr['yonalish_id'] ?? 0);
        if ($yonalishId <= 0) {
            continue;
        }

        $sourceFanId = (int) $row['source_fan_id']; // fanlar jadvalidagi ID
        $sourceFanRow = $db->get_data_by_table('fanlar', [
            'id' => $sourceFanId
        ]);
        if (!$sourceFanRow) {
            echo json_encode(['success' => false, 'message' => 'Tanlangan fan topilmadi']);
            return;
        }

        $sourceSemestrId = (int) ($sourceFanRow['semestr_id'] ?? 0);
        if ($sourceSemestrId !== $semestrId) {
            echo json_encode(['success' => false, 'message' => 'Fan tanlovi yo\'nalish+semestrga mos emas']);
            return;
        }

        $sourceCode = trim((string) ($sourceFanRow['fan_code'] ?? ''));
        $sourceName = trim((string) ($sourceFanRow['fan_name'] ?? ''));
        if ($sourceCode !== $masterFanCode || $sourceName !== $masterFanName) {
            echo json_encode(['success' => false, 'message' => 'Faqat bir xil fan (kod+nom) biriktirilishi mumkin']);
            return;
        }

        // Izoh: umumtalim_fan_id faqat master (umumtalim_fanlar ID), source_fan_id esa fanlar jadvalidagi ID.
        $db->query("
            INSERT INTO umumtalim_fan_biriktirish (umumtalim_fan_id, source_fan_id, yonalish_id, semestr_id)
            VALUES ($masterUmumtalimId, $sourceFanId, $yonalishId, $semestrId)
            ON DUPLICATE KEY UPDATE source_fan_id = $sourceFanId
        ");
    }

    echo json_encode(['success' => true, 'message' => 'Birlashtiriladigan fanlar yo\'nalishlarga biriktirildi']);
?>
