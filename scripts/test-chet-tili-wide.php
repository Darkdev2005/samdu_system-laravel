<?php
// Keng test: 1 ta bazaviy chet tili + 5 ta variant yaratib, guruhlar kesimida taqsimotni tekshiradi.

include __DIR__ . '/../legacy/dashboard/config.php';

$db = new Database();
$logs = [];

function fail(string $message): void
{
    global $logs;
    foreach ($logs as $line) {
        echo $line . PHP_EOL;
    }
    echo "FAIL: {$message}" . PHP_EOL;
    exit(1);
}

function ok(string $message): void
{
    global $logs;
    $logs[] = "OK: {$message}";
}

function runSaveEndpoint(array $postData): string
{
    $oldPost = $_POST ?? [];
    $_POST = $postData;

    $oldCwd = getcwd();
    chdir(__DIR__ . '/../legacy/dashboard/insert');
    ob_start();
    include 'save_chet_tili_taqsimot.php';
    $raw = trim(ob_get_clean());
    chdir($oldCwd);

    $_POST = $oldPost;
    return $raw;
}

$candidateRes = $db->query("
    SELECT
        s.id AS semestr_id,
        s.semestr AS semestr_num,
        y.id AS yonalish_id,
        y.name AS yonalish_name,
        y.kirish_yili,
        COUNT(g.id) AS group_count,
        COALESCE(SUM(g.soni), 0) AS total_students
    FROM semestrlar s
    JOIN yonalishlar y ON y.id = s.yonalish_id
    LEFT JOIN guruhlar g ON g.yonalish_id = y.id
    GROUP BY s.id, s.semestr, y.id, y.name, y.kirish_yili
    HAVING COUNT(g.id) > 0
    ORDER BY group_count DESC, total_students DESC, s.id ASC
    LIMIT 1
");
$candidate = $candidateRes ? mysqli_fetch_assoc($candidateRes) : null;
if (!$candidate) {
    fail("Test uchun guruhli semestr topilmadi");
}

$semestrId = (int)$candidate['semestr_id'];
$semestrNum = (int)$candidate['semestr_num'];
$yonalishId = (int)$candidate['yonalish_id'];
$yonalishLabel = trim((string)$candidate['yonalish_name']) . ' - ' . trim((string)$candidate['kirish_yili']);

ok("Test semestr: ID={$semestrId}, {$semestrNum}-semestr, yo'nalish={$yonalishLabel}");

$groups = [];
$groupRes = $db->query("
    SELECT id, guruh_nomer, soni
    FROM guruhlar
    WHERE yonalish_id = $yonalishId
    ORDER BY guruh_nomer
");
if ($groupRes) {
    while ($row = mysqli_fetch_assoc($groupRes)) {
        $groups[] = [
            'id' => (int)$row['id'],
            'name' => trim((string)$row['guruh_nomer']),
            'size' => (int)$row['soni'],
        ];
    }
}
if (count($groups) === 0) {
    fail("Yo'nalish uchun guruh topilmadi");
}
ok("Guruhlar soni: " . count($groups));

$kafedraIds = [];
$kRes = $db->query("SELECT id FROM kafedralar ORDER BY id");
if ($kRes) {
    while ($k = mysqli_fetch_assoc($kRes)) {
        $kid = (int)$k['id'];
        if ($kid > 0) {
            $kafedraIds[] = $kid;
        }
    }
}
if (count($kafedraIds) === 0) {
    fail("Variant fanlar uchun kafedra topilmadi");
}

$code = 'ZZ_CHT_WIDE_' . date('YmdHis');
$baseFanId = (int)$db->insert('fanlar', [
    'fan_code' => $code,
    'fan_name' => 'Chet tili',
    'kafedra_id' => 0,
    'semestr_id' => $semestrId,
    'tanlov_fan' => 3,
]);
if ($baseFanId <= 0) {
    fail("Bazaviy fan yaratilmadi");
}
ok("Bazaviy fan yaratildi: ID={$baseFanId}, code={$code}");

$ishchiId = (int)$db->insert('ishchi_oquv_reja', [
    'base_fan_id' => $baseFanId,
    'semestr_id' => $semestrId,
]);
if ($ishchiId <= 0) {
    fail("ishchi_oquv_reja yaratilmadi");
}

$variantNames = [
    '1. Ingliz tili',
    '2. Nemis tili',
    '3. Fransuz tili',
    '4. Ispan tili',
    '5. Turk tili',
];
$variantIds = [];
foreach ($variantNames as $idx => $name) {
    $kafedraId = $kafedraIds[$idx % count($kafedraIds)];
    $variantFanId = (int)$db->insert('fanlar', [
        'fan_code' => $code,
        'fan_name' => $name,
        'kafedra_id' => $kafedraId,
        'semestr_id' => $semestrId,
        'tanlov_fan' => 3,
    ]);
    if ($variantFanId <= 0) {
        fail("Variant fan yaratilmadi: {$name}");
    }
    $variantIds[] = $variantFanId;

    $bindOk = $db->insert('ishchi_oquv_reja_variants', [
        'ishchi_reja_id' => $ishchiId,
        'fan_id' => $variantFanId,
    ]);
    if ((int)$bindOk <= 0) {
        fail("Variant ishchi reja bilan bog'lanmadi: {$name}");
    }
}
ok("5 ta variant yaratildi va ishchi rejaga bog'landi");

$allocations = [];
$expectedVariantTotals = [];
foreach ($variantIds as $vid) {
    $expectedVariantTotals[$vid] = 0;
}

foreach ($groups as $group) {
    $size = (int)$group['size'];
    $base = intdiv($size, count($variantIds));
    $rem = $size % count($variantIds);

    $allocations[(string)$semestrId][(string)$group['id']] = [];
    foreach ($variantIds as $i => $vid) {
        $value = $base + ($i < $rem ? 1 : 0);
        $allocations[(string)$semestrId][(string)$group['id']][(string)$vid] = $value;
        $expectedVariantTotals[$vid] += $value;
    }
}
ok("Taqsimot payload tayyorlandi");

$postData = [
    'base_fan_id' => (string)$baseFanId,
    'semestr_ids_json' => json_encode([$semestrId], JSON_UNESCAPED_UNICODE),
    'allocations_json' => json_encode($allocations, JSON_UNESCAPED_UNICODE),
];
$responseRaw = runSaveEndpoint($postData);
$response = json_decode($responseRaw, true);

if (!is_array($response) || empty($response['success'])) {
    fail("Endpoint xato qaytardi: {$responseRaw}");
}
ok("Endpoint success: " . ($response['message'] ?? ''));

$variantSql = implode(',', array_map('intval', $variantIds));

foreach ($groups as $group) {
    $gid = (int)$group['id'];
    $size = (int)$group['size'];
    $sumRes = $db->query("
        SELECT COALESCE(SUM(talabalar_soni), 0) AS s
        FROM chet_tili_talablar
        WHERE semestr_id = $semestrId
          AND guruh_id = $gid
          AND fan_id IN ($variantSql)
    ");
    $sumRow = $sumRes ? mysqli_fetch_assoc($sumRes) : null;
    $sum = (int)($sumRow['s'] ?? 0);
    if ($sum !== $size) {
        fail("Guruh {$group['name']} yig'indisi xato: {$sum} != {$size}");
    }
}
ok("Har guruh bo'yicha yig'indi tekshiruvi PASS");

$oquvRes = $db->query("
    SELECT fan_id, jami_talaba
    FROM chet_tili_oquv_guruhlar
    WHERE semestr_num = $semestrNum AND fan_id IN ($variantSql)
");
$actualVariantTotals = [];
if ($oquvRes) {
    while ($row = mysqli_fetch_assoc($oquvRes)) {
        $actualVariantTotals[(int)$row['fan_id']] = (int)$row['jami_talaba'];
    }
}

foreach ($variantIds as $vid) {
    $exp = (int)$expectedVariantTotals[$vid];
    $act = (int)($actualVariantTotals[$vid] ?? 0);
    if ($exp !== $act) {
        fail("Variant {$vid} jami xato: {$act} != {$exp}");
    }
}
ok("Til bo'yicha bitta guruh yig'indilari PASS");

echo PHP_EOL;
foreach ($logs as $line) {
    echo $line . PHP_EOL;
}
echo "TEST PASS" . PHP_EOL;
echo "Base fan ID: {$baseFanId}" . PHP_EOL;
echo "Fan code: {$code}" . PHP_EOL;
echo "Variant IDs: " . implode(', ', $variantIds) . PHP_EOL;
echo "Semestr ID: {$semestrId}" . PHP_EOL;
?>
