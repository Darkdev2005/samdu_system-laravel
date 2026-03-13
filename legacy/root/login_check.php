<?php 
session_start();
include_once 'dashboard/config.php';
header('Content-Type: application/json; charset=utf-8');

$username = $_POST['username'] ?? '';
$password = !empty($_POST['password']) ? md5($_POST['password']) : '';

$db = new Database();
$ret = [];

if (empty($username) || empty($password)) {
    echo json_encode([
        'error' => 1,
        'message' => "Username yoki parol kiritilmadi!"
    ]);
    exit;
}

$fetch = $db->get_data_by_table('users', [
    'username' => $username,
    'password' => $password
]);

if ($fetch) {
    $_SESSION['id'] = $fetch['id'];
    $_SESSION['username'] = $fetch['username']; 

    $ret = [
        'error'   => 0,
        'message' => "Muvaffaqiyatli tizimga kirdingiz!"
    ];
} else {
    $ret = [
        'error'   => 1,
        'message' => "Username yoki parol noto'g'ri!"
    ];
}

echo json_encode($ret);
exit;
?>
