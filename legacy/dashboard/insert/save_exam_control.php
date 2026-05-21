<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
require_once '../config.php';

try {
    $db = new Database();

    $controlsRaw = $_POST['controls'] ?? null;
    if (is_string($controlsRaw) && trim($controlsRaw) !== '') {
        $controls = json_decode($controlsRaw, true);
        if (!is_array($controls)) {
            echo json_encode(['success' => false, 'message' => "Ma'lumot formati noto'g'ri"]);
            exit;
        }

        $saved = 0;
        $failed = 0;
        foreach ($controls as $control) {
            if (!is_array($control)) {
                $failed++;
                continue;
            }

            $semestrId = (int)($control['semestr_id'] ?? 0);
            $fanCode = trim((string)($control['fan_code'] ?? ''));
            $examType = strtoupper(trim((string)($control['exam_type'] ?? 'I')));
            if ($semestrId <= 0 || $fanCode === '') {
                $failed++;
                continue;
            }
            if (!in_array($examType, ['T', 'I'], true)) {
                $examType = 'I';
            }

            $ok = $db->save_exam_control($semestrId, $fanCode, $examType, 0, 0);
            $ok ? $saved++ : $failed++;
        }

        echo json_encode([
            'success' => $failed === 0,
            'saved' => $saved,
            'failed' => $failed,
            'message' => $failed === 0 ? 'Saqlandi' : "Qisman saqlandi: {$saved} ta, xato: {$failed} ta",
        ]);
        exit;
    }

    $semestrId = (int)($_POST['semestr_id'] ?? 0);
    $fanCode = trim((string)($_POST['fan_code'] ?? ''));
    $examType = strtoupper(trim((string)($_POST['exam_type'] ?? 'I')));
    $oraliq = (float)($_POST['oraliq_nazorat'] ?? 0);
    $yakuniy = (float)($_POST['yakuniy_nazorat'] ?? 0);

    if ($semestrId <= 0 || $fanCode === '') {
        echo json_encode(['success' => false, 'message' => "Semestr yoki fan kodi noto'g'ri"]);
        exit;
    }

    if (!in_array($examType, ['T', 'I'], true)) {
        $examType = 'I';
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
