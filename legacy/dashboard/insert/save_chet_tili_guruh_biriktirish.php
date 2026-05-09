<?php
    // Izoh: Chet tili bo'yicha tanlangan guruhlarni qo'lda biriktirishni saqlash.
    include_once dirname(__DIR__) . '/config.php';
    header('Content-Type: application/json; charset=utf-8');
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '1');
    @set_time_limit(120);

    function respond_json(array $payload, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    register_shutdown_function(static function (): void {
        $error = error_get_last();
        if (!is_array($error)) {
            return;
        }
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array((int)($error['type'] ?? 0), $fatalTypes, true)) {
            return;
        }
        respond_json([
            'success' => false,
            'message' => "Serverda kritik xatolik yuz berdi",
            'error_hint' => trim((string)($error['message'] ?? '')),
        ], 500);
    });

    /**
     * Izoh: Til nomlarini bir xil kalitga tushiramiz (masalan: "Ingliz tili" => "ingliz").
     */
    function normalize_language_name(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $normalized = (string)mb_strtolower($normalized, 'UTF-8');
        } else {
            $normalized = strtolower($normalized);
        }

        $normalized = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        $normalized = preg_replace('/\b(chet|xorijiy|foreign)\b/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\btili?\b/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        if ($normalized === '') {
            return 'xorijiy_til';
        }

        return $normalized;
    }

    /**
     * Izoh: Bir xil semestr_id + umumiy til nomi uchun canonical fan/semestr juftligini topamiz.
     * Avval aynan shu semestr_id ichidan qidiramiz; topilmasa semestr raqami bo'yicha fallback qilamiz.
     */
    function resolve_target_fan_binding(Database $db, int $fanId): array
    {
        if ($fanId <= 0) {
            return ['fan_id' => 0, 'semestr_id' => 0, 'candidate_fan_ids' => []];
        }

        // Izoh: Tilni aniqlashda aynan tanlangan fan ishlatiladi; base_fan_id'ga o'tmaymiz.
        $fan = $db->get_data_by_table('fanlar', ['id' => $fanId]);
        if (!$fan) {
            return ['fan_id' => $fanId, 'semestr_id' => 0, 'candidate_fan_ids' => []];
        }

        $sourceSemestrId = (int)($fan['semestr_id'] ?? 0);
        $sourceFanCode = trim((string)($fan['fan_code'] ?? ''));
        $fanNameKey = normalize_language_name((string)($fan['fan_name'] ?? ''));
        if ($sourceSemestrId <= 0 || $fanNameKey === '') {
            return [
                'fan_id' => (int)($fan['id'] ?? $fanId),
                'semestr_id' => $sourceSemestrId,
                'candidate_fan_ids' => [(int)($fan['id'] ?? $fanId)],
            ];
        }

        // Izoh: Source fan allaqachon o'quv rejaga ega bo'lsa, boshqa fanlarga map qilmaymiz.
        $sourceRejaRes = $db->query("
            SELECT COUNT(*) AS cnt
            FROM oquv_rejalar
            WHERE fan_id = {$fanId}
        ");
        $sourceRejaCount = 0;
        if ($sourceRejaRes) {
            $sourceRejaRow = mysqli_fetch_assoc($sourceRejaRes);
            $sourceRejaCount = (int)($sourceRejaRow['cnt'] ?? 0);
        }
        if ($sourceRejaCount > 0) {
            return [
                'fan_id' => (int)$fanId,
                'semestr_id' => $sourceSemestrId,
                'candidate_fan_ids' => [(int)$fanId],
            ];
        }

        $semestrRow = $db->get_data_by_table('semestrlar', ['id' => $sourceSemestrId]);
        $semestrNum = (int)($semestrRow['semestr'] ?? 0);
        if ($semestrNum <= 0) {
            return [
                'fan_id' => (int)($fan['id'] ?? $fanId),
                'semestr_id' => $sourceSemestrId,
                'candidate_fan_ids' => [(int)($fan['id'] ?? $fanId)],
            ];
        }

        $candidates = [];
        $res = $db->query("
            SELECT
                f.id,
                f.semestr_id,
                f.fan_code,
                f.fan_name,
                k.name AS kafedra_name,
                (SELECT COUNT(*) FROM oquv_rejalar r WHERE r.fan_id = f.id) AS reja_count
            FROM fanlar f
            JOIN semestrlar s ON s.id = f.semestr_id
            LEFT JOIN kafedralar k ON k.id = f.kafedra_id
            WHERE f.tanlov_fan = 3
              AND (
                    f.semestr_id = {$sourceSemestrId}
                    OR s.semestr = {$semestrNum}
                  )
            ORDER BY f.id
        ");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $candidateId = (int)($row['id'] ?? 0);
                $candidateSemestrId = (int)($row['semestr_id'] ?? 0);
                if ($candidateId <= 0 || $candidateSemestrId <= 0) {
                    continue;
                }

                $candidateKey = normalize_language_name((string)($row['fan_name'] ?? ''));
                if ($candidateKey !== $fanNameKey) {
                    continue;
                }

                $kafedraNameKey = normalize_language_name((string)($row['kafedra_name'] ?? ''));
                $kafedraMatch = false;
                if ($kafedraNameKey !== '' && $fanNameKey !== '') {
                    $kafedraMatch = strpos(' ' . $kafedraNameKey . ' ', ' ' . $fanNameKey . ' ') !== false;
                }

                $candidates[] = [
                    'id' => $candidateId,
                    'semestr_id' => $candidateSemestrId,
                    'reja_count' => (int)($row['reja_count'] ?? 0),
                    'kafedra_match' => $kafedraMatch ? 1 : 0,
                    'same_semestr_id' => $candidateSemestrId === $sourceSemestrId ? 1 : 0,
                    'same_fan_code' => trim((string)($row['fan_code'] ?? '')) !== '' && trim((string)($row['fan_code'] ?? '')) === $sourceFanCode ? 1 : 0,
                    'is_source_id' => $candidateId === $fanId ? 1 : 0,
                ];
            }
        }

        if (count($candidates) === 0) {
            return [
                'fan_id' => (int)($fan['id'] ?? $fanId),
                'semestr_id' => $sourceSemestrId,
                'candidate_fan_ids' => [(int)($fan['id'] ?? $fanId)],
            ];
        }

        usort($candidates, static function (array $a, array $b): int {
            $sourceCmp = ((int)$b['is_source_id']) <=> ((int)$a['is_source_id']);
            if ($sourceCmp !== 0) {
                return $sourceCmp;
            }
            $sameSemestrCmp = ((int)$b['same_semestr_id']) <=> ((int)$a['same_semestr_id']);
            if ($sameSemestrCmp !== 0) {
                return $sameSemestrCmp;
            }
            $sameCodeCmp = ((int)$b['same_fan_code']) <=> ((int)$a['same_fan_code']);
            if ($sameCodeCmp !== 0) {
                return $sameCodeCmp;
            }
            $kafedraCmp = ((int)$b['kafedra_match']) <=> ((int)$a['kafedra_match']);
            if ($kafedraCmp !== 0) {
                return $kafedraCmp;
            }
            $rejaCmp = ((int)$b['reja_count']) <=> ((int)$a['reja_count']);
            if ($rejaCmp !== 0) {
                return $rejaCmp;
            }
            return ((int)$a['id']) <=> ((int)$b['id']);
        });

        $candidateFanIds = [];
        foreach ($candidates as $candidate) {
            $cid = (int)($candidate['id'] ?? 0);
            if ($cid > 0) {
                $candidateFanIds[$cid] = true;
            }
        }

        return [
            'fan_id' => (int)$candidates[0]['id'],
            'semestr_id' => (int)$candidates[0]['semestr_id'],
            'candidate_fan_ids' => array_map('intval', array_keys($candidateFanIds)),
        ];
    }

    /**
     * Izoh: Tanlangan til fani yuklamada ko'rinishi uchun target fan uchun minimal o'quv reja mavjudligini ta'minlaymiz.
     * Avval shu semestrdagi mos donor fan rejasidan ko'chiramiz, bo'lmasa 1-4 tur uchun 0 soat yaratamiz.
     */
    function ensure_target_fan_has_oquv_reja(Database $db, int $targetFanId, int $targetSemestrId, string $targetLangKey): void
    {
        try {
            if ($targetFanId <= 0 || $targetSemestrId <= 0) {
                return;
            }

            $existingRes = $db->query("
                SELECT COUNT(*) AS cnt
                FROM oquv_rejalar
                WHERE fan_id = {$targetFanId}
            ");
            $existingCount = 0;
            if ($existingRes) {
                $existingRow = mysqli_fetch_assoc($existingRes);
                $existingCount = (int)($existingRow['cnt'] ?? 0);
            }
            if ($existingCount > 0) {
                return;
            }

            $targetSemestrRow = $db->get_data_by_table('semestrlar', ['id' => $targetSemestrId]);
            $targetSemestrNum = (int)($targetSemestrRow['semestr'] ?? 0);
            if ($targetSemestrNum <= 0) {
                return;
            }

            $donorFanId = 0;
            $donorBestScore = -1;
            $candidateRes = $db->query("
                SELECT
                    f.id,
                    f.fan_name,
                    (SELECT COUNT(*) FROM oquv_rejalar r WHERE r.fan_id = f.id) AS reja_count
                FROM fanlar f
                JOIN semestrlar s ON s.id = f.semestr_id
                WHERE s.semestr = {$targetSemestrNum}
                  AND f.id <> {$targetFanId}
                ORDER BY f.id
            ");
            if ($candidateRes) {
                while ($candidate = mysqli_fetch_assoc($candidateRes)) {
                    $candidateId = (int)($candidate['id'] ?? 0);
                    $candidateReja = (int)($candidate['reja_count'] ?? 0);
                    if ($candidateId <= 0 || $candidateReja <= 0) {
                        continue;
                    }

                    $candidateLangKey = normalize_language_name((string)($candidate['fan_name'] ?? ''));
                    $score = $candidateLangKey === $targetLangKey ? 2 : 1;
                    if ($score > $donorBestScore) {
                        $donorBestScore = $score;
                        $donorFanId = $candidateId;
                    }
                }
            }

            $templateRows = [];
            if ($donorFanId > 0) {
                $templateRes = $db->query("
                    SELECT dars_tur_id, dars_soat
                    FROM oquv_rejalar
                    WHERE fan_id = {$donorFanId}
                ");
                if ($templateRes) {
                    while ($templateRow = mysqli_fetch_assoc($templateRes)) {
                        $darsTurId = (int)($templateRow['dars_tur_id'] ?? 0);
                        if ($darsTurId <= 0) {
                            continue;
                        }
                        $templateRows[$darsTurId] = (float)($templateRow['dars_soat'] ?? 0);
                    }
                }
            }

            if (count($templateRows) === 0) {
                foreach ([1, 2, 3, 4] as $defaultTurId) {
                    $templateRows[$defaultTurId] = 0;
                }
            }

            foreach ($templateRows as $darsTurId => $darsSoat) {
                $darsTurId = (int)$darsTurId;
                if ($darsTurId <= 0) {
                    continue;
                }
                $db->insert('oquv_rejalar', [
                    'fan_id' => (string)$targetFanId,
                    'dars_tur_id' => (string)$darsTurId,
                    'dars_soat' => (string)$darsSoat,
                    'izoh' => '',
                ]);
            }
        } catch (Throwable $e) {
            // Izoh: Auto o'quv reja yaratish xatosi biriktirish endpointini yiqitmasin.
            error_log('ensure_target_fan_has_oquv_reja error: ' . $e->getMessage());
            return;
        }
    }

    $requestStart = microtime(true);
    $db = new Database();
    $scopeItemsRaw = json_decode((string)($_POST['scope_items_json'] ?? '[]'), true);
    if (!is_array($scopeItemsRaw) || count($scopeItemsRaw) === 0) {
        respond_json(['success' => false, 'message' => "Ma'lumotlar to'liq emas"], 422);
    }

    $scopeItems = [];
    $scopeKeyMap = [];

    foreach ($scopeItemsRaw as $item) {
        if (!is_array($item) || empty($item['selected'])) {
            continue;
        }

        $semestrId = (int)($item['semestr_id'] ?? 0);
        $yonalishId = (int)($item['yonalish_id'] ?? 0);
        $guruhId = (int)($item['guruh_id'] ?? 0);
        $fanId = (int)($item['fan_id'] ?? 0);
        if ($semestrId <= 0 || $yonalishId <= 0 || $guruhId <= 0 || $fanId <= 0) {
            respond_json(['success' => false, 'message' => "Tanlangan guruh ma'lumoti noto'g'ri"], 422);
        }

        $key = $semestrId . '|' . $guruhId . '|' . $fanId;
        if (isset($scopeKeyMap[$key])) {
            continue;
        }
        $scopeKeyMap[$key] = true;

        $scopeItems[] = [
            'semestr_id' => $semestrId,
            'yonalish_id' => $yonalishId,
            'guruh_id' => $guruhId,
            'fan_id' => $fanId,
        ];
    }

    if (count($scopeItems) === 0) {
        respond_json(['success' => false, 'message' => "Kamida bitta guruhni belgilang"], 422);
    }

    // Izoh: Tezlik uchun kerakli jadval yozuvlarini bir martada olib map qilamiz.
    $scopeSemestrIds = [];
    $scopeGuruhIds = [];
    $scopeFanIds = [];
    foreach ($scopeItems as $row) {
        $scopeSemestrIds[(int)$row['semestr_id']] = true;
        $scopeGuruhIds[(int)$row['guruh_id']] = true;
        $scopeFanIds[(int)$row['fan_id']] = true;
    }
    $scopeSemestrIds = array_values(array_map('intval', array_keys($scopeSemestrIds)));
    $scopeGuruhIds = array_values(array_map('intval', array_keys($scopeGuruhIds)));
    $scopeFanIds = array_values(array_map('intval', array_keys($scopeFanIds)));

    $sourceMap = [];
    if (!empty($scopeSemestrIds) && !empty($scopeGuruhIds) && !empty($scopeFanIds)) {
        $semestrSql = implode(',', $scopeSemestrIds);
        $guruhSql = implode(',', $scopeGuruhIds);
        $fanSql = implode(',', $scopeFanIds);
        $sourceRes = $db->query("
            SELECT semestr_id, guruh_id, fan_id, talabalar_soni
            FROM chet_tili_talablar
            WHERE semestr_id IN ({$semestrSql})
              AND guruh_id IN ({$guruhSql})
              AND fan_id IN ({$fanSql})
        ");
        if ($sourceRes) {
            while ($src = mysqli_fetch_assoc($sourceRes)) {
                $k = (int)($src['semestr_id'] ?? 0) . '|' . (int)($src['guruh_id'] ?? 0) . '|' . (int)($src['fan_id'] ?? 0);
                $sourceMap[$k] = $src;
            }
        }
    }

    $guruhMap = [];
    if (!empty($scopeGuruhIds)) {
        $guruhSql = implode(',', $scopeGuruhIds);
        $guruhRes = $db->query("
            SELECT id, yonalish_id
            FROM guruhlar
            WHERE id IN ({$guruhSql})
        ");
        if ($guruhRes) {
            while ($g = mysqli_fetch_assoc($guruhRes)) {
                $gid = (int)($g['id'] ?? 0);
                if ($gid > 0) {
                    $guruhMap[$gid] = $g;
                }
            }
        }
    }

    $fanMap = [];
    $fanSemestrIds = [];
    if (!empty($scopeFanIds)) {
        $fanSql = implode(',', $scopeFanIds);
        $fanRes = $db->query("
            SELECT id, semestr_id, tanlov_fan, fan_name
            FROM fanlar
            WHERE id IN ({$fanSql})
        ");
        if ($fanRes) {
            while ($f = mysqli_fetch_assoc($fanRes)) {
                $fid = (int)($f['id'] ?? 0);
                if ($fid <= 0) {
                    continue;
                }
                $fanMap[$fid] = $f;
                $sid = (int)($f['semestr_id'] ?? 0);
                if ($sid > 0) {
                    $fanSemestrIds[$sid] = true;
                }
            }
        }
    }

    $semestrNumCache = [];
    if (!empty($fanSemestrIds)) {
        $sidSql = implode(',', array_values(array_map('intval', array_keys($fanSemestrIds))));
        $semRes = $db->query("
            SELECT id, semestr
            FROM semestrlar
            WHERE id IN ({$sidSql})
        ");
        if ($semRes) {
            while ($s = mysqli_fetch_assoc($semRes)) {
                $sid = (int)($s['id'] ?? 0);
                if ($sid > 0) {
                    $semestrNumCache[$sid] = (int)($s['semestr'] ?? 0);
                }
            }
        }
    }

    $validatedRows = [];
    $canonicalTargetCache = [];
    $deleteTargetMap = [];

    foreach ($scopeItems as $row) {
        $semestrId = (int)$row['semestr_id'];
        $yonalishId = (int)$row['yonalish_id'];
        $guruhId = (int)$row['guruh_id'];
        $fanId = (int)$row['fan_id'];

        $sourceKey = $semestrId . '|' . $guruhId . '|' . $fanId;
        $source = $sourceMap[$sourceKey] ?? null;
        if (!$source) {
            respond_json(['success' => false, 'message' => "Tanlangan guruh source jadvalda topilmadi"], 422);
        }

        $guruh = $guruhMap[$guruhId] ?? null;
        if (!$guruh || (int)($guruh['yonalish_id'] ?? 0) !== $yonalishId) {
            respond_json(['success' => false, 'message' => "Guruh yo'nalishga mos emas"], 422);
        }

        $fan = $fanMap[$fanId] ?? null;
        if (!$fan || (int)($fan['tanlov_fan'] ?? 0) !== 3) {
            respond_json(['success' => false, 'message' => "Chet tili fani topilmadi"], 422);
        }

        $fanSemestrId = (int)($fan['semestr_id'] ?? 0);
        $semestrNum = (int)($semestrNumCache[$fanSemestrId] ?? 0);
        $fanCode = trim((string)($fan['fan_code'] ?? ''));
        $fanNameKey = normalize_language_name((string)($fan['fan_name'] ?? ''));
        $canonicalKey = $fanSemestrId . '|' . $fanCode . '|' . $fanNameKey;
        if ($fanSemestrId <= 0 || $fanNameKey === '') {
            $canonicalKey = 'fan_id|' . $fanId;
        }

        if (!array_key_exists($canonicalKey, $canonicalTargetCache)) {
            $target = resolve_target_fan_binding($db, $fanId);
            $targetFanId = (int)($target['fan_id'] ?? 0);
            $targetSemestrId = (int)($target['semestr_id'] ?? 0);
            if ($targetFanId <= 0) {
                $targetFanId = $fanId;
            }
            if ($targetSemestrId <= 0) {
                $targetSemestrId = $semestrId;
            }
            ensure_target_fan_has_oquv_reja($db, $targetFanId, $targetSemestrId, $fanNameKey);
            $candidateFanIds = $target['candidate_fan_ids'] ?? [];
            if (!is_array($candidateFanIds) || count($candidateFanIds) === 0) {
                $candidateFanIds = [$targetFanId];
            }
            $candidateFanIds = array_values(array_unique(array_map('intval', $candidateFanIds)));

            $canonicalTargetCache[$canonicalKey] = [
                'fan_id' => $targetFanId,
                'semestr_id' => $targetSemestrId,
                'candidate_fan_ids' => $candidateFanIds,
            ];
        }

        $targetFanId = (int)($canonicalTargetCache[$canonicalKey]['fan_id'] ?? 0);
        $targetSemestrId = (int)($canonicalTargetCache[$canonicalKey]['semestr_id'] ?? 0);
        $candidateFanIds = $canonicalTargetCache[$canonicalKey]['candidate_fan_ids'] ?? [$targetFanId];
        if (!is_array($candidateFanIds) || count($candidateFanIds) === 0) {
            $candidateFanIds = [$targetFanId];
        }

        $validatedRows[] = [
            'semestr_id' => $semestrId,
            'yonalish_id' => $yonalishId,
            'guruh_id' => $guruhId,
            'fan_id' => $fanId,
            'target_fan_id' => $targetFanId,
            'talabalar_soni' => (int)($source['talabalar_soni'] ?? 0),
            'selected' => true,
        ];

        // Izoh: Har bir yo'nalish o'z semestr_id'si bilan saqlanishi shart.
        $deleteKey = $semestrId . '|' . $canonicalKey;
        if (!isset($deleteTargetMap[$deleteKey])) {
            $deleteTargetMap[$deleteKey] = [
            'semestr_id' => $semestrId,
                'fan_ids' => [],
            ];
        }
        foreach ($candidateFanIds as $candidateFanId) {
            $candidateFanId = (int)$candidateFanId;
            if ($candidateFanId > 0) {
                $deleteTargetMap[$deleteKey]['fan_ids'][$candidateFanId] = true;
            }
        }
    }

    $insertRows = [];
    $insertRowKeyMap = [];
    foreach ($validatedRows as $row) {
        if (empty($row['selected'])) {
            continue;
        }

        $insertKey = (int)$row['semestr_id'] . '|' . (int)$row['guruh_id'] . '|' . (int)$row['target_fan_id'];
        if (isset($insertRowKeyMap[$insertKey])) {
            // Izoh: Bir xil target guruh/fan kombinatsiyasini bir marta saqlaymiz.
            continue;
        }
        $insertRowKeyMap[$insertKey] = true;
        $insertRows[] = $row;
    }

    if (count($insertRows) === 0) {
        respond_json(['success' => false, 'message' => "Saqlash uchun tanlangan guruh topilmadi"], 422);
    }

    $deleteWhereParts = [];
    foreach ($deleteTargetMap as $target) {
        $targetSemestrId = (int)($target['semestr_id'] ?? 0);
        $targetFanIds = $target['fan_ids'] ?? [];
        if ($targetSemestrId <= 0 || !is_array($targetFanIds) || count($targetFanIds) === 0) {
            continue;
        }
        $fanIdsSql = implode(',', array_map('intval', array_keys($targetFanIds)));
        if ($fanIdsSql === '') {
            continue;
        }
        $deleteWhereParts[] = "(semestr_id = {$targetSemestrId} AND fan_id IN ({$fanIdsSql}))";
    }

    $finalGroupMap = [];
    foreach ($insertRows as $row) {
        $finalGroupMap[(int)$row['semestr_id'] . '|' . (int)$row['target_fan_id']] = true;
    }
    $finalGroupCount = count($finalGroupMap);

    try {
        $db->query("START TRANSACTION");
        $ok = true;

        if (!empty($deleteWhereParts)) {
            $deleteWhereSql = implode(' OR ', $deleteWhereParts);
            $ok = $ok && $db->query("
                DELETE FROM chet_tili_biriktirilgan_guruhlar
                WHERE {$deleteWhereSql}
            ");
        }

        if ($ok) {
            $values = [];
            foreach ($insertRows as $row) {
                $values[] = '('
                    . (int)$row['semestr_id'] . ','
                    . (int)$row['yonalish_id'] . ','
                    . (int)$row['guruh_id'] . ','
                    . (int)$row['target_fan_id'] . ','
                    . (int)$row['talabalar_soni']
                    . ')';
            }
            if (!empty($values)) {
                $ok = $ok && $db->query("
                    INSERT INTO chet_tili_biriktirilgan_guruhlar
                        (semestr_id, yonalish_id, guruh_id, fan_id, talabalar_soni)
                    VALUES " . implode(',', $values)
                );
            }
        }

        if ($ok) {
            $db->query("COMMIT");
            respond_json([
                'success' => true,
                'message' => "Biriktirish saqlandi ({$finalGroupCount} ta yakuniy guruh)",
                'duration_ms' => round((microtime(true) - $requestStart) * 1000, 2),
                'insert_count' => count($insertRows),
                'delete_target_count' => count($deleteWhereParts),
            ]);
        } else {
            $db->query("ROLLBACK");
            respond_json(['success' => false, 'message' => "Saqlashda xatolik yuz berdi"], 500);
        }
    } catch (Throwable $e) {
        $db->query("ROLLBACK");
        respond_json([
            'success' => false,
            'message' => "Saqlashda texnik xatolik yuz berdi",
            'error_hint' => trim($e->getMessage()),
        ], 500);
    }
?>
