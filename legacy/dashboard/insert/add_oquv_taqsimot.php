<?php
include_once __DIR__ . '/../config.php';
$db = new Database();
header('Content-Type: application/json');

$yuklama_id  = isset($_POST['yuklama_id']) ? (int)$_POST['yuklama_id'] : 0;
$type        = trim($_POST['type'] ?? '');
$taqsimotlar = json_decode($_POST['taqsimotlar'] ?? '[]', true);

if ($yuklama_id <= 0 || empty($type) || !is_array($taqsimotlar)) {
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
    } elseif ($type === 'M') {
        $scopeFilters['maxsus_oquv_reja_id'] = $yuklama_id;
        $allowedRows = $db->get_maxsus_oquv_taqsimotlar($scopeFilters);
    } else {
        $allowedRows = [];
    }

    if (empty($allowedRows)) {
        echo json_encode([
            'success' => false,
            'message' => 'Bu yuklama sizning kafedrangizga tegishli emas.'
        ]);
        return;
    }
}

$success = false;
foreach ($taqsimotlar as $t) {
    $teacher_id = (int)($t['oqituvchi_id'] ?? 0);
    $soat       = (float)($t['soat_soni'] ?? 0);
    if ($teacher_id <= 0 || $soat <= 0) continue;

    if (!legacy_can_access_teacher($db, $teacher_id)) {
        echo json_encode([
            'success' => false,
            'message' => 'Tanlangan o‘qituvchi sizning kafedrangizga tegishli emas.'
        ]);
        return;
    }

    $exists = $db->get_data_by_table('taqsimotlar', [
        'oquv_reja_id' => $yuklama_id,
        'teacher_id'   => $teacher_id,
        'type'         => $type
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
            'teacher_id'   => $teacher_id,
            'soat'         => $soat,
            'type'         => $type
        ]);
    }
    if ($res) $success = true;
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
        $db->query("
            UPDATE taqsimot_resync_events
            SET status = 'done', done_at = NOW()
            WHERE yonalish_id = {$yonalishId} AND status = 'pending'
        ");
    }
}

echo json_encode([
    'success' => $success,
    'message' => $success ? 'Taqsimot saqlandi' : 'Saqlashda xatolik'
]);
