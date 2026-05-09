<?php
    // Izoh: Chet tili biriktirishlarini (fan + semestr bo'yicha) o'chirish.
    include_once '../config.php';
    header('Content-Type: application/json');
    $db = new Database();

    $rowId = (int) ($_POST['id'] ?? 0);
    $fanId = (int) ($_POST['fan_id'] ?? 0);
    $semestrId = (int) ($_POST['semestr_id'] ?? 0);
    $semestrNum = (int) ($_POST['semestr_num'] ?? 0);
    $fanCode = '';
    if ($fanId > 0) {
        $fanRow = $db->get_data_by_table('fanlar', ['id' => $fanId]);
        $fanCode = trim((string)($fanRow['fan_code'] ?? ''));
    }

    $joinSql = '';
    $whereSql = '';

    if ($rowId > 0) {
        $whereSql = "ct.id = $rowId";
    } elseif ($fanId > 0 && $semestrNum > 0) {
        // Izoh: Fan kodi + semestr raqami bo'yicha guruhni o'chirish (eski dublikatlarni ham qamrab oladi).
        if ($fanCode !== '') {
            $safeFanCode = addslashes($fanCode);
            $joinSql = "
                JOIN semestrlar s ON s.id = ct.semestr_id
                JOIN fanlar bf ON bf.id = ct.fan_id
            ";
            $whereSql = "
                s.semestr = $semestrNum
                AND bf.tanlov_fan = 3
                AND (bf.kafedra_id = 0 OR bf.kafedra_id IS NULL OR bf.kafedra_id = '')
                AND bf.fan_code = '$safeFanCode'
            ";
        } else {
            $joinSql = "JOIN semestrlar s ON s.id = ct.semestr_id";
            $whereSql = "ct.fan_id = $fanId AND s.semestr = $semestrNum";
        }
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
        $db->query("
            DELETE FROM chet_tili_biriktirilgan_guruhlar
            WHERE semestr_id IN ($semestrSql) AND fan_id IN ($fanSql)
        ");

        // Izoh: Til bo'yicha hosil qilingan o'quv guruhlarini ham tozalaymiz.
        $semestrNums = [];
        $numRes = $db->query("
            SELECT DISTINCT semestr
            FROM semestrlar
            WHERE id IN ($semestrSql)
        ");
        if ($numRes) {
            while ($numRow = mysqli_fetch_assoc($numRes)) {
                $num = (int)($numRow['semestr'] ?? 0);
                if ($num > 0) {
                    $semestrNums[$num] = true;
                }
            }
        }

        if (count($semestrNums) > 0) {
            $numSql = implode(',', array_map('intval', array_keys($semestrNums)));
            $oldGroupIds = [];
            $oldRes = $db->query("
                SELECT id
                FROM chet_tili_oquv_guruhlar
                WHERE semestr_num IN ($numSql) AND fan_id IN ($fanSql)
            ");
            if ($oldRes) {
                while ($oldRow = mysqli_fetch_assoc($oldRes)) {
                    $oid = (int)($oldRow['id'] ?? 0);
                    if ($oid > 0) {
                        $oldGroupIds[] = $oid;
                    }
                }
            }

            if (count($oldGroupIds) > 0) {
                $oldIdsSql = implode(',', array_map('intval', $oldGroupIds));
                $db->query("
                    DELETE FROM chet_tili_oquv_guruh_items
                    WHERE oquv_guruh_id IN ($oldIdsSql)
                ");
                $db->query("
                    DELETE FROM chet_tili_oquv_guruhlar
                    WHERE id IN ($oldIdsSql)
                ");
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Chet tili biriktirishlari o\'chirildi']);
?>
