<?php
include_once __DIR__ . '/../config.php';
$db = new Database();
header('Content-Type: application/json');

$yuklama_id = isset($_POST['yuklama_id']) ? (int)$_POST['yuklama_id'] : 0;
$legacy_yuklama_id = isset($_POST['legacy_yuklama_id']) ? (int)$_POST['legacy_yuklama_id'] : 0;
$type = strtoupper(trim($_POST['type'] ?? ''));
$replaceMode = ((int)($_POST['replace_mode'] ?? 0) === 1);
$taqsimotlar = json_decode($_POST['taqsimotlar'] ?? '[]', true);

if ($yuklama_id <= 0 || !in_array($type, ['A', 'Q', 'M'], true) || !is_array($taqsimotlar)) {
    echo json_encode([
        'success' => false,
        'message' => "Noto'g'ri ma'lumot yuborildi"
    ]);
    return;
}

if (legacy_is_kafedra_mudiri()) {
    $scopeFilters = ['kafedra_id' => legacy_user_kafedra_id()];
    if ($type === 'A') {
        $scopeFilters['oquv_reja_id'] = $yuklama_id;
        $allowedRows = $db->get_oquv_taqsimotlar($scopeFilters);
    } elseif ($type === 'Q') {
        $scopeFilters['qoshimcha_oquv_reja_id'] = $yuklama_id;
        $allowedRows = $db->get_qoshimcha_oquv_taqsimotlar($scopeFilters);
    } else {
        $scopeFilters['maxsus_oquv_reja_id'] = $yuklama_id;
        $allowedRows = $db->get_maxsus_oquv_taqsimotlar($scopeFilters);
    }

    if (empty($allowedRows)) {
        echo json_encode([
            'success' => false,
            'message' => "Bu yuklama sizning kafedrangizga tegishli emas."
        ]);
        return;
    }
}

$normalizedRows = [];
foreach ($taqsimotlar as $row) {
    $teacherId = (int)($row['oqituvchi_id'] ?? 0);
    $soat = (float)($row['soat_soni'] ?? 0);
    if ($teacherId <= 0 || $soat <= 0) {
        continue;
    }

    if (!legacy_can_access_teacher($db, $teacherId)) {
        echo json_encode([
            'success' => false,
            'message' => "Tanlangan o'qituvchi sizning kafedrangizga tegishli emas."
        ]);
        return;
    }

    if (!isset($normalizedRows[$teacherId])) {
        $normalizedRows[$teacherId] = 0.0;
    }
    $normalizedRows[$teacherId] += $soat;
}

$success = false;

if ($replaceMode) {
    $success = true;
    $db->query('START TRANSACTION');

    try {
        $deleteIds = [$yuklama_id];
        if ($legacy_yuklama_id > 0 && $legacy_yuklama_id !== $yuklama_id) {
            $deleteIds[] = $legacy_yuklama_id;
        }
        $deleteIdsSql = implode(',', array_map('intval', array_unique($deleteIds)));
        $deleted = $db->delete('taqsimotlar', "oquv_reja_id IN ({$deleteIdsSql}) AND type = '" . addslashes($type) . "'");
        if (!$deleted) {
            $success = false;
        }

        if ($success) {
            foreach ($normalizedRows as $teacherId => $soat) {
                $insertId = $db->insert('taqsimotlar', [
                    'oquv_reja_id' => $yuklama_id,
                    'teacher_id' => (int)$teacherId,
                    'soat' => $soat,
                    'type' => $type
                ]);
                if ($insertId <= 0) {
                    $success = false;
                    break;
                }
            }
        }

        if ($success) {
            $db->query('COMMIT');
        } else {
            $db->query('ROLLBACK');
        }
    } catch (Throwable $e) {
        $db->query('ROLLBACK');
        $success = false;
    }
} else {
    foreach ($normalizedRows as $teacherId => $soat) {
        $exists = $db->get_data_by_table('taqsimotlar', [
            'oquv_reja_id' => $yuklama_id,
            'teacher_id' => (int)$teacherId,
            'type' => $type
        ]);

        if ($exists) {
            $res = $db->update(
                'taqsimotlar',
                ['soat' => $soat + (float)$exists['soat']],
                'id = ' . (int)$exists['id']
            );
        } else {
            $res = $db->insert('taqsimotlar', [
                'oquv_reja_id' => $yuklama_id,
                'teacher_id' => (int)$teacherId,
                'soat' => $soat,
                'type' => $type
            ]);
        }

        if ($res) {
            $success = true;
        }
    }
}

// Izoh: Qayta taqsimot pending bo'lib turgan yo'nalish bo'lsa, taqsimot kiritilgach "done" qilamiz.
if ($success) {
    $yonalishId = 0;
    if ($type === 'A') {
        $row = $db->get_data_by_table_all('oquv_rejalar r JOIN fanlar f ON f.id = r.fan_id JOIN semestrlar s ON s.id = f.semestr_id', "WHERE r.id = {$yuklama_id} LIMIT 1");
        if (!empty($row[0]['yonalish_id'])) {
            $yonalishId = (int)$row[0]['yonalish_id'];
        }
    } elseif ($type === 'Q') {
        $row = $db->get_data_by_table_all('qoshimcha_oquv_rejalar q JOIN qoshimcha_fanlar qf ON qf.id = q.qoshimcha_fanid JOIN semestrlar s ON s.id = qf.semestr_id', "WHERE q.id = {$yuklama_id} LIMIT 1");
        if (!empty($row[0]['yonalish_id'])) {
            $yonalishId = (int)$row[0]['yonalish_id'];
        }
    } elseif ($type === 'M') {
        $row = $db->get_data_by_table_all('maxsus_oquv_reja_soatlar ms JOIN maxsus_oquv_rejalar mr ON mr.id = ms.maxsus_reja_id', "WHERE ms.id = {$yuklama_id} LIMIT 1");
        if (!empty($row[0]['yonalish_id'])) {
            $yonalishId = (int)$row[0]['yonalish_id'];
        }
    }

    if ($yonalishId > 0) {
        $db->query("\n            UPDATE taqsimot_resync_events\n            SET status = 'done', done_at = NOW()\n            WHERE yonalish_id = {$yonalishId} AND status = 'pending'\n        ");
    }
}

echo json_encode([
    'success' => $success,
    'message' => $success
        ? ($replaceMode ? 'Taqsimot yangilandi' : 'Taqsimot saqlandi')
        : 'Saqlashda xatolik'
]);
?>
