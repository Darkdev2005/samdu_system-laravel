<?php
    include_once '../config.php';
    $db = new Database();
    header('Content-Type: application/json');

    $talim_shakllar = $db->get_data_by_table_all('talim_shakllar');

?>
<?php foreach ($talim_shakllar as $talim_shakl): ?>
    <tr>
        <td><?php echo htmlspecialchars($talim_shakl['id']); ?></td>
        <td><?php echo htmlspecialchars($talim_shakl['name']); ?></td>
        <td><?php echo htmlspecialchars($talim_shakl['create_at']); ?></td>
        <td>
            <button
                class="btn btn-sm btn-warning editFakultetBtn"
                data-id="<?php echo $talim_shakl['id']; ?>"
                data-name="<?php echo htmlspecialchars($talim_shakl['name'], ENT_QUOTES, 'UTF-8'); ?>"
            >
                <i class="fas fa-edit"></i> Tahrirlash
            </button>
            <button class="btn btn-sm btn-danger deleteFakultetBtn" data-id="<?php echo $talim_shakl['id']; ?>">
                <i class="fas fa-trash-alt"></i> O'chirish
            </button>
        </td>
    </tr>
<?php endforeach; ?>
