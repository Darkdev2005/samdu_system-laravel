<?php
include_once '../config.php';
$db = new Database();
header('Content-Type: application/json');

// Izoh: Tahrir qilishdan oldin eski holatni history jadvalga yozamiz.
$id = (int) ($_POST['id'] ?? 0);
$nomi = trim($_POST['nomi'] ?? '');
$code = trim($_POST['code'] ?? '');
$muddati = trim($_POST['muddati'] ?? '');
$kvalifikatsiya = trim($_POST['kvalifikatsiya'] ?? '');
$akademik_daraja_id = trim($_POST['akademik_daraja_id'] ?? '');
$talim_shakli_id = trim($_POST['talim_shakli_id'] ?? '');
$fakultet_id = trim($_POST['fakultet_id'] ?? '');
$kirish_yili = trim($_POST['kirish_yili'] ?? '');
$patok_soni = trim($_POST['patok_soni'] ?? '');
$kattaguruh_soni = trim($_POST['kattaguruh_soni'] ?? '');
$kichikguruh_soni = trim($_POST['kichikguruh_soni'] ?? '');
$sync_mode = strtolower(trim($_POST['sync_mode'] ?? 'nosync'));
if (!in_array($sync_mode, ['sync', 'nosync'], true)) {
    $sync_mode = 'nosync';
}

try {
    $db->query("START TRANSACTION");

if (
    $id <= 0 || $nomi === '' || $code === '' || $muddati === '' ||
    $kvalifikatsiya === '' || $akademik_daraja_id === '' ||
    $talim_shakli_id === '' || $fakultet_id === '' ||
    $kirish_yili === '' || $patok_soni === '' ||
    $kattaguruh_soni === '' || $kichikguruh_soni === ''
) {
    $db->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => "Ma'lumotlar to'liq emas"
    ]);
    return;
}

$old = $db->get_data_by_table('yonalishlar', ['id' => $id]);
if (!$old) {
    $db->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => "Yo'nalish topilmadi"
    ]);
    return;
}

$historySaved = $db->insert('yonalishlar_history', [
    'yonalish_id' => $old['id'],
    'name' => $old['name'],
    'code' => $old['code'],
    'muddati' => $old['muddati'],
    'kirish_yili' => $old['kirish_yili'],
    'patok_soni' => $old['patok_soni'],
    'kattaguruh_soni' => $old['kattaguruh_soni'],
    'kichikguruh_soni' => $old['kichikguruh_soni'],
    'akademik_daraja_id' => $old['akademik_daraja_id'],
    'talim_shakli_id' => $old['talim_shakli_id'],
    'kvalifikatsiya' => $old['kvalifikatsiya'],
    'fakultet_id' => $old['fakultet_id'],
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

$updated = $db->update('yonalishlar', [
    'name' => $nomi,
    'code' => $code,
    'muddati' => $muddati,
    'kvalifikatsiya' => $kvalifikatsiya,
    'akademik_daraja_id' => $akademik_daraja_id,
    'talim_shakli_id' => $talim_shakli_id,
    'fakultet_id' => $fakultet_id,
    'kirish_yili' => $kirish_yili,
    'patok_soni' => $patok_soni,
    'kattaguruh_soni' => $kattaguruh_soni,
    'kichikguruh_soni' => $kichikguruh_soni
], "id = $id");

if (!$updated) {
    $db->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => "Yo'nalishni tahrirlashda xatolik yuz berdi"
    ]);
    return;
}

$yonalishId = (int)$id;
$maxSemestr = max(0, (int)$muddati * 2);
$syncedTables = [];
$deletedSemestrRows = 0;
$blockedSemestrRows = 0;

