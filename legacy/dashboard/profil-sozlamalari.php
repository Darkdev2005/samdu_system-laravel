<?php
include_once 'config.php';

$db = new Database();
$userId = legacy_user_id();

if ($userId <= 0) {
    header('Location: /login');
    exit;
}

$user = $db->get_data_by_table('users', ['id' => $userId]);
if (empty($user)) {
    header('Location: /logout');
    exit;
}

$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil sozlamalari</title>
    <link rel="stylesheet" href="../assets/css/oquv_yuklama_style.css">
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="top-navbar">
            <div class="navbar-left">
                <h1>Profil sozlamalari</h1>
                <p class="navbar-subtitle">Login va parolni xavfsiz yangilash</p>
            </div>
        </header>

        <div class="content-container">
            <div class="table-container" style="max-width: 760px;">
                <div class="table-header">
                    <div class="table-title">
                        <h3>Shaxsiy hisob</h3>
                    </div>
                </div>

                <div style="padding: 20px;">
                    <form id="profileSettingsForm">
                        <div class="form-group">
                            <label>F.I.SH</label>
                            <input type="text" class="form-control" value="<?= $h($user['name'] ?? '') ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label>Rol</label>
                            <input type="text" class="form-control" value="<?= $h(legacy_user_role_label()) ?>" readonly>
                        </div>

                        <div class="form-group">
                            <label>Login (username)</label>
                            <input type="text" class="form-control" name="username" id="usernameInput" value="<?= $h($user['username'] ?? '') ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Joriy parol</label>
                            <input type="password" class="form-control" name="current_password" id="currentPasswordInput" autocomplete="current-password" required>
                            <small style="color:#64748b;">O'zgarishni saqlash uchun joriy parolni kiriting.</small>
                        </div>

                        <div class="form-group">
                            <label>Yangi parol (ixtiyoriy)</label>
                            <input type="password" class="form-control" name="new_password" id="newPasswordInput" autocomplete="new-password">
                        </div>

                        <div class="form-group">
                            <label>Yangi parolni tasdiqlash</label>
                            <input type="password" class="form-control" name="new_password_confirm" id="newPasswordConfirmInput" autocomplete="new-password">
                        </div>

                        <div class="filter-actions" style="justify-content: flex-start;">
                            <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                                <i class="fas fa-save me-2"></i>Saqlash
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../assets/js/app.js"></script>
<script>
    const profileForm = document.getElementById('profileSettingsForm');
    const saveBtn = document.getElementById('saveProfileBtn');

    profileForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const username = String(document.getElementById('usernameInput').value || '').trim();
        const currentPassword = String(document.getElementById('currentPasswordInput').value || '');
        const newPassword = String(document.getElementById('newPasswordInput').value || '');
        const newPasswordConfirm = String(document.getElementById('newPasswordConfirmInput').value || '');

        if (username === '' || currentPassword === '') {
            Swal.fire({ icon: 'error', title: "Login va joriy parolni kiriting." });
            return;
        }
        if (newPassword !== '' && newPassword.length < 6) {
            Swal.fire({ icon: 'error', title: "Yangi parol kamida 6 belgidan iborat bo'lsin." });
            return;
        }
        if (newPassword !== newPasswordConfirm) {
            Swal.fire({ icon: 'error', title: "Yangi parol tasdiqlash bilan mos emas." });
            return;
        }

        const formData = new FormData(profileForm);
        saveBtn.disabled = true;
        const oldBtnText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saqlanmoqda...';

        try {
            const response = await fetch('insert/update_mening_hisobim.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (!data.success) {
                Swal.fire({ icon: 'error', title: data.message || 'Xatolik yuz berdi' });
                return;
            }
            Swal.fire({ icon: 'success', title: data.message || "Muvaffaqiyatli saqlandi", timer: 1800, showConfirmButton: false });
            document.getElementById('currentPasswordInput').value = '';
            document.getElementById('newPasswordInput').value = '';
            document.getElementById('newPasswordConfirmInput').value = '';
        } catch (error) {
            Swal.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = oldBtnText;
        }
    });
</script>
</body>
</html>
