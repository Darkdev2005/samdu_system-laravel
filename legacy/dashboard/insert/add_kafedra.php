<?php
    include_once '../config.php';
    $db = new Database();
    header('Content-Type: application/json');

    if (!isset($_POST['nomi']) || empty(trim($_POST['nomi']))) {
        echo json_encode([
            'success' => false,
            'message' => 'Kafedra nomi majburiy'
        ]);
        return;
    }

    $nomi = trim($_POST['nomi']);
    $fakultet_id = trim($_POST['fakultet_id']);
    $insertsql = $db->insert('kafedralar', [
        'name' => $nomi,
        'fakultet_id' => $fakultet_id
    ]);
    if ($insertsql) {
        echo json_encode([
            'success' => true,
            'message' => 'Kafedra muvaffaqiyatli qo\'shildi'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Kafedra qo\'shishda xatolik yuz berdi'
        ]);
    }
?>
