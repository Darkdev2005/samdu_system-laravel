<?php
    include_once '../config.php';
    $db = new Database();
    $kafedralar = $db->get_kafedralar();
?>
<?php foreach ($kafedralar as $kafedra): ?>
    <tr>
        <td><?php echo htmlspecialchars($kafedra['id']); ?></td>
        <td><?php echo htmlspecialchars($kafedra['name']); ?></td>
        <td><?php echo htmlspecialchars($kafedra['fakultet_name']); ?></td>
        <td><?php echo htmlspecialchars($kafedra['create_at']); ?></td>
        <td>
            <button
                class="btn btn-sm btn-warning editKafedraBtn"
                data-id="<?php echo $kafedra['id']; ?>"
                data-name="<?php echo htmlspecialchars($kafedra['name'], ENT_QUOTES, 'UTF-8'); ?>"
                data-fakultet-id="<?php echo (int) $kafedra['fakultet_id']; ?>"
            >
                <i class="fas fa-edit"></i> Tahrirlash
            </button>
            <button class="btn btn-sm btn-danger deleteKafedraBtn" data-id="<?php echo $kafedra['id']; ?>">
                <i class="fas fa-trash-alt"></i> O'chirish
            </button>
        </td>
    </tr>
<?php endforeach; ?>
