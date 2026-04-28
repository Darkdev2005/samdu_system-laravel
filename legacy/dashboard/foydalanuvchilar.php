<?php
include_once 'config.php';
legacy_require_admin();

$db = new Database();
$kafedralar = $db->get_data_by_table_all('kafedralar', 'ORDER BY name');
$h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foydalanuvchilar - O'quv Qo'llanma</title>
    <link rel="stylesheet" href="../assets/css/oquv_yuklama_style.css">
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <link href="/assets/vendor/select2/css/select2.min.css" rel="stylesheet" />
    <style>
        .filter-grid.three-col {
            grid-template-columns: repeat(3, minmax(220px, 1fr));
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.active {
            background: #dcfce7;
            color: #166534;
        }
        .status-badge.inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #075985;
            font-size: 12px;
            font-weight: 600;
        }
        .field-note {
            margin-top: 6px;
            color: #64748b;
            font-size: 12px;
        }
        .hidden-field {
            display: none;
        }
        @media (max-width: 1100px) {
            .filter-grid.three-col {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="top-navbar">
            <div class="navbar-left">
                <h1>Foydalanuvchilar</h1>
                <p class="navbar-subtitle">Admin uchun foydalanuvchilarni boshqarish bo'limi</p>
            </div>
            <div class="navbar-right">
                <button class="btn btn-primary" id="addUserBtn">
                    <i class="fas fa-user-plus"></i> Foydalanuvchi qo'shish
                </button>
            </div>
        </header>

        <div class="content-container">
            <div class="filter-container">
                <div class="filter-grid three-col">
                    <div class="form-group">
                        <label><i class="fas fa-user-shield me-2"></i>Rol</label>
                        <select class="form-control" id="roleFilter">
                            <option value="">Barcha rollar</option>
                            <option value="admin">Admin</option>
                            <option value="kafedra_mudiri">Kafedra mudiri</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-sitemap me-2"></i>Kafedra</label>
                        <select class="form-control" id="kafedraFilter">
                            <option value="">Barcha kafedralar</option>
                            <?php foreach ($kafedralar as $kafedra): ?>
                                <option value="<?= (int)$kafedra['id'] ?>"><?= $h($kafedra['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-toggle-on me-2"></i>Holati</label>
                        <select class="form-control" id="statusFilter">
                            <option value="">Barchasi</option>
                            <option value="1">Faol</option>
                            <option value="0">Nofaol</option>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <button class="btn btn-primary" type="button" id="applyFiltersBtn">
                        <i class="fas fa-filter me-2"></i>Filtrlash
                    </button>
                    <button class="btn btn-secondary" type="button" id="resetFiltersBtn">
                        <i class="fas fa-redo me-2"></i>Tozalash
                    </button>
                </div>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        <h3>Barcha foydalanuvchilar</h3>
                        <span class="badge" id="totalUsers">0 ta</span>
                    </div>
                    <div class="table-actions">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchUser" placeholder="Username, ism yoki email bo'yicha qidirish...">
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>F.I.SH</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Kafedra</th>
                            <th>Holati</th>
                            <th>Yaratilgan</th>
                            <th>Harakatlar</th>
                        </tr>
                        </thead>
                        <tbody id="usersTable">
                        <tr>
                            <td colspan="9">Yuklanmoqda...</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="modal" id="userModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="userModalTitle">Foydalanuvchi qo'shish</h3>
            <button class="modal-close" id="closeUserModal" type="button">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body">
            <form id="userForm">
                <input type="hidden" id="userEditId" value="">

                <div class="form-group">
                    <label>F.I.SH</label>
                    <input type="text" class="form-control" name="name" id="userNameInput" required>
                </div>

                <div class="form-group">
                    <label>Username</label>
                    <input type="text" class="form-control" name="username" id="usernameInput" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" class="form-control" name="email" id="emailInput" required>
                </div>

                <div class="form-group">
                    <label>Rol</label>
                    <select class="form-control" name="role" id="userRoleSelect" required>
                        <option value="admin">Admin</option>
                        <option value="kafedra_mudiri">Kafedra mudiri</option>
                    </select>
                </div>

                <div class="form-group" id="kafedraFormGroup">
                    <label>Kafedra</label>
                    <select class="form-control" name="kafedra_id" id="userKafedraSelect">
                        <option value="">Kafedrani tanlang</option>
                        <?php foreach ($kafedralar as $kafedra): ?>
                            <option value="<?= (int)$kafedra['id'] ?>"><?= $h($kafedra['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-note">Kafedra faqat `Kafedra mudiri` rolida majburiy.</div>
                </div>

                <div class="form-group">
                    <label>Holati</label>
                    <select class="form-control" name="is_active" id="userStatusSelect" required>
                        <option value="1">Faol</option>
                        <option value="0">Nofaol</option>
                    </select>
                </div>

                <div class="form-group">
                    <label id="passwordLabel">Parol</label>
                    <input type="password" class="form-control" name="password" id="passwordInput" autocomplete="new-password">
                    <div class="field-note" id="passwordNote">Kamida 6 belgidan iborat bo'lsin.</div>
                </div>
            </form>
        </div>

        <div class="modal-footer">
            <button class="btn btn-secondary" id="cancelUserBtn" type="button">Bekor qilish</button>
            <button class="btn btn-primary" id="saveUserBtn" type="button">Saqlash</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/assets/vendor/jquery/jquery-3.6.0.min.js"></script>
<script>window.jQuery || document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>')</script>
<script src="/assets/vendor/select2/js/select2.min.js"></script>
<script>if (window.jQuery && !window.jQuery.fn.select2) { document.write('<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"><\/script>'); }</script>
<script src="../assets/js/app.js"></script>

<script>
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2000,
        timerProgressBar: true
    });

    let searchTimer = null;

    function initSelects() {
        $('#roleFilter, #kafedraFilter, #statusFilter, #userRoleSelect, #userKafedraSelect, #userStatusSelect').select2({
            placeholder: 'Tanlang',
            allowClear: true,
            width: '100%'
        });
    }

    function toggleKafedraField() {
        const role = String($('#userRoleSelect').val() || 'admin');
        const kafedraGroup = $('#kafedraFormGroup');
        const kafedraSelect = $('#userKafedraSelect');

        if (role === 'kafedra_mudiri') {
            kafedraGroup.removeClass('hidden-field');
            kafedraSelect.prop('disabled', false);
            return;
        }

        kafedraGroup.addClass('hidden-field');
        kafedraSelect.val('').trigger('change');
        kafedraSelect.prop('disabled', true);
    }

    function updateTotalUsers() {
        const count = $('#usersTable tr[data-row="1"]').length;
        $('#totalUsers').text(`${count} ta`);
    }

    function loadUsers() {
        const formData = new FormData();
        formData.append('role', String($('#roleFilter').val() || ''));
        formData.append('kafedra_id', String($('#kafedraFilter').val() || ''));
        formData.append('is_active', String($('#statusFilter').val() || ''));
        formData.append('search', String($('#searchUser').val() || '').trim());

        fetch('get/foydalanuvchilar_table.php', {
            method: 'POST',
            body: formData
        })
            .then((res) => res.text())
            .then((html) => {
                $('#usersTable').html(html);
                updateTotalUsers();
            })
            .catch(() => {
                $('#usersTable').html('<tr><td colspan="9">Server bilan bog\'lanib bo\'lmadi</td></tr>');
                $('#totalUsers').text('0 ta');
            });
    }

    function resetUserForm() {
        $('#userForm')[0].reset();
        $('#userEditId').val('');
        $('#userModalTitle').text("Foydalanuvchi qo'shish");
        $('#saveUserBtn').text('Saqlash');
        $('#passwordLabel').text('Parol');
        $('#passwordNote').text("Kamida 6 belgidan iborat bo'lsin.");
        $('#userRoleSelect').val('admin').trigger('change');
        $('#userKafedraSelect').val('').trigger('change');
        $('#userStatusSelect').val('1').trigger('change');
        toggleKafedraField();
    }

    function openUserModal() {
        $('#userModal').addClass('show');
    }

    function closeUserModal() {
        $('#userModal').removeClass('show');
    }

    function validateUserForm(editMode = false) {
        const name = String($('#userNameInput').val() || '').trim();
        const username = String($('#usernameInput').val() || '').trim();
        const email = String($('#emailInput').val() || '').trim();
        const role = String($('#userRoleSelect').val() || 'admin');
        const kafedraId = String($('#userKafedraSelect').val() || '');
        const password = String($('#passwordInput').val() || '');

        if (name === '' || username === '' || email === '') {
            Toast.fire({ icon: 'error', title: "Barcha majburiy maydonlarni to'ldiring" });
            return false;
        }
        if (role === 'kafedra_mudiri' && kafedraId === '') {
            Toast.fire({ icon: 'error', title: "Kafedra mudiri uchun kafedra tanlang" });
            return false;
        }
        if (!editMode && password.length < 6) {
            Toast.fire({ icon: 'error', title: "Parol kamida 6 belgidan iborat bo'lsin" });
            return false;
        }
        if (editMode && password !== '' && password.length < 6) {
            Toast.fire({ icon: 'error', title: "Yangi parol kamida 6 belgidan iborat bo'lsin" });
            return false;
        }

        return true;
    }

    function saveUser() {
        const editId = String($('#userEditId').val() || '');
        const editMode = editId !== '';

        if (!validateUserForm(editMode)) {
            return;
        }

        const formData = new FormData($('#userForm')[0]);
        if (editMode) {
            formData.append('id', editId);
        }

        fetch(editMode ? 'insert/update_foydalanuvchi.php' : 'insert/add_foydalanuvchi.php', {
            method: 'POST',
            body: formData
        })
            .then((res) => res.json())
            .then((data) => {
                if (data && data.success) {
                    Toast.fire({ icon: 'success', title: data.message || 'Saqlandi' });
                    closeUserModal();
                    resetUserForm();
                    loadUsers();
                } else {
                    Toast.fire({ icon: 'error', title: (data && data.message) || 'Saqlashda xatolik' });
                }
            })
            .catch(() => {
                Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
            });
    }

    $(document).ready(function() {
        initSelects();
        resetUserForm();
        loadUsers();

        $('#addUserBtn').on('click', function() {
            resetUserForm();
            openUserModal();
        });
        $('#closeUserModal, #cancelUserBtn').on('click', closeUserModal);
        $('#saveUserBtn').on('click', saveUser);
        $('#userRoleSelect').on('change', toggleKafedraField);
        $('#applyFiltersBtn').on('click', loadUsers);
        $('#resetFiltersBtn').on('click', function() {
            $('#roleFilter').val('').trigger('change');
            $('#kafedraFilter').val('').trigger('change');
            $('#statusFilter').val('').trigger('change');
            $('#searchUser').val('');
            loadUsers();
        });
        $('#searchUser').on('input', function() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadUsers, 250);
        });
    });

    $(document).on('click', '.editUserBtn', function() {
        const button = $(this);
        const id = String(button.data('id') || '');
        const role = String(button.data('role') || 'admin');
        const kafedraId = String(button.data('kafedra-id') || '');

        $('#userEditId').val(id);
        $('#userNameInput').val(String(button.data('name') || ''));
        $('#usernameInput').val(String(button.data('username') || ''));
        $('#emailInput').val(String(button.data('email') || ''));
        $('#userRoleSelect').val(role).trigger('change');
        $('#userKafedraSelect').val(kafedraId).trigger('change');
        $('#userStatusSelect').val(String(button.data('is-active') || '1')).trigger('change');
        $('#passwordInput').val('');
        $('#userModalTitle').text("Foydalanuvchini tahrirlash");
        $('#saveUserBtn').text('Yangilash');
        $('#passwordLabel').text('Yangi parol');
        $('#passwordNote').text("Parolni o'zgartirmasangiz, maydonni bo'sh qoldiring.");
        toggleKafedraField();
        openUserModal();
    });

    $(document).on('click', '.deleteUserBtn', function() {
        const id = String($(this).data('id') || '');
        if (id === '') {
            return;
        }

        Swal.fire({
            title: "Foydalanuvchi o'chirilsinmi?",
            text: "Bu amalni ortga qaytarib bo'lmaydi.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Ha, o'chirish",
            cancelButtonText: "Bekor qilish"
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            const formData = new FormData();
            formData.append('id', id);

            fetch('insert/delete_foydalanuvchi.php', {
                method: 'POST',
                body: formData
            })
                .then((res) => res.json())
                .then((data) => {
                    Toast.fire({
                        icon: data && data.success ? 'success' : 'error',
                        title: (data && data.message) || "Amal bajarilmadi"
                    });
                    if (data && data.success) {
                        loadUsers();
                    }
                })
                .catch(() => {
                    Toast.fire({ icon: 'error', title: "Server bilan bog'lanib bo'lmadi" });
                });
        });
    });
</script>
</body>
</html>
