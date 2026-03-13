<?php
    include_once '../config.php';
    $db = new Database();
    $dars_soat_turlar = $db->get_data_by_table_all('dars_soat_turlar');
?>
<?php foreach ($dars_soat_turlar as $dars_soat_turi): ?>
    <tr>
        <td><?php echo htmlspecialchars($dars_soat_turi['id']); ?></td>
        <td><?php echo htmlspecialchars($dars_soat_turi['name']); ?></td>
        <td><?php echo htmlspecialchars($dars_soat_turi['create_at']); ?></td>
        <td>
            <button
                class="btn btn-sm btn-warning editDarsSoatTuriBtn"
                data-id="<?php echo $dars_soat_turi['id']; ?>"
                data-name="<?php echo htmlspecialchars($dars_soat_turi['name'], ENT_QUOTES, 'UTF-8'); ?>"
            >
                <i class="fas fa-edit"></i> Tahrirlash
            </button>
            <button class="btn btn-sm btn-danger deleteDarsSoatTuriBtn" data-id="<?php echo $dars_soat_turi['id']; ?>">
                <i class="fas fa-trash-alt"></i> O'chirish
            </button>
        </td>
    </tr>
<?php endforeach; ?>
