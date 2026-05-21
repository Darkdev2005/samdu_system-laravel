<?php
include_once __DIR__ . '/../config.php';
$db = new Database();
header('Content-Type: application/json');

$yuklama_id = isset($_POST['yuklama_id']) ? (int)$_POST['yuklama_id'] : 0;
$legacy_yuklama_id = isset($_POST['legacy_yuklama_id']) ? (int)$_POST['legacy_yuklama_id'] : 0;
$type = strtoupper(trim($_POST['type'] ?? ''));
$soatTuri = trim((string)($_POST['soat_turi'] ?? ''));
$replaceMode = ((int)($_POST['replace_mode'] ?? 0) === 1);
$taqsimotlar = json_decode($_POST['taqsimotlar'] ?? '[]', true);
$useScopedSoatTuri = legacy_is_scoped_taqsimot_soat_turi($soatTuri);
$safeSoatTuri = addslashes($soatTuri);

if ($yuklama_id <= 0 || !in_array($type, ['A', 'Q', 'M', 'D'], true) || !is_array($taqsimotlar)) {
    echo json_encode([
        'success' => false,
        'message' => "Noto'g'ri ma'lumot yuborildi"
    ]);
    return;
}

if (legacy_is_kafedra_mudiri()) {
    $scopeFilters = ['kafedra_id' => legacy_user_kafedra_id()];
    if ($type === 'A') {
        $virtualFanId = legacy_taqsimot_virtual_fan_id($yuklama_id);
        if ($virtualFanId > 0) {
            $scopeFilters['taqsimot_variant_fan_id'] = $virtualFanId;
        } else {
            $scopeFilters['oquv_reja_id'] = $yuklama_id;
            $scopeFilters['strict_oquv_reja_id'] = true;
        }
        $allowedRows = $db->get_oquv_taqsimotlar($scopeFilters);
    } elseif ($type === 'Q') {
        $scopeFilters['qoshimcha_oquv_reja_id'] = $yuklama_id;
        $allowedRows = $db->get_qoshimcha_oquv_taqsimotlar($scopeFilters);
    } elseif ($type === 'M') {
        $scopeFilters['maxsus_oquv_reja_id'] = $yuklama_id;
        $allowedRows = $db->get_maxsus_oquv_taqsimotlar($scopeFilters);
    } else {
        $scopeFilters['qoshimcha_oquv_reja_id'] = $yuklama_id;
        $allowedRows = $db->get_magistr_doktorant_taqsimotlar($scopeFilters);
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
$assignedGroups = [];
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

    $guruhlar = [];
    if (!empty($row['guruhlar']) && is_array($row['guruhlar'])) {
        foreach ($row['guruhlar'] as $guruh) {
            $guruh = trim((string)$guruh);
            if ($guruh !== '' && !in_array($guruh, $guruhlar, true)) {
                $guruhlar[] = $guruh;
            }
        }
    }
    foreach ($guruhlar as $guruh) {
        if (isset($assignedGroups[$guruh])) {
            echo json_encode([
                'success' => false,
                'message' => "{$guruh} guruhi shu fan va soat turi ichida ikki marta tanlangan."
            ]);
            return;
        }
        $assignedGroups[$guruh] = true;
    }

    $normalizedRows[] = [
        'teacher_id' => $teacherId,
        'soat' => $soat,
        'guruhlar_json' => !empty($guruhlar) ? json_encode($guruhlar, JSON_UNESCAPED_UNICODE) : null,
    ];
}

$controlSoatTurlari = ['oraliq_nazorat', 'yakuniy_nazorat'];
$lessonSoatTurlari = ['amalda_maruz', 'amalda_amaliy'];
if (in_array($soatTuri, $controlSoatTurlari, true) && !empty($normalizedRows)) {
    $teacherIds = array_values(array_unique(array_map(static function (array $row): int {
        return (int)$row['teacher_id'];
    }, $normalizedRows)));

    if (!empty($teacherIds)) {
        $teacherIdsSql = implode(',', array_map('intval', $teacherIds));
        $lessonSoatSql = "'" . implode("','", array_map('addslashes', $lessonSoatTurlari)) . "'";
        $conflictSql = "
            SELECT DISTINCT o.fio, COALESCE(t.soat_turi, '') AS soat_turi
            FROM taqsimotlar t
            JOIN oqituvchilar o ON o.id = t.teacher_id
            WHERE t.oquv_reja_id = {$yuklama_id}
              AND t.type = '" . addslashes($type) . "'
              AND t.teacher_id IN ({$teacherIdsSql})
              AND COALESCE(t.soat_turi, '') IN ({$lessonSoatSql})
        ";
        $conflictResult = $db->query($conflictSql);
        $conflicts = [];
        $lessonLabels = [
            'amalda_maruz' => "ma'ruza",
            'amalda_amaliy' => 'amaliy',
        ];
        if ($conflictResult) {
            while ($conflictRow = mysqli_fetch_assoc($conflictResult)) {
                $fio = trim((string)($conflictRow['fio'] ?? ''));
                $lessonType = trim((string)($conflictRow['soat_turi'] ?? ''));
                $label = $lessonLabels[$lessonType] ?? $lessonType;
                if ($fio !== '') {
                    $conflicts[$fio . '|' . $label] = "{$fio} ({$label})";
                }
            }
        }

        if (!empty($conflicts)) {
            echo json_encode([
                'success' => false,
                'icon' => 'warning',
                'title' => 'Ogohlantirish',
                'message' => "Bu o'qituvchi ushbu fanning " . implode(', ', array_values($conflicts)) . " darslarini o'tgan. Oraliq/yakuniy nazoratni shu o'qituvchiga taqsimlash mumkin emas."
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
    }
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
        $deleteCondition = "oquv_reja_id IN ({$deleteIdsSql}) AND type = '" . addslashes($type) . "'";
        if ($useScopedSoatTuri) {
            $deleteCondition .= " AND COALESCE(soat_turi, '') = '{$safeSoatTuri}'";
        }
        $deleted = $db->delete('taqsimotlar', $deleteCondition);
        if (!$deleted) {
            $success = false;
        }

        if ($success) {
            foreach ($normalizedRows as $normalizedRow) {
                $insertPayload = [
                    'oquv_reja_id' => $yuklama_id,
                    'teacher_id' => (int)$normalizedRow['teacher_id'],
                    'soat' => (float)$normalizedRow['soat'],
                    'type' => $type,
                ];
                if ($useScopedSoatTuri) {
                    $insertPayload['soat_turi'] = $soatTuri;
                }
                if (!empty($normalizedRow['guruhlar_json'])) {
                    $insertPayload['guruhlar_json'] = $normalizedRow['guruhlar_json'];
                }
                $insertId = $db->insert('taqsimotlar', $insertPayload);
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
    foreach ($normalizedRows as $normalizedRow) {
        $teacherId = (int)$normalizedRow['teacher_id'];
        $soat = (float)$normalizedRow['soat'];
        $existsFilters = [
            'oquv_reja_id' => $yuklama_id,
            'teacher_id' => (int)$teacherId,
            'type' => $type,
        ];
        if ($useScopedSoatTuri) {
            $existsFilters['soat_turi'] = $soatTuri;
        }
        $exists = $db->get_data_by_table('taqsimotlar', $existsFilters);

        if ($exists) {
            $res = $db->update(
                'taqsimotlar',
                ['soat' => $soat + (float)$exists['soat']],
                'id = ' . (int)$exists['id']
            );
        } else {
            $insertPayload = [
                'oquv_reja_id' => $yuklama_id,
                'teacher_id' => (int)$teacherId,
                'soat' => $soat,
                'type' => $type,
            ];
            if ($useScopedSoatTuri) {
                $insertPayload['soat_turi'] = $soatTuri;
            }
            if (!empty($normalizedRow['guruhlar_json'])) {
                $insertPayload['guruhlar_json'] = $normalizedRow['guruhlar_json'];
            }
            $res = $db->insert('taqsimotlar', $insertPayload);
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
    } elseif ($type === 'D') {
        $row = $db->get_data_by_table_all('magistr_doktorant_qoshimcha_rejalar mdqr JOIN magistr_doktorant_yuklamalar mdy ON mdy.id = mdqr.magistr_doktorant_id LEFT JOIN semestrlar s ON s.id = mdy.semestr_id', "WHERE mdqr.id = {$yuklama_id} LIMIT 1");
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
