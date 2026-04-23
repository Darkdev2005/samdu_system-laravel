<?php
    // Izoh: Birlashtiriladigan fanini yo'nalish+semestrga biriktirish yozuvini saqlash.
    include_once '../config.php';
    header('Content-Type: application/json');
    $db = new Database();

    // Izoh: fan_ids[] = fanlar jadvalidagi IDlar (source_fan_id bo'lib yoziladi).
    $fanIdsRaw = $_POST['fan_ids'] ?? [];
    $masterFanId = (int) ($_POST['master_fan_id'] ?? 0);
    $semestrIdsRaw = $_POST['semestr_ids'] ?? [];
    $guruhIdsRaw = $_POST['guruh_ids'] ?? [];
    $mergeByNameOnly = ((int)($_POST['merge_by_name_only'] ?? 0) === 1);

    $normalizeFanName = static function (string $value): string {
        $value = trim($value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        if (function_exists('mb_strtolower')) {
            return (string) mb_strtolower($value, 'UTF-8');
        }
        return strtolower($value);
    };

    if (!is_array($fanIdsRaw) || !is_array($semestrIdsRaw) || !is_array($guruhIdsRaw) || count($fanIdsRaw) === 0 || count($semestrIdsRaw) === 0 || count($guruhIdsRaw) === 0) {
        echo json_encode(['success' => false, 'message' => 'Yo\'nalish+semestr, fan va guruh tanlanmagan']);
        return;
    }

    if (count($fanIdsRaw) !== count($semestrIdsRaw) || count($fanIdsRaw) !== count($guruhIdsRaw)) {
        echo json_encode(['success' => false, 'message' => 'Biriktirish ma\'lumotlari to\'liq emas']);
        return;
    }

    // Izoh: Birinchi tanlangan fan (fanlar jadvalidagi ID) master bo'ladi.
    $rows = [];
    $seenItems = [];

    for ($i = 0; $i < count($semestrIdsRaw); $i++) {
        $semestrId = (int) ($semestrIdsRaw[$i] ?? 0);
        $sourceFanId = (int) ($fanIdsRaw[$i] ?? 0);
        $guruhId = (int) ($guruhIdsRaw[$i] ?? 0);

        if ($semestrId <= 0 && $sourceFanId <= 0 && $guruhId <= 0) {
            continue;
        }

        if ($semestrId <= 0 || $sourceFanId <= 0 || $guruhId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Yo\'nalish+semestr, fan va guruh tanlanishi shart']);
            return;
        }

        $seenKey = $sourceFanId . ':' . $guruhId;
        if (isset($seenItems[$seenKey])) {
            echo json_encode(['success' => false, 'message' => 'Bir xil fan+guruh qayta tanlangan']);
            return;
        }
        $seenItems[$seenKey] = true;

        if ($masterFanId <= 0) {
            $masterFanId = $sourceFanId;
        }

        $rows[] = [
            'semestr_id' => $semestrId,
            'source_fan_id' => $sourceFanId,
            'guruh_id' => $guruhId
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

    $masterFanCode = trim((string) ($masterFanRow['fan_code'] ?? ''));
    $masterFanName = trim((string) ($masterFanRow['fan_name'] ?? ''));
    $masterFanNameNorm = $normalizeFanName($masterFanName);
    $masterKafedraId = (int) ($masterFanRow['kafedra_id'] ?? 0);

    if ((!$mergeByNameOnly && ($masterFanCode === '' || $masterFanName === '')) || ($mergeByNameOnly && $masterFanName === '')) {
        echo json_encode(['success' => false, 'message' => 'Master fan ma\'lumoti to\'liq emas']);
        return;
    }
    if ($semestrNum <= 0) {
        echo json_encode(['success' => false, 'message' => 'Master fan semestri noto\'g\'ri']);
        return;
    }

    // Izoh: Ushbu sahifada foydalanuvchi tanlagan birinchi fan alohida master bo'lishi kerak.
    // Shu sabab fan nomi bo'yicha mavjud masterlarni qidirib ulab yubormaymiz,
    // aks holda alohida biriktirishlar bitta umumta'lim ID ostiga qo'shilib ketadi.
    if ($mergeByNameOnly) {
        $masterUmumtalim = null;
    } else {
        $masterUmumtalim = $db->get_data_by_table('umumtalim_fanlar', [
            'fan_code'   => $masterFanCode,
            'fan_name'   => $masterFanName,
            'semestr'    => $semestrNum
        ]);
    }

    if (!$masterUmumtalim) {
        $insertId = (int) $db->insert('umumtalim_fanlar', [
            'fan_code' => $masterFanCode,
            'fan_name' => $masterFanName,
            'kafedra_id' => $masterKafedraId,
            'semestr' => $semestrNum
        ]);

        if ($insertId > 0) {
            $masterUmumtalim = ['id' => $insertId];
        } else {
            if ($mergeByNameOnly) {
                // Izoh: Insert muvaffaqiyatsiz bo'lsa shu sessiyadagi aynan shu master satrini topishga urinib ko'ramiz.
                $masterUmumtalim = null;
            } else {
                $masterUmumtalim = $db->get_data_by_table('umumtalim_fanlar', [
                    'fan_code'   => $masterFanCode,
                    'fan_name'   => $masterFanName,
                    'semestr'    => $semestrNum
                ]);
            }
        }
    }

    $masterUmumtalimId = (int) ($masterUmumtalim['id'] ?? 0);
    if ($masterUmumtalimId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Birlashtiriladigan fanni tayyorlashda xatolik']);
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
        $guruhId = (int) $row['guruh_id'];
        $guruh = $db->get_data_by_table('guruhlar', [
            'id' => $guruhId
        ]);
        if (!$guruh || (int)($guruh['yonalish_id'] ?? 0) !== $yonalishId) {
            echo json_encode(['success' => false, 'message' => 'Tanlangan guruh yo\'nalishga mos emas']);
            return;
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
        if ($mergeByNameOnly) {
            if ($normalizeFanName($sourceName) !== $masterFanNameNorm) {
                echo json_encode(['success' => false, 'message' => 'Faqat bir xil fan nomi biriktirilishi mumkin']);
                return;
            }
        } else {
            if ($sourceCode !== $masterFanCode || $sourceName !== $masterFanName) {
                echo json_encode(['success' => false, 'message' => 'Faqat bir xil fan (kod+nom) biriktirilishi mumkin']);
                return;
            }
        }

        // Izoh: umumtalim_fan_id faqat master (umumtalim_fanlar ID), source_fan_id esa fanlar jadvalidagi ID.
        $db->query("
            INSERT INTO umumtalim_fan_biriktirish (umumtalim_fan_id, source_fan_id, yonalish_id, semestr_id)
            VALUES ($masterUmumtalimId, $sourceFanId, $yonalishId, $semestrId)
            ON DUPLICATE KEY UPDATE source_fan_id = $sourceFanId
        ");
        $biriktirishId = 0;
        $biriktirishResult = $db->query("
            SELECT id
            FROM umumtalim_fan_biriktirish
            WHERE umumtalim_fan_id = $masterUmumtalimId
              AND yonalish_id = $yonalishId
              AND semestr_id = $semestrId
            LIMIT 1
        ");
        if ($biriktirishResult && ($biriktirishRow = mysqli_fetch_assoc($biriktirishResult))) {
            $biriktirishId = (int)($biriktirishRow['id'] ?? 0);
        }
        if ($biriktirishId <= 0) {
            echo json_encode(['success' => false, 'message' => 'Biriktirish yozuvini tayyorlashda xatolik']);
            return;
        }

        $db->query("
            INSERT INTO umumtalim_fan_biriktirish_guruhlar
                (biriktirish_id, source_fan_id, semestr_id, yonalish_id, guruh_id)
            VALUES
                ($biriktirishId, $sourceFanId, $semestrId, $yonalishId, $guruhId)
            ON DUPLICATE KEY UPDATE biriktirish_id = VALUES(biriktirish_id)
        ");
    }

    echo json_encode(['success' => true, 'message' => 'Birlashtiriladigan fanlar guruhlar bo\'yicha biriktirildi']);
?>
