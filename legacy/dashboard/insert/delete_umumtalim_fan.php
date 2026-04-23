<?php
    // Izoh: Birlashtiriladigan fanini o'chirish va bog'langan biriktirishlarni tozalash.
    include_once '../config.php';
    header('Content-Type: application/json');
    $db = new Database();

    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID noto\'g\'ri']);
        return;
    }

    // Izoh: Biriktirishlarni oldin o'chiramiz.
    $db->query("
        DELETE ubg
        FROM umumtalim_fan_biriktirish_guruhlar ubg
        JOIN umumtalim_fan_biriktirish ub ON ub.id = ubg.biriktirish_id
        WHERE ub.umumtalim_fan_id = $id
    ");
    $db->delete('umumtalim_fan_biriktirish', "umumtalim_fan_id = $id");

    $deleted = $db->delete('umumtalim_fanlar', "id = $id");

    if ($deleted) {
        echo json_encode(['success' => true, 'message' => 'Umumta\'lim fan o\'chirildi']);
    } else {
        echo json_encode(['success' => false, 'message' => 'O\'chirishda xatolik yuz berdi']);
    }
?>
