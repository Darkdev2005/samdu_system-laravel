<?php
    include_once '../config.php';
    $db = new Database();
    header('Content-Type: application/json');

    $akademikdarajalar = $db->get_data_by_table_all('akademik_darajalar');

?>
<?php foreach ($akademikdarajalar as $akademikdaraja): ?>
    <tr>
        <td><?php echo htmlspecialchars($akademikdaraja['id']); ?></td>
        <td><?php echo htmlspecialchars($akademikdaraja['name']); ?></td>
        <td><?php echo htmlspecialchars($akademikdaraja['create_at']); ?></td>
        <td>
            <button
                class="btn btn-sm btn-warning editFakultetBtn"
                data-id="<?php echo $akademikdaraja['id']; ?>"
                data-name="<?php echo htmlspecialchars($akademikdaraja['name'], ENT_QUOTES, 'UTF-8'); ?>"
            >
                <i class="fas fa-edit"></i> Tahrirlash
            </button>
            <button class="btn btn-sm btn-danger deleteFakultetBtn" data-id="<?php echo $akademikdaraja['id']; ?>">
                <i class="fas fa-trash-alt"></i> O'chirish
            </button>
        </td>
    </tr>
<?php endforeach; ?>
