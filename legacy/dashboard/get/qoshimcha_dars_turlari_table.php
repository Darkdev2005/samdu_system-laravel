<?php
    include_once '../config.php';
    $db = new Database();
    $qoshimcha_dars_turlar = $db->get_data_by_table_all('qoshimcha_dars_turlar');
?>
<?php foreach ($qoshimcha_dars_turlar as $dars_turi): ?>
    <tr>
        <td><?php echo htmlspecialchars($dars_turi['id']); ?></td>
        <td><?php echo htmlspecialchars($dars_turi['name']); ?></td>
        <td><?php echo htmlspecialchars($dars_turi['koifesent']); ?></td>
        <td><?php echo htmlspecialchars($dars_turi['create_at']); ?></td>
        <td>
            <button
                class="btn btn-sm btn-warning editDarsTuriBtn"
                data-id="<?php echo $dars_turi['id']; ?>"
                data-name="<?php echo htmlspecialchars($dars_turi['name'], ENT_QUOTES, 'UTF-8'); ?>"
                data-koifesent="<?php echo htmlspecialchars((string) $dars_turi['koifesent'], ENT_QUOTES, 'UTF-8'); ?>"
            >
                <i class="fas fa-edit"></i> Tahrirlash
            </button>
            <button class="btn btn-sm btn-danger deleteDarsTuriBtn" data-id="<?php echo $dars_turi['id']; ?>">
                <i class="fas fa-trash-alt"></i> O'chirish
            </button>
        </td>
    </tr>
<?php endforeach; ?>
