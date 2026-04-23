<?php
include_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$db = new Database();

$fanId = (int)($_POST['fan_id'] ?? 0);
if ($fanId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => "Fan ID noto'g'ri"
    ]);
    return;
}

$fan = $db->get_data_by_table('fanlar', ['id' => $fanId]);
if (!$fan) {
    echo json_encode([
        'success' => false,
        'message' => 'Fan topilmadi'
    ]);
    return;
}

try {
    $db->query("START TRANSACTION");
    $ok = true;

    // Izoh: Avval o'quv reja satrlariga bog'langan taqsimotlarni tozalaymiz.
    $rejaIds = [];
    $rejaRes = $db->query("SELECT id FROM oquv_rejalar WHERE fan_id = $fanId");
    if ($rejaRes) {
        while ($row = mysqli_fetch_assoc($rejaRes)) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $rejaIds[] = $id;
            }
        }
    }

    if (!empty($rejaIds)) {
        $rejaIdsSql = implode(',', array_map('intval', array_unique($rejaIds)));
        $ok = $ok && $db->query("DELETE FROM taqsimotlar WHERE type = 'A' AND oquv_reja_id IN ($rejaIdsSql)");
    }

    if ($ok) {
        $ok = $ok && $db->query("DELETE FROM oquv_rejalar WHERE fan_id = $fanId");
    }

    // Izoh: Fan ishchi rejaning bazasi bo'lsa bog'liq variantlarni ham tozalaymiz.
    if ($ok) {
        $ishchiIds = [];
        $ishchiRes = $db->query("SELECT id FROM ishchi_oquv_reja WHERE base_fan_id = $fanId");
        if ($ishchiRes) {
            while ($row = mysqli_fetch_assoc($ishchiRes)) {
                $id = (int)($row['id'] ?? 0);
                if ($id > 0) {
                    $ishchiIds[] = $id;
                }
            }
        }

        if (!empty($ishchiIds)) {
            $ishchiIdsSql = implode(',', array_map('intval', array_unique($ishchiIds)));
            $ok = $ok && $db->query("DELETE FROM ishchi_oquv_reja_variants WHERE ishchi_reja_id IN ($ishchiIdsSql)");
            $ok = $ok && $db->query("DELETE FROM ishchi_oquv_reja WHERE id IN ($ishchiIdsSql)");
        }
    }

    // Izoh: Fan variant sifatida bog'langan bo'lsa ham yozuvni olib tashlaymiz.
    if ($ok) {
        $ok = $ok && $db->query("DELETE FROM ishchi_oquv_reja_variants WHERE fan_id = $fanId");
    }

    // Izoh: Tanlov fan talabalar taqsimotida shu bazaviy yoki variant fan bo'lsa tozalaymiz.
    if ($ok) {
        $ok = $ok && $db->query("DELETE FROM tanlov_fan_talablar WHERE base_fan_id = $fanId OR variant_fan_id = $fanId");
    }

    // Izoh: Umumta'lim biriktirishda source fan bo'lib turgan bog'lanishlar.
    if ($ok) {
        $ok = $ok && $db->query("DELETE FROM umumtalim_fan_biriktirish WHERE source_fan_id = $fanId");
    }

    // Izoh: Chet tili biriktirish/talab/taqsimot jadvallarida shu fan bilan bog'liq qatorlar.
    if ($ok) {
        $oquvGuruhIds = [];
        $oquvGuruhRes = $db->query("SELECT id FROM chet_tili_oquv_guruhlar WHERE fan_id = $fanId");
        if ($oquvGuruhRes) {
            while ($row = mysqli_fetch_assoc($oquvGuruhRes)) {
                $gid = (int)($row['id'] ?? 0);
                if ($gid > 0) {
                    $oquvGuruhIds[] = $gid;
                }
            }
        }

        if (!empty($oquvGuruhIds)) {
            $gidSql = implode(',', array_map('intval', array_unique($oquvGuruhIds)));
            $ok = $ok && $db->query("DELETE FROM chet_tili_oquv_guruh_items WHERE oquv_guruh_id IN ($gidSql)");
        }

        $ok = $ok && $db->query("DELETE FROM chet_tili_oquv_guruh_items WHERE source_fan_id = $fanId");
        $ok = $ok && $db->query("DELETE FROM chet_tili_oquv_guruhlar WHERE fan_id = $fanId");
        $ok = $ok && $db->query("DELETE FROM chet_tili_talablar WHERE fan_id = $fanId");
        $ok = $ok && $db->query("DELETE FROM chet_tili_guruhlar WHERE fan_id = $fanId");
    }

    if ($ok) {
        $ok = $ok && $db->delete('fanlar', "id = $fanId");
    }

    if ($ok) {
        $db->query("COMMIT");
        echo json_encode([
            'success' => true,
            'message' => "Fan o'chirildi"
        ]);
    } else {
        $db->query("ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => "O'chirishda xatolik yuz berdi"
        ]);
    }
} catch (Throwable $e) {
    $db->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => "O'chirishda texnik xatolik yuz berdi"
    ]);
}
