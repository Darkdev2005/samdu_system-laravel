<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $db = new Database();

    $fanId = (int)($_POST['fan_id'] ?? 0);
    $fanName = trim((string)($_POST['fan_name'] ?? ''));
    $kafedraId = (int)($_POST['kafedra_id'] ?? 0);

    if ($fanId <= 0 || $fanName === '' || $kafedraId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => "Ma'lumotlar to'liq emas",
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

