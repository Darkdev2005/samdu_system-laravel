<?php
    // Izoh: Chet tili biriktirishlarini (fan + semestr bo'yicha) o'chirish.
    include_once '../config.php';
    header('Content-Type: application/json');
    $db = new Database();

    $rowId = (int) ($_POST['id'] ?? 0);
    $fanId = (int) ($_POST['fan_id'] ?? 0);
    $semestrId = (int) ($_POST['semestr_id'] ?? 0);
    $semestrNum = (int) ($_POST['semestr_num'] ?? 0);

    $joinSql = '';
    $whereSql = '';

    if ($rowId > 0) {
        $whereSql = "ct.id = $rowId";
    } elseif ($fanId > 0 && $semestrNum > 0) {
        // Izoh: Fan + semestr raqami bo'yicha guruhni o'chirish.
        $joinSql = "JOIN semestrlar s ON s.id = ct.semestr_id";
        $whereSql = "ct.fan_id = $fanId AND s.semestr = $semestrNum";
    } elseif ($fanId > 0 && $semestrId > 0) {
        // Izoh: Eski format uchun fallback.
        $whereSql = "ct.fan_id = $fanId AND ct.semestr_id = $semestrId";
    } else {
        echo json_encode(['success' => false, 'message' => 'Ma\'lumotlar to\'liq emas']);
        return;
    }

    // Izoh: O'chirishdan oldin bog'langan semestr va variant fanlarini yig'ib olamiz.
    $targetSemestrIds = [];
    $targetSourceFanIds = [];
    $targetRes = $db->query("
        SELECT ct.semestr_id, ct.source_fan_ids
        FROM chet_tili_guruhlar ct
        $joinSql
        WHERE $whereSql
    ");
    if ($targetRes) {
        while ($row = mysqli_fetch_assoc($targetRes)) {
            $sid = (int)($row['semestr_id'] ?? 0);
            if ($sid > 0) {
                $targetSemestrIds[$sid] = true;
            }
            $sourceRaw = trim((string)($row['source_fan_ids'] ?? ''));
            if ($sourceRaw !== '') {
                $parts = explode(',', $sourceRaw);
                foreach ($parts as $part) {
                    $fid = (int)trim($part);
                    if ($fid > 0) {
                        $targetSourceFanIds[$fid] = true;
                    }
                }
            }
        }
    }

    $db->query("
        DELETE ct FROM chet_tili_guruhlar ct
        $joinSql
        WHERE $whereSql
    ");

    // Izoh: Taqsimot jadvalida ham shu biriktirishga tegishli satrlarni tozalaymiz.
    if (count($targetSemestrIds) > 0 && count($targetSourceFanIds) > 0) {
        $semestrSql = implode(',', array_map('intval', array_keys($targetSemestrIds)));
        $fanSql = implode(',', array_map('intval', array_keys($targetSourceFanIds)));
        $db->query("
            DELETE FROM chet_tili_talablar
            WHERE semestr_id IN ($semestrSql) AND fan_id IN ($fanSql)
        ");
    }

    echo json_encode(['success' => true, 'message' => 'Chet tili biriktirishlari o\'chirildi']);
?>
