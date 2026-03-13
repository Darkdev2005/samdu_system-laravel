<?php
    include_once '../config.php';
    $db = new Database();
    $guruhlar = $db->get_guruhlar();
?>
<?php foreach($guruhlar as $guruh): ?>
    <tr>
        <td><?php echo htmlspecialchars($guruh['id']); ?></td>
        <td><?php echo htmlspecialchars($guruh['yonalish_name']); ?></td>
        <td><?php echo htmlspecialchars($guruh['guruh_nomer']); ?></td>
        <td><?php echo htmlspecialchars($guruh['soni']); ?></td>
        <td><?php echo htmlspecialchars($guruh['create_at']); ?></td>
        <td>
            <button class="btn btn-sm btn-warning editGuruhBtn" data-id="<?php echo $guruh['id']; ?>">
                <i class="fas fa-edit"></i> Tahrirlash
            </button>
            <button class="btn btn-sm btn-danger deleteGuruhBtn" data-id="<?php echo $guruh['id']; ?>">
                <i class="fas fa-trash-alt"></i> O'chirish
            </button>
        </td>
    </tr>
<?php endforeach; ?>
