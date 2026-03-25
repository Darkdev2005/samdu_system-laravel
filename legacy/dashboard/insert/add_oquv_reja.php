<?php
    include_once '../config.php';
    header('Content-Type: application/json');
    $db = new Database();

    if (empty($_POST['semestr_id']) || empty($_POST['tanlov_fan'])) {
        echo json_encode(['success' => false, 'message' => 'Asosiy maʼlumotlar toʻliq emas']);
        return;
    }

    $semestr_id = (int) $_POST['semestr_id'];
    $izoh = trim($_POST['izoh'] ?? '');
    $tanlov_fanlar = $_POST['tanlov_fan'];
    $insertCount = 0;
    $hasTanlovReference = false;
    // Izoh: Bir xil so'rovda kelgan bir nechta tanlov bloklari bitta ishchi reja bazasiga yig'ilishi uchun
    // variantlarni faqat bir marta tozalaymiz (har blokda qayta tozalanmaydi).
    $clearedIshchiVariantIds = [];

    foreach ($tanlov_fanlar as $index => $tanlov_fan_value) {
        $tanlov_fan = (int) $tanlov_fan_value;
        
        if ($tanlov_fan != 1 && $tanlov_fan != 3) {
            // MAJBURIY FAN yoki UMUMTA'LIM FANI
            if (!isset($_POST['fan_code'][$index], 
                    $_POST['fan_nomi'][$index], 
                    $_POST['kafedra_id'][$index])) {
                continue;
            }
            
            $fanCode = trim($_POST['fan_code'][$index]);
            $fanName = trim($_POST['fan_nomi'][$index]);
            $kafedra_id = (int) $_POST['kafedra_id'][$index];
            
            if (empty($fanCode) || empty($fanName) || $kafedra_id <= 0) {
                continue;
            }

            $fanType = ($tanlov_fan == 2) ? 2 : 0;
            
            $insert_fanid = $db->insert('fanlar', [
                'fan_code'   => $fanCode,
                'fan_name'   => $fanName,
                'kafedra_id' => $kafedra_id,
                'semestr_id' => $semestr_id,
                'tanlov_fan' => $fanType
            ]);
            
            if (!$insert_fanid) {
                continue;
            }

            // Izoh: Umumta'lim fan o'quv rejada yaratilsa, umumtalim_fanlar jadvaliga ham yozamiz.
            if ($fanType === 2) {
                $semestrRow = $db->get_data_by_table('semestrlar', [
                    'id' => $semestr_id
                ]);
                $semestrNum = (int) ($semestrRow['semestr'] ?? 0);

                if ($semestrNum > 0) {
                    $umumtalimExists = $db->get_data_by_table('umumtalim_fanlar', [
                        'fan_code'   => $fanCode,
                        'fan_name'   => $fanName,
                        'kafedra_id' => $kafedra_id,
                        'semestr'    => $semestrNum
                    ]);

                    if (!$umumtalimExists) {
                        $db->insert('umumtalim_fanlar', [
                            'fan_code'   => $fanCode,
                            'fan_name'   => $fanName,
                            'kafedra_id' => $kafedra_id,
                            'semestr'    => $semestrNum
                        ]);
                    }
                }
            }
            
            if (isset($_POST['dars_turi'][$index], $_POST['dars_soati'][$index])) {
                foreach ($_POST['dars_turi'][$index] as $i => $darsTurId) {
                    $darsTurId = (int) $darsTurId;
                    $rawDarsSoat = trim((string) ($_POST['dars_soati'][$index][$i] ?? ''));
                    if ($rawDarsSoat === '') {
                        continue;
                    }

                    $darsSoat = (int) $rawDarsSoat;

                    if ($darsTurId <= 0 || $darsSoat <= 0) {
                        continue;
                    }
                    
                    // Izoh: Bir fan + dars turi qayta saqlansa duplicate yozuv qo'shmaymiz, mavjudini yangilaymiz.
                    $existingReja = $db->get_data_by_table('oquv_rejalar', [
                        'fan_id'      => $insert_fanid,
                        'dars_tur_id' => $darsTurId
                    ]);

                    if ($existingReja) {
                        $db->update('oquv_rejalar', [
                            'dars_soat' => $darsSoat,
                            'izoh'      => $izoh
                        ], 'id = ' . (int)$existingReja['id']);
                        // Izoh: Oldindan qolgan duplicate yozuvlar bo'lsa tozalaymiz.
                        $db->query("DELETE FROM oquv_rejalar WHERE fan_id = " . (int)$insert_fanid . " AND dars_tur_id = " . (int)$darsTurId . " AND id <> " . (int)$existingReja['id']);
                        $insertCount++;
                    } else {
                        $insert = $db->insert('oquv_rejalar', [
                            'fan_id'      => $insert_fanid,
                            'dars_tur_id' => $darsTurId,
                            'dars_soat'   => $darsSoat,
                            'izoh'        => $izoh
                        ]);
                        
                        if ($insert) {
                            // Izoh: Noyoblikni saqlash uchun shu juftlik bo'yicha ortiqcha yozuvlarni o'chiramiz.
                            $db->query("DELETE FROM oquv_rejalar WHERE fan_id = " . (int)$insert_fanid . " AND dars_tur_id = " . (int)$darsTurId . " AND id <> " . (int)$insert);
                            $insertCount++;
                        }
                    }
                }
            }
            
        } else {
            // TANLOV FAN yoki CHET TILI
            $electiveType = ($tanlov_fan == 3) ? 3 : 1;
            // Izoh: O'quv reja sahifasida tanlov fan / chet tili (kod + nom) bazaga yoziladi.
            if (isset($_POST['tanlov_fan_code'][$index], $_POST['tanlov_fan_nomi'][$index]) && !is_array($_POST['tanlov_fan_nomi'][$index])) {
                $tanlovFanCode = trim($_POST['tanlov_fan_code'][$index]);
                $tanlovFanName = trim($_POST['tanlov_fan_nomi'][$index]);

                if ($tanlovFanCode === '' || $tanlovFanName === '') {
                    continue;
                }

                $existing = $db->get_data_by_table('fanlar', [
                    'fan_code'   => $tanlovFanCode,
                    'fan_name'   => $tanlovFanName,
                    'semestr_id' => $semestr_id,
                    'tanlov_fan' => $electiveType,
                    'kafedra_id' => 0
                ]);

                $baseFanId = 0;
                if ($existing) {
                    $baseFanId = (int) ($existing['id'] ?? 0);
                    $hasTanlovReference = true;
                } else {
                    $insert_base = $db->insert('fanlar', [
                        'fan_code'   => $tanlovFanCode,
                        'fan_name'   => $tanlovFanName,
                        'kafedra_id' => 0,
                        'semestr_id' => $semestr_id,
                        'tanlov_fan' => $electiveType
                    ]);

                    if ($insert_base) {
                        $baseFanId = (int) $insert_base;
                        $hasTanlovReference = true;
                    } else {
                        echo json_encode([
                            'success' => false,
                            'message' => "Tanlov fan / Chet tili bazaga yozilmadi. Kod va nomni tekshiring."
                        ]);
                        return;
                    }
                }

                // Izoh: O'quv reja sahifasida tanlov fan / chet tiliga dars turi yoziladi (soat bo'lmasa 0).
                if ($baseFanId > 0 && isset($_POST['dars_turi'][$index])) {
                    foreach ($_POST['dars_turi'][$index] as $i => $darsTurId) {
                        $darsTurId = (int) $darsTurId;
                        $rawDarsSoat = trim((string) ($_POST['dars_soati'][$index][$i] ?? ''));
                        if ($rawDarsSoat === '') {
                            continue;
                        }

                        $darsSoat = (int) $rawDarsSoat;

                        if ($darsTurId <= 0 || $darsSoat <= 0) {
                            continue;
                        }

                        // Izoh: Bazaviy tanlov/chet tili fanida ham dars turi duplicate bo'lib ketmasligi uchun upsert qilamiz.
                        $existingReja = $db->get_data_by_table('oquv_rejalar', [
                            'fan_id'      => $baseFanId,
                            'dars_tur_id' => $darsTurId
                        ]);

                        if ($existingReja) {
                            $db->update('oquv_rejalar', [
                                'dars_soat' => $darsSoat,
                                'izoh'      => $izoh
                            ], 'id = ' . (int)$existingReja['id']);
                            // Izoh: Oldindan qolgan duplicate yozuvlar bo'lsa tozalaymiz.
                            $db->query("DELETE FROM oquv_rejalar WHERE fan_id = " . (int)$baseFanId . " AND dars_tur_id = " . (int)$darsTurId . " AND id <> " . (int)$existingReja['id']);
                            $insertCount++;
                        } else {
                            $insert = $db->insert('oquv_rejalar', [
                                'fan_id'      => $baseFanId,
                                'dars_tur_id' => $darsTurId,
                                'dars_soat'   => $darsSoat,
                                'izoh'        => $izoh
                            ]);

                            if ($insert) {
                                // Izoh: Noyoblikni saqlash uchun shu juftlik bo'yicha ortiqcha yozuvlarni o'chiramiz.
                                $db->query("DELETE FROM oquv_rejalar WHERE fan_id = " . (int)$baseFanId . " AND dars_tur_id = " . (int)$darsTurId . " AND id <> " . (int)$insert);
                                $insertCount++;
                            }
                        }
                    }
                }

                continue;
            }

            // Izoh: Tanlov fan yaratish sahifasidan kelgan variantlar uchun bazadagi asosiy fan tekshiriladi.
            if (!isset($_POST['tanlov_fan_code'][$index], 
                    $_POST['tanlov_fan_base_nomi'][$index],
                    $_POST['tanlov_fan_nomi'][$index], 
                    $_POST['tanlov_kafedra_id'][$index])) {
                continue;
            }
            
            $tanlovFanCode = trim($_POST['tanlov_fan_code'][$index]);
            $tanlovBaseName = trim($_POST['tanlov_fan_base_nomi'][$index]);
            $tanlovFanNomi = $_POST['tanlov_fan_nomi'][$index];
            $tanlovKafedraId = $_POST['tanlov_kafedra_id'][$index];
            
            if (empty($tanlovFanCode) || empty($tanlovBaseName) || empty($tanlovFanNomi) || empty($tanlovKafedraId)) {
                continue;
            }

            $baseExists = $db->get_data_by_table('fanlar', [
                'fan_code'   => $tanlovFanCode,
                'fan_name'   => $tanlovBaseName,
                'semestr_id' => $semestr_id,
                'tanlov_fan' => $electiveType,
                'kafedra_id' => 0
            ]);

            if (!$baseExists) {
                echo json_encode([
                    'success' => false,
                    'message' => "Tanlov fan / Chet tili bazasi topilmadi. Avval O'quv reja yaratish sahifasida kod + nom yarating."
                ]);
                return;
            }

            $baseFanId = (int) ($baseExists['id'] ?? 0);
            $ishchiId = 0;
            if ($baseFanId > 0) {
                $ishchiRow = $db->get_data_by_table('ishchi_oquv_reja', [
                    'base_fan_id' => $baseFanId,
                    'semestr_id'  => $semestr_id
                ]);
                if ($ishchiRow) {
                    $ishchiId = (int) ($ishchiRow['id'] ?? 0);
                } else {
                    $ishchiId = (int) $db->insert('ishchi_oquv_reja', [
                        'base_fan_id' => $baseFanId,
                        'semestr_id'  => $semestr_id
                    ]);
                }
            }

            if ($ishchiId > 0 && !isset($clearedIshchiVariantIds[$ishchiId])) {
                $db->query("DELETE FROM ishchi_oquv_reja_variants WHERE ishchi_reja_id = $ishchiId");
                $clearedIshchiVariantIds[$ishchiId] = true;
            }

            $variantSaved = false;
            
            foreach ($tanlovFanNomi as $variantIndex => $fanName) {
                $fanName = trim($fanName);
                $kafedra_id = (int) ($tanlovKafedraId[$variantIndex] ?? 0);
                
                if (empty($fanName) || $kafedra_id <= 0) {
                    continue;
                }

                $variantExists = $db->get_data_by_table('fanlar', [
                    'fan_code'   => $tanlovFanCode,
                    'fan_name'   => $fanName,
                    'kafedra_id' => $kafedra_id,
                    'semestr_id' => $semestr_id,
                    'tanlov_fan' => $electiveType
                ]);

                $variantFanId = 0;
                if ($variantExists) {
                    $variantFanId = (int) ($variantExists['id'] ?? 0);
                } else {
                    $variantFanId = (int) $db->insert('fanlar', [
                        'fan_code'   => $tanlovFanCode,
                        'fan_name'   => $fanName,
                        'kafedra_id' => $kafedra_id,
                        'semestr_id' => $semestr_id,
                        'tanlov_fan' => $electiveType
                    ]);
                }

                if ($variantFanId > 0 && $ishchiId > 0) {
                    $db->insert('ishchi_oquv_reja_variants', [
                        'ishchi_reja_id' => $ishchiId,
                        'fan_id' => $variantFanId
                    ]);
                    $variantSaved = true;
                }
            }

            if ($variantSaved) {
                $hasTanlovReference = true;
            }
        }
    }

    // Izoh: Tanlov fan / Chet tili bazasi yaratilgan bo'lsa ham muvaffaqiyat qaytaramiz.
    if ($insertCount === 0 && !$hasTanlovReference) {
        echo json_encode(['success' => false, 'message' => 'Saqlash uchun yaroqli maҷlumot topilmadi']);
        return;
    }

    if ($insertCount > 0) {
        echo json_encode(['success' => true, 'message' => "O'quv reja muvaffaqiyatli saqlandi ({$insertCount} ta dars soati)"]);
    } else {
        echo json_encode(['success' => true, 'message' => "O'quv reja muvaffaqiyatli saqlandi"]);
    }
?>
