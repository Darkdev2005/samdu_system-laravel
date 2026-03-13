<?php
    // Izoh: Umumta'lim fan qo'shish.
    include_once '../config.php';
    header('Content-Type: application/json');
    $db = new Database();

    $fan_code = trim($_POST['fan_code'] ?? '');
    $fan_name = trim($_POST['fan_name'] ?? '');
    $semestr = (int) ($_POST['semestr'] ?? 0);
    $kafedra_id = (int) ($_POST['kafedra_id'] ?? 0);

    if ($fan_code === '' || $fan_name === '' || $semestr <= 0 || $kafedra_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ma\'lumotlar to\'liq emas']);
        return;
    }

    $exists = $db->get_data_by_table('umumtalim_fanlar', [
        'fan_code' => $fan_code,
        'fan_name' => $fan_name,
        'semestr' => $semestr,
        'kafedra_id' => $kafedra_id
    ]);

    if ($exists) {
        echo json_encode(['success' => false, 'message' => 'Bu fan allaqachon mavjud']);
        return;
    }

    $insert = $db->insert('umumtalim_fanlar', [
        'fan_code' => $fan_code,
        'fan_name' => $fan_name,
        'kafedra_id' => $kafedra_id
    ]);

    if ($insert) {
        echo json_encode(['success' => true, 'message' => 'Umumta\'lim fan qo\'shildi']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Saqlashda xatolik yuz berdi']);
    }
?>