// Izoh: Yo'nalish muddati kamayganda ortiqcha semestrlarni avtomatik tozalash.
// Izoh: Faqat bog'langan ma'lumotlari yo'q semestrlargina o'chiriladi.
if ($maxSemestr > 0) {
    $candidateIds = [];
    $candidateRes = $db->query("
        SELECT id
        FROM semestrlar
        WHERE yonalish_id = {$yonalishId}
          AND semestr > {$maxSemestr}
    ");
    while ($candidateRes && $candidateRow = mysqli_fetch_assoc($candidateRes)) {
        $candidateIds[] = (int)$candidateRow['id'];
    }

    if (!empty($candidateIds)) {
        $ids = implode(',', array_unique($candidateIds));
        $deletableIds = [];
        $deletableRes = $db->query("
            SELECT s.id
            FROM semestrlar s
            WHERE s.id IN ({$ids})
              AND NOT EXISTS (SELECT 1 FROM fanlar f WHERE f.semestr_id = s.id)
              AND NOT EXISTS (SELECT 1 FROM qoshimcha_fanlar qf WHERE qf.semestr_id = s.id)
              AND NOT EXISTS (SELECT 1 FROM umumtalim_fan_biriktirish ub WHERE ub.semestr_id = s.id)
              AND NOT EXISTS (SELECT 1 FROM chet_tili_guruhlar ct WHERE ct.semestr_id = s.id)
              AND NOT EXISTS (SELECT 1 FROM chet_tili_biriktirilgan_guruhlar bg WHERE bg.semestr_id = s.id)
        ");
        while ($deletableRes && $dRow = mysqli_fetch_assoc($deletableRes)) {
            $deletableIds[] = (int)$dRow['id'];
        }

        if (!empty($deletableIds)) {
            $deleteIds = implode(',', array_unique($deletableIds));
            $db->query("DELETE FROM semestrlar WHERE id IN ({$deleteIds})");
            $deletedSemestrRows = count($deletableIds);
        }

        $blockedSemestrRows = count($candidateIds) - $deletedSemestrRows;
    }
}

if ($deletedSemestrRows > 0 || $blockedSemestrRows > 0) {
    $syncedTables[] = "semestrlar(tozalandi: {$deletedSemestrRows}, band: {$blockedSemestrRows})";
}

// Izoh: Foydalanuvchi "sinxronlansin" desa bog'liq jadvallarni ham yangilaymiz.
if ($sync_mode === 'sync') {
    $fakultetId = (int)$fakultet_id;

    // 1) semestrlar jadvalida fakultet id ni yo'nalish bilan birga sync qilamiz.
    $db->query("UPDATE semestrlar SET fakultet_id = {$fakultetId} WHERE yonalish_id = {$yonalishId}");
    $syncedTables[] = 'semestrlar(fakultet_id)';

    // 2) muddati oshgan bo'lsa yetishmayotgan semestrlarni qo'shamiz.
    if ($maxSemestr > 0) {
        for ($sem = 1; $sem <= $maxSemestr; $sem++) {
            $db->query("
                INSERT IGNORE INTO semestrlar (fakultet_id, yonalish_id, semestr)
                VALUES ({$fakultetId}, {$yonalishId}, {$sem})
            ");
        }
        $syncedTables[] = 'semestrlar(semestrlar to\'ldirildi)';
    }

    // 3) Taqsimotga ta'sir qiladigan yo'nalishni "pending" holatga qo'yamiz.
    $eventId = $db->insert('taqsimot_resync_events', [
        'entity_type' => 'yonalish',
        'entity_id' => $yonalishId,
        'yonalish_id' => $yonalishId,
        'reason' => "Yo'nalish tahriri sabab qayta taqsimot talab qilinadi",
        'archived_rows' => 0,
        'status' => 'pending'
    ]);

    $archivedRows = 0;
    if ($eventId) {
        // 3.1) Asosiy o'quv reja bo'yicha taqsimotlarni arxivlaymiz.
        $aRejaIds = [];
        $aRes = $db->query("
            SELECT r.id
            FROM oquv_rejalar r
            JOIN fanlar f ON f.id = r.fan_id
            JOIN semestrlar s ON s.id = f.semestr_id
            WHERE s.yonalish_id = {$yonalishId}
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
                    'yonalish_id' => $yonalishId,
                    'event_id' => (int)$eventId,
                    'entity_type' => 'yonalish',
                    'entity_id' => $yonalishId
                ]);
                if ($ok) $archivedRows++;
            }
            $db->query("DELETE FROM taqsimotlar WHERE type = 'A' AND oquv_reja_id IN ($aIds)");
        }

        // 3.2) Qo'shimcha ishchi reja bo'yicha taqsimotlarni arxivlaymiz.
        $qRejaIds = [];
        $qRes = $db->query("
            SELECT q.id
            FROM qoshimcha_oquv_rejalar q
            JOIN qoshimcha_fanlar qf ON qf.id = q.qoshimcha_fanid
            JOIN semestrlar s ON s.id = qf.semestr_id
            WHERE s.yonalish_id = {$yonalishId}
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
                    'yonalish_id' => $yonalishId,
                    'event_id' => (int)$eventId,
                    'entity_type' => 'yonalish',
                    'entity_id' => $yonalishId
                ]);
                if ($ok) $archivedRows++;
            }
            $db->query("DELETE FROM taqsimotlar WHERE type = 'Q' AND oquv_reja_id IN ($qIds)");
        }

        $db->update('taqsimot_resync_events', [
            'archived_rows' => $archivedRows
        ], "id = {$eventId}");
    }
    $syncedTables[] = "taqsimotlar(archived: {$archivedRows})";
    $syncedTables[] = 'taqsimot_resync_events(pending)';
}

    $db->query("COMMIT");
    echo json_encode([
        'success' => true,
        'message' => "Yo'nalish tahrirlandi: " . ($sync_mode === 'sync' ? "sinxronlangan" : "sinxronlanmagan"),
        'synced_tables' => $syncedTables
    ]);
} catch (Throwable $e) {
    $db->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => "Yangilashda texnik xatolik yuz berdi"
    ]);
}
