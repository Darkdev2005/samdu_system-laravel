<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();

$qoshimchaFanId = (int)($_POST['qoshimcha_fanid'] ?? 0);
if ($qoshimchaFanId <= 0) {
    echo json_encode(['success' => false, 'message' => "Fan ID noto'g'ri"]);
    return;
}

$fan = $db->get_data_by_table('qoshimcha_fanlar', ['id' => $qoshimchaFanId]);
if (!$fan) {
    echo json_encode(['success' => false, 'message' => 'Fan topilmadi']);
    return;
}

try {
    $db->query("START TRANSACTION");
    $ok = true;

    $qRejaIds = [];
    $qRejaRes = $db->query("SELECT id FROM qoshimcha_oquv_rejalar WHERE qoshimcha_fanid = $qoshimchaFanId");
    if ($qRejaRes) {
        while ($row = mysqli_fetch_assoc($qRejaRes)) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $qRejaIds[] = $id;
            }
        }
    }

    if (!empty($qRejaIds)) {
        $idsSql = implode(',', array_map('intval', array_unique($qRejaIds)));
        $ok = $ok && $db->query("DELETE FROM taqsimotlar WHERE type = 'Q' AND oquv_reja_id IN ($idsSql)");
    }

    if ($ok) {
        $ok = $ok && $db->query("DELETE FROM qoshimcha_oquv_rejalar WHERE qoshimcha_fanid = $qoshimchaFanId");
    }

    if ($ok) {
        $ok = $ok && $db->delete('qoshimcha_fanlar', "id = $qoshimchaFanId");
    }

    if ($ok) {
        $db->query("COMMIT");
        echo json_encode(['success' => true, 'message' => "Qo'shimcha fan o'chirildi"]);
    } else {
        $db->query("ROLLBACK");
        echo json_encode(['success' => false, 'message' => "O'chirishda xatolik yuz berdi"]);
    }
} catch (Throwable $e) {
    $db->query("ROLLBACK");
    echo json_encode(['success' => false, 'message' => "O'chirishda texnik xatolik yuz berdi"]);
}
