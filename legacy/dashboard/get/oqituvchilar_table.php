<?php 
    include_once '../config.php';
    $db = new Database();
    $filters = [];
    if (!empty($_POST['fakultet_id'])) {
        $filters['fakultet_id'] = (int)$_POST['fakultet_id'];
    }
    if (!empty($_POST['kafedra_id'])) {
        $filters['kafedra_id'] = (int)$_POST['kafedra_id'];
    }
    legacy_apply_kafedra_scope($filters);
    $oqituvchilar = $db->get_oqtuvchilar($filters);

    $normalizeIshturName = static function (string $value): string {
        $value = trim($value);
        $value = str_replace(['’', '‘', 'ʼ', 'ʻ', '`'], "'", $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    };

    $formatIshturLabel = static function (string $name) use ($normalizeIshturName): string {
        $normalized = $normalizeIshturName($name);
        if (
            (strpos($normalized, 'rindosh') !== false || strpos($normalized, 'orindosh') !== false)
            && strpos($normalized, 'ichki') !== false
            && (strpos($normalized, 'qoshimcha') !== false || strpos($normalized, "qo'shimcha") !== false)
        ) {
            return "O'rindosh (ichki-qo'shimcha)";
        }
        if (strpos($normalized, 'ichki') !== false && strpos($normalized, 'rindosh') !== false) {
            return "O'rindosh (ichki-asosiy)";
        }
        if (strpos($normalized, 'tashqi') !== false && strpos($normalized, 'rindosh') !== false) {
            return "O'rindosh (tashqi)";
        }
        return $name;
    };

    $normalizeAcademicName = static function (string $value): string {
        $value = trim($value);
        $value = str_replace(['вЂ™', 'вЂ', 'Кј', 'К»', '`'], "'", $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    };

    $formatIlmiyUnvonLabel = static function (string $name) use ($normalizeAcademicName): string {
        if ($normalizeAcademicName($name) === 'assistent') {
            return 'Unvonsiz';
        }
        return $name;
    };

    $formatIlmiyDarajaLabel = static function (string $name) use ($normalizeAcademicName): string {
        if ($normalizeAcademicName($name) === 'magistr') {
            return 'Ilmiy darajasiz';
        }
        return $name;
    };
?>
<?php foreach ($oqituvchilar as $oqituvchi): ?>
    <tr>
        <td><?php echo htmlspecialchars($oqituvchi['id']); ?></td>
        <td><?php echo htmlspecialchars($oqituvchi['fio']); ?></td>
        <td><?php echo htmlspecialchars($oqituvchi['fakultet_name']); ?></td>
        <td><?php echo htmlspecialchars($oqituvchi['kafedra_name']); ?></td>
        <td><?php echo htmlspecialchars($oqituvchi['lavozim']); ?></td>
        <td><?php echo htmlspecialchars($oqituvchi['stavka']); ?></td>
        <td><?php echo htmlspecialchars($formatIshturLabel((string)($oqituvchi['ishtur_name'] ?? ''))); ?></td>
        <td><?php echo htmlspecialchars($formatIlmiyUnvonLabel((string)($oqituvchi['ilmiy_unvon_name'] ?? ''))); ?></td>
        <td><?php echo htmlspecialchars($formatIlmiyDarajaLabel((string)($oqituvchi['ilmiy_daraja_name'] ?? ''))); ?></td>
        <td>
            <button
                class="btn btn-sm btn-warning editOqituvchiBtn"
                data-id="<?php echo (int) $oqituvchi['id']; ?>"
                data-fakultet-id="<?php echo (int) $oqituvchi['fakultet_id']; ?>"
                data-kafedra-id="<?php echo (int) $oqituvchi['kafedra_id']; ?>"
                data-fio="<?php echo htmlspecialchars((string) $oqituvchi['fio'], ENT_QUOTES, 'UTF-8'); ?>"
                data-lavozim="<?php echo htmlspecialchars((string) $oqituvchi['lavozim'], ENT_QUOTES, 'UTF-8'); ?>"
                data-stavka="<?php echo htmlspecialchars((string) $oqituvchi['stavka'], ENT_QUOTES, 'UTF-8'); ?>"
                data-ishtur-id="<?php echo (int) $oqituvchi['ishtur_id']; ?>"
                data-ilmiy-unvon-id="<?php echo (int) $oqituvchi['ilmiy_unvon_id']; ?>"
                data-ilmiy-daraja-id="<?php echo (int) $oqituvchi['ilmiy_daraja_id']; ?>"
            >
                <i class="fas fa-edit"></i> Tahrirlash
            </button>
            <button class="btn btn-sm btn-danger deleteOqituvchiBtn" data-id="<?php echo $oqituvchi['id']; ?>">
                <i class="fas fa-trash-alt"></i> O'chirish
            </button>
        </td>
    </tr>
<?php endforeach; ?>
