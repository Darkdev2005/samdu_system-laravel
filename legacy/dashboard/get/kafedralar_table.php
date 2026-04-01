<?php
include_once '../config.php';
$db = new Database();

$fakultetId = (int)($_GET['fakultet_id'] ?? 0);

if ($fakultetId > 0) {
    $sql = "
        SELECT
            k.id,
            k.name,
            k.fakultet_id,
            k.create_at,
            f.name AS fakultet_name
        FROM kafedralar k
        LEFT JOIN fakultetlar f ON f.id = k.fakultet_id
        WHERE k.fakultet_id = {$fakultetId}
        ORDER BY k.id ASC
    ";
    $result = $db->query($sql);
    $kafedralar = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $kafedralar[] = $row;
        }
    }
} else {
    $kafedralar = $db->get_kafedralar();
}
?>

<?php if (count($kafedralar) > 0): ?>
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
                    data-fakultet-id="<?php echo (int)$kafedra['fakultet_id']; ?>"
                >
                    <i class="fas fa-edit"></i> Tahrirlash
                </button>
                <button
                    class="btn btn-sm btn-danger deleteKafedraBtn"
                    data-id="<?php echo $kafedra['id']; ?>"
                >
                    <i class="fas fa-trash-alt"></i> O'chirish
                </button>
            </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="5">Ma'lumot topilmadi</td>
    </tr>
<?php endif; ?>