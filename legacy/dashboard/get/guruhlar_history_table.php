<?php
include_once '../config.php';
$db = new Database();
$historyRows = $db->get_guruhlar_history();
?>
<?php foreach ($historyRows as $row): ?>
    <tr>
        <td><?= htmlspecialchars($row['id']); ?></td>
        <td><?= htmlspecialchars($row['guruh_id']); ?></td>
        <td><?= htmlspecialchars($row['yonalish_name'] ?? '-'); ?></td>
        <td><?= htmlspecialchars($row['guruh_nomer']); ?></td>
        <td><?= htmlspecialchars($row['soni']); ?></td>
        <td>
            <?= $row['sync_status'] === 'sync' ? 'Sinxronlangan' : 'Sinxronlanmagan'; ?>
        </td>
        <td><?= htmlspecialchars($row['changed_at']); ?></td>
    </tr>
<?php endforeach; ?>
