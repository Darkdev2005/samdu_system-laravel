<?php
include_once '../config.php';
$db = new Database();
$historyRows = $db->get_yunalishlar_history_with_details();
?>
<?php foreach ($historyRows as $row): ?>
    <tr>
        <td><?= htmlspecialchars($row['id']); ?></td>
        <td><?= htmlspecialchars($row['yonalish_id']); ?></td>
        <td><?= htmlspecialchars($row['yonalish_nomi']); ?></td>
        <td><?= htmlspecialchars($row['yonalish_kodi']); ?></td>
        <td><?= htmlspecialchars($row['patok_soni']); ?></td>
        <td><?= htmlspecialchars($row['kattaguruh_soni']); ?></td>
        <td><?= htmlspecialchars($row['kichikguruh_soni']); ?></td>
        <td><?= htmlspecialchars($row['akademik_daraja'] ?? '-'); ?></td>
        <td><?= htmlspecialchars($row['talim_shakli'] ?? '-'); ?></td>
        <td><?= htmlspecialchars($row['fakultet'] ?? '-'); ?></td>
        <td>
            <?= $row['sync_status'] === 'sync' ? 'Sinxronlangan' : 'Sinxronlanmagan'; ?>
        </td>
        <td><?= htmlspecialchars($row['changed_at']); ?></td>
    </tr>
<?php endforeach; ?>
