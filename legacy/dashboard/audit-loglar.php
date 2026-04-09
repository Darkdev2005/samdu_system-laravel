<?php
include_once 'config.php';

// Izoh: Audit log web sahifasi default holatda yopiq.
// Faqat server tomonda .env da AUDIT_LOG_WEB_VIEW=true bo'lsa ochiladi.
$allowAuditWebView = false;
if (function_exists('env')) {
    $allowAuditWebView = filter_var((string)env('AUDIT_LOG_WEB_VIEW', 'false'), FILTER_VALIDATE_BOOLEAN);
} else {
    $allowAuditWebView = filter_var((string)getenv('AUDIT_LOG_WEB_VIEW'), FILTER_VALIDATE_BOOLEAN);
}

if (!$allowAuditWebView) {
    http_response_code(404);
    exit('Not Found');
}

$db = new Database();

$filters = [
    'user_id' => trim((string)($_GET['user_id'] ?? '')),
    'action' => trim((string)($_GET['action'] ?? '')),
    'table_name' => trim((string)($_GET['table_name'] ?? '')),
    'source_file' => trim((string)($_GET['source_file'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
    'date_from' => trim((string)($_GET['date_from'] ?? '')),
    'date_to' => trim((string)($_GET['date_to'] ?? '')),
];
$limit = (int)($_GET['limit'] ?? 300);
$logs = $db->get_audit_logs($filters, $limit);
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Loglar - O'quv Qo'lanma</title>
    <link rel="stylesheet" href="../assets/css/dashboard_style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }
        .filter-grid input, .filter-grid select {
            width: 100%;
            height: 38px;
            border: 1px solid #d8e2eb;
            border-radius: 8px;
            padding: 0 10px;
            font-size: 14px;
        }
        .filter-actions {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        .payload-box {
            max-width: 420px;
            max-height: 120px;
            overflow: auto;
            white-space: pre-wrap;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px;
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>
    <main class="main-content">
        <header class="top-navbar">
            <div class="navbar-left">
                <h1>Audit loglar</h1>
                <p class="navbar-subtitle">Kim, qachon, qayerda va nimani o'zgartirgani</p>
            </div>
        </header>

        <div class="content-container">
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        <h3>So'nggi harakatlar</h3>
                        <span class="badge"><?= count($logs) ?> ta</span>
                    </div>
                </div>

                <form method="get">
                    <div class="filter-grid">
                        <input type="number" name="user_id" placeholder="user_id" value="<?= htmlspecialchars($filters['user_id']) ?>">
                        <select name="action">
                            <option value="">Barcha action</option>
                            <option value="INSERT" <?= $filters['action'] === 'INSERT' ? 'selected' : '' ?>>INSERT</option>
                            <option value="UPDATE" <?= $filters['action'] === 'UPDATE' ? 'selected' : '' ?>>UPDATE</option>
                            <option value="DELETE" <?= $filters['action'] === 'DELETE' ? 'selected' : '' ?>>DELETE</option>
                            <option value="REPLACE" <?= $filters['action'] === 'REPLACE' ? 'selected' : '' ?>>REPLACE</option>
                        </select>
                        <input type="text" name="table_name" placeholder="table_name" value="<?= htmlspecialchars($filters['table_name']) ?>">
                        <input type="text" name="source_file" placeholder="source_file" value="<?= htmlspecialchars($filters['source_file']) ?>">
                        <select name="status">
                            <option value="">Barcha status</option>
                            <option value="success" <?= $filters['status'] === 'success' ? 'selected' : '' ?>>success</option>
                            <option value="error" <?= $filters['status'] === 'error' ? 'selected' : '' ?>>error</option>
                        </select>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>">
                        <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>">
                        <input type="number" name="limit" min="1" max="1000" value="<?= htmlspecialchars((string)$limit) ?>" placeholder="limit">
                    </div>
                    <div class="filter-actions">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filtrlash</button>
                        <a class="btn btn-secondary" href="audit-loglar.php"><i class="fas fa-rotate-left"></i> Tozalash</a>
                    </div>
                </form>

                <div class="table-responsive" style="margin-top: 12px;">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Vaqt</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Jadval</th>
                            <th>Source</th>
                            <th>URI</th>
                            <th>IP</th>
                            <th>Status</th>
                            <th>Payload</th>
                            <th>SQL</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $row): ?>
                                <tr>
                                    <td><?= (int)$row['id'] ?></td>
                                    <td><?= htmlspecialchars((string)$row['created_at']) ?></td>
                                    <td>
                                        <?= htmlspecialchars((string)($row['username'] ?? '')) ?>
                                        <?php if (!empty($row['user_id'])): ?>
                                            (<?= (int)$row['user_id'] ?>)
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string)$row['action']) ?></td>
                                    <td><?= htmlspecialchars((string)$row['table_name']) ?></td>
                                    <td><?= htmlspecialchars((string)$row['source_file']) ?></td>
                                    <td><?= htmlspecialchars((string)$row['request_uri']) ?></td>
                                    <td><?= htmlspecialchars((string)$row['ip_address']) ?></td>
                                    <td><?= htmlspecialchars((string)$row['status']) ?></td>
                                    <td>
                                        <div class="payload-box"><?= htmlspecialchars((string)($row['payload'] ?? '')) ?></div>
                                    </td>
                                    <td>
                                        <div class="payload-box"><?= htmlspecialchars((string)($row['sql_text'] ?? '')) ?></div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11">Ma'lumot topilmadi</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/app.js"></script>
</body>
</html>
