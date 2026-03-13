<?php
    include_once '../config.php';
    $db = new Database();

    $yunalishlar = $db->get_yunalishlar_with_details();
?>
<?php foreach ($yunalishlar as $yunalish): ?>
    <tr>
        <td><?php echo htmlspecialchars($yunalish['id']); ?></td>
        <td><?php echo htmlspecialchars($yunalish['yonalish_nomi']); ?></td>
        <td><?php echo htmlspecialchars($yunalish['yonalish_kodi']); ?></td>
        <td><?php echo htmlspecialchars($yunalish['talim_muddati']); ?></td>
        <td><?php echo htmlspecialchars($yunalish['kirish_yili']); ?></td>
        <td><?php echo htmlspecialchars($yunalish['akademik_daraja']); ?></td>
        <td><?php echo htmlspecialchars($yunalish['talim_shakli']); ?></td>
        <td><?php echo htmlspecialchars($yunalish['kvalifikatsiya']); ?></td>
        <td><?php echo htmlspecialchars($yunalish['fakultet']); ?></td>
        <td><?php echo htmlspecialchars($yunalish['create_at']); ?></td>
        <td>
            <button
                class="btn btn-sm btn-warning editYonalishBtn"
                data-id="<?php echo $yunalish['id']; ?>"
                onclick="openYonalishEdit(<?php echo (int)$yunalish['id']; ?>)"
            >
                <i class="fas fa-edit"></i> Tahrirlash
            </button>
            <button class="btn btn-sm btn-danger deleteYunalishBtn" data-id="<?php echo $yunalish['id']; ?>">
                <i class="fas fa-trash-alt"></i> O'chirish
            </button>
        </td>
    </tr>
<?php endforeach; ?>
