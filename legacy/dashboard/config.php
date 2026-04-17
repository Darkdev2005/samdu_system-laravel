<?php

class Database{
    private $host = 'localhost';
    private $port = 3306;
    private $db_name = 'lm_db_laravel';
    private $username = 'root';
    private $password = '';
    private $link;
    private $auditTable = 'system_audit_logs';
    private $suppressQueryAudit = false;
    private $auditInProgress = false;
    private $auditEnabled = true;
    private $auditRetentionDays = 90;
    private $auditMaxRows = 200000;
    private $auditCleanupChance = 100;

    private function readEnv(string $key, string $default = ''): string
    {
        if (function_exists('config')) {
            $configKey = match ($key) {
                'DB_HOST' => 'database.connections.mysql.host',
                'DB_PORT' => 'database.connections.mysql.port',
                'DB_DATABASE' => 'database.connections.mysql.database',
                'DB_USERNAME' => 'database.connections.mysql.username',
                'DB_PASSWORD' => 'database.connections.mysql.password',
                default => null,
            };

            if ($configKey !== null) {
                $value = config($configKey);
                if ($value !== null && $value !== '') {
                    return (string)$value;
                }
            }
        }

        if (function_exists('env')) {
            $value = env($key);
            if ($value !== null && $value !== '') {
                return (string)$value;
            }
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string)$_ENV[$key];
        }

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return (string)$value;
        }

        return $default;
    }

    private function readEnvBool(string $key, bool $default): bool
    {
        $raw = $this->readEnv($key, $default ? 'true' : 'false');
        $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }

    private function readEnvInt(string $key, int $default): int
    {
        $raw = trim($this->readEnv($key, (string)$default));
        if ($raw === '' || !is_numeric($raw)) {
            return $default;
        }
        return (int)$raw;
    }

    function __construct() {
        $this->host = $this->readEnv('DB_HOST', $this->host);
        $this->port = (int)$this->readEnv('DB_PORT', (string)$this->port);
        $this->db_name = $this->readEnv('DB_DATABASE', $this->db_name);
        $this->username = $this->readEnv('DB_USERNAME', $this->username);
        $this->password = $this->readEnv('DB_PASSWORD', $this->password);
        $this->auditEnabled = $this->readEnvBool('AUDIT_LOG_ENABLED', true);
        $this->auditRetentionDays = max(0, $this->readEnvInt('AUDIT_LOG_RETENTION_DAYS', 90));
        $this->auditMaxRows = max(0, $this->readEnvInt('AUDIT_LOG_MAX_ROWS', 200000));
        $this->auditCleanupChance = max(1, $this->readEnvInt('AUDIT_LOG_CLEANUP_CHANCE', 100));

        $this->link = mysqli_connect($this->host, $this->username, $this->password, $this->db_name, $this->port);
        if (!$this->link) {
            exit("Bazaga ulanmadi!");
        }
        mysqli_set_charset($this->link, 'utf8mb4');

        // Izoh: Foydalanuvchi harakatlarini kuzatish uchun audit jadvali.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS {$this->auditTable} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                username VARCHAR(255) NULL,
                action VARCHAR(20) NOT NULL,
                table_name VARCHAR(191) NULL,
                row_id BIGINT NULL,
                request_method VARCHAR(10) NULL,
                request_uri VARCHAR(1000) NULL,
                source_file VARCHAR(500) NULL,
                ip_address VARCHAR(64) NULL,
                user_agent VARCHAR(1024) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'success',
                error_message TEXT NULL,
                payload LONGTEXT NULL,
                sql_text LONGTEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_audit_user (user_id),
                INDEX idx_audit_table (table_name),
                INDEX idx_audit_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        // Izoh: Umumta'lim fanlar jadvali mavjud bo'lmasa avtomatik yaratish.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS umumtalim_fanlar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fan_code VARCHAR(100) NOT NULL,
                fan_name VARCHAR(255) NOT NULL,                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         
                kafedra_id INT NOT NULL,
                semestr INT NOT NULL,
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        // Izoh: Umumta'lim fanlar jadvalida semestr ustuni yo'q bo'lsa qo'shamiz.
        $semestrColumn = mysqli_query($this->link, "SHOW COLUMNS FROM umumtalim_fanlar LIKE 'semestr'");
        if ($semestrColumn && mysqli_num_rows($semestrColumn) === 0) {
            mysqli_query($this->link, "ALTER TABLE umumtalim_fanlar ADD COLUMN semestr INT NOT NULL DEFAULT 1 AFTER kafedra_id");
        }
        // Izoh: Umumta'lim fan biriktirish jadvali mavjud bo'lmasa avtomatik yaratish.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS umumtalim_fan_biriktirish (
                id INT AUTO_INCREMENT PRIMARY KEY,
                umumtalim_fan_id INT NOT NULL,
                source_fan_id INT NULL,
                yonalish_id INT NOT NULL,
                semestr_id INT NOT NULL,
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_biriktirish (umumtalim_fan_id, yonalish_id, semestr_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        // Izoh: Biriktirish jadvalida asl tanlangan fan ID (source_fan_id) yo'q bo'lsa qo'shamiz.
        $sourceFanColumn = mysqli_query($this->link, "SHOW COLUMNS FROM umumtalim_fan_biriktirish LIKE 'source_fan_id'");
        if ($sourceFanColumn && mysqli_num_rows($sourceFanColumn) === 0) {
            mysqli_query($this->link, "ALTER TABLE umumtalim_fan_biriktirish ADD COLUMN source_fan_id INT NULL AFTER umumtalim_fan_id");
        }
          // Izoh: Chet tili guruhlari jadvali mavjud bo'lmasa avtomatik yaratish.
          mysqli_query($this->link, "
              CREATE TABLE IF NOT EXISTS chet_tili_guruhlar (
                  id INT AUTO_INCREMENT PRIMARY KEY,
                  fan_id INT NOT NULL,
                  yonalish_id INT NOT NULL,
                  semestr_id INT NOT NULL,
                  yonalish_ids TEXT NULL,
                  source_fan_ids TEXT NULL,
                  guruh_no INT NOT NULL,
                  create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                  UNIQUE KEY uniq_chet_tili (fan_id, yonalish_id, semestr_id, guruh_no)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
          ");
          // Izoh: Yonalishlar birlashtirilishi uchun yonalish_ids ustuni yo'q bo'lsa qo'shamiz.
          $yonalishIdsColumn = mysqli_query($this->link, "SHOW COLUMNS FROM chet_tili_guruhlar LIKE 'yonalish_ids'");
          if ($yonalishIdsColumn && mysqli_num_rows($yonalishIdsColumn) === 0) {
              mysqli_query($this->link, "ALTER TABLE chet_tili_guruhlar ADD COLUMN yonalish_ids TEXT NULL AFTER semestr_id");
          }
        // Izoh: Birlashtirishda asl tanlangan fan IDlar ro'yxati uchun source_fan_ids ustuni yo'q bo'lsa qo'shamiz.
        $sourceFanIdsColumn = mysqli_query($this->link, "SHOW COLUMNS FROM chet_tili_guruhlar LIKE 'source_fan_ids'");
        if ($sourceFanIdsColumn && mysqli_num_rows($sourceFanIdsColumn) === 0) {
            mysqli_query($this->link, "ALTER TABLE chet_tili_guruhlar ADD COLUMN source_fan_ids TEXT NULL AFTER yonalish_ids");
        }

        // Izoh: Chet tili talabi guruhlar kesimida saqlanadi.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS chet_tili_talablar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                semestr_id INT NOT NULL,
                yonalish_id INT NOT NULL,
                guruh_id INT NOT NULL,
                fan_id INT NOT NULL,
                talabalar_soni INT NOT NULL,
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                update_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_chet_tili_talab (semestr_id, guruh_id, fan_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Izoh: Talablar asosida shakllangan chet tili o'quv guruhlari.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS chet_tili_oquv_guruhlar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                semestr_num INT NOT NULL,
                fan_id INT NOT NULL,
                guruh_no INT NOT NULL,
                jami_talaba INT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'ready',
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_chet_tili_oquv_guruh (semestr_num, fan_id, guruh_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Izoh: Har bir chet tili o'quv guruhi tarkibida qaysi akademik guruhdan nechta talaba borligi.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS chet_tili_oquv_guruh_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                oquv_guruh_id INT NOT NULL,
                semestr_id INT NOT NULL,
                yonalish_id INT NOT NULL,
                guruh_id INT NOT NULL,
                source_fan_id INT NOT NULL,
                talabalar_soni INT NOT NULL,
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_chet_tili_oquv_item (oquv_guruh_id, guruh_id, source_fan_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Izoh: Ishchi o'quv reja jadvali (tanlov fanlar uchun variantlar biriktiriladi).
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS ishchi_oquv_reja (
                id INT AUTO_INCREMENT PRIMARY KEY,
                base_fan_id INT NOT NULL,
                semestr_id INT NOT NULL,
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_ishchi_reja (base_fan_id, semestr_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS ishchi_oquv_reja_variants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ishchi_reja_id INT NOT NULL,
                fan_id INT NOT NULL,
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_ishchi_variant (ishchi_reja_id, fan_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Izoh: Yo'nalishlar tahriri tarixini saqlash uchun history jadvali.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS yonalishlar_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                yonalish_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                code VARCHAR(255) NOT NULL,
                muddati INT NOT NULL,
                kirish_yili INT NOT NULL,
                patok_soni INT NOT NULL,
                kattaguruh_soni INT NOT NULL,
                kichikguruh_soni INT NOT NULL,
                akademik_daraja_id INT NOT NULL,
                talim_shakli_id INT NOT NULL,
                kvalifikatsiya VARCHAR(255) NOT NULL,
                fakultet_id INT NOT NULL,
                sync_status VARCHAR(20) NOT NULL DEFAULT 'nosync',
                change_type VARCHAR(20) NOT NULL DEFAULT 'update',
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Izoh: Guruhlar tahriri tarixini saqlash uchun history jadvali.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS guruhlar_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                guruh_id INT NOT NULL,
                yonalish_id INT NOT NULL,
                guruh_nomer VARCHAR(255) NOT NULL,
                soni INT NOT NULL,
                sync_status VARCHAR(20) NOT NULL DEFAULT 'nosync',
                change_type VARCHAR(20) NOT NULL DEFAULT 'update',
                changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Izoh: Tarix jadvallarida sync_status ustuni yo'q bo'lsa qo'shamiz.
        $yonalishSyncCol = mysqli_query($this->link, "SHOW COLUMNS FROM yonalishlar_history LIKE 'sync_status'");
        if ($yonalishSyncCol && mysqli_num_rows($yonalishSyncCol) === 0) {
            mysqli_query($this->link, "ALTER TABLE yonalishlar_history ADD COLUMN sync_status VARCHAR(20) NOT NULL DEFAULT 'nosync' AFTER fakultet_id");
        }
        $yonalishChangeTypeCol = mysqli_query($this->link, "SHOW COLUMNS FROM yonalishlar_history LIKE 'change_type'");
        if ($yonalishChangeTypeCol && mysqli_num_rows($yonalishChangeTypeCol) === 0) {
            mysqli_query($this->link, "ALTER TABLE yonalishlar_history ADD COLUMN change_type VARCHAR(20) NOT NULL DEFAULT 'update' AFTER sync_status");
        }
        $yonalishChangedAtCol = mysqli_query($this->link, "SHOW COLUMNS FROM yonalishlar_history LIKE 'changed_at'");
        if ($yonalishChangedAtCol && mysqli_num_rows($yonalishChangedAtCol) === 0) {
            mysqli_query($this->link, "ALTER TABLE yonalishlar_history ADD COLUMN changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        }

        $guruhSyncCol = mysqli_query($this->link, "SHOW COLUMNS FROM guruhlar_history LIKE 'sync_status'");
        if ($guruhSyncCol && mysqli_num_rows($guruhSyncCol) === 0) {
            mysqli_query($this->link, "ALTER TABLE guruhlar_history ADD COLUMN sync_status VARCHAR(20) NOT NULL DEFAULT 'nosync' AFTER soni");
        }
        $guruhChangeTypeCol = mysqli_query($this->link, "SHOW COLUMNS FROM guruhlar_history LIKE 'change_type'");
        if ($guruhChangeTypeCol && mysqli_num_rows($guruhChangeTypeCol) === 0) {
            mysqli_query($this->link, "ALTER TABLE guruhlar_history ADD COLUMN change_type VARCHAR(20) NOT NULL DEFAULT 'update' AFTER sync_status");
        }
        $guruhChangedAtCol = mysqli_query($this->link, "SHOW COLUMNS FROM guruhlar_history LIKE 'changed_at'");
        if ($guruhChangedAtCol && mysqli_num_rows($guruhChangedAtCol) === 0) {
            mysqli_query($this->link, "ALTER TABLE guruhlar_history ADD COLUMN changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        }

        // Izoh: Qaysi yo'nalish qayta taqsimot talab qilishini kuzatish uchun event jadvali.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS taqsimot_resync_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(20) NOT NULL,
                entity_id INT NOT NULL,
                yonalish_id INT NOT NULL,
                reason TEXT NULL,
                archived_rows INT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                done_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $resyncStatusCol = mysqli_query($this->link, "SHOW COLUMNS FROM taqsimot_resync_events LIKE 'status'");
        if ($resyncStatusCol && mysqli_num_rows($resyncStatusCol) === 0) {
            mysqli_query($this->link, "ALTER TABLE taqsimot_resync_events ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER archived_rows");
        }
        $resyncDoneAtCol = mysqli_query($this->link, "SHOW COLUMNS FROM taqsimot_resync_events LIKE 'done_at'");
        if ($resyncDoneAtCol && mysqli_num_rows($resyncDoneAtCol) === 0) {
            mysqli_query($this->link, "ALTER TABLE taqsimot_resync_events ADD COLUMN done_at DATETIME NULL");
        }

        // Izoh: Taqsimotlar qayta hisoblashdan oldin arxivga olinadi.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS taqsimotlar_archive (
                id INT AUTO_INCREMENT PRIMARY KEY,
                taqsimot_id INT NOT NULL,
                oquv_reja_id INT NOT NULL,
                teacher_id INT NOT NULL,
                soat DECIMAL(10,2) NOT NULL DEFAULT 0,
                type VARCHAR(10) NOT NULL,
                yonalish_id INT NOT NULL,
                event_id INT NOT NULL DEFAULT 0,
                entity_type VARCHAR(20) NOT NULL DEFAULT '',
                entity_id INT NOT NULL DEFAULT 0,
                archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $archiveEntityTypeCol = mysqli_query($this->link, "SHOW COLUMNS FROM taqsimotlar_archive LIKE 'entity_type'");
        if ($archiveEntityTypeCol && mysqli_num_rows($archiveEntityTypeCol) === 0) {
            mysqli_query($this->link, "ALTER TABLE taqsimotlar_archive ADD COLUMN entity_type VARCHAR(20) NOT NULL DEFAULT '' AFTER event_id");
        }
        $archiveEntityIdCol = mysqli_query($this->link, "SHOW COLUMNS FROM taqsimotlar_archive LIKE 'entity_id'");
        if ($archiveEntityIdCol && mysqli_num_rows($archiveEntityIdCol) === 0) {
            mysqli_query($this->link, "ALTER TABLE taqsimotlar_archive ADD COLUMN entity_id INT NOT NULL DEFAULT 0 AFTER entity_type");
        }

        // Izoh: O'chirilgan yo'nalishlardan qolib ketgan orphan semestrlarni avtomatik tozalaymiz.
        $hasSemestrlar = mysqli_query($this->link, "SHOW TABLES LIKE 'semestrlar'");
        if ($hasSemestrlar && mysqli_num_rows($hasSemestrlar) > 0) {
            mysqli_query($this->link, "
                DELETE s
                FROM semestrlar s
                LEFT JOIN yonalishlar y ON y.id = s.yonalish_id
                WHERE y.id IS NULL
                  AND NOT EXISTS (SELECT 1 FROM fanlar f WHERE f.semestr_id = s.id)
                  AND NOT EXISTS (SELECT 1 FROM qoshimcha_fanlar qf WHERE qf.semestr_id = s.id)
                  AND NOT EXISTS (SELECT 1 FROM umumtalim_fan_biriktirish ub WHERE ub.semestr_id = s.id)
                  AND NOT EXISTS (SELECT 1 FROM chet_tili_guruhlar ct WHERE ct.semestr_id = s.id)
            ");
        }
    }
    private function isMutatingQuery(string $query): ?string
    {
        $trimmed = ltrim($query);
        if (preg_match('/^(INSERT|UPDATE|DELETE|REPLACE)\b/i', $trimmed, $m)) {
            return strtoupper($m[1]);
        }
        return null;
    }

    private function extractTableNameFromSql(string $query, string $action): ?string
    {
        $patterns = [
            'INSERT' => '/^INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i',
            'REPLACE' => '/^REPLACE\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i',
            'UPDATE' => '/^UPDATE\s+`?([a-zA-Z0-9_]+)`?/i',
            'DELETE' => '/^DELETE\s+(?:[a-zA-Z0-9_`]+\s+)?FROM\s+`?([a-zA-Z0-9_]+)`?/i',
        ];

        $pattern = $patterns[$action] ?? null;
        if ($pattern === null) {
            return null;
        }
        if (!preg_match($pattern, ltrim($query), $m)) {
            return null;
        }
        return $m[1] ?? null;
    }

    private function isSensitiveKey(string $key): bool
    {
        return (bool)preg_match('/(password|parol|token|secret|app_key|remember)/i', $key);
    }

    private function sanitizeAuditValue($value)
    {
        if (is_array($value)) {
            $clean = [];
            foreach ($value as $k => $v) {
                if (is_string($k) && $this->isSensitiveKey($k)) {
                    $clean[$k] = '***';
                    continue;
                }
                $clean[$k] = $this->sanitizeAuditValue($v);
            }
            return $clean;
        }

        if (is_object($value)) {
            return '[object]';
        }

        if (is_string($value) && strlen($value) > 5000) {
            return substr($value, 0, 5000) . '...[truncated]';
        }

        return $value;
    }

    private function encodeAuditPayload($payload): ?string
    {
        if ($payload === null) {
            return null;
        }
        $sanitized = $this->sanitizeAuditValue($payload);
        $json = json_encode($sanitized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        return $json === false ? null : $json;
    }

    private function getRequestUserId(): ?int
    {
        if (isset($_SESSION['id']) && is_numeric($_SESSION['id'])) {
            return (int)$_SESSION['id'];
        }

        if (function_exists('session')) {
            try {
                $val = session('id');
                if (is_numeric($val)) {
                    return (int)$val;
                }
            } catch (Throwable $e) {
            }
        }

        if (function_exists('auth')) {
            try {
                $user = auth()->user();
                if ($user && isset($user->id)) {
                    return (int)$user->id;
                }
            } catch (Throwable $e) {
            }
        }

        return null;
    }

    private function getRequestUsername(): ?string
    {
        if (isset($_SESSION['username']) && $_SESSION['username'] !== '') {
            return (string)$_SESSION['username'];
        }

        if (function_exists('session')) {
            try {
                $val = session('username');
                if (is_string($val) && $val !== '') {
                    return $val;
                }
            } catch (Throwable $e) {
            }
        }

        if (function_exists('auth')) {
            try {
                $user = auth()->user();
                if ($user) {
                    if (isset($user->username) && $user->username !== '') {
                        return (string)$user->username;
                    }
                    if (isset($user->name) && $user->name !== '') {
                        return (string)$user->name;
                    }
                }
            } catch (Throwable $e) {
            }
        }

        return null;
    }

    private function getClientIpAddress(): ?string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
            if ($ip !== '') {
                return $ip;
            }
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return (string)$_SERVER['REMOTE_ADDR'];
        }

        return null;
    }

    private function getAuditSourceFile(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
        foreach ($trace as $frame) {
            if (empty($frame['file'])) {
                continue;
            }
            $file = str_replace('\\', '/', (string)$frame['file']);
            if (substr($file, -strlen('/legacy/dashboard/config.php')) === '/legacy/dashboard/config.php') {
                continue;
            }
            $marker = '/legacy/dashboard/';
            $pos = strpos($file, $marker);
            if ($pos !== false) {
                return substr($file, $pos + strlen($marker));
            }
        }

        return isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : null;
    }

    private function maybeCleanupAuditLogs(): void
    {
        if (!$this->link || !$this->auditEnabled) {
            return;
        }

        // Izoh: Har yozuvda cleanup qilmaslik uchun ehtimoliy ishga tushirish.
        if (mt_rand(1, $this->auditCleanupChance) !== 1) {
            return;
        }

        try {
            if ($this->auditRetentionDays > 0) {
                $days = (int)$this->auditRetentionDays;
                mysqli_query(
                    $this->link,
                    "DELETE FROM {$this->auditTable} WHERE created_at < (NOW() - INTERVAL {$days} DAY)"
                );
            }

            if ($this->auditMaxRows > 0) {
                $maxRows = (int)$this->auditMaxRows;
                $cutoffRes = mysqli_query(
                    $this->link,
                    "SELECT id FROM {$this->auditTable} ORDER BY id DESC LIMIT {$maxRows}, 1"
                );
                if ($cutoffRes) {
                    $cutoffRow = mysqli_fetch_assoc($cutoffRes);
                    if (!empty($cutoffRow['id'])) {
                        $cutoffId = (int)$cutoffRow['id'];
                        if ($cutoffId > 0) {
                            mysqli_query(
                                $this->link,
                                "DELETE FROM {$this->auditTable} WHERE id <= {$cutoffId}"
                            );
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // Izoh: Cleanup xatoligi asosiy ish jarayonini to'xtatmasin.
        }
    }

    private function writeAuditLog(array $data): void
    {
        if (!$this->auditEnabled || $this->auditInProgress || !$this->link) {
            return;
        }

        $tableName = (string)($data['table_name'] ?? '');
        if ($tableName === $this->auditTable) {
            return;
        }

        $this->maybeCleanupAuditLogs();

        $this->auditInProgress = true;
        try {
            $userId = $data['user_id'] ?? $this->getRequestUserId();
            $username = $data['username'] ?? $this->getRequestUsername();
            $action = (string)($data['action'] ?? 'UNKNOWN');
            $rowId = $data['row_id'] ?? null;
            $requestMethod = $data['request_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? null);
            $requestUri = $data['request_uri'] ?? ($_SERVER['REQUEST_URI'] ?? null);
            $sourceFile = $data['source_file'] ?? $this->getAuditSourceFile();
            $ip = $data['ip_address'] ?? $this->getClientIpAddress();
            $userAgent = $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
            $status = (string)($data['status'] ?? 'success');
            $errorMessage = $data['error_message'] ?? null;
            $payloadJson = array_key_exists('payload', $data) ? $this->encodeAuditPayload($data['payload']) : null;
            $sqlText = $data['sql_text'] ?? null;

            $esc = function ($val): string {
                return mysqli_real_escape_string($this->link, (string)$val);
            };
            $nullable = function ($val) use ($esc): string {
                if ($val === null || $val === '') {
                    return 'NULL';
                }
                return "'" . $esc($val) . "'";
            };

            $sql = "
                INSERT INTO {$this->auditTable}
                (user_id, username, action, table_name, row_id, request_method, request_uri, source_file, ip_address, user_agent, status, error_message, payload, sql_text)
                VALUES (
                    " . ($userId === null ? 'NULL' : (int)$userId) . ",
                    " . $nullable($username) . ",
                    '" . $esc($action) . "',
                    " . $nullable($tableName) . ",
                    " . ($rowId === null ? 'NULL' : (int)$rowId) . ",
                    " . $nullable($requestMethod) . ",
                    " . $nullable($requestUri) . ",
                    " . $nullable($sourceFile) . ",
                    " . $nullable($ip) . ",
                    " . $nullable($userAgent) . ",
                    '" . $esc($status) . "',
                    " . $nullable($errorMessage) . ",
                    " . $nullable($payloadJson) . ",
                    " . $nullable($sqlText) . "
                )
            ";
            mysqli_query($this->link, $sql);
        } catch (Throwable $e) {
            // Audit logdagi xatolik asosiy ish jarayonini to'xtatmasin.
        } finally {
            $this->auditInProgress = false;
        }
    }

    public function query($query) {
        $result = false;
        $errorMessage = null;
        $thrown = null;

        try {
            $result = mysqli_query($this->link, $query);
            if ($result === false) {
                $errorMessage = mysqli_error($this->link);
            }
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
            $thrown = $e;
        }

        if (!$this->suppressQueryAudit) {
            $action = $this->isMutatingQuery((string)$query);
            if ($action !== null) {
                $tableName = $this->extractTableNameFromSql((string)$query, $action) ?? '';
                if ($tableName !== $this->auditTable) {
                    $this->writeAuditLog([
                        'action' => $action,
                        'table_name' => $tableName,
                        'status' => ($result !== false && $thrown === null) ? 'success' : 'error',
                        'error_message' => $errorMessage,
                        'sql_text' => substr((string)$query, 0, 10000),
                    ]);
                }
            }
        }

        if ($thrown !== null) {
            throw $thrown;
        }

        return $result;
    }
    public function get_data_by_table($table, $arr, $con = 'no'){
        $sql = "SELECT * FROM ".$table. " WHERE ";
        $t = '';
        $i=0;
        $n = count($arr);
        foreach($arr as $key=>$val){
            $i++;
            // Izoh: Maxsus belgilar sababli SQL xatosi chiqmasligi uchun qiymatni escapelaymiz.
            if ($val === null) {
                $condition = "$key IS NULL";
            } else {
                $safeVal = mysqli_real_escape_string($this->link, (string)$val);
                $condition = "$key = '$safeVal'";
            }

            if($i==$n){
                $t .= $condition;
            }else{
                $t .= $condition . " AND ";
            }
        }
        $sql .= $t;
        if ($con != 'no'){
            $sql .= $con;
        }
        $fetch = mysqli_fetch_assoc($this->query($sql));
        return $fetch;
    }
    public function get_data_by_table_all($table, $con = 'no'){
        $sql = "SELECT * FROM ".$table;
        if ($con != 'no'){
            $sql .= " ".$con;
        }
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }
    public function insert($table, $arr){
        $sql = "INSERT INTO ".$table. " ";
        $t1 = '';
        $t2 = '';
        $i = 0;
        $n = count($arr);
        foreach($arr as $key=>$val){
            $val = mysqli_real_escape_string($this->link, $val);
            $i++;
            if($i==$n){
                $t1 .= $key;
                $t2 .= "'".$val."'";
            }else{
                $t1 .= $key.', ';
                $t2 .= "'".$val."', ";
            }
        }
        $sql .= "($t1) VALUES ($t2);";
        $this->suppressQueryAudit = true;
        try {
            $result = $this->query($sql);
        } finally {
            $this->suppressQueryAudit = false;
        }

        if ($result) {
            $insertId = mysqli_insert_id($this->link);
            $this->writeAuditLog([
                'action' => 'INSERT',
                'table_name' => $table,
                'row_id' => $insertId,
                'payload' => $arr,
                'sql_text' => substr($sql, 0, 10000),
                'status' => 'success',
            ]);
            return $insertId;
        } else {
            $this->writeAuditLog([
                'action' => 'INSERT',
                'table_name' => $table,
                'payload' => $arr,
                'sql_text' => substr($sql, 0, 10000),
                'status' => 'error',
                'error_message' => mysqli_error($this->link),
            ]);
            return 0;
        }
    }
    public function update($table, $arr, $con = 'no'){
        $sql = "UPDATE ".$table. " SET ";
        $t = '';
        $i=0;
        $n = count($arr);
        foreach($arr as $key=>$val){
            $val = addslashes($val);

            $i++;
            if($i==$n){
                $t .= "$key = '$val'";
            }else{
                $t .= "$key = '$val', ";
            }
        }
        $sql .= $t;
        if ($con != 'no'){
            $sql .= " WHERE ".$con;
        }

        $this->suppressQueryAudit = true;
        try {
            $result = $this->query($sql);
        } finally {
            $this->suppressQueryAudit = false;
        }

        $this->writeAuditLog([
            'action' => 'UPDATE',
            'table_name' => $table,
            'payload' => [
                'values' => $arr,
                'where' => $con,
            ],
            'sql_text' => substr($sql, 0, 10000),
            'status' => $result ? 'success' : 'error',
            'error_message' => $result ? null : mysqli_error($this->link),
        ]);

        return $result;
    }

    public function delete($table, $con = 'no'){
        $sql = "DELETE FROM ".$table;
        if ($con != 'no'){
            $sql .= " WHERE ".$con;
        }
        $this->suppressQueryAudit = true;
        try {
            $result = $this -> query($sql);
        } finally {
            $this->suppressQueryAudit = false;
        }

        $this->writeAuditLog([
            'action' => 'DELETE',
            'table_name' => $table,
            'payload' => [
                'where' => $con,
            ],
            'sql_text' => substr($sql, 0, 10000),
            'status' => $result ? 'success' : 'error',
            'error_message' => $result ? null : mysqli_error($this->link),
        ]);

        return $result;
    }

    public function get_audit_logs(array $filters = [], int $limit = 300): array
    {
        $limit = max(1, min(1000, (int)$limit));
        $where = [];

        if (!empty($filters['user_id'])) {
            $where[] = "user_id = " . (int)$filters['user_id'];
        }

        if (!empty($filters['action'])) {
            $action = mysqli_real_escape_string($this->link, strtoupper((string)$filters['action']));
            $where[] = "action = '{$action}'";
        }

        if (!empty($filters['table_name'])) {
            $table = mysqli_real_escape_string($this->link, (string)$filters['table_name']);
            $where[] = "table_name LIKE '%{$table}%'";
        }

        if (!empty($filters['source_file'])) {
            $source = mysqli_real_escape_string($this->link, (string)$filters['source_file']);
            $where[] = "source_file LIKE '%{$source}%'";
        }

        if (!empty($filters['status'])) {
            $status = mysqli_real_escape_string($this->link, strtolower((string)$filters['status']));
            $where[] = "status = '{$status}'";
        }

        if (!empty($filters['date_from'])) {
            $from = mysqli_real_escape_string($this->link, (string)$filters['date_from']);
            $where[] = "DATE(created_at) >= '{$from}'";
        }

        if (!empty($filters['date_to'])) {
            $to = mysqli_real_escape_string($this->link, (string)$filters['date_to']);
            $where[] = "DATE(created_at) <= '{$to}'";
        }

        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        $sql = "
            SELECT
                id,
                user_id,
                username,
                action,
                table_name,
                row_id,
                source_file,
                ip_address,
                status,
                request_method,
                request_uri,
                error_message,
                payload,
                sql_text,
                created_at
            FROM {$this->auditTable}
            {$whereSql}
            ORDER BY id DESC
            LIMIT {$limit}
        ";

        $result = mysqli_query($this->link, $sql);
        $rows = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
    public function get_yunalishlar_with_details($filters = []){
        $where = [];
        if (!empty($filters['fakultet_id'])) {
            $where[] = "y.fakultet_id = " . (int)$filters['fakultet_id'];
        }
        if (!empty($filters['yonalish_id'])) {
            $where[] = "y.id = " . (int)$filters['yonalish_id'];
        }
        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        $sql = "SELECT 
            y.id,
            y.name            AS yonalish_nomi,
            y.code            AS yonalish_kodi,
            y.muddati         AS talim_muddati,
            y.kirish_yili     AS kirish_yili,
            ad.name           AS akademik_daraja,
            ts.name           AS talim_shakli,
            y.kvalifikatsiya,
            f.name            AS fakultet,
            y.create_at
        FROM yonalishlar y
        LEFT JOIN akademik_darajalar ad ON y.akademik_daraja_id = ad.id
        LEFT JOIN talim_shakllar ts    ON y.talim_shakli_id = ts.id
        LEFT JOIN fakultetlar f         ON y.fakultet_id = f.id
        {$whereSql}
        ORDER BY y.id DESC;
        ";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;

    }
    public function get_kafedralar(){
        $sql = "SELECT k.id, k.name, k.fakultet_id, k.create_at, f.name AS fakultet_name FROM `kafedralar` k
        LEFT JOIN fakultetlar f ON k.fakultet_id = f.id
        ";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }
    public function get_semestrlar($filters = []){
        $where = [
            "y.id IS NOT NULL",
            "(IFNULL(y.muddati, 0) = 0 OR s.semestr <= (IFNULL(y.muddati, 0) * 2))"
        ];

        if (!empty($filters['fakultet_id'])) {
            $where[] = "y.fakultet_id = " . (int)$filters['fakultet_id'];
        }
        if (!empty($filters['yonalish_id'])) {
            $where[] = "s.yonalish_id = " . (int)$filters['yonalish_id'];
        }
        if (!empty($filters['semestr'])) {
            $where[] = "s.semestr = " . (int)$filters['semestr'];
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT 
            s.id,
            s.fakultet_id,
            s.yonalish_id,
            y.fakultet_id AS yonalish_fakultet_id,
            f.name AS fakultet_name,
            y.name AS yonalish_name,
            y.kirish_yili,
            y.talim_shakli_id,
            tsh.name AS talim_shakli_name,
            ad.name AS akademik_daraja_name,
            y.patok_soni,
            y.kattaguruh_soni,
            y.kichikguruh_soni,
            s.semestr,
            s.create_at,
            COALESCE(SUM(g.soni), 0) AS jami_talabalar,
            COUNT(DISTINCT g.id) AS guruhlar_soni
        FROM semestrlar s
        LEFT JOIN yonalishlar y ON y.id = s.yonalish_id
        LEFT JOIN fakultetlar f ON f.id = y.fakultet_id
        LEFT JOIN talim_shakllar tsh ON tsh.id = y.talim_shakli_id
        LEFT JOIN akademik_darajalar ad ON ad.id = y.akademik_daraja_id
        LEFT JOIN guruhlar g ON g.yonalish_id = y.id
        {$whereSql}
        GROUP BY
            s.id,
            s.fakultet_id,
            s.yonalish_id,
            y.fakultet_id,
            f.name,
            y.name,
            y.kirish_yili,
            y.talim_shakli_id,
            tsh.name,
            ad.name,
            y.patok_soni,
            y.kattaguruh_soni,
            y.kichikguruh_soni,
            s.semestr,
            s.create_at
        ORDER BY f.name, y.name, s.semestr;
        ;";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }
    public function insert_semestrlar(){
        $sql = "
            INSERT IGNORE INTO semestrlar (fakultet_id, yonalish_id, semestr)
            SELECT
                y.fakultet_id,
                y.id AS yonalish_id,
                n.num AS semestr
            FROM yonalishlar y
            JOIN numbers n
                ON n.num <= y.muddati * 2
            WHERE y.muddati IS NOT NULL
            AND y.muddati > 0
        ";
        $result = $this->query($sql);

        return $result;
    }
    public function get_oquv_rejalar($filters = []){
        $hasYonalishFakultet = false;
        $fakultetColRes = $this->query("SHOW COLUMNS FROM yonalishlar LIKE 'fakultet_id'");
        if ($fakultetColRes && mysqli_num_rows($fakultetColRes) > 0) {
            $hasYonalishFakultet = true;
        }
        $limit = !empty($filters['limit']) ? max(1, (int)$filters['limit']) : 0;

        $where = [];
        if (!empty($filters['yonalish_id'])) {
            $where[] = "y.id = " . (int)$filters['yonalish_id'];
        }
        if (!empty($filters['fakultet_id'])) {
            $fakultetId = (int)$filters['fakultet_id'];
            if ($hasYonalishFakultet) {
                $where[] = "(y.fakultet_id = {$fakultetId} OR (y.fakultet_id IS NULL AND s.fakultet_id = {$fakultetId}))";
            } else {
                $where[] = "s.fakultet_id = {$fakultetId}";
            }
        }
        if (!empty($filters['semestr'])) {
            $s = (int)$filters['semestr'];
            $pairStart = ($s % 2 === 0) ? $s - 1 : $s;
            $pairEnd = $pairStart + 1;
            $where[] = "s.semestr IN ($pairStart, $pairEnd)";
        }
        $whereSQL = '';
        if (!empty($where)) {
            $whereSQL = 'WHERE ' . implode(' AND ', $where);
        }
        $limitSQL = $limit > 0 ? "LIMIT {$limit}" : '';
        $sql = "
                SELECT
            s.id AS semestr_id,
            s.semestr,
            y.id AS yonalish_id,
            y.name AS yonalish_name,
            f.id AS fan_id,
            f.fan_code,
            f.fan_name,
            f.tanlov_fan,
            COALESCE(NULLIF(qkafedra.kafedra_names, ''), k.name) AS kafedra_name,

            SUM(CASE WHEN dst.id = 1 THEN o.dars_soat ELSE 0 END) AS lecture,
            SUM(CASE WHEN dst.id = 2 THEN o.dars_soat ELSE 0 END) AS practical,
            SUM(CASE WHEN dst.id = 3 THEN o.dars_soat ELSE 0 END) AS lab,
            SUM(CASE WHEN dst.id = 4 THEN o.dars_soat ELSE 0 END) AS seminar,
            SUM(CASE WHEN dst.id = 5 THEN o.dars_soat ELSE 0 END) AS mustaqilTalim,
            SUM(CASE WHEN dst.name = 'Kurs ishi' THEN o.dars_soat ELSE 0 END) AS kursIshi,
            MAX(CASE WHEN dst.name = 'Kurs ishi' THEN 1 ELSE 0 END) AS kursIshiFlag,
            SUM(
                CASE
                    WHEN dst.name IN ('Kurs loyihasi', 'Kurs loyihasi va himoyasi')
                    THEN o.dars_soat
                    ELSE 0
                END
            ) AS kursLoyiha,
            MAX(
                CASE
                    WHEN dst.name IN ('Kurs loyihasi', 'Kurs loyihasi va himoyasi')
                    THEN 1
                    ELSE 0
                END
            ) AS kursLoyihaFlag,
            COALESCE(qfext.kursIshiExtraFlag, 0) AS kursIshiExtraFlag,
            COALESCE(qfext.kursLoyihaExtraFlag, 0) AS kursLoyihaExtraFlag,
            SUM(
                CASE
                    WHEN dst.name IN ('Malaka amaliyoti', 'Malakaviy amaliyot')
                         OR dst.name LIKE 'Malaka%amaliyot%'
                         OR dst.name LIKE 'Malakaviy%amaliyot%'
                    THEN o.dars_soat
                    ELSE 0
                END
            ) AS malakaAmaliyot

        FROM oquv_rejalar o
        JOIN fanlar f ON f.id = o.fan_id
        JOIN dars_soat_turlar dst ON dst.id = o.dars_tur_id
        JOIN semestrlar s ON s.id = f.semestr_id
        JOIN yonalishlar y ON y.id = s.yonalish_id
        LEFT JOIN kafedralar k ON k.id = f.kafedra_id
        LEFT JOIN (
            SELECT
                semestr_id,
                fan_name,
                MAX(CASE WHEN qoshimcha_dars_id = 1 THEN 1 ELSE 0 END) AS kursIshiExtraFlag,
                MAX(CASE WHEN qoshimcha_dars_id = 2 THEN 1 ELSE 0 END) AS kursLoyihaExtraFlag
            FROM qoshimcha_fanlar
            GROUP BY semestr_id, fan_name
        ) qfext ON qfext.semestr_id = f.semestr_id AND qfext.fan_name = f.fan_name
        LEFT JOIN (
            SELECT
                qf.semestr_id,
                qf.fan_name,
                GROUP_CONCAT(DISTINCT kq.name ORDER BY kq.name SEPARATOR ', ') AS kafedra_names
            FROM qoshimcha_oquv_rejalar q
            JOIN qoshimcha_fanlar qf ON qf.id = q.qoshimcha_fanid
            LEFT JOIN kafedralar kq ON kq.id = q.kafedra_id
            GROUP BY qf.semestr_id, qf.fan_name
        ) qkafedra ON qkafedra.semestr_id = f.semestr_id AND qkafedra.fan_name = f.fan_name
        $whereSQL
        GROUP BY
            f.id,
            s.id,
            s.semestr,
            y.id,
            y.name,
            f.fan_code,
            f.fan_name,
            f.tanlov_fan,
            k.name,
            qkafedra.kafedra_names

        ORDER BY
            s.semestr,
            f.fan_code,
            f.fan_name
        {$limitSQL};
        ";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }
    public function get_guruhlar($filters = []){
        $hasYonalishFakultet = false;
        $fakultetColRes = $this->query("SHOW COLUMNS FROM yonalishlar LIKE 'fakultet_id'");
        if ($fakultetColRes && mysqli_num_rows($fakultetColRes) > 0) {
            $hasYonalishFakultet = true;
        }

        $where = [];
        if (!empty($filters['yonalish_id'])) {
            $where[] = "y.id = " . (int)$filters['yonalish_id'];
        }
        if (!empty($filters['fakultet_id'])) {
            $fakultetId = (int)$filters['fakultet_id'];
            if ($hasYonalishFakultet) {
                $where[] = "(y.fakultet_id = {$fakultetId} OR (y.fakultet_id IS NULL AND sf.fakultet_id = {$fakultetId}))";
            } else {
                $where[] = "sf.fakultet_id = {$fakultetId}";
            }
        }

        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        $fakultetSelect = $hasYonalishFakultet
            ? "COALESCE(yf.id, sf.fakultet_id) AS fakultet_id,
               COALESCE(yf.name, sf_name.name) AS fakultet_name"
            : "sf.fakultet_id AS fakultet_id,
               sf_name.name AS fakultet_name";

        $fakultetJoin = $hasYonalishFakultet
            ? "LEFT JOIN fakultetlar yf ON yf.id = y.fakultet_id"
            : "";

        $sql = "SELECT
                    g.id,
                    g.guruh_nomer,
                    g.soni,
                    g.create_at,
                    y.id AS yonalish_id,
                    y.name AS yonalish_name,
                    {$fakultetSelect}
                FROM guruhlar g
                JOIN yonalishlar y ON y.id = g.yonalish_id
                LEFT JOIN (
                    SELECT yonalish_id, MAX(fakultet_id) AS fakultet_id
                    FROM semestrlar
                    WHERE fakultet_id IS NOT NULL
                    GROUP BY yonalish_id
                ) sf ON sf.yonalish_id = y.id
                {$fakultetJoin}
                LEFT JOIN fakultetlar sf_name ON sf_name.id = sf.fakultet_id
                {$whereSql}
                ORDER BY g.id ASC";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }
    public function get_oqtuvchi_total_hours($teacher_id){
        $sql = "SELECT 
            t.id,
            t.soat,
            t.type,
            o.fio,
            o.lavozim,
            f.fan_name,
            f.fan_code,
            dst.name AS dars_turi,
            r.dars_soat
        FROM taqsimotlar t
        JOIN oqituvchilar o ON o.id = t.teacher_id
        JOIN oquv_rejalar r ON r.id = t.oquv_reja_id
        JOIN dars_soat_turlar dst ON dst.id = r.dars_tur_id
        JOIN fanlar f ON f.id = r.fan_id
        WHERE t.teacher_id = $teacher_id
        ";
        $result = $this->query($sql);
        $details = [];
        $totalHours = 0;
        $fio = '';
        $lavozim = '';

        while ($row = mysqli_fetch_assoc($result)) {
            $details[] = $row;
            $totalHours += (float)$row['soat'];
            $fio = $row['fio'];
            $lavozim = $row['lavozim'];
        }

        return [
            'fio' => $fio,
            'lavozim' => $lavozim,
            'total_hours' => $totalHours,
            'details' => $details
        ];
    }
    public function get_oquv_haftaliklar(){
        $sql = "SELECT oh.*, y.name as yonalish_nomi, y.code as yonalish_code, y.muddati 
            FROM oquv_haftaliklar oh 
            JOIN yonalishlar y ON oh.yonalish_id = y.id 
            ORDER BY oh.create_at DESC";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }
    public function get_talaba_soni($semestr_id){
        $sql = "SELECT COALESCE(SUM(g.soni),0) AS talabalar_soni
        FROM guruhlar g
        WHERE g.yonalish_id = (
            SELECT yonalish_id FROM semestrlar WHERE id = $semestr_id
        );";
        $result = $this->query($sql);
        $data = mysqli_fetch_assoc($result);
        return $data;
    }
    public function get_taqsimot_by_teacher($oquvreja_id, $type){
        $sql = "SELECT t.id, t.soat as soat_soni, t.type, o.fio, o.lavozim, t.oquv_reja_id
        FROM `taqsimotlar` t 
        JOIN oqituvchilar o ON o.id = t.teacher_id
        WHERE t.oquv_reja_id=$oquvreja_id AND t.type='$type';";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }

    public function get_oquv_yuklamalar($filters = []){
        $limit = !empty($filters['limit']) ? max(1, (int)$filters['limit']) : 0;
        // Izoh: Filtrlar uchun SQL bo'laklari (kafedra va semestr).
        $filterKafedraBase = '';
        $filterKafedraMerged = '';
        $filterYonalish = '';
        $filterSemestr = '';
        $filterSemestrLecture = '';
        $filterCurrentSemestr = '';
        $filterCurrentLecture = '';
        $filterSemestrType = '';
        $filterSemestrTypeLecture = '';
        $filterOquvYil = '';
        $filterOquvYilLecture = '';
        $filterKurs = '';
        $filterKursLecture = '';
        if (!empty($filters['kafedra_id'])) {
            $kid = (int)$filters['kafedra_id'];
            $filterKafedraBase = " AND (
                fr.kafedra_id = {$kid}
                OR EXISTS (
                    SELECT 1
                    FROM ishchi_variant_dept ivd
                    WHERE ivd.base_fan_id = fr.fan_id
                      AND ivd.variant_kafedra_id = {$kid}
                )
            )";
            $filterKafedraMerged = " AND k.id = {$kid}";
        }
        if (!empty($filters['yonalish_id'])) {
            $filterYonalish = " AND y.id = " . (int)$filters['yonalish_id'];
        }
        if (!empty($filters['semestr'])) {
            $filterSemestr = " AND s.semestr = " . (int)$filters['semestr'];
            $filterSemestrLecture = " AND ul.semestr = " . (int)$filters['semestr'];
        } elseif (!empty($filters['oquv_yil_start'])) {
            // Izoh: O'quv yili juftligi bo'yicha aniq semestrni hisoblash (semestr turi bo'lmasa joriy turini olamiz).
            $startYear = (int)$filters['oquv_yil_start'];
            $semType = !empty($filters['semestr_turi']) ? $filters['semestr_turi'] : '';
            if ($semType !== 'fall' && $semType !== 'spring') {
                $month = (int)date('n');
                $semType = ($month >= 9 || $month === 1) ? 'fall' : 'spring';
            }
            $parityAdd = ($semType === 'fall') ? 1 : 2;
            $semExpr = "GREATEST(1, LEAST(10, (({$startYear} - y.kirish_yili + 1) * 2) + " . ($parityAdd - 2) . "))";
            $filterOquvYil = " AND s.semestr = {$semExpr}";
            // Izoh: Umumta'lim ma'ruza CTE ichida y mavjud, shu yerda filter ishlatiladi.
            $filterOquvYilLecture = '';
        } elseif (!empty($filters['semestr_turi'])) {
            // Izoh: Semestr turi bo'yicha filter (Kuzgi/Bahorgi).
            if ($filters['semestr_turi'] === 'fall') {
                $filterSemestrType = " AND MOD(s.semestr, 2) = 1";
                $filterSemestrTypeLecture = " AND MOD(ul.semestr, 2) = 1";
            } elseif ($filters['semestr_turi'] === 'spring') {
                $filterSemestrType = " AND MOD(s.semestr, 2) = 0";
                $filterSemestrTypeLecture = " AND MOD(ul.semestr, 2) = 0";
            }
        } else {
            // Izoh: Hech qanday semestr filtri berilmasa barcha semestrlar ko'rsatiladi.
            // $filterCurrentSemestr bo'sh qoladi.
        }
        if (!empty($filters['kurs'])) {
            $kurs = (int)$filters['kurs'];
            if ($kurs > 0) {
                $filterKurs = " AND FLOOR((s.semestr + 1)/2) = {$kurs}";
                $filterKursLecture = " AND ul.kurs = {$kurs}";
            }
        }
        $limitSQL = $limit > 0 ? "LIMIT {$limit}" : '';

        // Izoh: Umumta'lim biriktirishda ma'ruza bitta, qolgan darslar yo'nalish bo'yicha alohida chiqishi uchun UNION ishlatiladi.
        $sql = "
            WITH fan_reja AS (
                SELECT
                    f.id AS fan_id,
                    f.fan_name,
                    f.fan_code,
                    f.semestr_id,
                    f.kafedra_id,

                    SUM(CASE WHEN r.dars_tur_id = 1 THEN r.dars_soat ELSE 0 END) AS maruza_soat,
                    SUM(CASE WHEN r.dars_tur_id = 2 THEN r.dars_soat ELSE 0 END) AS amaliy_soat,
                    SUM(CASE WHEN r.dars_tur_id = 3 THEN r.dars_soat ELSE 0 END) AS laboratoriya_soat,
                    SUM(CASE WHEN r.dars_tur_id = 4 THEN r.dars_soat ELSE 0 END) AS seminar_soat

                FROM fanlar f
                JOIN oquv_rejalar r ON r.fan_id = f.id
                GROUP BY f.id, f.fan_name, f.fan_code, f.semestr_id, f.kafedra_id
            ),
            fan_reja_umum AS (
                SELECT
                    f.fan_code,
                    f.fan_name,
                    f.kafedra_id,
                    s.semestr,
                    MAX(CASE WHEN r.dars_tur_id = 1 THEN r.dars_soat ELSE 0 END) AS maruza_soat,
                    MAX(CASE WHEN r.dars_tur_id = 2 THEN r.dars_soat ELSE 0 END) AS amaliy_soat,
                    MAX(CASE WHEN r.dars_tur_id = 3 THEN r.dars_soat ELSE 0 END) AS laboratoriya_soat,
                    MAX(CASE WHEN r.dars_tur_id = 4 THEN r.dars_soat ELSE 0 END) AS seminar_soat
                FROM fanlar f
                JOIN oquv_rejalar r ON r.fan_id = f.id
                JOIN semestrlar s ON s.id = f.semestr_id
                GROUP BY f.fan_code, f.fan_name, f.kafedra_id, s.semestr
            ),
            guruh_agg AS (
                SELECT
                    yonalish_id,
                    GROUP_CONCAT(guruh_nomer SEPARATOR ' | ') AS guruh_raqami,
                    COUNT(id) AS guruhlar_soni,
                    SUM(soni) AS talabalar_soni
                FROM guruhlar
                GROUP BY yonalish_id
            ),
            ishchi_variant_info AS (
                SELECT
                    ior.base_fan_id,
                    GROUP_CONCAT(
                        DISTINCT CONCAT(
                            fv.fan_name,
                            IF(kv.name IS NOT NULL AND kv.name <> '', CONCAT(' (', kv.name, ')'), '')
                        )
                        ORDER BY fv.fan_name
                        SEPARATOR ' | '
                    ) AS variant_names,
                    GROUP_CONCAT(DISTINCT kv.name ORDER BY kv.name SEPARATOR ' | ') AS kafedra_names
                FROM ishchi_oquv_reja ior
                JOIN ishchi_oquv_reja_variants iv ON iv.ishchi_reja_id = ior.id
                JOIN fanlar fv ON fv.id = iv.fan_id
                LEFT JOIN kafedralar kv ON kv.id = fv.kafedra_id
                GROUP BY ior.base_fan_id
            ),
            ishchi_variant_dept AS (
                SELECT DISTINCT
                    ior.base_fan_id,
                    fv.kafedra_id AS variant_kafedra_id
                FROM ishchi_oquv_reja ior
                JOIN ishchi_oquv_reja_variants iv ON iv.ishchi_reja_id = ior.id
                JOIN fanlar fv ON fv.id = iv.fan_id
                WHERE fv.kafedra_id IS NOT NULL
                  AND fv.kafedra_id > 0
            ),
            umumtalim_birik AS (
                SELECT
                    ub.umumtalim_fan_id,
                    ub.semestr_id,
                    ub.source_fan_id,
                    ub.yonalish_id,
                    uf.fan_code,
                    uf.fan_name,
                    uf.kafedra_id AS kafedra_id,
                    sf.kafedra_id AS source_kafedra_id
                FROM umumtalim_fan_biriktirish ub
                JOIN umumtalim_fanlar uf ON uf.id = ub.umumtalim_fan_id
                LEFT JOIN fanlar sf ON sf.id = ub.source_fan_id
            ),
            umumtalim_fan_soat AS (
                SELECT
                    ub.umumtalim_fan_id,
                    s.semestr,
                    MAX(COALESCE(fr_src.maruza_soat, fr_fb.maruza_soat, fr_name.maruza_soat, 0)) AS maruza_soat,
                    MAX(COALESCE(fr_src.amaliy_soat, fr_fb.amaliy_soat, fr_name.amaliy_soat, 0)) AS amaliy_soat,
                    MAX(COALESCE(fr_src.laboratoriya_soat, fr_fb.laboratoriya_soat, fr_name.laboratoriya_soat, 0)) AS laboratoriya_soat,
                    MAX(COALESCE(fr_src.seminar_soat, fr_fb.seminar_soat, fr_name.seminar_soat, 0)) AS seminar_soat
                FROM umumtalim_birik ub
                JOIN semestrlar s ON s.id = ub.semestr_id
                LEFT JOIN fan_reja fr_src ON fr_src.fan_id = ub.source_fan_id
                LEFT JOIN fan_reja_umum fr_fb
                    ON fr_fb.fan_code = ub.fan_code
                   AND fr_fb.fan_name = ub.fan_name
                   AND fr_fb.kafedra_id = ub.kafedra_id
                   AND fr_fb.semestr = s.semestr
                LEFT JOIN fan_reja fr_name
                    ON fr_name.semestr_id = ub.semestr_id
                   AND fr_name.kafedra_id = COALESCE(ub.source_kafedra_id, ub.kafedra_id)
                   AND fr_name.fan_name = ub.fan_name
                GROUP BY ub.umumtalim_fan_id, s.semestr
            ),
            umumtalim_birik_info AS (
                SELECT
                    ub.umumtalim_fan_id,
                    s.semestr,
                    GROUP_CONCAT(DISTINCT y.code ORDER BY y.code SEPARATOR ', ') AS biriktirilgan_yonalish_code,
                    GROUP_CONCAT(
                        DISTINCT CONCAT(y.name, ' - ', y.kirish_yili)
                        ORDER BY y.code
                        SEPARATOR ' | '
                    ) AS biriktirilgan_yonalishlar
                FROM umumtalim_birik ub
                JOIN yonalishlar y ON y.id = ub.yonalish_id
                JOIN semestrlar s ON s.id = ub.semestr_id
                GROUP BY ub.umumtalim_fan_id, s.semestr
            ),
            umumtalim_lecture AS (
                SELECT
                    ub.umumtalim_fan_id,
                    ub.fan_code,
                    ub.fan_name,
                    ub.kafedra_id,
                    s.semestr,
                    FLOOR((s.semestr + 1)/2) AS kurs,
                    GROUP_CONCAT(DISTINCT ga.guruh_raqami ORDER BY ga.guruh_raqami SEPARATOR ' | ') AS guruh_raqami,
                    SUM(COALESCE(ga.guruhlar_soni, 0)) AS guruhlar_soni,
                    SUM(COALESCE(ga.talabalar_soni, 0)) AS talabalar_soni,
                    GROUP_CONCAT(DISTINCT y.code ORDER BY y.code SEPARATOR ', ') AS yonalish_code,
                    GROUP_CONCAT(DISTINCT CONCAT(y.name, ' - ', y.kirish_yili) ORDER BY y.code SEPARATOR ' | ') AS talim_yonalishi,
                    GROUP_CONCAT(DISTINCT tsh.name ORDER BY tsh.name SEPARATOR ' | ') AS oquv_shakli
                FROM umumtalim_birik ub
                JOIN semestrlar s ON s.id = ub.semestr_id
                JOIN yonalishlar y ON y.id = ub.yonalish_id
                JOIN talim_shakllar tsh ON tsh.id = y.talim_shakli_id
                LEFT JOIN guruh_agg ga ON ga.yonalish_id = y.id
                WHERE 1=1
                $filterYonalish
                $filterCurrentSemestr
                $filterOquvYil
                GROUP BY ub.umumtalim_fan_id, s.semestr
            ),
            umumtalim_kopaytirgich AS (
                SELECT
                    ub.umumtalim_fan_id,
                    s.semestr,
                    1 AS patok_soni,
                    SUM(y.kattaguruh_soni) AS kattaguruh_soni,
                    SUM(y.kichikguruh_soni) AS kichikguruh_soni
                FROM umumtalim_birik ub
                JOIN semestrlar s ON s.id = ub.semestr_id
                JOIN yonalishlar y ON y.id = ub.yonalish_id
                WHERE 1=1
                $filterYonalish
                $filterCurrentSemestr
                $filterOquvYil
                GROUP BY ub.umumtalim_fan_id, s.semestr
            )

            SELECT *
            FROM (
                -- Izoh: Oddiy fanlar (umumta'lim biriktirilmaganlar).
                SELECT
                    COALESCE(NULLIF(ivi.variant_names, ''), fr.fan_name) AS fan_name,
                    y.name AS talim_yonalishi,
                    y.code AS yonalish_code,
                    COALESCE(NULLIF(k.name, ''), NULLIF(ivi.kafedra_names, ''), 'Kafedra belgilanmagan') AS kafedra_nomi,
                    tsh.name AS oquv_shakli,
                    s.semestr,
                    FLOOR((s.semestr + 1)/2) AS kurs,

                    ga.guruh_raqami,
                    ga.guruhlar_soni,
                    ga.talabalar_soni,

                    y.patok_soni,
                    y.kattaguruh_soni,
                    y.kichikguruh_soni,

                    fr.maruza_soat,
                    fr.amaliy_soat,
                    fr.laboratoriya_soat,
                    fr.seminar_soat,
                    fr.maruza_soat AS amalda_maruz,
                    fr.amaliy_soat * y.kattaguruh_soni AS amalda_amaliy,
                    fr.laboratoriya_soat * y.kichikguruh_soni AS amalda_lab,
                    fr.seminar_soat * y.kattaguruh_soni AS amalda_seminar,
                    fr.maruza_soat
                    + fr.amaliy_soat * y.kattaguruh_soni
                    + fr.laboratoriya_soat * y.kichikguruh_soni
                    + fr.seminar_soat * y.kattaguruh_soni
                    AS jami_soat,
                    '' AS biriktirilgan_yonalish_code,
                    '' AS biriktirilgan_yonalishlar,
                    0 AS is_birlashtirilgan
                FROM fan_reja fr
                JOIN semestrlar s ON s.id = fr.semestr_id
                JOIN yonalishlar y ON y.id = s.yonalish_id
                JOIN talim_shakllar tsh ON tsh.id = y.talim_shakli_id
                LEFT JOIN kafedralar k ON k.id = fr.kafedra_id
                LEFT JOIN ishchi_variant_info ivi ON ivi.base_fan_id = fr.fan_id
                JOIN guruh_agg ga ON ga.yonalish_id = y.id
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM umumtalim_birik ub
                    WHERE ub.source_fan_id = fr.fan_id
                       OR (
                            ub.semestr_id = fr.semestr_id
                            AND ub.fan_code = fr.fan_code
                            AND ub.fan_name = fr.fan_name
                            AND ub.kafedra_id = fr.kafedra_id
                       )
                       OR (
                            ub.semestr_id = fr.semestr_id
                            AND ub.fan_name = fr.fan_name
                            AND ub.kafedra_id = fr.kafedra_id
                       )
                )
                $filterKafedraBase
                $filterSemestr
                $filterYonalish
                $filterCurrentSemestr
                $filterSemestrType
                $filterOquvYil
                $filterKurs

                UNION ALL

                -- Izoh: Umumta'lim fanlari uchun birlashtirilgan MA'RUZA satri.
                SELECT
                    ul.fan_name,
                    ul.talim_yonalishi,
                    ul.yonalish_code,
                    k.name AS kafedra_nomi,
                    ul.oquv_shakli,
                    ul.semestr,
                    ul.kurs,

                    ul.guruh_raqami,
                    ul.guruhlar_soni,
                    ul.talabalar_soni,

                    uk.patok_soni,
                    COALESCE(NULLIF(ul.guruhlar_soni, 0), uk.kattaguruh_soni) AS kattaguruh_soni,
                    uk.kichikguruh_soni,

                    COALESCE(ufs.maruza_soat, 0) AS maruza_soat,
                    COALESCE(ufs.amaliy_soat, 0) AS amaliy_soat,
                    COALESCE(ufs.laboratoriya_soat, 0) AS laboratoriya_soat,
                    COALESCE(ufs.seminar_soat, 0) AS seminar_soat,
                    COALESCE(ufs.maruza_soat, 0) AS amalda_maruz,
                    COALESCE(ufs.amaliy_soat, 0) * COALESCE(NULLIF(ul.guruhlar_soni, 0), COALESCE(uk.kattaguruh_soni, 0)) AS amalda_amaliy,
                    COALESCE(ufs.laboratoriya_soat, 0) * COALESCE(uk.kichikguruh_soni, 0) AS amalda_lab,
                    COALESCE(ufs.seminar_soat, 0) * COALESCE(NULLIF(ul.guruhlar_soni, 0), COALESCE(uk.kattaguruh_soni, 0)) AS amalda_seminar,
                    COALESCE(ufs.maruza_soat, 0)
                    + (COALESCE(ufs.amaliy_soat, 0) * COALESCE(NULLIF(ul.guruhlar_soni, 0), COALESCE(uk.kattaguruh_soni, 0)))
                    + (COALESCE(ufs.laboratoriya_soat, 0) * COALESCE(uk.kichikguruh_soni, 0))
                    + (COALESCE(ufs.seminar_soat, 0) * COALESCE(NULLIF(ul.guruhlar_soni, 0), COALESCE(uk.kattaguruh_soni, 0)))
                    AS jami_soat,
                    COALESCE(ubi.biriktirilgan_yonalish_code, '') AS biriktirilgan_yonalish_code,
                    COALESCE(ubi.biriktirilgan_yonalishlar, '') AS biriktirilgan_yonalishlar,
                    1 AS is_birlashtirilgan
                FROM umumtalim_lecture ul
                LEFT JOIN umumtalim_fan_soat ufs
                    ON ufs.umumtalim_fan_id = ul.umumtalim_fan_id
                   AND ufs.semestr = ul.semestr
                LEFT JOIN umumtalim_kopaytirgich uk
                    ON uk.umumtalim_fan_id = ul.umumtalim_fan_id
                   AND uk.semestr = ul.semestr
                LEFT JOIN umumtalim_birik_info ubi
                    ON ubi.umumtalim_fan_id = ul.umumtalim_fan_id
                   AND ubi.semestr = ul.semestr
                JOIN kafedralar k ON k.id = ul.kafedra_id
                WHERE 1=1
                $filterKafedraMerged
                $filterSemestrLecture
                $filterSemestrTypeLecture
                $filterOquvYilLecture
                $filterCurrentLecture
                $filterKursLecture
            ) AS yuklama
            ORDER BY yuklama.semestr, yuklama.fan_name
            {$limitSQL};
        ";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }
    public function get_qoshimcha_oquv_yuklamalar($filters = []){
        $limit = !empty($filters['limit']) ? max(1, (int)$filters['limit']) : 0;
        $where = [];
        $currentSemestrSQL = '';
        if (!empty($filters['kafedra_id'])) {
            $where[] = "k.id = " . (int)$filters['kafedra_id'];
        }
        if (!empty($filters['yonalish_id'])) {
            $where[] = "y.id = " . (int)$filters['yonalish_id'];
        }
        if (!empty($filters['semestr'])) {
            $s = (int)$filters['semestr'];
            $pairStart = ($s % 2 === 0) ? $s - 1 : $s;
            $pairEnd = $pairStart + 1;
            $where[] = "s.semestr IN ($pairStart, $pairEnd)";
        } elseif (!empty($filters['oquv_yil_start'])) {
            $startYear = (int)$filters['oquv_yil_start'];
            $semType = !empty($filters['semestr_turi']) ? $filters['semestr_turi'] : '';
            if ($semType !== 'fall' && $semType !== 'spring') {
                $month = (int)date('n');
                $semType = ($month >= 9 || $month === 1) ? 'fall' : 'spring';
            }
            $parityAdd = ($semType === 'fall') ? 1 : 2;
            $semExpr = "GREATEST(1, LEAST(10, (({$startYear} - y.kirish_yili + 1) * 2) + " . ($parityAdd - 2) . "))";
            $where[] = "s.semestr = {$semExpr}";
        } elseif (!empty($filters['semestr_turi'])) {
            if ($filters['semestr_turi'] === 'fall') {
                $where[] = "MOD(s.semestr, 2) = 1";
            } elseif ($filters['semestr_turi'] === 'spring') {
                $where[] = "MOD(s.semestr, 2) = 0";
            }
        } else {
            // Izoh: Hech qanday semestr filtri berilmasa barcha semestrlar ko'rsatiladi.
        }
        if (!empty($filters['kurs'])) {
            $kurs = (int)$filters['kurs'];
            if ($kurs > 0) {
                $where[] = "FLOOR((s.semestr + 1)/2) = {$kurs}";
            }
        }
        $whereSQL = '';
        if (!empty($where)) {
            $whereSQL = 'WHERE ' . implode(' AND ', $where);
        }
        $limitSQL = $limit > 0 ? "LIMIT {$limit}" : '';
        $sql = "
            WITH guruh_agg AS (
                SELECT
                    yonalish_id,
                    GROUP_CONCAT(guruh_nomer SEPARATOR ' | ') AS guruh_raqami,
                    COUNT(id) AS guruhlar_soni,
                    SUM(soni) AS talabalar_soni
                FROM guruhlar
                GROUP BY yonalish_id
            )

            SELECT
                qdt.name AS fan_nomi,  
                y.name AS talim_yonalishi,
                y.code AS yonalish_code,
                k.name AS kafedra_nomi,
                tsh.name AS oquv_shakli,
                s.semestr,
                FLOOR((s.semestr + 1)/2) AS kurs,

                ga.guruh_raqami,
                ga.guruhlar_soni,
                ga.talabalar_soni,
                qf.fan_soat AS fan_soat,
                SUM(CASE WHEN qdt.id = 20 THEN q.dars_soati ELSE 0 END) AS oraliq_nazorat,
                SUM(CASE WHEN qdt.id = 21 THEN q.dars_soati ELSE 0 END) AS yakuniy_nazorat,
                y.patok_soni,
                y.kattaguruh_soni,
                y.kichikguruh_soni,
                SUM(CASE WHEN qdt.id = 1 THEN q.dars_soati ELSE 0 END) AS kurs_ishi,
                SUM(CASE WHEN qdt.id = 2 THEN q.dars_soati ELSE 0 END) AS kurs_loyiha,
                SUM(CASE WHEN qdt.id = 3 THEN q.dars_soati ELSE 0 END) AS oquv_ped_amaliyot,
                SUM(CASE WHEN qdt.id = 4 THEN q.dars_soati ELSE 0 END) AS uzluksiz_malakaviy,
                SUM(CASE WHEN qdt.id = 5 THEN q.dars_soati ELSE 0 END) AS dala_amaliyoti_otm,
                SUM(CASE WHEN qdt.id = 6 THEN q.dars_soati ELSE 0 END) AS dala_amaliyoti_tashqarida,
                SUM(CASE WHEN qdt.id = 7 THEN q.dars_soati ELSE 0 END) AS ishlab_chiqarish,
                SUM(CASE WHEN qdt.id = 8 THEN q.dars_soati ELSE 0 END) AS bmi_rahbarligi,
                SUM(CASE WHEN qdt.id = 9 THEN q.dars_soati ELSE 0 END) AS ilmiy_tadqiqot_ishi,
                SUM(CASE WHEN qdt.id = 10 THEN q.dars_soati ELSE 0 END) AS ilmiy_pedagogik_ishi,
                SUM(CASE WHEN qdt.id = 11 THEN q.dars_soati ELSE 0 END) AS ilmiy_stajirovka,
                SUM(CASE WHEN qdt.id = 12 THEN q.dars_soati ELSE 0 END) AS tayanch_doktorantura,
                SUM(CASE WHEN qdt.id = 13 THEN q.dars_soati ELSE 0 END) AS katta_ilmiy_tadqiqotchi,
                SUM(CASE WHEN qdt.id = 14 THEN q.dars_soati ELSE 0 END) AS stajyor_tadqiqotchi,
                SUM(CASE WHEN qdt.id = 15 THEN q.dars_soati ELSE 0 END) AS ochiq_dars,
                SUM(CASE WHEN qdt.id = 16 THEN q.dars_soati ELSE 0 END) AS yadak,
                SUM(CASE WHEN qdt.id = 17 THEN q.dars_soati ELSE 0 END) AS boshqa_soatlar,

                SUM(q.dars_soati) AS jami_soat

            FROM qoshimcha_oquv_rejalar q
            JOIN qoshimcha_fanlar qf ON qf.id = q.qoshimcha_fanid
            JOIN qoshimcha_dars_turlar qdt ON qdt.id = qf.qoshimcha_dars_id

            JOIN semestrlar s ON s.id = qf.semestr_id
            JOIN yonalishlar y ON y.id = s.yonalish_id
            JOIN talim_shakllar tsh ON tsh.id = y.talim_shakli_id
            JOIN kafedralar k ON k.id = q.kafedra_id
            JOIN guruh_agg ga ON ga.yonalish_id = y.id
            $whereSQL
            GROUP BY qf.id, q.kafedra_id

            ORDER BY s.semestr, qdt.name
            {$limitSQL};
        ";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }
    public function get_oqtuvchilar(){
        $sql = "SELECT o.*, f.name AS fakultet_name, k.name AS kafedra_name, iu.name AS ilmiy_unvon_name, isht.name AS ishtur_name, id.name AS ilmiy_daraja_name
        FROM `oqituvchilar` o
        JOIN fakultetlar f ON f.id=o.fakultet_id
        JOIN kafedralar k ON k.id=o.kafedra_id
        JOIN ilmiy_unvonlar iu ON iu.id=o.ilmiy_unvon_id
        JOIN ilmiy_darajalar id ON id.id=o.ilmiy_daraja_id
        JOIN ish_turlar isht ON isht.id=o.ishtur_id;";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }
    public function get_oquv_taqsimotlar($filters=[]){
        $limit = !empty($filters['limit']) ? max(1, (int)$filters['limit']) : 0;
        $whereBase = [];
        $whereMerged = [];
        if (!empty($filters['kafedra_id'])) {
            $kid = (int)$filters['kafedra_id'];
            $whereBase[] = "(fr.kafedra_id = {$kid} OR EXISTS (
                SELECT 1
                FROM ishchi_variant_dept ivd
                WHERE ivd.base_fan_id = fr.fan_id
                  AND ivd.variant_kafedra_id = {$kid}
            ))";
            $whereMerged[] = "k.id = {$kid}";
        }
        if (!empty($filters['semestr'])) {
            $s = (int)$filters['semestr'];
            $pairStart = ($s % 2 === 0) ? $s - 1 : $s;
            $pairEnd = $pairStart + 1;
            $whereBase[] = "s.semestr IN ({$pairStart}, {$pairEnd})";
            $whereMerged[] = "ul.semestr IN ({$pairStart}, {$pairEnd})";
        }
        if (!empty($filters['oquv_reja_id'])) {
            $rid = (int)$filters['oquv_reja_id'];
            $whereBase[] = "({$rid} IN (fr.maruza_reja_id, fr.amaliy_reja_id, fr.laboratoriya_reja_id, fr.seminar_reja_id))";
            $whereMerged[] = "({$rid} IN (ufs.maruza_reja_id, ufs.amaliy_reja_id, ufs.laboratoriya_reja_id, ufs.seminar_reja_id))";
        }

        $whereSQLBase = '';
        if (!empty($whereBase)) {
            $whereSQLBase = ' AND ' . implode(' AND ', $whereBase);
        }
        $whereSQLMerged = '';
        if (!empty($whereMerged)) {
            $whereSQLMerged = ' AND ' . implode(' AND ', $whereMerged);
        }
        $limitSQL = $limit > 0 ? "LIMIT {$limit}" : '';

        $sql = "WITH fan_reja AS (
                SELECT
                    f.id AS fan_id,
                    f.fan_name,
                    f.fan_code,
                    f.semestr_id,
                    f.kafedra_id,

                    MAX(CASE WHEN r.dars_tur_id = 1 THEN r.id END) AS maruza_reja_id,
                    MAX(CASE WHEN r.dars_tur_id = 2 THEN r.id END) AS amaliy_reja_id,
                    MAX(CASE WHEN r.dars_tur_id = 3 THEN r.id END) AS laboratoriya_reja_id,
                    MAX(CASE WHEN r.dars_tur_id = 4 THEN r.id END) AS seminar_reja_id,

                    SUM(CASE WHEN r.dars_tur_id = 1 THEN r.dars_soat ELSE 0 END) AS maruza_soat,
                    SUM(CASE WHEN r.dars_tur_id = 2 THEN r.dars_soat ELSE 0 END) AS amaliy_soat,
                    SUM(CASE WHEN r.dars_tur_id = 3 THEN r.dars_soat ELSE 0 END) AS laboratoriya_soat,
                    SUM(CASE WHEN r.dars_tur_id = 4 THEN r.dars_soat ELSE 0 END) AS seminar_soat
                FROM fanlar f
                JOIN oquv_rejalar r ON r.fan_id = f.id
                GROUP BY f.id, f.fan_name, f.fan_code, f.semestr_id, f.kafedra_id
            ),
            fan_reja_umum AS (
                SELECT
                    f.fan_code,
                    f.fan_name,
                    f.kafedra_id,
                    s.semestr,
                    MIN(CASE WHEN r.dars_tur_id = 1 THEN r.id END) AS maruza_reja_id,
                    MIN(CASE WHEN r.dars_tur_id = 2 THEN r.id END) AS amaliy_reja_id,
                    MIN(CASE WHEN r.dars_tur_id = 3 THEN r.id END) AS laboratoriya_reja_id,
                    MIN(CASE WHEN r.dars_tur_id = 4 THEN r.id END) AS seminar_reja_id,
                    MAX(CASE WHEN r.dars_tur_id = 1 THEN r.dars_soat ELSE 0 END) AS maruza_soat,
                    MAX(CASE WHEN r.dars_tur_id = 2 THEN r.dars_soat ELSE 0 END) AS amaliy_soat,
                    MAX(CASE WHEN r.dars_tur_id = 3 THEN r.dars_soat ELSE 0 END) AS laboratoriya_soat,
                    MAX(CASE WHEN r.dars_tur_id = 4 THEN r.dars_soat ELSE 0 END) AS seminar_soat
                FROM fanlar f
                JOIN oquv_rejalar r ON r.fan_id = f.id
                JOIN semestrlar s ON s.id = f.semestr_id
                GROUP BY f.fan_code, f.fan_name, f.kafedra_id, s.semestr
            ),
            guruh_agg AS (
                SELECT
                    g.yonalish_id,
                    GROUP_CONCAT(DISTINCT g.guruh_nomer ORDER BY g.guruh_nomer SEPARATOR ' | ') AS guruh_raqami,
                    COUNT(DISTINCT g.id) AS guruhlar_soni,
                    SUM(g.soni) AS talabalar_soni
                FROM guruhlar g
                GROUP BY g.yonalish_id
            ),
            ishchi_variant_info AS (
                SELECT
                    ior.base_fan_id,
                    GROUP_CONCAT(
                        DISTINCT CONCAT(
                            fv.fan_name,
                            IF(kv.name IS NOT NULL AND kv.name <> '', CONCAT(' (', kv.name, ')'), '')
                        )
                        ORDER BY fv.fan_name
                        SEPARATOR ' | '
                    ) AS variant_names,
                    GROUP_CONCAT(DISTINCT kv.name ORDER BY kv.name SEPARATOR ' | ') AS kafedra_names
                FROM ishchi_oquv_reja ior
                JOIN ishchi_oquv_reja_variants iv ON iv.ishchi_reja_id = ior.id
                JOIN fanlar fv ON fv.id = iv.fan_id
                LEFT JOIN kafedralar kv ON kv.id = fv.kafedra_id
                GROUP BY ior.base_fan_id
            ),
            ishchi_variant_dept AS (
                SELECT DISTINCT
                    ior.base_fan_id,
                    fv.kafedra_id AS variant_kafedra_id
                FROM ishchi_oquv_reja ior
                JOIN ishchi_oquv_reja_variants iv ON iv.ishchi_reja_id = ior.id
                JOIN fanlar fv ON fv.id = iv.fan_id
                WHERE fv.kafedra_id IS NOT NULL
                  AND fv.kafedra_id > 0
            ),
            umumtalim_birik AS (
                SELECT
                    ub.umumtalim_fan_id,
                    ub.semestr_id,
                    ub.source_fan_id,
                    ub.yonalish_id,
                    uf.fan_code,
                    uf.fan_name,
                    LOWER(TRIM(uf.fan_name)) AS fan_name_key,
                    COALESCE(sf.kafedra_id, uf.kafedra_id) AS kafedra_id
                FROM umumtalim_fan_biriktirish ub
                JOIN umumtalim_fanlar uf ON uf.id = ub.umumtalim_fan_id
                LEFT JOIN fanlar sf ON sf.id = ub.source_fan_id
            ),
            umumtalim_fan_soat AS (
                SELECT
                    ub.fan_name_key,
                    ub.kafedra_id,
                    s.semestr,
                    MAX(ub.fan_name) AS fan_name,
                    MIN(COALESCE(fr_src.maruza_reja_id, fr_fb.maruza_reja_id, fr_name.maruza_reja_id)) AS maruza_reja_id,
                    MIN(COALESCE(fr_src.amaliy_reja_id, fr_fb.amaliy_reja_id, fr_name.amaliy_reja_id)) AS amaliy_reja_id,
                    MIN(COALESCE(fr_src.laboratoriya_reja_id, fr_fb.laboratoriya_reja_id, fr_name.laboratoriya_reja_id)) AS laboratoriya_reja_id,
                    MIN(COALESCE(fr_src.seminar_reja_id, fr_fb.seminar_reja_id, fr_name.seminar_reja_id)) AS seminar_reja_id,
                    MAX(COALESCE(fr_src.maruza_soat, fr_fb.maruza_soat, fr_name.maruza_soat, 0)) AS maruza_soat,
                    MAX(COALESCE(fr_src.amaliy_soat, fr_fb.amaliy_soat, fr_name.amaliy_soat, 0)) AS amaliy_soat,
                    MAX(COALESCE(fr_src.laboratoriya_soat, fr_fb.laboratoriya_soat, fr_name.laboratoriya_soat, 0)) AS laboratoriya_soat,
                    MAX(COALESCE(fr_src.seminar_soat, fr_fb.seminar_soat, fr_name.seminar_soat, 0)) AS seminar_soat
                FROM umumtalim_birik ub
                JOIN semestrlar s ON s.id = ub.semestr_id
                LEFT JOIN fan_reja fr_src ON fr_src.fan_id = ub.source_fan_id
                LEFT JOIN fan_reja_umum fr_fb
                    ON fr_fb.fan_code = ub.fan_code
                   AND fr_fb.fan_name = ub.fan_name
                   AND fr_fb.kafedra_id = ub.kafedra_id
                   AND fr_fb.semestr = s.semestr
                LEFT JOIN fan_reja fr_name
                    ON fr_name.semestr_id = ub.semestr_id
                   AND fr_name.kafedra_id = ub.kafedra_id
                   AND fr_name.fan_name = ub.fan_name
                GROUP BY ub.fan_name_key, ub.kafedra_id, s.semestr
            ),
            umumtalim_lecture AS (
                SELECT
                    ub.fan_name_key,
                    MAX(ub.fan_name) AS fan_name,
                    ub.kafedra_id,
                    s.semestr,
                    FLOOR((s.semestr + 1)/2) AS kurs,
                    GROUP_CONCAT(DISTINCT ga.guruh_raqami ORDER BY ga.guruh_raqami SEPARATOR ' | ') AS guruh_raqami,
                    SUM(COALESCE(ga.guruhlar_soni, 0)) AS guruhlar_soni,
                    SUM(COALESCE(ga.talabalar_soni, 0)) AS talabalar_soni,
                    GROUP_CONCAT(DISTINCT y.code ORDER BY y.code SEPARATOR ', ') AS yonalish_code,
                    GROUP_CONCAT(DISTINCT CONCAT(y.name, ' - ', y.kirish_yili) ORDER BY y.code SEPARATOR ' | ') AS talim_yonalishi,
                    GROUP_CONCAT(DISTINCT tsh.name ORDER BY tsh.name SEPARATOR ' | ') AS oquv_shakli,
                    MAX(
                        CASE WHEN EXISTS (
                            SELECT 1
                            FROM taqsimot_resync_events tre
                            WHERE tre.yonalish_id = y.id
                              AND tre.status = 'pending'
                        ) THEN 1 ELSE 0 END
                    ) AS needs_resync
                FROM umumtalim_birik ub
                JOIN semestrlar s ON s.id = ub.semestr_id
                JOIN yonalishlar y ON y.id = ub.yonalish_id
                JOIN talim_shakllar tsh ON tsh.id = y.talim_shakli_id
                LEFT JOIN guruh_agg ga ON ga.yonalish_id = y.id
                GROUP BY ub.fan_name_key, ub.kafedra_id, s.semestr
            ),
            umumtalim_kopaytirgich AS (
                SELECT
                    ub.fan_name_key,
                    ub.kafedra_id,
                    s.semestr,
                    1 AS patok_soni,
                    SUM(y.kattaguruh_soni) AS kattaguruh_soni,
                    SUM(y.kichikguruh_soni) AS kichikguruh_soni
                FROM umumtalim_birik ub
                JOIN semestrlar s ON s.id = ub.semestr_id
                JOIN yonalishlar y ON y.id = ub.yonalish_id
                GROUP BY ub.fan_name_key, ub.kafedra_id, s.semestr
            )
            SELECT *
            FROM (
                SELECT
                    fr.maruza_reja_id,
                    fr.amaliy_reja_id,
                    fr.laboratoriya_reja_id,
                    fr.seminar_reja_id,
                    y.id AS yonalish_id,
                    COALESCE(NULLIF(ivi.variant_names, ''), fr.fan_name) AS fan_nomi,
                    y.name AS talim_yonalishi,
                    y.code AS yonalish_code,
                    COALESCE(NULLIF(k.name, ''), NULLIF(ivi.kafedra_names, ''), 'Kafedra belgilanmagan') AS kafedra_nomi,
                    tsh.name AS oquv_shakli,
                    s.semestr,
                    FLOOR((s.semestr + 1) / 2) AS kurs,
                    ga.guruh_raqami,
                    ga.guruhlar_soni,
                    ga.talabalar_soni,
                    y.patok_soni,
                    y.kattaguruh_soni,
                    y.kichikguruh_soni,
                    EXISTS (
                        SELECT 1
                        FROM taqsimot_resync_events tre
                        WHERE tre.yonalish_id = y.id
                          AND tre.status = 'pending'
                    ) AS needs_resync,
                    fr.maruza_soat AS reja_maruz,
                    fr.amaliy_soat AS reja_amaliy,
                    fr.laboratoriya_soat AS reja_laboratoriya,
                    fr.seminar_soat AS reja_seminar,
                    fr.maruza_soat AS amalda_maruz,
                    fr.amaliy_soat * y.kattaguruh_soni AS amalda_amaliy,
                    fr.laboratoriya_soat * y.kichikguruh_soni AS amalda_laboratoriya,
                    fr.seminar_soat * y.kattaguruh_soni AS amalda_seminar,
                    fr.maruza_soat
                    + fr.amaliy_soat * y.kattaguruh_soni
                    + fr.laboratoriya_soat * y.kichikguruh_soni
                    + fr.seminar_soat * y.kattaguruh_soni
                    AS jami_soat
                FROM fan_reja fr
                JOIN semestrlar s ON s.id = fr.semestr_id
                JOIN yonalishlar y ON y.id = s.yonalish_id
                LEFT JOIN kafedralar k ON k.id = fr.kafedra_id
                LEFT JOIN ishchi_variant_info ivi ON ivi.base_fan_id = fr.fan_id
                JOIN talim_shakllar tsh ON tsh.id = y.talim_shakli_id
                JOIN guruh_agg ga ON ga.yonalish_id = y.id
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM umumtalim_birik ub
                    WHERE ub.source_fan_id = fr.fan_id
                       OR (
                            ub.semestr_id = fr.semestr_id
                            AND ub.fan_code = fr.fan_code
                            AND ub.fan_name = fr.fan_name
                            AND ub.kafedra_id = fr.kafedra_id
                       )
                       OR (
                            ub.semestr_id = fr.semestr_id
                            AND ub.fan_name = fr.fan_name
                            AND ub.kafedra_id = fr.kafedra_id
                       )
                )
                $whereSQLBase

                UNION ALL

                SELECT
                    ufs.maruza_reja_id,
                    ufs.amaliy_reja_id,
                    ufs.laboratoriya_reja_id,
                    ufs.seminar_reja_id,
                    0 AS yonalish_id,
                    ul.fan_name AS fan_nomi,
                    ul.talim_yonalishi,
                    ul.yonalish_code,
                    k.name AS kafedra_nomi,
                    ul.oquv_shakli,
                    ul.semestr,
                    ul.kurs,
                    ul.guruh_raqami,
                    ul.guruhlar_soni,
                    ul.talabalar_soni,
                    uk.patok_soni,
                    COALESCE(NULLIF(ul.guruhlar_soni, 0), uk.kattaguruh_soni) AS kattaguruh_soni,
                    uk.kichikguruh_soni,
                    ul.needs_resync,
                    COALESCE(ufs.maruza_soat, 0) AS reja_maruz,
                    COALESCE(ufs.amaliy_soat, 0) AS reja_amaliy,
                    COALESCE(ufs.laboratoriya_soat, 0) AS reja_laboratoriya,
                    COALESCE(ufs.seminar_soat, 0) AS reja_seminar,
                    COALESCE(ufs.maruza_soat, 0) AS amalda_maruz,
                    COALESCE(ufs.amaliy_soat, 0) * COALESCE(NULLIF(ul.guruhlar_soni, 0), COALESCE(uk.kattaguruh_soni, 0)) AS amalda_amaliy,
                    COALESCE(ufs.laboratoriya_soat, 0) * COALESCE(uk.kichikguruh_soni, 0) AS amalda_laboratoriya,
                    COALESCE(ufs.seminar_soat, 0) * COALESCE(NULLIF(ul.guruhlar_soni, 0), COALESCE(uk.kattaguruh_soni, 0)) AS amalda_seminar,
                    COALESCE(ufs.maruza_soat, 0)
                    + (COALESCE(ufs.amaliy_soat, 0) * COALESCE(NULLIF(ul.guruhlar_soni, 0), COALESCE(uk.kattaguruh_soni, 0)))
                    + (COALESCE(ufs.laboratoriya_soat, 0) * COALESCE(uk.kichikguruh_soni, 0))
                    + (COALESCE(ufs.seminar_soat, 0) * COALESCE(NULLIF(ul.guruhlar_soni, 0), COALESCE(uk.kattaguruh_soni, 0)))
                    AS jami_soat
                FROM umumtalim_lecture ul
                LEFT JOIN umumtalim_fan_soat ufs
                    ON ufs.fan_name_key = ul.fan_name_key
                   AND ufs.kafedra_id = ul.kafedra_id
                   AND ufs.semestr = ul.semestr
                LEFT JOIN umumtalim_kopaytirgich uk
                    ON uk.fan_name_key = ul.fan_name_key
                   AND uk.kafedra_id = ul.kafedra_id
                   AND uk.semestr = ul.semestr
                JOIN kafedralar k ON k.id = ul.kafedra_id
                WHERE 1=1
                $whereSQLMerged
            ) AS taqsim
            ORDER BY taqsim.semestr, taqsim.fan_nomi
            {$limitSQL};
        ";

        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }
    public function get_qoshimcha_oquv_taqsimotlar($filters = []){
        $limit = !empty($filters['limit']) ? max(1, (int)$filters['limit']) : 0;
        $where = [];
        if (!empty($filters['kafedra_id'])) {
            $where[] = "k.id = " . (int)$filters['kafedra_id'];
        }
        if (!empty($filters['semestr'])) {
            $where[] = "s.semestr = " . (int)$filters['semestr'];
        }
        if (!empty($filters['qoshimcha_oquv_reja_id'])){
            $where[] = "q.id = " . (int)$filters['qoshimcha_oquv_reja_id'];
        }
        $whereSQL = '';
        if (!empty($where)) {
            $whereSQL = 'WHERE ' . implode(' AND ', $where);
        }
        $limitSQL = $limit > 0 ? "LIMIT {$limit}" : '';
        $sql = "
            WITH guruh_agg AS (
                SELECT
                    yonalish_id,
                    GROUP_CONCAT(DISTINCT guruh_nomer ORDER BY guruh_nomer SEPARATOR ' | ') AS guruh_raqami,
                    COUNT(DISTINCT id) AS guruhlar_soni,
                    SUM(soni) AS talabalar_soni
                FROM guruhlar
                GROUP BY yonalish_id
            )
            SELECT
                q.id AS qoshimcha_reja_id, 
                y.id AS yonalish_id,
                qf.fan_name AS fan_nomi,
                y.name AS talim_yonalishi,
                y.code AS yonalish_code,
                k.name AS kafedra_nomi,
                tsh.name AS oquv_shakli,
                s.semestr,
                FLOOR((s.semestr + 1)/2) AS kurs,

                ga.guruh_raqami,
                ga.guruhlar_soni,
                ga.talabalar_soni,
                CASE WHEN qf.qoshimcha_dars_id = 20 THEN q.dars_soati ELSE 0 END AS oraliq_nazorat,
                CASE WHEN qf.qoshimcha_dars_id = 21 THEN q.dars_soati ELSE 0 END AS yakuniy_nazorat,
                y.patok_soni,
                y.kattaguruh_soni,
                y.kichikguruh_soni,
                EXISTS (
                    SELECT 1
                    FROM taqsimot_resync_events tre
                    WHERE tre.yonalish_id = y.id
                      AND tre.status = 'pending'
                ) AS needs_resync,
                CASE WHEN qf.qoshimcha_dars_id = 1  THEN q.dars_soati ELSE 0 END AS kurs_ishi,
                CASE WHEN qf.qoshimcha_dars_id = 2  THEN q.dars_soati ELSE 0 END AS kurs_loyiha,
                CASE WHEN qf.qoshimcha_dars_id = 3  THEN q.dars_soati ELSE 0 END AS oquv_ped_amaliyot,
                CASE WHEN qf.qoshimcha_dars_id = 4  THEN q.dars_soati ELSE 0 END AS uzluksiz_malakaviy,
                CASE WHEN qf.qoshimcha_dars_id = 5  THEN q.dars_soati ELSE 0 END AS dala_amaliyoti_otm,
                CASE WHEN qf.qoshimcha_dars_id = 6  THEN q.dars_soati ELSE 0 END AS dala_amaliyoti_tashqarida,
                CASE WHEN qf.qoshimcha_dars_id = 7  THEN q.dars_soati ELSE 0 END AS ishlab_chiqarish,
                CASE WHEN qf.qoshimcha_dars_id = 8  THEN q.dars_soati ELSE 0 END AS bmi_rahbarligi,
                CASE WHEN qf.qoshimcha_dars_id = 9  THEN q.dars_soati ELSE 0 END AS ilmiy_tadqiqot_ishi,
                CASE WHEN qf.qoshimcha_dars_id = 10 THEN q.dars_soati ELSE 0 END AS ilmiy_pedagogik_ishi,
                CASE WHEN qf.qoshimcha_dars_id = 11 THEN q.dars_soati ELSE 0 END AS ilmiy_stajirovka,
                CASE WHEN qf.qoshimcha_dars_id = 12 THEN q.dars_soati ELSE 0 END AS tayanch_doktorantura,
                CASE WHEN qf.qoshimcha_dars_id = 13 THEN q.dars_soati ELSE 0 END AS katta_ilmiy_tadqiqotchi,
                CASE WHEN qf.qoshimcha_dars_id = 14 THEN q.dars_soati ELSE 0 END AS stajyor_tadqiqotchi,
                CASE WHEN qf.qoshimcha_dars_id = 15 THEN q.dars_soati ELSE 0 END AS ochiq_dars,
                CASE WHEN qf.qoshimcha_dars_id = 16 THEN q.dars_soati ELSE 0 END AS yadak,
                CASE WHEN qf.qoshimcha_dars_id = 17 THEN q.dars_soati ELSE 0 END AS boshqa_soatlar,

                q.dars_soati AS jami_soat
            FROM qoshimcha_oquv_rejalar q
            JOIN qoshimcha_fanlar qf ON qf.id = q.qoshimcha_fanid
            JOIN semestrlar s ON s.id = qf.semestr_id
            JOIN yonalishlar y ON y.id = s.yonalish_id
            JOIN talim_shakllar tsh ON tsh.id = y.talim_shakli_id
            JOIN kafedralar k ON k.id = q.kafedra_id
            JOIN guruh_agg ga ON ga.yonalish_id = y.id
            $whereSQL
            ORDER BY s.semestr, q.id
            {$limitSQL};
        ";
        
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;

    }
    public function get_yunalishlar_history_with_details(){
        $sql = "SELECT
            yh.id,
            yh.yonalish_id,
            yh.name AS yonalish_nomi,
            yh.code AS yonalish_kodi,
            yh.muddati AS talim_muddati,
            yh.kirish_yili,
            yh.patok_soni,
            yh.kattaguruh_soni,
            yh.kichikguruh_soni,
            ad.name AS akademik_daraja,
            ts.name AS talim_shakli,
            yh.kvalifikatsiya,
            f.name AS fakultet,
            yh.sync_status,
            yh.change_type,
            yh.changed_at
        FROM yonalishlar_history yh
        LEFT JOIN akademik_darajalar ad ON yh.akademik_daraja_id = ad.id
        LEFT JOIN talim_shakllar ts ON yh.talim_shakli_id = ts.id
        LEFT JOIN fakultetlar f ON yh.fakultet_id = f.id
        ORDER BY yh.id DESC";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }
    public function get_guruhlar_history(){
        $sql = "SELECT
            gh.id,
            gh.guruh_id,
            gh.yonalish_id,
            gh.guruh_nomer,
            gh.soni,
            gh.sync_status,
            gh.change_type,
            gh.changed_at,
            y.name AS yonalish_name
        FROM guruhlar_history gh
        LEFT JOIN yonalishlar y ON y.id = gh.yonalish_id
        ORDER BY gh.id DESC";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }

    public function get_oqituvchi_taqsimotlar($filters = []){
        // Izoh: O'qituvchilar bo'yicha taqsimot soatlarini hisoblaymiz (A va Q turlari).
        $where = [];
        if (!empty($filters['kafedra_id'])) {
            $where[] = "o.kafedra_id = " . (int)$filters['kafedra_id'];
        }
        if (!empty($filters['semestr'])) {
            $where[] = "ts.semestr = " . (int)$filters['semestr'];
        }
        if (!empty($filters['oqituvchi_id'])) {
            $where[] = "o.id = " . (int)$filters['oqituvchi_id'];
        }
        $whereSQL = '';
        if (!empty($where)) {
            $whereSQL = 'WHERE ' . implode(' AND ', $where);
        }

        $sql = "
            WITH taqsimot_src AS (
                SELECT
                    t.teacher_id,
                    t.soat,
                    t.type,
                    s.semestr
                FROM taqsimotlar t
                JOIN oquv_rejalar r ON r.id = t.oquv_reja_id
                JOIN fanlar f ON f.id = r.fan_id
                JOIN semestrlar s ON s.id = f.semestr_id
                WHERE t.type = 'A'

                UNION ALL

                SELECT
                    t.teacher_id,
                    t.soat,
                    t.type,
                    s.semestr
                FROM taqsimotlar t
                JOIN qoshimcha_oquv_rejalar q ON q.id = t.oquv_reja_id
                JOIN qoshimcha_fanlar qf ON qf.id = q.qoshimcha_fanid
                JOIN semestrlar s ON s.id = qf.semestr_id
                WHERE t.type = 'Q'
            )
            SELECT
                o.id AS oqituvchi_id,
                o.fio,
                k.name AS kafedra_nomi,
                SUM(ts.soat) AS jami_soat,
                SUM(CASE WHEN ts.type = 'A' THEN ts.soat ELSE 0 END) AS asosiy_soat,
                SUM(CASE WHEN ts.type = 'Q' THEN ts.soat ELSE 0 END) AS qoshimcha_soat
            FROM taqsimot_src ts
            JOIN oqituvchilar o ON o.id = ts.teacher_id
            LEFT JOIN kafedralar k ON k.id = o.kafedra_id
            $whereSQL
            GROUP BY o.id, o.fio, k.name
            ORDER BY jami_soat DESC, o.fio;
        ";

        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            // Izoh: Qo'shimcha yuklama soatlarini talaba soni asosida avtomatik hisoblash.
            $talaba = (int)($row['talabalar_soni'] ?? 0);
            $fanSoat = (float)($row['fan_soat'] ?? 0);
            $shakl = mb_strtolower(trim($row['oquv_shakli'] ?? ''), 'UTF-8');
            $isExternal = (strpos($shakl, 'sirtqi') !== false) || (strpos($shakl, 'masof') !== false) || (strpos($shakl, 'kechki') !== false);

            if (($row['oraliq_nazorat'] ?? 0) <= 0 && $talaba > 0) {
                if ($isExternal) {
                    $row['oraliq_nazorat'] = 0;
                } elseif ($fanSoat >= 60) {
                    $row['oraliq_nazorat'] = round($talaba * 0.4);
                } elseif ($fanSoat >= 30) {
                    $row['oraliq_nazorat'] = round($talaba * 0.2);
                } else {
                    $row['oraliq_nazorat'] = 0;
                }
            }

            if (($row['yakuniy_nazorat'] ?? 0) <= 0 && $talaba > 0) {
                $row['yakuniy_nazorat'] = $isExternal ? 0 : round($talaba * 0.3);
            }

            if (($row['kurs_ishi'] ?? 0) <= 0 && $talaba > 0) {
                $row['kurs_ishi'] = round($talaba * 2.4);
            }
            if (($row['kurs_loyiha'] ?? 0) <= 0 && $talaba > 0) {
                $row['kurs_loyiha'] = round($talaba * 3.6);
            }
            if (($row['uzluksiz_malakaviy'] ?? 0) <= 0 && $talaba > 0) {
                $row['uzluksiz_malakaviy'] = round($talaba * ($isExternal ? 0.4 : 2));
            }

            // Izoh: Jami soatni qayta hisoblaymiz (faqat hisoblangan/saqlangan qiymatlar).
            $row['jami_soat'] =
                (float)($row['oraliq_nazorat'] ?? 0) +
                (float)($row['yakuniy_nazorat'] ?? 0) +
                (float)($row['kurs_ishi'] ?? 0) +
                (float)($row['kurs_loyiha'] ?? 0) +
                (float)($row['oquv_ped_amaliyot'] ?? 0) +
                (float)($row['uzluksiz_malakaviy'] ?? 0) +
                (float)($row['dala_amaliyoti_otm'] ?? 0) +
                (float)($row['dala_amaliyoti_tashqarida'] ?? 0) +
                (float)($row['ishlab_chiqarish'] ?? 0) +
                (float)($row['bmi_rahbarligi'] ?? 0) +
                (float)($row['ilmiy_tadqiqot_ishi'] ?? 0) +
                (float)($row['ilmiy_pedagogik_ishi'] ?? 0) +
                (float)($row['ilmiy_stajirovka'] ?? 0) +
                (float)($row['tayanch_doktorantura'] ?? 0) +
                (float)($row['katta_ilmiy_tadqiqotchi'] ?? 0) +
                (float)($row['stajyor_tadqiqotchi'] ?? 0) +
                (float)($row['ochiq_dars'] ?? 0) +
                (float)($row['yadak'] ?? 0) +
                (float)($row['boshqa_soatlar'] ?? 0);

            $data[] = $row;
        }
        return $data;
    }

  
}

?>
