<?php
    include_once __DIR__ . '/../config.php';
    $db = new Database();
    $request = array_merge($_GET ?? [], $_POST ?? []);
    $filters = [];
    if (!empty($request['fakultet_id'])) {
        $filters['fakultet_id'] = (int)$request['fakultet_id'];
    }
    if (!empty($request['yonalish_id'])) {
        $filters['yonalish_id'] = (int)$request['yonalish_id'];
    }

    $yunalishlar = $db->get_yunalishlar_with_details($filters);
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
<?php if (empty($yunalishlar)): ?>
    <tr>
        <td colspan="11" style="text-align:center; color:#64748b;">Ma'lumot topilmadi</td>
    </tr>
<?php endif; ?>
