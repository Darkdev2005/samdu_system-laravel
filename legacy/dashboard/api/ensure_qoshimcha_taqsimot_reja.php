<?php
include_once __DIR__ . '/../config.php';

$db = new Database();
header('Content-Type: application/json; charset=UTF-8');

try {
    $qDarsId = isset($_POST['q_dars_id']) ? (int)$_POST['q_dars_id'] : 0;
    $yonalishId = isset($_POST['yonalish_id']) ? (int)$_POST['yonalish_id'] : 0;
    $semestrNum = isset($_POST['semestr']) ? (int)$_POST['semestr'] : 0;
    $kafedraId = isset($_POST['kafedra_id']) ? (int)$_POST['kafedra_id'] : 0;
    $kafedraNomi = trim((string)($_POST['kafedra_nomi'] ?? ''));
    $fanNomi = trim((string)($_POST['fan_nomi'] ?? ''));
    $soat = isset($_POST['soat']) ? (float)$_POST['soat'] : 0.0;

    $allowedDarsIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 20, 21];
    if (!in_array($qDarsId, $allowedDarsIds, true) || $yonalishId <= 0 || $semestrNum <= 0 || ($kafedraId <= 0 && $kafedraNomi === '') || $fanNomi === '' || $soat <= 0) {
        echo json_encode([
            'success' => false,
            'message' => "Qo'shimcha soat uchun yetarli ma'lumot topilmadi.",
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $semestr = $db->get_data_by_table('semestrlar', [
        'yonalish_id' => $yonalishId,
        'semestr' => $semestrNum,
    ]);
    $kafedra = null;
    if ($kafedraId > 0) {
        $kafedra = $db->get_data_by_table('kafedralar', ['id' => $kafedraId]);
    } else {
        $kafedraNames = array_values(array_filter(array_map('trim', explode('|', $kafedraNomi))));
        foreach ($kafedraNames as $namePart) {
            $kafedra = $db->get_data_by_table('kafedralar', ['name' => $namePart]);
            if (!empty($kafedra)) {
                break;
            }
        }

        if (empty($kafedra) && $kafedraNomi !== '') {
            $firstName = addslashes($kafedraNames[0] ?? $kafedraNomi);
            $kafedraRes = $db->query("
                SELECT *
                FROM kafedralar
                WHERE name LIKE '%{$firstName}%'
                ORDER BY id
                LIMIT 1
            ");
            $kafedra = $kafedraRes ? mysqli_fetch_assoc($kafedraRes) : null;
        }
    }

    $semestrId = (int)($semestr['id'] ?? 0);
    $kafedraId = (int)($kafedra['id'] ?? 0);
    if ($semestrId <= 0 || $kafedraId <= 0) {
        echo json_encode([
            'success' => false,
            'message' => "Semestr yoki kafedra aniqlanmadi.",
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    if (legacy_is_kafedra_mudiri() && $kafedraId !== legacy_user_kafedra_id()) {
        echo json_encode([
            'success' => false,
            'message' => "Bu yuklama sizning kafedrangizga tegishli emas.",
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $fanNameEsc = addslashes($fanNomi);
    $existingRes = $db->query("
        SELECT q.id AS qoshimcha_reja_id
        FROM qoshimcha_oquv_rejalar q
        JOIN qoshimcha_fanlar qf ON qf.id = q.qoshimcha_fanid
        WHERE qf.semestr_id = {$semestrId}
          AND qf.qoshimcha_dars_id = {$qDarsId}
          AND q.kafedra_id = {$kafedraId}
          AND qf.fan_name = '{$fanNameEsc}'
        ORDER BY q.id DESC
        LIMIT 1
    ");
    $existing = $existingRes ? mysqli_fetch_assoc($existingRes) : null;

    if (!empty($existing['qoshimcha_reja_id'])) {
        echo json_encode([
            'success' => true,
            'qoshimcha_reja_id' => (int)$existing['qoshimcha_reja_id'],
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    $db->query('START TRANSACTION');
    $qoshimchaFanId = $db->insert('qoshimcha_fanlar', [
        'semestr_id' => $semestrId,
        'fan_name' => $fanNomi,
        'fan_soat' => (string)$soat,
        'qoshimcha_dars_id' => $qDarsId,
        'subtype_code' => '',
        'formula_meta' => '',
    ]);

    if ($qoshimchaFanId <= 0) {
        throw new RuntimeException("Qo'shimcha fan yaratilmadi.");
    }

    $qoshimchaRejaId = $db->insert('qoshimcha_oquv_rejalar', [
        'qoshimcha_fanid' => $qoshimchaFanId,
        'kafedra_id' => $kafedraId,
        'dars_soati' => (string)$soat,
        'izoh' => 'taqsimot jadvalidan avtomatik yaratildi',
    ]);

    if ($qoshimchaRejaId <= 0) {
        throw new RuntimeException("Qo'shimcha reja yaratilmadi.");
    }

    $db->query('COMMIT');
    echo json_encode([
        'success' => true,
        'qoshimcha_reja_id' => $qoshimchaRejaId,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    try {
        $db->query('ROLLBACK');
    } catch (Throwable $rollbackError) {
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
?>
