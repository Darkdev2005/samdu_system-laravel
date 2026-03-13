<?php
    include_once '../config.php';
    $db = new Database();
    $oquv_shakllari = $db->get_data_by_table_all('oquv_shakllar');
?>
<?php foreach ($oquv_shakllari as $oquv_shakl): ?>
    <tr>
        <td><?php echo htmlspecialchars($oquv_shakl['id']); ?></td>
        <td><?php echo htmlspecialchars($oquv_shakl['name']); ?></td>
        <td><?php echo htmlspecialchars($oquv_shakl['create_at']); ?></td>
        <td>
            <button
                class="btn btn-sm btn-warning editOquvShakliBtn"
                data-id="<?php echo $oquv_shakl['id']; ?>"
                data-name="<?php echo htmlspecialchars($oquv_shakl['name'], ENT_QUOTES, 'UTF-8'); ?>"
            >
                <i class="fas fa-edit"></i> Tahrirlash
            </button>
            <button class="btn btn-sm btn-danger deleteOquvShakliBtn" data-id="<?php echo $oquv_shakl['id']; ?>">
                <i class="fas fa-trash-alt"></i> O'chirish
            </button>
        </td>
    </tr>
<?php endforeach; ?>
