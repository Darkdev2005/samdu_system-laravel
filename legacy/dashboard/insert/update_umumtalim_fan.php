<?php
    // Izoh: Birlashtiriladigan fanini tahrirlash uchun server tomoni.
    include_once '../config.php';
    header('Content-Type: application/json');
    $db = new Database();

    $id = (int) ($_POST['id'] ?? 0);
    $fanCode = trim($_POST['fan_code'] ?? '');
    $fanName = trim($_POST['fan_name'] ?? '');
    $semestr = (int) ($_POST['semestr'] ?? 0);
    $kafedraId = (int) ($_POST['kafedra_id'] ?? 0);

    if ($id <= 0 || $fanCode === '' || $fanName === '' || $semestr <= 0 || $kafedraId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Ma\'lumotlar to\'liq emas']);
        return;
    }

    $updated = $db->update('umumtalim_fanlar', [
        'fan_code'   => $fanCode,
        'fan_name'   => $fanName,
        'semestr'    => $semestr,
        'kafedra_id' => $kafedraId
    ], "id = $id");

    if ($updated) {
        echo json_encode(['success' => true, 'message' => 'Umumta\'lim fan tahrirlandi']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Tahrirlashda xatolik yuz berdi']);
    }
?>
