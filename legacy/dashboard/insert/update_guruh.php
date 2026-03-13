<?php
include_once '../config.php';
$db = new Database();
header('Content-Type: application/json');

// Izoh: Tahrir oldidan eski guruh holatini history jadvalga yozamiz.
$id = (int) ($_POST['id'] ?? 0);
$yonalish_id = trim($_POST['yonalish_id'] ?? '');
$guruh_nomi = trim($_POST['guruh_nomi'] ?? '');
$talaba_soni = trim($_POST['talaba_soni'] ?? '');
$sync_mode = strtolower(trim($_POST['sync_mode'] ?? 'nosync'));
if (!in_array($sync_mode, ['sync', 'nosync'], true)) {
    $sync_mode = 'nosync';
}

try {
    $db->query("START TRANSACTION");

if ($id <= 0 || $yonalish_id === '' || $guruh_nomi === '' || $talaba_soni === '') {
    $db->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => "Ma'lumotlar to'liq emas"
    ]);
    return;
}

$old = $db->get_data_by_table('guruhlar', ['id' => $id]);
if (!$old) {
    $db->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => "Guruh topilmadi"
    ]);
    return;
}

$historySaved = $db->insert('guruhlar_history', [
    'guruh_id' => $old['id'],
    'yonalish_id' => $old['yonalish_id'],
    'guruh_nomer' => $old['guruh_nomer'],
    'soni' => $old['soni'],
    'sync_status' => $sync_mode,
    'change_type' => 'update'
]);

if (!$historySaved) {
    $db->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => "Eski holatni saqlab bo'lmadi"
    ]);
    return;
}

$updated = $db->update('guruhlar', [
    'yonalish_id' => $yonalish_id,
    'guruh_nomer' => $guruh_nomi,
    'soni' => $talaba_soni
], "id = $id");

if ($updated) {
    $syncedTables = [];

    // Izoh: Guruh bo'yicha yuklamalar guruhlar jadvalidan real-time yig'iladi.
    // "sync" holatda hech qanday kesh jadval yo'q, shu sababli real-time jadvallarni belgilaymiz.
    if ($sync_mode === 'sync') {
        $affectedYonalishlar = array_values(array_unique([
            (int)$old['yonalish_id'],
            (int)$yonalish_id
        ]));

        $archivedRowsTotal = 0;
        foreach ($affectedYonalishlar as $affectedYonalishId) {
            if ($affectedYonalishId <= 0) continue;

            $eventId = $db->insert('taqsimot_resync_events', [
                'entity_type' => 'guruh',
                'entity_id' => $id,
                'yonalish_id' => $affectedYonalishId,
                'reason' => "Guruh tahriri sabab qayta taqsimot talab qilinadi",
                'archived_rows' => 0,
                'status' => 'pending'
            ]);

            $archivedRows = 0;
            if ($eventId) {
                // A turi (asosiy o'quv reja)
                $aRejaIds = [];
                $aRes = $db->query("
                    SELECT r.id
                    FROM oquv_rejalar r
                    JOIN fanlar f ON f.id = r.fan_id
                    JOIN semestrlar s ON s.id = f.semestr_id
                    WHERE s.yonalish_id = {$affectedYonalishId}
                ");
                while ($aRes && $aRow = mysqli_fetch_assoc($aRes)) {
                    $aRejaIds[] = (int)$aRow['id'];
                }
                if (!empty($aRejaIds)) {
                    $aIds = implode(',', array_unique($aRejaIds));
                    $aTaqsimotlar = $db->get_data_by_table_all('taqsimotlar', "WHERE type = 'A' AND oquv_reja_id IN ($aIds)");
                    foreach ($aTaqsimotlar as $t) {
                        $ok = $db->insert('taqsimotlar_archive', [
                            'taqsimot_id' => (int)$t['id'],
                            'oquv_reja_id' => (int)$t['oquv_reja_id'],
                            'teacher_id' => (int)$t['teacher_id'],
                            'soat' => (float)$t['soat'],
                            'type' => $t['type'],
                            'yonalish_id' => $affectedYonalishId,
                            'event_id' => (int)$eventId,
                            'entity_type' => 'guruh',
                            'entity_id' => $id
                        ]);
                        if ($ok) $archivedRows++;
                    }
                    $db->query("DELETE FROM taqsimotlar WHERE type = 'A' AND oquv_reja_id IN ($aIds)");
                }

                // Q turi (qo'shimcha ishchi reja)
                $qRejaIds = [];
                $qRes = $db->query("
                    SELECT q.id
                    FROM qoshimcha_oquv_rejalar q
                    JOIN qoshimcha_fanlar qf ON qf.id = q.qoshimcha_fanid
                    JOIN semestrlar s ON s.id = qf.semestr_id
                    WHERE s.yonalish_id = {$affectedYonalishId}
                ");
                while ($qRes && $qRow = mysqli_fetch_assoc($qRes)) {
                    $qRejaIds[] = (int)$qRow['id'];
                }
                if (!empty($qRejaIds)) {
                    $qIds = implode(',', array_unique($qRejaIds));
                    $qTaqsimotlar = $db->get_data_by_table_all('taqsimotlar', "WHERE type = 'Q' AND oquv_reja_id IN ($qIds)");
                    foreach ($qTaqsimotlar as $t) {
                        $ok = $db->insert('taqsimotlar_archive', [
                            'taqsimot_id' => (int)$t['id'],
                            'oquv_reja_id' => (int)$t['oquv_reja_id'],
                            'teacher_id' => (int)$t['teacher_id'],
                            'soat' => (float)$t['soat'],
                            'type' => $t['type'],
                            'yonalish_id' => $affectedYonalishId,
                            'event_id' => (int)$eventId,
                            'entity_type' => 'guruh',
                            'entity_id' => $id
                        ]);
                        if ($ok) $archivedRows++;
                    }
                    $db->query("DELETE FROM taqsimotlar WHERE type = 'Q' AND oquv_reja_id IN ($qIds)");
                }

                $db->update('taqsimot_resync_events', [
                    'archived_rows' => $archivedRows
                ], "id = {$eventId}");
            }
            $archivedRowsTotal += $archivedRows;
        }

        $syncedTables = [
            'oquv_yuklama(real-time)',
            'oquv_taqsimoti(real-time)',
            'oqituvchi_taqsimoti(real-time)',
            "taqsimotlar(archived: {$archivedRowsTotal})",
            'taqsimot_resync_events(pending)'
        ];
    }

    $db->query("COMMIT");
    echo json_encode([
        'success' => true,
        'message' => "Guruh tahrirlandi: " . ($sync_mode === 'sync' ? "sinxronlangan" : "sinxronlanmagan"),
        'synced_tables' => $syncedTables
    ]);
} else {
    $db->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => "Guruhni tahrirlashda xatolik yuz berdi"
    ]);
}
} catch (Throwable $e) {
    $db->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => "Yangilashda texnik xatolik yuz berdi"
    ]);
}
