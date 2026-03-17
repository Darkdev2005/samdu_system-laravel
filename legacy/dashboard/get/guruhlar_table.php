<?php
    include_once '../config.php';
    $db = new Database();
    $request = array_merge($_GET ?? [], $_POST ?? []);
    $filters = [];
    if (!empty($request['fakultet_id'])) {
        $filters['fakultet_id'] = (int)$request['fakultet_id'];
    }
    if (!empty($request['yonalish_id'])) {
        $filters['yonalish_id'] = (int)$request['yonalish_id'];
    }
    $guruhlar = $db->get_guruhlar($filters);
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
<?php if (empty($guruhlar)): ?>
    <tr>
        <td colspan="6" style="text-align:center; color:#64748b;">Ma'lumot topilmadi</td>
    </tr>
<?php endif; ?>
