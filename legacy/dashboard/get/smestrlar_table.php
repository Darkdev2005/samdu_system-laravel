<?php
    include_once '../config.php';
    $db = new Database();
    $semsetrlar = $db->get_semestrlar();
?>
<?php foreach ($semsetrlar as $semestr): ?>
    <tr>
        <td><?php echo htmlspecialchars($semestr['id']); ?></td>
        <td><?php echo htmlspecialchars($semestr['fakultet_name']); ?></td>
        <td><?= implode('', array_map(fn($w)=>mb_strtoupper(mb_substr($w,0,1,'UTF-8'),'UTF-8'), preg_split('/\s+/u', trim($semestr['yonalish_name'])))).'_'.$semestr['kirish_yili']; ?></td>
        <td><?php echo htmlspecialchars($semestr['semestr']); ?></td>
        <td><?php echo htmlspecialchars($semestr['create_at']); ?></td>
        <td>
            <button
                class="btn btn-sm btn-warning editSmestrBtn"
                data-id="<?php echo $semestr['id']; ?>"
                data-semestr="<?php echo (int) $semestr['semestr']; ?>"
            >
                <i class="fas fa-edit"></i> Tahrirlash
            </button>
            <button class="btn btn-sm btn-danger deleteSmestrBtn" data-id="<?php echo $semestr['id']; ?>">
                <i class="fas fa-trash-alt"></i> O'chirish
            </button>
        </td>
    </tr>
<?php endforeach; ?>
