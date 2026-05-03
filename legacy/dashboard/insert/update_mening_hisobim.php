<?php
include_once '../config.php';
header('Content-Type: application/json; charset=UTF-8');

$db = new Database();
$userId = legacy_user_id();

if ($userId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => "Sessiya topilmadi. Qayta tizimga kiring."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

$user = $db->get_data_by_table('users', ['id' => $userId]);
if (empty($user)) {
    echo json_encode([
        'success' => false,
        'message' => "Foydalanuvchi topilmadi."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

$username = trim((string)($_POST['username'] ?? ''));
$currentPassword = (string)($_POST['current_password'] ?? '');
$newPassword = (string)($_POST['new_password'] ?? '');
$newPasswordConfirm = (string)($_POST['new_password_confirm'] ?? '');

if ($username === '' || $currentPassword === '') {
    echo json_encode([
        'success' => false,
        'message' => "Login va joriy parol majburiy."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

$currentHash = (string)($user['password'] ?? '');
if ($currentHash === '' || !password_verify($currentPassword, $currentHash)) {
    echo json_encode([
        'success' => false,
        'message' => "Joriy parol noto'g'ri."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

if ($newPassword !== '' && strlen($newPassword) < 6) {
    echo json_encode([
        'success' => false,
        'message' => "Yangi parol kamida 6 belgidan iborat bo'lsin."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

if ($newPassword !== $newPasswordConfirm) {
    echo json_encode([
        'success' => false,
        'message' => "Yangi parol tasdiqlash bilan mos emas."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

$safeUsername = addslashes($username);
$existsRes = $db->query("SELECT id FROM users WHERE username = '{$safeUsername}' AND id <> {$userId} LIMIT 1");
if ($existsRes && mysqli_num_rows($existsRes) > 0) {
    echo json_encode([
        'success' => false,
        'message' => "Bu login allaqachon band."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

$currentUsername = (string)($user['username'] ?? '');
if ($currentUsername === $username && $newPassword === '') {
    echo json_encode([
        'success' => true,
        'message' => "O'zgarish topilmadi."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

$updateData = [
    'username' => $username,
    'updated_at' => date('Y-m-d H:i:s'),
];

if ($newPassword !== '') {
    $updateData['password'] = password_hash($newPassword, PASSWORD_BCRYPT);
}

$updated = $db->update('users', $updateData, 'id = ' . $userId);
if (!$updated) {
    echo json_encode([
        'success' => false,
        'message' => "Saqlashda xatolik yuz berdi."
    ], JSON_UNESCAPED_UNICODE);
    return;
}

if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['username'] = $username;
    if (isset($_SESSION['legacy_user']) && is_array($_SESSION['legacy_user'])) {
        $_SESSION['legacy_user']['username'] = $username;
    }
}

if (function_exists('session')) {
    try {
        session(['username' => $username]);
        $legacyUser = session('legacy_user');
        if (is_array($legacyUser)) {
            $legacyUser['username'] = $username;
            session(['legacy_user' => $legacyUser]);
        }
    } catch (Throwable $e) {
    }
}

echo json_encode([
    'success' => true,
    'message' => "Profil ma'lumotlari muvaffaqiyatli yangilandi."
], JSON_UNESCAPED_UNICODE);
