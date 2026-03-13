<?php
    include_once '../config.php';
    $db = new Database();
    $fakultet_id = isset($_POST['fakultet_id']) ? (int)$_POST['fakultet_id'] : 0;
    $kafedra_id = isset($_POST['kafedra_id']) ? (int)$_POST['kafedra_id'] : 0;
    $fio = isset($_POST['fio']) ? trim($_POST['fio']) : '';
    $ilmiy_unvon_id = isset($_POST['ilmiy_unvon_id']) ? (int)$_POST['ilmiy_unvon_id'] : 0;
    $ilmiy_daraja_id = isset($_POST['ilmiy_daraja_id']) ? (int)$_POST['ilmiy_daraja_id'] : 0;
$lavozim = isset($_POST['lavozim']) ? trim($_POST['lavozim']) : '';
$stavka = isset($_POST['stavka']) ? trim($_POST['stavka']) : '';
$ishtur_id = isset($_POST['ishtur_id']) ? (int)$_POST['ishtur_id'] : 0;
$ishtur = $ishtur_id ? $db->get_data_by_table('ish_turlar', ['id' => $ishtur_id]) : null;
$ishtur_name = mb_strtolower(trim($ishtur['name'] ?? ''), 'UTF-8');
$is_soatbay = $ishtur_name !== '' && strpos($ishtur_name, 'soatbay') !== false;

if ($is_soatbay && $stavka === '') {
    $stavka = '0';
}

if ($fakultet_id == 0 || $kafedra_id == 0 || empty($fio) || $ilmiy_unvon_id == 0 || $ilmiy_daraja_id == 0 || $ishtur_id == 0 || (!$is_soatbay && $stavka === '')) {
        echo json_encode([
            'success' => false,
            'message' => 'Iltimos, barcha maydonlarni to‘ldiring.'
        ]);
        return;
    }
    $data = [
        'fakultet_id' => $fakultet_id,
        'kafedra_id' => $kafedra_id,
        'fio' => $fio,
        'lavozim' => $lavozim,
        'stavka' => $stavka,
        'ishtur_id' => $ishtur_id,
        'ilmiy_unvon_id' => $ilmiy_unvon_id,
        'ilmiy_daraja_id' => $ilmiy_daraja_id
    ];
    $inserted = $db->insert('oqituvchilar', $data);
    if ($inserted) {
        echo json_encode([
            'success' => true,
            'message' => 'O‘qituvchi muvaffaqiyatli qo‘shildi.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'O‘qituvchini qo‘shishda xatolik yuz berdi.'
        ]);
    }

?>
