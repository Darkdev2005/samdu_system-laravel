<?php
include_once '../config.php';
legacy_require_admin(true);
header('Content-Type: application/json; charset=UTF-8');

$db = new Database();
$id = (int)($_POST['id'] ?? 0);
$currentUserId = legacy_user_id();

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => "Foydalanuvchi ID noto'g'ri."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

if ($id === $currentUserId) {
    echo json_encode([
        'success' => false,
        'message' => "O'zingizni o'chirish mumkin emas."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

$user = $db->get_data_by_table('users', ['id' => $id]);
if (empty($user)) {
    echo json_encode([
        'success' => false,
        'message' => "Foydalanuvchi topilmadi."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

$deleted = $db->delete('users', 'id = ' . $id);
if ($deleted) {
    echo json_encode([
        'success' => true,
        'message' => "Foydalanuvchi o'chirildi."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

echo json_encode([
    'success' => false,
    'message' => "Foydalanuvchini o'chirishda xatolik yuz berdi."
], JSON_UNESCAPED_UNICODE);
