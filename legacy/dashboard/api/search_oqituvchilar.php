<?php
include_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$db = new Database();
$term = trim((string)($_GET['q'] ?? ''));
$kafedraId = (int)($_GET['kafedra_id'] ?? 0);
$loadAll = isset($_GET['all']) && (int)$_GET['all'] === 1;
$isKafedraMudiri = legacy_is_kafedra_mudiri();

if ($isKafedraMudiri) {
    $kafedraId = legacy_user_kafedra_id();
}

function legacy_search_oqituvchilar_rows(Database $db, string $term, int $kafedraId, bool $loadAll): array
{
    $where = [];
    if ($term !== '') {
        $safeTerm = addslashes($term);
        $where[] = "(o.fio LIKE '%{$safeTerm}%' OR o.lavozim LIKE '%{$safeTerm}%')";
    }
    if ($kafedraId > 0) {
        $where[] = 'o.kafedra_id = ' . $kafedraId;
    }

    $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
    $limitSql = $loadAll ? 'LIMIT 2000' : 'LIMIT 30';
    $sql = "
        SELECT o.id, o.fio, o.lavozim
        FROM oqituvchilar o
        {$whereSql}
        ORDER BY o.fio
        {$limitSql}
    ";

    $result = $db->query($sql);
    $items = [];
    if ($result !== false) {
        while ($row = mysqli_fetch_assoc($result)) {
            $fio = trim((string)($row['fio'] ?? ''));
            $lavozim = trim((string)($row['lavozim'] ?? ''));
            $items[] = [
                'id' => (int)($row['id'] ?? 0),
                'text' => $lavozim !== '' ? ($fio . ' (' . $lavozim . ')') : $fio,
            ];
        }
    }

    return $items;
}

$items = legacy_search_oqituvchilar_rows($db, $term, $kafedraId, $loadAll);

echo json_encode(['results' => $items], JSON_UNESCAPED_UNICODE);
    