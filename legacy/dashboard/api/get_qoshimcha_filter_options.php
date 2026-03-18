<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config.php';

$db = new Database();

$fakultetId = (int)($_GET['fakultet_id'] ?? 0);
$yonalishId = (int)($_GET['yonalish_id'] ?? 0);

$makeShortCode = static function (string $name): string {
    $words = preg_split('/\s+/', trim($name)) ?: [];
    $short = '';
    foreach ($words as $word) {
        $word = trim((string)$word);
        if ($word === '') {
            continue;
        }
        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            $first = @mb_substr($word, 0, 1, 'UTF-8');
            if ($first !== false && $first !== '') {
                $short .= (string)@mb_strtoupper($first, 'UTF-8');
                continue;
            }
        }
        $short .= strtoupper((string)substr($word, 0, 1));
    }
    return $short;
};

$yonalishSql = "
    SELECT
        y.id,
        y.name,
        y.kirish_yili,
        y.fakultet_id
    FROM yonalishlar y
    JOIN semestrlar s ON s.yonalish_id = y.id
    " . ($fakultetId > 0 ? "WHERE y.fakultet_id = {$fakultetId}" : "") . "
    GROUP BY y.id, y.name, y.kirish_yili, y.fakultet_id
    ORDER BY y.name, y.kirish_yili
";

$yonalishRes = $db->query($yonalishSql);
$yonalishlar = [];
$allowedYonalishIds = [];
while ($row = mysqli_fetch_assoc($yonalishRes)) {
    $id = (int)($row['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }
    $allowedYonalishIds[$id] = true;
    $name = (string)($row['name'] ?? '');
    $kirishYili = (string)($row['kirish_yili'] ?? '');
    $yonalishlar[] = [
        'id' => $id,
        'name' => $name,
        'kirish_yili' => $kirishYili,
        'fakultet_id' => (int)($row['fakultet_id'] ?? 0),
        'label' => $name . ($kirishYili !== '' ? ' - ' . $kirishYili : ''),
    ];
}

if ($yonalishId > 0 && !isset($allowedYonalishIds[$yonalishId])) {
    $yonalishId = 0;
}

$semFilters = [];
if ($fakultetId > 0) {
    $semFilters['fakultet_id'] = $fakultetId;
}
if ($yonalishId > 0) {
    $semFilters['yonalish_id'] = $yonalishId;
}

$semestrRows = $db->get_semestrlar($semFilters);
$semestrlar = [];
foreach ($semestrRows as $s) {
    $darajaRaw = trim((string)($s['akademik_daraja_name'] ?? ''));
    $daraja = function_exists('mb_strtolower')
        ? (string)@mb_strtolower($darajaRaw, 'UTF-8')
        : strtolower($darajaRaw);
    $darajaPrefix = '';
    if (strpos($daraja, 'magistr') !== false) {
        $darajaPrefix = 'M ';
    } elseif (strpos($daraja, 'bakalavr') !== false) {
        $darajaPrefix = 'B ';
    }

    $short = $makeShortCode((string)($s['yonalish_name'] ?? ''));
    $label = $darajaPrefix
        . $short
        . '_'
        . (string)($s['kirish_yili'] ?? '')
        . ' - '
        . (string)($s['semestr'] ?? '')
        . '-semestr('
        . (string)($s['jami_talabalar'] ?? 0)
        . ')';

    $semestrlar[] = [
        'id' => (int)($s['id'] ?? 0),
        'label' => $label,
        'fakultet_id' => (int)($s['yonalish_fakultet_id'] ?? ($s['fakultet_id'] ?? 0)),
        'yonalish_id' => (int)($s['yonalish_id'] ?? 0),
        'talaba' => (int)($s['jami_talabalar'] ?? 0),
        'talim_shakli' => (string)($s['talim_shakli_name'] ?? ''),
        'talim_shakli_id' => (int)($s['talim_shakli_id'] ?? 0),
        'daraja' => (string)($s['akademik_daraja_name'] ?? ''),
        'patok' => (int)($s['patok_soni'] ?? 0),
        'guruh' => (int)($s['guruhlar_soni'] ?? 0),
    ];
}

echo json_encode([
    'success' => true,
    'effective_yonalish_id' => $yonalishId,
    'yonalishlar' => $yonalishlar,
    'semestrlar' => $semestrlar,
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

