<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database();

    $variantIds = $_POST['variant_ids'] ?? [];
    if (is_array($variantIds) && count($variantIds) > 0) {
        $baseFanId = (int)($_POST['base_fan_id'] ?? 0);
        $semestrId = (int)($_POST['semestr_id'] ?? 0);
        $fanNames = $_POST['fan_names'] ?? [];
        $kafedraIds = $_POST['kafedra_ids'] ?? [];
        $talabalarSoniList = $_POST['talabalar_soni'] ?? [];

        if ($baseFanId <= 0 || $semestrId <= 0 || !is_array($fanNames) || !is_array($kafedraIds) || !is_array($talabalarSoniList)) {
            echo json_encode([
                'success' => false,
                'message' => "Tahrirlash ma'lumotlari to'liq emas",
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $baseFan = $db->get_data_by_table('fanlar', [
            'id' => $baseFanId,
            'semestr_id' => $semestrId,
            'tanlov_fan' => 1,
            'kafedra_id' => 0,
        ]);
        if (!$baseFan) {
            echo json_encode([
                'success' => false,
                'message' => "Asosiy tanlov fan topilmadi",
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $fanCode = trim((string)($baseFan['fan_code'] ?? ''));
        $semestr = $db->get_data_by_table('semestrlar', ['id' => $semestrId]);
        $yonalishId = (int)($semestr['yonalish_id'] ?? 0);
        $talabaRow = $db->get_talaba_soni($semestrId);
        $jamiTalaba = (int)($talabaRow['talabalar_soni'] ?? 0);
        if ($fanCode === '' || $yonalishId <= 0 || $jamiTalaba <= 0) {
            echo json_encode([
                'success' => false,
                'message' => "Yo'nalish yoki talabalar soni topilmadi",
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        $rows = [];
        $sum = 0;
        $seenVariantIds = [];
        $seenNameDept = [];
        foreach ($variantIds as $i => $variantIdRaw) {
            $variantId = (int)$variantIdRaw;
            $fanName = trim((string)($fanNames[$i] ?? ''));
            $kafedraId = (int)($kafedraIds[$i] ?? 0);
            $talabaRaw = trim((string)($talabalarSoniList[$i] ?? ''));
            if ($variantId <= 0 || $fanName === '' || $kafedraId <= 0 || $talabaRaw === '' || !is_numeric($talabaRaw)) {
                echo json_encode([
                    'success' => false,
                    'message' => "Variant #" . ($i + 1) . " ma'lumotlari to'liq emas",
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            $talabalarSoni = (int)$talabaRaw;
            if ($talabalarSoni < 0 || ($talabalarSoni > 0 && $talabalarSoni < 1)) {
                echo json_encode([
                    'success' => false,
                    'message' => "{$fanName}: aktiv variant kamida 1 talaba bo'lishi kerak",
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            if (isset($seenVariantIds[$variantId])) {
                echo json_encode([
                    'success' => false,
                    'message' => "Bir xil variant qayta yuborilgan",
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            $seenVariantIds[$variantId] = true;

            $uniqueKey = (function_exists('mb_strtolower') ? mb_strtolower($fanName, 'UTF-8') : strtolower($fanName)) . '|' . $kafedraId;
            if (isset($seenNameDept[$uniqueKey])) {
                echo json_encode([
                    'success' => false,
                    'message' => "{$fanName}: bir xil variant qayta kiritilgan",
                ], JSON_UNESCAPED_UNICODE);
                return;
            }
            $seenNameDept[$uniqueKey] = true;

            $variant = $db->get_data_by_table('fanlar', [
                'id' => $variantId,
                'fan_code' => $fanCode,
                'semestr_id' => $semestrId,
                'tanlov_fan' => 1,
            ]);
            if (!$variant || (int)($variant['kafedra_id'] ?? 0) <= 0) {
                echo json_encode([
                    'success' => false,
                    'message' => "{$fanName}: tanlov varianti topilmadi",
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $duplicate = $db->get_data_by_table('fanlar', [
                'fan_code' => $fanCode,
                'fan_name' => $fanName,
                'kafedra_id' => $kafedraId,
                'semestr_id' => $semestrId,
                'tanlov_fan' => 1,
            ], " AND id <> $variantId");
            if ($duplicate) {
                echo json_encode([
                    'success' => false,
                    'message' => "{$fanName}: shu semestrda bir xil variant mavjud",
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            $sum += $talabalarSoni;
            $rows[] = [
                'variant_id' => $variantId,
                'fan_name' => $fanName,
                'kafedra_id' => $kafedraId,
                'talabalar_soni' => $talabalarSoni,
            ];
        }

        if ($sum !== $jamiTalaba) {
            echo json_encode([
                'success' => false,
                'message' => "Taqsimlangan talabalar soni {$sum}. Jami {$jamiTalaba} bo'lishi kerak",
            ], JSON_UNESCAPED_UNICODE);
            return;
        }

        foreach ($rows as $row) {
            $variantId = (int)$row['variant_id'];
            $kafedraId = (int)$row['kafedra_id'];
            $talabalarSoni = (int)$row['talabalar_soni'];
            $db->update('fanlar', [
                'fan_name' => $row['fan_name'],
                'kafedra_id' => $kafedraId,
            ], "id = $variantId");
            $db->query("
                INSERT INTO tanlov_fan_talablar
                    (semestr_id, yonalish_id, base_fan_id, variant_fan_id, talabalar_soni)
                VALUES
                    ($semestrId, $yonalishId, $baseFanId, $variantId, $talabalarSoni)
                ON DUPLICATE KEY UPDATE
                    yonalish_id = $yonalishId,
                    base_fan_id = $baseFanId,
                    talabalar_soni = $talabalarSoni
            ");
        }

        echo json_encode([
            'success' => true,
            'message' => "Tanlov fan taqsimoti yangilandi",
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $fanId = (int)($_POST['fan_id'] ?? 0);
    $fanName = trim((string)($_POST['fan_name'] ?? ''));
    $kafedraId = (int)($_POST['kafedra_id'] ?? 0);
    $talabalarSoni = (int)($_POST['talabalar_soni'] ?? -1);

    if ($fanId <= 0 || $fanName === '' || $kafedraId <= 0 || $talabalarSoni < 0) {
        echo json_encode([
            'success' => false,
            'message' => "Ma'lumotlar to'liq emas",
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    if ($talabalarSoni > 0 && $talabalarSoni < 1) {
        echo json_encode([
            'success' => false,
            'message' => "Aktiv tanlov varianti kamida 1 talaba bo'lishi kerak",
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $fan = $db->get_data_by_table('fanlar', ['id' => $fanId]);
    if (!$fan) {
        echo json_encode([
            'success' => false,
            'message' => "Fan topilmadi",
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $tanlovFan = (int)($fan['tanlov_fan'] ?? 0);
    $semestrId = (int)($fan['semestr_id'] ?? 0);
    $fanCode = trim((string)($fan['fan_code'] ?? ''));
    $oldKafedraId = (int)($fan['kafedra_id'] ?? 0);
    if ($tanlovFan !== 1 || $oldKafedraId <= 0 || $semestrId <= 0 || $fanCode === '') {
        echo json_encode([
            'success' => false,
            'message' => "Faqat tanlov fan varianti tahrirlanadi",
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $duplicate = $db->get_data_by_table('fanlar', [
        'fan_code' => $fanCode,
        'fan_name' => $fanName,
        'kafedra_id' => $kafedraId,
        'semestr_id' => $semestrId,
        'tanlov_fan' => 1,
    ], " AND id <> $fanId");

    if ($duplicate) {
        echo json_encode([
            'success' => false,
            'message' => "Shu semestrda bir xil tanlov varianti allaqachon mavjud",
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $updated = $db->update('fanlar', [
        'fan_name' => $fanName,
        'kafedra_id' => $kafedraId,
    ], "id = $fanId");

    if (!$updated) {
        echo json_encode([
            'success' => false,
            'message' => "Yangilashda xatolik yuz berdi",
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $semestr = $db->get_data_by_table('semestrlar', ['id' => $semestrId]);
    $yonalishId = (int)($semestr['yonalish_id'] ?? 0);
    $baseFan = $db->get_data_by_table('fanlar', [
        'fan_code' => $fanCode,
        'semestr_id' => $semestrId,
        'tanlov_fan' => 1,
        'kafedra_id' => 0,
    ]);
    $baseFanId = (int)($baseFan['id'] ?? 0);

    if ($yonalishId > 0 && $baseFanId > 0) {
        $db->query("
            INSERT INTO tanlov_fan_talablar
                (semestr_id, yonalish_id, base_fan_id, variant_fan_id, talabalar_soni)
            VALUES
                ($semestrId, $yonalishId, $baseFanId, $fanId, $talabalarSoni)
            ON DUPLICATE KEY UPDATE
                yonalish_id = $yonalishId,
                base_fan_id = $baseFanId,
                talabalar_soni = $talabalarSoni
        ");
    }

    echo json_encode([
        'success' => true,
        'message' => "Tanlov fan yangilandi",
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => "Texnik xatolik: " . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
