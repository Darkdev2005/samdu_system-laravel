<?php
    include_once '../config.php';
    $db = new Database();

    // Izoh: Birlashtiriladigan fanlar ro'yxatini kafedra bilan chiqarish.
    $result = $db->query("
        SELECT uf.id, uf.fan_code, uf.fan_name, uf.semestr, uf.create_at, k.name AS kafedra_name
        FROM umumtalim_fanlar uf
        LEFT JOIN kafedralar k ON k.id = uf.kafedra_id
        ORDER BY uf.id DESC
    ");
?>
<?php if ($result): ?>
    <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['id']); ?></td>
            <td><?php echo htmlspecialchars($row['fan_code']); ?></td>
            <td><?php echo htmlspecialchars($row['fan_name']); ?></td>
            <td><?php echo htmlspecialchars($row['semestr']); ?></td>
            <td><?php echo htmlspecialchars($row['kafedra_name'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($row['create_at']); ?></td>
            <td>
                <button class="btn btn-sm btn-warning" disabled>
                    <i class="fas fa-edit"></i> Tahrirlash
                </button>
                <button class="btn btn-sm btn-danger" disabled>
                    <i class="fas fa-trash-alt"></i> O'chirish
                </button>
            </td>
        </tr>
    <?php endwhile; ?>
<?php endif; ?>
