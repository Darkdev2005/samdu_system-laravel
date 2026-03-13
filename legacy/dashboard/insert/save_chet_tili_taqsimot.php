<?php
    // Izoh: Chet tili variantlariga guruhlar kesimida talabalarni taqsimlashni saqlash.
    include_once '../config.php';
    header('Content-Type: application/json');
    $db = new Database();

    $baseFanId = (int)($_POST['base_fan_id'] ?? 0);
    $semestrIdsRaw = json_decode((string)($_POST['semestr_ids_json'] ?? '[]'), true);
    $allocationsRaw = json_decode((string)($_POST['allocations_json'] ?? '{}'), true);

    if ($baseFanId <= 0 || !is_array($semestrIdsRaw) || !is_array($allocationsRaw)) {
        echo json_encode(['success' => false, 'message' => "Ma'lumotlar to'liq emas"]);
        return;
    }

    $baseFan = $db->get_data_by_table('fanlar', ['id' => $baseFanId]);
    if (!$baseFan || (int)($baseFan['tanlov_fan'] ?? 0) !== 3) {
        echo json_encode(['success' => false, 'message' => "Bazaviy chet tili fan topilmadi"]);
        return;
    }

    $baseSemestrId = (int)($baseFan['semestr_id'] ?? 0);
    $baseSemestrRow = $db->get_data_by_table('semestrlar', ['id' => $baseSemestrId]);
    $baseSemestrNum = (int)($baseSemestrRow['semestr'] ?? 0);
    if ($baseSemestrNum <= 0) {
        echo json_encode(['success' => false, 'message' => "Bazaviy fan semestri topilmadi"]);
        return;
    }

    $variantIds = [];
    $variantRes = $db->query("
        SELECT iv.fan_id
        FROM ishchi_oquv_reja ir
        JOIN ishchi_oquv_reja_variants iv ON iv.ishchi_reja_id = ir.id
        WHERE ir.base_fan_id = $baseFanId
        ORDER BY iv.fan_id
    ");
    if ($variantRes) {
        while ($row = mysqli_fetch_assoc($variantRes)) {
            $fanId = (int)($row['fan_id'] ?? 0);
            if ($fanId > 0 && !in_array($fanId, $variantIds, true)) {
                $variantIds[] = $fanId;
            }
        }
    }

    if (count($variantIds) === 0) {
        echo json_encode(['success' => false, 'message' => "Bazaviy fan uchun variant fanlar topilmadi"]);
        return;
    }

    $semestrIds = [];
    foreach ($semestrIdsRaw as $idRaw) {
        $id = (int)$idRaw;
        if ($id > 0 && !in_array($id, $semestrIds, true)) {
            $semestrIds[] = $id;
        }
    }
    if (count($semestrIds) === 0) {
        echo json_encode(['success' => false, 'message' => "Kamida bitta yo'nalish+semestr tanlang"]);
        return;
    }

    $semestrMap = [];
    $yonalishIds = [];
    foreach ($semestrIds as $semestrId) {
        $semestr = $db->get_data_by_table('semestrlar', ['id' => $semestrId]);
        if (!$semestr) {
            echo json_encode(['success' => false, 'message' => "Semestr topilmadi: {$semestrId}"]);
            return;
        }

        $semestrNum = (int)($semestr['semestr'] ?? 0);
        if ($semestrNum !== $baseSemestrNum) {
            echo json_encode([
                'success' => false,
                'message' => "Tanlangan yo'nalish semestri {$baseSemestrNum}-semestr bo'lishi shart"
            ]);
            return;
        }

        $yonalishId = (int)($semestr['yonalish_id'] ?? 0);
        if ($yonalishId <= 0) {
            echo json_encode(['success' => false, 'message' => "Semestrga yo'nalish biriktirilmagan"]);
            return;
        }
        $yonalishIds[$yonalishId] = true;

        $groups = [];
        $groupRes = $db->query("
            SELECT id, soni
            FROM guruhlar
            WHERE yonalish_id = $yonalishId
            ORDER BY guruh_nomer
        ");
        if ($groupRes) {
            while ($groupRow = mysqli_fetch_assoc($groupRes)) {
                $guruhId = (int)($groupRow['id'] ?? 0);
                if ($guruhId <= 0) {
                    continue;
                }
                $groups[$guruhId] = (int)($groupRow['soni'] ?? 0);
            }
        }

        if (count($groups) === 0) {
            echo json_encode(['success' => false, 'message' => "Tanlangan yo'nalishda guruh topilmadi"]);
            return;
        }

        $semestrMap[$semestrId] = [
            'yonalish_id' => $yonalishId,
            'groups' => $groups,
        ];
    }

    $rowsToInsert = [];
    foreach ($semestrMap as $semestrId => $meta) {
        foreach ($meta['groups'] as $guruhId => $groupSize) {
            $sum = 0;
            $groupAlloc = $allocationsRaw[(string)$semestrId][(string)$guruhId] ?? [];
            if (!is_array($groupAlloc)) {
                $groupAlloc = [];
            }

            foreach ($variantIds as $fanId) {
                $val = (int)($groupAlloc[(string)$fanId] ?? 0);
                if ($val < 0) {
                    echo json_encode(['success' => false, 'message' => "Manfiy qiymat kiritib bo'lmaydi"]);
                    return;
                }
                $sum += $val;

                if ($val > 0) {
                    $rowsToInsert[] = [
                        'semestr_id' => (int)$semestrId,
                        'yonalish_id' => (int)$meta['yonalish_id'],
                        'guruh_id' => (int)$guruhId,
                        'fan_id' => (int)$fanId,
                        'talabalar_soni' => $val,
                    ];
                }
            }

            if ($sum !== (int)$groupSize) {
                echo json_encode([
                    'success' => false,
                    'message' => "Guruh #{$guruhId} uchun yig'indi {$sum}, keraklisi {$groupSize}"
                ]);
                return;
            }
        }
    }

    $variantSql = implode(',', array_map('intval', $variantIds));
    $semestrSql = implode(',', array_map('intval', array_keys($semestrMap)));
    $yonalishSql = implode(',', array_map('intval', array_keys($yonalishIds)));
    $sourceFanIds = implode(',', array_map('intval', $variantIds));

    $db->query("START TRANSACTION");
    $ok = true;

    if ($semestrSql !== '' && $variantSql !== '') {
        $ok = $ok && $db->query("
            DELETE FROM chet_tili_talablar
            WHERE semestr_id IN ($semestrSql) AND fan_id IN ($variantSql)
        ");
    }

    if ($ok) {
        foreach ($rowsToInsert as $row) {
            $ok = $ok && $db->query("
                INSERT INTO chet_tili_talablar (semestr_id, yonalish_id, guruh_id, fan_id, talabalar_soni)
                VALUES (
                    " . (int)$row['semestr_id'] . ",
                    " . (int)$row['yonalish_id'] . ",
                    " . (int)$row['guruh_id'] . ",
                    " . (int)$row['fan_id'] . ",
                    " . (int)$row['talabalar_soni'] . "
                )
            ");
            if (!$ok) {
                break;
            }
        }
    }

    if ($ok) {
        $ok = $ok && $db->query("
            DELETE ct FROM chet_tili_guruhlar ct
            JOIN semestrlar s ON s.id = ct.semestr_id
            WHERE ct.fan_id = $baseFanId AND s.semestr = $baseSemestrNum
        ");
    }

    if ($ok) {
        foreach ($semestrMap as $semestrId => $meta) {
            $yonalishId = (int)$meta['yonalish_id'];
            $ok = $ok && $db->query("
                INSERT INTO chet_tili_guruhlar (fan_id, yonalish_id, semestr_id, yonalish_ids, source_fan_ids, guruh_no)
                VALUES (
                    $baseFanId,
                    $yonalishId,
                    " . (int)$semestrId . ",
                    '$yonalishSql',
                    '$sourceFanIds',
                    1
                )
            ");
            if (!$ok) {
                break;
            }
        }
    }

    if ($ok) {
        $db->query("COMMIT");
        echo json_encode([
            'success' => true,
            'message' => "Biriktirish saqlandi (" . count($rowsToInsert) . " ta qator)"
        ]);
    } else {
        $db->query("ROLLBACK");
        echo json_encode(['success' => false, 'message' => "Saqlashda xatolik yuz berdi"]);
    }
?>
