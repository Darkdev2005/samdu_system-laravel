<?php
include_once '../config.php';
header('Content-Type: application/json');
$db = new Database();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$fakultetId = isset($_POST['fakultet_id']) ? (int) $_POST['fakultet_id'] : 0;
$kafedraId = isset($_POST['kafedra_id']) ? (int) $_POST['kafedra_id'] : 0;
$fio = isset($_POST['fio']) ? trim($_POST['fio']) : '';
$lavozim = isset($_POST['lavozim']) ? trim($_POST['lavozim']) : '';
$stavka = isset($_POST['stavka']) ? trim($_POST['stavka']) : '';
$ishturId = isset($_POST['ishtur_id']) ? (int) $_POST['ishtur_id'] : 0;
$ilmiyUnvonId = isset($_POST['ilmiy_unvon_id']) ? (int) $_POST['ilmiy_unvon_id'] : 0;
$ilmiyDarajaId = isset($_POST['ilmiy_daraja_id']) ? (int) $_POST['ilmiy_daraja_id'] : 0;

$ishtur = $ishturId > 0 ? $db->get_data_by_table('ish_turlar', ['id' => $ishturId]) : null;
$ishturName = mb_strtolower(trim($ishtur['name'] ?? ''), 'UTF-8');
$isSoatbay = $ishturName !== '' && strpos($ishturName, 'soatbay') !== false;

if ($isSoatbay && $stavka === '') {
    $stavka = '0';
}

if (
    $id <= 0 ||
    $fakultetId <= 0 ||
    $kafedraId <= 0 ||
    $fio === '' ||
    $lavozim === '' ||
    $ishturId <= 0 ||
    $ilmiyUnvonId <= 0 ||
    $ilmiyDarajaId <= 0 ||
    (!$isSoatbay && $stavka === '')
) {
    echo json_encode([
        'success' => false,
        'message' => 'Iltimos, barcha maydonlarni to\'ldiring.'
    ]);
    return;
}

$updated = $db->update('oqituvchilar', [
    'fakultet_id' => $fakultetId,
    'kafedra_id' => $kafedraId,
    'fio' => $fio,
    'lavozim' => $lavozim,
    'stavka' => $stavka,
    'ishtur_id' => $ishturId,
    'ilmiy_unvon_id' => $ilmiyUnvonId,
    'ilmiy_daraja_id' => $ilmiyDarajaId
], "id = $id");

if ($updated) {
    echo json_encode([
        'success' => true,
        'message' => 'O\'qituvchi tahrirlandi.'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'O\'qituvchini tahrirlashda xatolik yuz berdi.'
    ]);
}
?>
