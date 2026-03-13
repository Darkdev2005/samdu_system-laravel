<?php
    // Izoh: Chet tili fanlarini yo'nalish + semestr bo'yicha guruhlab biriktirish.
    include_once '../config.php';
    header('Content-Type: application/json');
    $db = new Database();

    $fanIds = $_POST['fan_ids'] ?? [];
    $semestrIds = $_POST['semestr_ids'] ?? [];
    if (!is_array($fanIds) || !is_array($semestrIds) ||
        count($fanIds) === 0 || count($semestrIds) === 0) {
        echo json_encode(['success' => false, 'message' => 'Ma\'lumotlar to\'liq emas']);
        return;
    }

    if (count($fanIds) !== count($semestrIds)) {
        echo json_encode(['success' => false, 'message' => 'Biriktirish ma\'lumotlari to\'liq emas']);
        return;
    }

    $inserted = 0;
    $entries = [];
    $masterByKey = [];

    foreach ($semestrIds as $i => $semestrIdRaw) {
        $semestrId = (int) $semestrIdRaw;
        $fanId = (int) ($fanIds[$i] ?? 0);

        if ($semestrId <= 0 || $fanId <= 0) {
            continue;
        }

        $semestr = $db->get_data_by_table('semestrlar', [
            'id' => $semestrId
        ]);
        if (!$semestr) {
            continue;
        }

        $yonalishId = (int) ($semestr['yonalish_id'] ?? 0);
        if ($yonalishId <= 0) {
            continue;
        }

        $fanRow = $db->get_data_by_table('fanlar', [
            'id' => $fanId
        ]);
        if (!$fanRow) {
            continue;
        }

        $fanCode = trim($fanRow['fan_code'] ?? '');
        $fanName = trim($fanRow['fan_name'] ?? '');
        $semestrNum = (int) ($semestr['semestr'] ?? 0);
        if ($fanCode === '' || $fanName === '' || $semestrNum <= 0) {
            continue;
        }

        // Izoh: Bir xil fan_code + fan_name + semestr bo'lsa bitta masterga birlashtiriladi.
        $key = $fanCode . '|' . $fanName . '|' . $semestrNum;
        if (!isset($masterByKey[$key])) {
            $masterByKey[$key] = $fanId; // master fan_id (birinchi tanlangan)
        }

        $entries[] = [
            'key' => $key,
            'fan_id' => $fanId,
            'semestr_id' => $semestrId,
            'yonalish_id' => $yonalishId
        ];
    }

    foreach ($entries as $entry) {
        $masterFanId = (int) ($masterByKey[$entry['key']] ?? 0);
        $fanId = (int) $entry['fan_id'];
        $semestrId = (int) $entry['semestr_id'];
        $yonalishId = (int) $entry['yonalish_id'];

        if ($masterFanId <= 0 || $fanId <= 0 || $semestrId <= 0 || $yonalishId <= 0) {
            continue;
        }

        // Izoh: Har bir tanlov alohida row bo'lib yoziladi. Master fan_id birinchi tanlangan bo'ladi.
        $db->query("
            DELETE FROM chet_tili_guruhlar
            WHERE fan_id = $masterFanId AND semestr_id = $semestrId AND yonalish_id = $yonalishId
        ");

        $ok = $db->query("
            INSERT IGNORE INTO chet_tili_guruhlar (fan_id, yonalish_id, semestr_id, yonalish_ids, source_fan_ids, guruh_no)
            VALUES ($masterFanId, $yonalishId, $semestrId, '$yonalishId', '$fanId', 1)
        ");
        if ($ok) {
            $inserted++;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "Chet tili fanlari biriktirildi"
    ]);
?>
