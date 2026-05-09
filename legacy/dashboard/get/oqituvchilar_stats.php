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
$total = count($oqituvchilar);

$normalizeText = static function (string $value): string {
    $value = trim($value);
    $value = str_replace(['’', '‘', 'ʼ', 'ʻ', '`'], "'", $value);
    $value = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $value);
    while (strpos($value, '  ') !== false) {
        $value = str_replace('  ', ' ', $value);
    }
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
};

$formatIshturLabel = static function (string $name) use ($normalizeText): string {
    $normalized = $normalizeText($name);
    if (
        (strpos($normalized, 'rindosh') !== false || strpos($normalized, 'orindosh') !== false)
        && strpos($normalized, 'ichki') !== false
        && (strpos($normalized, 'qoshimcha') !== false || strpos($normalized, "qo'shimcha") !== false)
    ) {
        return "O'rindosh (ichki-qo'shimcha)";
    }
    if (strpos($normalized, 'ichki') !== false && (strpos($normalized, 'rindosh') !== false || strpos($normalized, 'orindosh') !== false)) {
        return "O'rindosh (ichki-asosiy)";
    }
    if (strpos($normalized, 'tashqi') !== false && (strpos($normalized, 'rindosh') !== false || strpos($normalized, 'orindosh') !== false)) {
        return "O'rindosh (tashqi)";
    }
    return $name !== '' ? $name : 'Belgilanmagan';
};

$formatIlmiyUnvonLabel = static function (string $name) use ($normalizeText): string {
    $normalized = $normalizeText($name);
    if ($normalized === '' || $normalized === 'assistent') {
        return 'Unvonsiz';
    }
    return $name;
};

$formatIlmiyDarajaLabel = static function (string $name) use ($normalizeText): string {
    $normalized = $normalizeText($name);
    if ($normalized === '' || $normalized === 'magistr') {
        return 'Darajasiz';
    }
    return $name;
};

$shtatBirligi = [];
$shtatTuri = [];
$ilmiyUnvon = [];
$ilmiyDaraja = [];
$allowedStavka = ['0.25', '0.5', '0.75', '1', '1.25', '1.5'];

foreach ($oqituvchilar as $row) {
    $ishturNameRaw = (string)($row['ishtur_name'] ?? '');
    $ishturNameNorm = $normalizeText($ishturNameRaw);
    $isSoatbay = strpos($ishturNameNorm, 'soatbay') !== false;

    $stavkaRaw = (string)($row['stavka'] ?? '');
    $stavkaRaw = str_replace(["\xC2\xA0", ',', ' '], ['', '.', ''], $stavkaRaw);
    $stavkaRaw = trim($stavkaRaw);

    if ($stavkaRaw === '' || !is_numeric($stavkaRaw)) {
        $stavkaLabel = $isSoatbay ? 'Soatbay (stavkasiz)' : "Noma'lum";
    } else {
        $stavkaFloat = (float)$stavkaRaw;
        if ($stavkaFloat <= 0) {
            $stavkaLabel = $isSoatbay ? 'Soatbay (stavkasiz)' : "Noma'lum";
        } else {
            $stavkaLabel = rtrim(rtrim(number_format($stavkaFloat, 2, '.', ''), '0'), '.');
            if (!in_array($stavkaLabel, $allowedStavka, true)) {
                $stavkaLabel = $isSoatbay ? 'Soatbay (stavkasiz)' : "Noma'lum";
            }
        }
    }
    $shtatBirligi[$stavkaLabel] = ($shtatBirligi[$stavkaLabel] ?? 0) + 1;

    $turiLabel = $formatIshturLabel((string)($row['ishtur_name'] ?? ''));
    $shtatTuri[$turiLabel] = ($shtatTuri[$turiLabel] ?? 0) + 1;

    $unvonLabel = $formatIlmiyUnvonLabel((string)($row['ilmiy_unvon_name'] ?? ''));
    $ilmiyUnvon[$unvonLabel] = ($ilmiyUnvon[$unvonLabel] ?? 0) + 1;

    $darajaLabel = $formatIlmiyDarajaLabel((string)($row['ilmiy_daraja_name'] ?? ''));
    $ilmiyDaraja[$darajaLabel] = ($ilmiyDaraja[$darajaLabel] ?? 0) + 1;
}

