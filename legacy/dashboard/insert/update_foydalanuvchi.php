<?php
include_once '../config.php';
legacy_require_admin(true);
header('Content-Type: application/json; charset=UTF-8');

$db = new Database();

$id = (int)($_POST['id'] ?? 0);
$username = trim((string)($_POST['username'] ?? ''));
$name = trim((string)($_POST['name'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$role = trim((string)($_POST['role'] ?? 'admin'));
$kafedraId = (int)($_POST['kafedra_id'] ?? 0);
$isActive = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;
$currentUserId = legacy_user_id();

if ($id <= 0 || $username === '' || $name === '' || $email === '') {
    echo json_encode([
        'success' => false,
        'message' => "Barcha majburiy maydonlarni to'ldiring."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => "Email manzili noto'g'ri."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

if ($password !== '' && strlen($password) < 6) {
    echo json_encode([
        'success' => false,
        'message' => "Yangi parol kamida 6 belgidan iborat bo'lsin."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

if (!in_array($role, ['admin', 'kafedra_mudiri'], true)) {
    $role = 'admin';
}

if ($role === 'kafedra_mudiri' && $kafedraId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => "Kafedra mudiri uchun kafedra tanlang."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

if ($role !== 'kafedra_mudiri') {
    $kafedraId = 0;
}

if ($kafedraId > 0) {
    $kafedra = $db->get_data_by_table('kafedralar', ['id' => $kafedraId]);
    if (empty($kafedra)) {
        echo json_encode([
            'success' => false,
            'message' => "Tanlangan kafedra topilmadi."
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
}

$user = $db->get_data_by_table('users', ['id' => $id]);
if (empty($user)) {
    echo json_encode([
        'success' => false,
        'message' => "Foydalanuvchi topilmadi."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

if ($id === $currentUserId && ($role !== 'admin' || $isActive !== 1)) {
    echo json_encode([
        'success' => false,
        'message' => "O'zingizni adminlikdan chiqarish yoki nofaol qilish mumkin emas."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

$safeUsername = addslashes($username);
$safeEmail = addslashes($email);

$usernameExists = $db->query("SELECT id FROM users WHERE username = '{$safeUsername}' AND id <> {$id} LIMIT 1");
if ($usernameExists && mysqli_num_rows($usernameExists) > 0) {
    echo json_encode([
        'success' => false,
        'message' => "Bu username allaqachon mavjud."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

$emailExists = $db->query("SELECT id FROM users WHERE email = '{$safeEmail}' AND id <> {$id} LIMIT 1");
if ($emailExists && mysqli_num_rows($emailExists) > 0) {
    echo json_encode([
        'success' => false,
        'message' => "Bu email allaqachon mavjud."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

$updateData = [
    'username' => $username,
    'name' => $name,
    'email' => $email,
    'role' => $role,
    'kafedra_id' => (string)$kafedraId,
    'is_active' => (string)$isActive,
    'updated_at' => date('Y-m-d H:i:s'),
];

if ($password !== '') {
    $updateData['password'] = password_hash($password, PASSWORD_BCRYPT);
}

$updated = $db->update('users', $updateData, 'id = ' . $id);
if ($updated) {
    echo json_encode([
        'success' => true,
        'message' => "Foydalanuvchi muvaffaqiyatli yangilandi."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

echo json_encode([
    'success' => false,
    'message' => "Foydalanuvchini yangilashda xatolik yuz berdi."
], JSON_UNESCAPED_UNICODE);
