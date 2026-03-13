<?php
    include_once '../config.php';
    $db = new Database();
    $oquv_haftalik_turlar = $db->get_data_by_table_all('oquv_haftalik_turlar');
?>
<?php foreach ($oquv_haftalik_turlar as $haftalik): ?>
    <tr>
        <td><?php echo htmlspecialchars($haftalik['id']); ?></td>
        <td><?php echo htmlspecialchars($haftalik['name']); ?></td>
        <td><?php echo htmlspecialchars($haftalik['short_name']); ?></td>
        <td><?php echo htmlspecialchars($haftalik['create_at']); ?></td>
        <td>
            <button
                class="btn btn-sm btn-warning editHaftalikBtn"
                data-id="<?php echo $haftalik['id']; ?>"
                data-name="<?php echo htmlspecialchars($haftalik['name'], ENT_QUOTES, 'UTF-8'); ?>"
                data-short-name="<?php echo htmlspecialchars($haftalik['short_name'], ENT_QUOTES, 'UTF-8'); ?>"
            >
                <i class="fas fa-edit"></i> Tahrirlash
            </button>
            <button class="btn btn-sm btn-danger deleteHaftalikBtn" data-id="<?php echo $haftalik['id']; ?>">
                <i class="fas fa-trash-alt"></i> O'chirish
            </button>
        </td>
    </tr>
<?php endforeach; ?>
