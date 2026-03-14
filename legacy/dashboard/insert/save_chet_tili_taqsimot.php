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

    // Izoh: Fallback - ishchi_oquv_reja_variants bo'sh bo'lsa ham fan_code+semestr bo'yicha variantlarni topamiz.
    $baseCode = trim((string)($baseFan['fan_code'] ?? ''));
    if ($baseCode !== '' && $baseSemestrId > 0) {
        $safeCode = addslashes($baseCode);
        $fallbackVariantRes = $db->query("
            SELECT vf.id AS fan_id
            FROM fanlar vf
            WHERE vf.tanlov_fan = 3
              AND vf.semestr_id = $baseSemestrId
              AND vf.fan_code = '$safeCode'
              AND vf.kafedra_id > 0
              AND vf.id <> $baseFanId
            ORDER BY vf.id
        ");
        if ($fallbackVariantRes) {
            while ($row = mysqli_fetch_assoc($fallbackVariantRes)) {
                $fanId = (int)($row['fan_id'] ?? 0);
                if ($fanId > 0 && !in_array($fanId, $variantIds, true)) {
                    $variantIds[] = $fanId;
                }
            }
        }
    }

    if (count($variantIds) === 0) {
        echo json_encode(['success' => false, 'message' => "Bazaviy fan uchun variant fanlar topilmadi"]);
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
    $safeBaseCode = addslashes($baseCode);

    // Izoh: Shu fan_code + semestr bo'yicha oldingi biriktirish variantlarini ham tozalash uchun yig'amiz.
    $cleanupFanIdMap = [];
    foreach ($variantIds as $fanId) {
        $cleanupFanIdMap[(int)$fanId] = true;
    }
    if ($semestrSql !== '' && $safeBaseCode !== '') {
        $legacyRes = $db->query("
            SELECT ct.source_fan_ids
            FROM chet_tili_guruhlar ct
            JOIN semestrlar s ON s.id = ct.semestr_id
            JOIN fanlar bf ON bf.id = ct.fan_id
            WHERE ct.semestr_id IN ($semestrSql)
              AND s.semestr = $baseSemestrNum
              AND bf.tanlov_fan = 3
              AND (bf.kafedra_id = 0 OR bf.kafedra_id IS NULL OR bf.kafedra_id = '')
              AND bf.fan_code = '$safeBaseCode'
        ");
        if ($legacyRes) {
            while ($legacyRow = mysqli_fetch_assoc($legacyRes)) {
                $raw = trim((string)($legacyRow['source_fan_ids'] ?? ''));
                if ($raw === '') {
                    continue;
                }
                $parts = explode(',', $raw);
                foreach ($parts as $part) {
                    $oldFanId = (int)trim($part);
                    if ($oldFanId > 0) {
                        $cleanupFanIdMap[$oldFanId] = true;
                    }
                }
            }
        }
    }
    $cleanupVariantIds = array_map('intval', array_keys($cleanupFanIdMap));
    $cleanupVariantSql = implode(',', $cleanupVariantIds);

    // Izoh: Har bir til (variant fan) bo'yicha yakuniy bitta o'quv guruh hosil qilish uchun agregatlar.
    $variantTotals = [];
    $variantItems = [];
    foreach ($variantIds as $fanId) {
        $variantTotals[$fanId] = 0;
        $variantItems[$fanId] = [];
    }
    foreach ($rowsToInsert as $row) {
        $fanId = (int)$row['fan_id'];
        $cnt = (int)$row['talabalar_soni'];
        if (!isset($variantTotals[$fanId])) {
            $variantTotals[$fanId] = 0;
            $variantItems[$fanId] = [];
        }
        $variantTotals[$fanId] += $cnt;
        $variantItems[$fanId][] = [
            'semestr_id' => (int)$row['semestr_id'],
            'yonalish_id' => (int)$row['yonalish_id'],
            'guruh_id' => (int)$row['guruh_id'],
            'talabalar_soni' => $cnt,
        ];
    }

    $db->query("START TRANSACTION");
    $ok = true;

    if ($semestrSql !== '' && $cleanupVariantSql !== '') {
        $ok = $ok && $db->query("
            DELETE FROM chet_tili_talablar
            WHERE semestr_id IN ($semestrSql) AND fan_id IN ($cleanupVariantSql)
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
            JOIN fanlar bf ON bf.id = ct.fan_id
            WHERE ct.semestr_id IN ($semestrSql)
              AND s.semestr = $baseSemestrNum
              AND bf.tanlov_fan = 3
              AND (bf.kafedra_id = 0 OR bf.kafedra_id IS NULL OR bf.kafedra_id = '')
              AND bf.fan_code = '$safeBaseCode'
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

    // Izoh: Eski avtomatik o'quv guruhlarini tozalab, har til uchun bitta guruh qayta yaratamiz.
    if ($ok && $cleanupVariantSql !== '') {
        $oldOquvGroupIds = [];
        $oldRes = $db->query("
            SELECT id
            FROM chet_tili_oquv_guruhlar
            WHERE semestr_num = $baseSemestrNum AND fan_id IN ($cleanupVariantSql)
        ");
        if ($oldRes) {
            while ($oldRow = mysqli_fetch_assoc($oldRes)) {
                $oid = (int)($oldRow['id'] ?? 0);
                if ($oid > 0) {
                    $oldOquvGroupIds[] = $oid;
                }
            }
        }

        if (count($oldOquvGroupIds) > 0) {
            $oldIdsSql = implode(',', array_map('intval', $oldOquvGroupIds));
            $ok = $ok && $db->query("
                DELETE FROM chet_tili_oquv_guruh_items
                WHERE oquv_guruh_id IN ($oldIdsSql)
            ");
            $ok = $ok && $db->query("
                DELETE FROM chet_tili_oquv_guruhlar
                WHERE id IN ($oldIdsSql)
            ");
        }
    }

    if ($ok) {
        foreach ($variantIds as $fanId) {
            $fanId = (int)$fanId;
            $total = (int)($variantTotals[$fanId] ?? 0);
            if ($total <= 0) {
                continue;
            }

            $oquvGuruhId = (int)$db->insert('chet_tili_oquv_guruhlar', [
                'semestr_num' => $baseSemestrNum,
                'fan_id' => $fanId,
                'guruh_no' => 1,
                'jami_talaba' => $total,
                'status' => 'ready',
            ]);
            if ($oquvGuruhId <= 0) {
                $ok = false;
                break;
            }

            $items = $variantItems[$fanId] ?? [];
            foreach ($items as $item) {
                $ok = $ok && $db->query("
                    INSERT INTO chet_tili_oquv_guruh_items
                        (oquv_guruh_id, semestr_id, yonalish_id, guruh_id, source_fan_id, talabalar_soni)
                    VALUES (
                        $oquvGuruhId,
                        " . (int)$item['semestr_id'] . ",
                        " . (int)$item['yonalish_id'] . ",
                        " . (int)$item['guruh_id'] . ",
                        $fanId,
                        " . (int)$item['talabalar_soni'] . "
                    )
                ");
                if (!$ok) {
                    break 2;
                }
            }
        }
    }

    if ($ok) {
        $db->query("COMMIT");
        $createdGroups = 0;
        foreach ($variantTotals as $sum) {
            if ((int)$sum > 0) {
                $createdGroups++;
            }
        }
        echo json_encode([
            'success' => true,
            'message' => "Biriktirish saqlandi (" . count($rowsToInsert) . " ta qator, {$createdGroups} ta til guruhi)"
        ]);
    } else {
        $db->query("ROLLBACK");
        echo json_encode(['success' => false, 'message' => "Saqlashda xatolik yuz berdi"]);
    }
?>
