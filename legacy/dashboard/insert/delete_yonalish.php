<?php
include_once '../config.php';
header('Content-Type: application/json');
$db = new Database();

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID noto\'g\'ri'
    ]);
    return;
}

$checks = [
    'semestrlar' => "SELECT COUNT(*) AS cnt FROM semestrlar WHERE yonalish_id = $id",
    'guruhlar' => "SELECT COUNT(*) AS cnt FROM guruhlar WHERE yonalish_id = $id",
    'oquv_haftaliklar' => "SELECT COUNT(*) AS cnt FROM oquv_haftaliklar WHERE yonalish_id = $id",
    'umumtalim_fan_biriktirish' => "SELECT COUNT(*) AS cnt FROM umumtalim_fan_biriktirish WHERE yonalish_id = $id",
    'chet_tili_guruhlar' => "SELECT COUNT(*) AS cnt FROM chet_tili_guruhlar WHERE yonalish_id = $id",
];

$used = [];
foreach ($checks as $label => $sql) {
    $res = $db->query($sql);
    $count = (int) (mysqli_fetch_assoc($res)['cnt'] ?? 0);
    if ($count > 0) {
        $used[] = "{$label}: {$count}";
    }
}

if (!empty($used)) {
    echo json_encode([
        'success' => false,
        'message' => "Yo'nalish bog'langan: " . implode(', ', $used)
    ]);
    return;
}

try {
    $db->query("START TRANSACTION");

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
        'sync_status' => 'nosync',
        'change_type' => 'delete'
    ]);

    if (!$historySaved) {
        $db->query("ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => "Tarixni saqlab bo'lmadi"
        ]);
        return;
    }

    $deleted = $db->delete('yonalishlar', "id = $id");
    if (!$deleted) {
        $db->query("ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => "Yo'nalishni o'chirishda xatolik yuz berdi"
        ]);
        return;
    }

    $db->query("COMMIT");
    echo json_encode([
        'success' => true,
        'message' => "Yo'nalish o'chirildi"
    ]);
} catch (Throwable $e) {
    $db->query("ROLLBACK");
    echo json_encode([
        'success' => false,
        'message' => "O'chirishda texnik xatolik yuz berdi"
    ]);
}
?>
