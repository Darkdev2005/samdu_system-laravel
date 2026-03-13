<?php
    include_once '../config.php';
    $db = new Database();
    header('Content-Type: application/json');

    $fakultetlar = $db->get_data_by_table_all('fakultetlar');

?>
<?php foreach ($fakultetlar as $fakultet): ?>
    <tr>
        <td><?php echo htmlspecialchars($fakultet['id']); ?></td>
        <td><?php echo htmlspecialchars($fakultet['name']); ?></td>
        <td><?php echo htmlspecialchars($fakultet['create_at']); ?></td>
        <td>
            <button
                class="btn btn-sm btn-warning editFakultetBtn"
                data-id="<?php echo $fakultet['id']; ?>"
                data-name="<?php echo htmlspecialchars($fakultet['name'], ENT_QUOTES, 'UTF-8'); ?>"
            >
                <i class="fas fa-edit"></i> Tahrirlash
            </button>
            <button class="btn btn-sm btn-danger deleteFakultetBtn" data-id="<?php echo $fakultet['id']; ?>">
                <i class="fas fa-trash-alt"></i> O'chirish
            </button>
        </td>
    </tr>
<?php endforeach; ?>
