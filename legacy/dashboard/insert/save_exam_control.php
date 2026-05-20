<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

try {
    $db = new Database();
    $semestrId = (int)($_POST['semestr_id'] ?? 0);
    $fanCode = trim((string)($_POST['fan_code'] ?? ''));
    $examType = strtoupper(trim((string)($_POST['exam_type'] ?? 'T')));
    $oraliq = (float)($_POST['oraliq_nazorat'] ?? 0);
    $yakuniy = (float)($_POST['yakuniy_nazorat'] ?? 0);

    if ($semestrId <= 0 || $fanCode === '') {
        echo json_encode(['success' => false, 'message' => "Semestr yoki fan kodi noto'g'ri"]);
        exit;
    }

    if (!in_array($examType, ['T', 'I'], true)) {
        $examType = 'T';
    }
    if ($examType === 'T') {
        $yakuniy = 0;
    }

    $ok = $db->save_exam_control($semestrId, $fanCode, $examType, $oraliq, $yakuniy);
    echo json_encode([
        'success' => $ok,
        'message' => $ok ? 'Saqlandi' : 'Saqlashda xatolik'
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

