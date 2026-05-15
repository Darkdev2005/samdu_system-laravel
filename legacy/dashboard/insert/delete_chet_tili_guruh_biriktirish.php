<?php
// Izoh: 3-tabda qo'lda biriktirilgan chet tili guruhlarini o'chirish.
include_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();
$semestrId = (int)($_POST['semestr_id'] ?? 0);
$semestrIdsRaw = trim((string)($_POST['semestr_ids'] ?? ''));
$semestrIds = [];
if ($semestrIdsRaw !== '') {
    foreach (explode(',', $semestrIdsRaw) as $part) {
        $value = (int)trim($part);
        if ($value > 0) {
            $semestrIds[$value] = true;
        }
    }
}
if ($semestrId > 0) {
    $semestrIds[$semestrId] = true;
}
$semestrIds = array_map('intval', array_keys($semestrIds));

$fanId = (int)($_POST['fan_id'] ?? 0);
$fanIdsRaw = trim((string)($_POST['fan_ids'] ?? ''));
$fanIds = [];
if ($fanIdsRaw !== '') {
    foreach (explode(',', $fanIdsRaw) as $part) {
        $value = (int)trim($part);
        if ($value > 0) {
            $fanIds[$value] = true;
        }
    }
}
if ($fanId > 0) {
    $fanIds[$fanId] = true;
}
$fanIds = array_map('intval', array_keys($fanIds));

if (empty($semestrIds) || empty($fanIds)) {
    echo json_encode([
        'success' => false,
        'message' => "Ma'lumotlar to'liq emas",
    ], JSON_UNESCAPED_UNICODE);
    return;
}

try {
    $db->query("START TRANSACTION");
    $semestrIdsSql = implode(',', $semestrIds);
    $fanIdsSql = implode(',', $fanIds);
    $ok = $db->query("
        DELETE FROM chet_tili_biriktirilgan_guruhlar
        WHERE semestr_id IN ({$semestrIdsSql})
          AND fan_id IN ({$fanIdsSql})
    ");

    if ($ok) {
        $db->query("COMMIT");
        echo json_encode([
            'success' => true,
            'message' => "Biriktirilgan guruh o'chirildi",
        ], JSON_UNESCAPED_UNICODE);
    } else {
        $db->query("ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => "O'chirishda xatolik yuz berdi",
        ], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    $db->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => "Texnik xatolik yuz berdi",
    ], JSON_UNESCAPED_UNICODE);
}