$sortByOrder = static function (array &$items, array $orderMap): void {
    uksort($items, static function (string $a, string $b) use ($orderMap): int {
        $orderA = $orderMap[$a] ?? 1000;
        $orderB = $orderMap[$b] ?? 1000;
        if ($orderA !== $orderB) {
            return $orderA <=> $orderB;
        }
        return strcmp($a, $b);
    });
};

$sortByOrder($ilmiyDaraja, [
    'DSc' => 10,
    'PhD' => 20,
    'Fan nomzodi' => 30,
    'Darajasiz' => 40,
]);

$sortByOrder($ilmiyUnvon, [
    'Professor' => 10,
    'Dotsent' => 20,
    "Katta o'qituvchi" => 30,
    'Unvonsiz' => 40,
]);

$sortByOrder($shtatTuri, [
    'Asosiy shtatda' => 10,
    "O'rindosh (ichki-asosiy)" => 20,
    "O'rindosh (ichki-qo'shimcha)" => 30,
    "O'rindosh (tashqi)" => 40,
    'Soatbay' => 50,
    'Vakant' => 60,
    'Belgilanmagan' => 70,
]);

uksort($shtatBirligi, static function (string $a, string $b): int {
    $aIsNum = is_numeric($a);
    $bIsNum = is_numeric($b);
    if ($aIsNum && $bIsNum) {
        return (float)$a <=> (float)$b;
    }
    if ($aIsNum) {
        return -1;
    }
    if ($bIsNum) {
        return 1;
    }
    if ($a === 'Soatbay (stavkasiz)') {
        return 1;
    }
    if ($b === 'Soatbay (stavkasiz)') {
        return -1;
    }
    if ($a === "Noma'lum") {
        return 1;
    }
    if ($b === "Noma'lum") {
        return -1;
    }
    return strcmp($a, $b);
});

$percentText = static function (int $count, int $totalCount): string {
    if ($totalCount <= 0) {
        return '0%';
    }
    return round(($count / $totalCount) * 100) . '%';
};
?>

<div class="teacher-stats-grid">
    <div class="teacher-stat-card">
        <h3>Ilmiy daraja bo'yicha</h3>
        <table class="teacher-stat-table">
            <thead>
                <tr>
                    <th>Daraja</th>
                    <th class="numeric">Soni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ilmiyDaraja as $label => $count): ?>
                    <tr>
                        <td><?= htmlspecialchars($label) ?></td>
                        <td class="numeric"><?= (int)$count ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="teacher-stat-card">
        <h3>Ilmiy unvon bo'yicha</h3>
        <table class="teacher-stat-table">
            <thead>
                <tr>
                    <th>Unvon</th>
                    <th class="numeric">Soni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ilmiyUnvon as $label => $count): ?>
                    <tr>
                        <td><?= htmlspecialchars($label) ?></td>
                        <td class="numeric"><?= (int)$count ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="teacher-stat-card">
        <h3>Shtat turi bo'yicha</h3>
        <table class="teacher-stat-table">
            <thead>
                <tr>
                    <th>Shtat turi</th>
                    <th class="numeric">Soni</th>
                    <th class="numeric">Ulushi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shtatTuri as $label => $count): ?>
                    <tr>
                        <td><?= htmlspecialchars($label) ?></td>
                        <td class="numeric"><?= (int)$count ?></td>
                        <td class="numeric"><?= $percentText((int)$count, $total) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="teacher-stat-card">
        <h3>Shtat birligi bo'yicha</h3>
        <table class="teacher-stat-table">
            <thead>
                <tr>
                    <th>Shtat birligi</th>
                    <th class="numeric">O'qituvchi soni</th>
                    <th class="numeric">Ulushi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shtatBirligi as $label => $count): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$label) ?></td>
                        <td class="numeric"><?= (int)$count ?></td>
                        <td class="numeric"><?= $percentText((int)$count, $total) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($total <= 0): ?>
    <div class="teacher-stat-empty">Statistika uchun o'qituvchilar topilmadi.</div>
<?php endif; ?>
