<?php
include_once '../config.php';
legacy_require_admin();

$db = new Database();

$role = trim((string)($_POST['role'] ?? ''));
$kafedraId = (int)($_POST['kafedra_id'] ?? 0);
$isActive = trim((string)($_POST['is_active'] ?? ''));
$search = trim((string)($_POST['search'] ?? ''));

$where = [];
if ($role !== '' && in_array($role, ['admin', 'kafedra_mudiri'], true)) {
    $where[] = "u.role = '" . addslashes($role) . "'";
}
if ($kafedraId > 0) {
    $where[] = 'u.kafedra_id = ' . $kafedraId;
}
if ($isActive === '0' || $isActive === '1') {
    $where[] = 'u.is_active = ' . (int)$isActive;
}
if ($search !== '') {
    $safeSearch = addslashes($search);
    $where[] = "(
        u.username LIKE '%{$safeSearch}%'
        OR u.name LIKE '%{$safeSearch}%'
        OR u.email LIKE '%{$safeSearch}%'
    )";
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
$result = $db->query("
    SELECT
        u.id,
        u.username,
        u.name,
        u.email,
        u.role,
        u.kafedra_id,
        u.is_active,
        u.created_at,
        k.name AS kafedra_name
    FROM users u
    LEFT JOIN kafedralar k ON k.id = u.kafedra_id
    {$whereSql}
    ORDER BY u.id DESC
");

$rows = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
}

if (empty($rows)): ?>
    <tr>
        <td colspan="9">Ma'lumot topilmadi</td>
    </tr>
<?php
    return;
endif;

foreach ($rows as $row):
    $roleLabel = ($row['role'] ?? '') === 'kafedra_mudiri' ? 'Kafedra mudiri' : 'Admin';
    $statusActive = (int)($row['is_active'] ?? 1) === 1;
    $createdAt = trim((string)($row['created_at'] ?? ''));
    $createdAtLabel = $createdAt !== '' ? date('Y-m-d H:i', strtotime($createdAt)) : '-';
    ?>
    <tr data-row="1">
        <td><?= (int)$row['id'] ?></td>
        <td><?= htmlspecialchars((string)($row['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars((string)($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
            <span class="role-badge"><?= htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </td>
        <td><?= htmlspecialchars((string)($row['kafedra_name'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
        <td>
            <span class="status-badge <?= $statusActive ? 'active' : 'inactive' ?>">
                <?= $statusActive ? 'Faol' : 'Nofaol' ?>
            </span>
        </td>
        <td><?= htmlspecialchars($createdAtLabel, ENT_QUOTES, 'UTF-8') ?></td>
        <td>
            <button
                class="btn btn-sm btn-warning editUserBtn"
                data-id="<?= (int)$row['id'] ?>"
                data-username="<?= htmlspecialchars((string)($row['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                data-name="<?= htmlspecialchars((string)($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                data-email="<?= htmlspecialchars((string)($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                data-role="<?= htmlspecialchars((string)($row['role'] ?? 'admin'), ENT_QUOTES, 'UTF-8') ?>"
                data-kafedra-id="<?= (int)($row['kafedra_id'] ?? 0) ?>"
                data-is-active="<?= (int)($row['is_active'] ?? 1) ?>"
            >
                <i class="fas fa-edit"></i> Tahrirlash
            </button>
            <button class="btn btn-sm btn-danger deleteUserBtn" data-id="<?= (int)$row['id'] ?>">
                <i class="fas fa-trash-alt"></i> O'chirish
            </button>
        </td>
    </tr>
<?php endforeach; ?>
