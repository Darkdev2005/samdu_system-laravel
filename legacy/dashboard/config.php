<?php

if (!function_exists('legacy_session_user')) {
    function legacy_session_user(): array
    {
        $user = [];

        if (isset($_SESSION['legacy_user']) && is_array($_SESSION['legacy_user'])) {
            $user = $_SESSION['legacy_user'];
        }

        if (function_exists('session')) {
            try {
                $sessionUser = session('legacy_user');
                if (is_array($sessionUser)) {
                    $user = array_merge($user, $sessionUser);
                }

                foreach (['id', 'username', 'name', 'role', 'kafedra_id'] as $key) {
                    $value = session($key);
                    if ($value !== null && $value !== '') {
                        $user[$key] = $value;
                    }
                }
            } catch (Throwable $e) {
            }
        }

        return $user;
    }
}

if (!function_exists('legacy_current_user')) {
    function legacy_current_user()
    {
        if (function_exists('auth')) {
            try {
                $authUser = auth()->user();
                if ($authUser) {
                    return $authUser;
                }
            } catch (Throwable $e) {
            }
        }

        $sessionUser = legacy_session_user();
        return !empty($sessionUser) ? (object)$sessionUser : null;
    }
}

if (!function_exists('legacy_user_role')) {
    function legacy_user_role(): string
    {
        $sessionUser = legacy_session_user();
        if (!empty($sessionUser['role'])) {
            return (string)$sessionUser['role'];
        }

        $user = legacy_current_user();
        $role = is_object($user) ? ($user->role ?? null) : null;
        $role = is_string($role) ? trim($role) : '';
        return $role !== '' ? $role : 'admin';
    }
}

if (!function_exists('legacy_is_kafedra_mudiri')) {
    function legacy_is_kafedra_mudiri(): bool
    {
        return legacy_user_role() === 'kafedra_mudiri';
    }
}

if (!function_exists('legacy_is_admin')) {
    function legacy_is_admin(): bool
    {
        return !legacy_is_kafedra_mudiri();
    }
}

if (!function_exists('legacy_user_id')) {
    function legacy_user_id(): int
    {
        $sessionUser = legacy_session_user();
        if (!empty($sessionUser['id']) && is_numeric($sessionUser['id'])) {
            return (int)$sessionUser['id'];
        }

        $user = legacy_current_user();
        $id = is_object($user) ? ($user->id ?? null) : null;
        return is_numeric($id) ? (int)$id : 0;
    }
}

if (!function_exists('legacy_user_kafedra_id')) {
    function legacy_user_kafedra_id(): int
    {
        $sessionUser = legacy_session_user();
        if (!empty($sessionUser['kafedra_id']) && is_numeric($sessionUser['kafedra_id'])) {
            return (int)$sessionUser['kafedra_id'];
        }

        $user = legacy_current_user();
        $kafedraId = is_object($user) ? ($user->kafedra_id ?? null) : null;
        return is_numeric($kafedraId) ? (int)$kafedraId : 0;
    }
}

if (!function_exists('legacy_user_display_name')) {
    function legacy_user_display_name(): string
    {
        $sessionUser = legacy_session_user();
        foreach (['name', 'username'] as $key) {
            if (!empty($sessionUser[$key])) {
                return (string)$sessionUser[$key];
            }
        }

        $user = legacy_current_user();
        if (is_object($user)) {
            if (!empty($user->name)) {
                return (string)$user->name;
            }
            if (!empty($user->username)) {
                return (string)$user->username;
            }
        }

        return 'Foydalanuvchi';
    }
}

if (!function_exists('legacy_user_role_label')) {
    function legacy_user_role_label(): string
    {
        return legacy_is_kafedra_mudiri() ? 'Kafedra mudiri' : 'Admin';
    }
}

if (!function_exists('legacy_require_admin')) {
    function legacy_require_admin(bool $json = false): void
    {
        if (legacy_is_admin()) {
            return;
        }

        if ($json) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }
            echo json_encode([
                'success' => false,
                'message' => 'Ushbu bo‘lim faqat admin uchun.',
            ], JSON_UNESCAPED_UNICODE);
        } else {
            if (!headers_sent()) {
                http_response_code(403);
                header('Content-Type: text/html; charset=UTF-8');
            }
            echo 'Ushbu bo‘lim faqat admin uchun.';
        }

        exit;
    }
}

if (!function_exists('legacy_apply_kafedra_scope')) {
    function legacy_apply_kafedra_scope(array &$filters): void
    {
        if (!legacy_is_kafedra_mudiri()) {
            return;
        }

        $kafedraId = legacy_user_kafedra_id();
        if ($kafedraId > 0) {
            $filters['kafedra_id'] = $kafedraId;
        }
    }
}

if (!function_exists('legacy_resolve_requested_kafedra_id')) {
    function legacy_resolve_requested_kafedra_id(mixed $requestedKafedraId): int
    {
        if (legacy_is_kafedra_mudiri()) {
            return legacy_user_kafedra_id();
        }

        return is_numeric($requestedKafedraId) ? max(0, (int)$requestedKafedraId) : 0;
    }
}

if (!function_exists('legacy_current_kafedra_row')) {
    function legacy_current_kafedra_row(Database $db): ?array
    {
        $kafedraId = legacy_user_kafedra_id();
        if ($kafedraId <= 0) {
            return null;
        }

        $row = $db->get_data_by_table('kafedralar', ['id' => $kafedraId]);
        return !empty($row) ? $row : null;
    }
}

if (!function_exists('legacy_decode_formula_meta')) {
    function legacy_decode_formula_meta(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('legacy_qoshimcha_subtype_label')) {
    function legacy_qoshimcha_subtype_label(?int $qoshimchaDarsId, ?string $subtypeCode): string
    {
        $qoshimchaDarsId = (int)$qoshimchaDarsId;
        $subtypeCode = trim((string)$subtypeCode);

        if ($qoshimchaDarsId !== 16 || $subtypeCode === '') {
            return '';
        }

        $map = [
            'konsultatsiya' => 'Konsultatsiya',
            'yozma_ish' => 'Yozma ish',
            'bmi_rahbarligi' => 'BMI rahbarligi',
            'bmi_himoyasi' => 'BMI himoyasi',
        ];

        return $map[$subtypeCode] ?? $subtypeCode;
    }
}

if (!function_exists('legacy_qoshimcha_display_name')) {
    function legacy_qoshimcha_display_name(string $baseName, ?int $qoshimchaDarsId, ?string $subtypeCode): string
    {
        $label = legacy_qoshimcha_subtype_label($qoshimchaDarsId, $subtypeCode);
        if ($label === '') {
            return $baseName;
        }

        return trim($baseName) !== '' ? ($baseName . ' - ' . $label) : $label;
    }
}

if (!function_exists('legacy_is_scoped_taqsimot_soat_turi')) {
    function legacy_is_scoped_taqsimot_soat_turi(?string $soatTuri): bool
    {
        $soatTuri = trim((string)$soatTuri);
        return in_array($soatTuri, ['oraliq_nazorat', 'yakuniy_nazorat'], true);
    }
}

if (!function_exists('legacy_can_access_teacher')) {
    function legacy_can_access_teacher(Database $db, int $teacherId): bool
    {
        if ($teacherId <= 0) {
            return false;
        }

        if (!legacy_is_kafedra_mudiri()) {
            return true;
        }

        $teacher = $db->get_data_by_table('oqituvchilar', ['id' => $teacherId]);
        return !empty($teacher) && (int)($teacher['kafedra_id'] ?? 0) === legacy_user_kafedra_id();
    }
}

if (!function_exists('legacy_dashboard_relative_path')) {
    function legacy_dashboard_relative_path(): string
    {
        $basePath = realpath(__DIR__);
        $scriptPath = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $scriptReal = $scriptPath !== '' ? realpath($scriptPath) : false;

        if ($basePath && $scriptReal) {
            $basePrefix = rtrim(str_replace('\\', '/', $basePath), '/') . '/';
            $scriptNormalized = str_replace('\\', '/', $scriptReal);
            if (strpos($scriptNormalized, $basePrefix) === 0) {
                return substr($scriptNormalized, strlen($basePrefix));
            }
        }

        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if (preg_match('#/dashboard/([^?]+)#', $requestUri, $matches)) {
            return trim((string)$matches[1], '/');
        }

        return 'index.php';
    }
}

class Database{
    private $host = 'localhost';
    private $port = 3306;
    private $db_name = 'lm_db_laravel';
    private $username = 'root';
    private $password = '';
    private mysqli $link;
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

    private function isMasofaviyEducationForm(?string $form): bool
    {
        $value = trim((string)$form);
        if ($value === '') {
            return false;
        }

        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }

        return strpos($value, 'masof') !== false;
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
        // Izoh: Laravel users jadvalida role/kafedra/holat ustunlari bo'lmasa avtomatik qo'shamiz.
        $usersTableRes = mysqli_query($this->link, "SHOW TABLES LIKE 'users'");
        if ($usersTableRes && mysqli_num_rows($usersTableRes) > 0) {
            $userRoleColumn = mysqli_query($this->link, "SHOW COLUMNS FROM users LIKE 'role'");
            if ($userRoleColumn && mysqli_num_rows($userRoleColumn) === 0) {
                mysqli_query($this->link, "ALTER TABLE users ADD COLUMN role VARCHAR(50) NOT NULL DEFAULT 'admin' AFTER password");
            }

            $userKafedraColumn = mysqli_query($this->link, "SHOW COLUMNS FROM users LIKE 'kafedra_id'");
            if ($userKafedraColumn && mysqli_num_rows($userKafedraColumn) === 0) {
                mysqli_query($this->link, "ALTER TABLE users ADD COLUMN kafedra_id BIGINT UNSIGNED NULL AFTER role");
            }

            $userActiveColumn = mysqli_query($this->link, "SHOW COLUMNS FROM users LIKE 'is_active'");
            if ($userActiveColumn && mysqli_num_rows($userActiveColumn) === 0) {
                mysqli_query($this->link, "ALTER TABLE users ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER kafedra_id");
            }
        }
        // Izoh: Qo'shimcha fanlarda subtype va formula meta saqlash uchun ustunlar.
        $qoshimchaFanTableRes = mysqli_query($this->link, "SHOW TABLES LIKE 'qoshimcha_fanlar'");
        if ($qoshimchaFanTableRes && mysqli_num_rows($qoshimchaFanTableRes) > 0) {
            $subtypeCol = mysqli_query($this->link, "SHOW COLUMNS FROM qoshimcha_fanlar LIKE 'subtype_code'");
            if ($subtypeCol && mysqli_num_rows($subtypeCol) === 0) {
                mysqli_query($this->link, "ALTER TABLE qoshimcha_fanlar ADD COLUMN subtype_code VARCHAR(50) NULL AFTER qoshimcha_dars_id");
            }

            $formulaMetaCol = mysqli_query($this->link, "SHOW COLUMNS FROM qoshimcha_fanlar LIKE 'formula_meta'");
            if ($formulaMetaCol && mysqli_num_rows($formulaMetaCol) === 0) {
                mysqli_query($this->link, "ALTER TABLE qoshimcha_fanlar ADD COLUMN formula_meta LONGTEXT NULL AFTER subtype_code");
            }
        }
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
        // Izoh: Umumta'lim fan biriktirish endi yo'nalish emas, tanlangan guruhlar kesimida ham saqlanadi.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS umumtalim_fan_biriktirish_guruhlar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                biriktirish_id INT NOT NULL,
                source_fan_id INT NOT NULL,
                semestr_id INT NOT NULL,
                yonalish_id INT NOT NULL,
                guruh_id INT NOT NULL,
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_umumtalim_guruh (source_fan_id, guruh_id),
                INDEX idx_ufbg_biriktirish (biriktirish_id),
                INDEX idx_ufbg_semestr (semestr_id),
                INDEX idx_ufbg_yonalish (yonalish_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
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
        // Izoh: Chet tili guruhlarini qo'lda biriktirish natijasini alohida saqlaymiz.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS chet_tili_biriktirilgan_guruhlar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                semestr_id INT NOT NULL,
                yonalish_id INT NOT NULL,
                guruh_id INT NOT NULL,
                fan_id INT NOT NULL,
                talabalar_soni INT NOT NULL DEFAULT 0,
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                update_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_chet_tili_biriktirilgan (semestr_id, guruh_id, fan_id),
                INDEX idx_ctbg_semestr (semestr_id),
                INDEX idx_ctbg_yonalish (yonalish_id),
                INDEX idx_ctbg_fan (fan_id)
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
        // Izoh: Tanlov fan variantlariga yo'nalishdagi talabalarni taqsimlash.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS tanlov_fan_talablar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                semestr_id INT NOT NULL,
                yonalish_id INT NOT NULL,
                base_fan_id INT NOT NULL,
                variant_fan_id INT NOT NULL,
                talabalar_soni INT NOT NULL DEFAULT 0,
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                update_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_tanlov_fan_talab (semestr_id, base_fan_id, variant_fan_id),
                INDEX idx_tanlov_talab_base (base_fan_id),
                INDEX idx_tanlov_talab_variant (variant_fan_id),
                INDEX idx_tanlov_talab_yonalish (yonalish_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        // Izoh: Maxsus guruhlar ro'yxati va ular uchun alohida o'quv reja.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS maxsus_guruhlar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                yonalish_id INT NOT NULL,
                guruh_id INT NOT NULL,
                izoh TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                update_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_maxsus_guruh (yonalish_id, guruh_id),
                INDEX idx_maxsus_guruh_yonalish (yonalish_id),
                INDEX idx_maxsus_guruh_guruh (guruh_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS maxsus_oquv_rejalar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                fan_code VARCHAR(100) NOT NULL,
                fan_name VARCHAR(255) NOT NULL,
                kafedra_id INT NOT NULL,
                semestr_id INT NOT NULL,
                yonalish_id INT NOT NULL,
                guruh_id INT NOT NULL,
                izoh TEXT NULL,
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                update_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_maxsus_reja (semestr_id, guruh_id, fan_code, fan_name, kafedra_id),
                INDEX idx_maxsus_reja_kafedra (kafedra_id),
                INDEX idx_maxsus_reja_semestr (semestr_id),
                INDEX idx_maxsus_reja_yonalish (yonalish_id),
                INDEX idx_maxsus_reja_guruh (guruh_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS maxsus_oquv_reja_soatlar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                maxsus_reja_id INT NOT NULL,
                dars_tur_id INT NOT NULL,
                dars_soat DECIMAL(10,2) NOT NULL DEFAULT 0,
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                update_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_maxsus_reja_dars (maxsus_reja_id, dars_tur_id),
                INDEX idx_maxsus_reja_soat_reja (maxsus_reja_id),
                INDEX idx_maxsus_reja_soat_tur (dars_tur_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Izoh: Magistr/doktorant yozuvlari o'quv/ishchi rejaga kirmaydi, faqat yuklama va taqsimotda ko'rinadi.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS magistr_doktorant_yuklamalar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                semestr_id INT NOT NULL DEFAULT 0,
                turi VARCHAR(20) NOT NULL,
                kurs INT NOT NULL DEFAULT 1,
                kirish_yili INT NOT NULL DEFAULT 0,
                kod VARCHAR(100) NOT NULL,
                ism_familiya VARCHAR(255) NOT NULL,
                kafedra_id INT NOT NULL,
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                update_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_md_semestr (semestr_id),
                INDEX idx_md_kafedra (kafedra_id),
                INDEX idx_md_turi (turi)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $mdKursColumn = mysqli_query($this->link, "SHOW COLUMNS FROM magistr_doktorant_yuklamalar LIKE 'kurs'");
        if ($mdKursColumn && mysqli_num_rows($mdKursColumn) === 0) {
            mysqli_query($this->link, "ALTER TABLE magistr_doktorant_yuklamalar ADD COLUMN kurs INT NOT NULL DEFAULT 1 AFTER turi");
        }
        $mdKirishYiliColumn = mysqli_query($this->link, "SHOW COLUMNS FROM magistr_doktorant_yuklamalar LIKE 'kirish_yili'");
        if ($mdKirishYiliColumn && mysqli_num_rows($mdKirishYiliColumn) === 0) {
            mysqli_query($this->link, "ALTER TABLE magistr_doktorant_yuklamalar ADD COLUMN kirish_yili INT NOT NULL DEFAULT 0 AFTER kurs");
        }
        mysqli_query($this->link, "ALTER TABLE magistr_doktorant_yuklamalar MODIFY semestr_id INT NOT NULL DEFAULT 0");
        // Izoh: Magistr/doktorant qo'shimcha rejalari umumiy qo'shimcha o'quv rejadan alohida saqlanadi.
        mysqli_query($this->link, "
            CREATE TABLE IF NOT EXISTS magistr_doktorant_qoshimcha_rejalar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                magistr_doktorant_id INT NOT NULL,
                qoshimcha_dars_id INT NOT NULL,
                dars_soati DECIMAL(10,2) NOT NULL DEFAULT 0,
                izoh TEXT NULL,
                create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                update_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_mdqr_person (magistr_doktorant_id),
                INDEX idx_mdqr_dars (qoshimcha_dars_id)
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
        $hasTaqsimotlar = mysqli_query($this->link, "SHOW TABLES LIKE 'taqsimotlar'");
        if ($hasTaqsimotlar && mysqli_num_rows($hasTaqsimotlar) > 0) {
            $taqsimotSoatTuriCol = mysqli_query($this->link, "SHOW COLUMNS FROM taqsimotlar LIKE 'soat_turi'");
            if ($taqsimotSoatTuriCol && mysqli_num_rows($taqsimotSoatTuriCol) === 0) {
                mysqli_query($this->link, "ALTER TABLE taqsimotlar ADD COLUMN soat_turi VARCHAR(50) NULL AFTER type");
            }
        }
        $archiveSoatTuriCol = mysqli_query($this->link, "SHOW COLUMNS FROM taqsimotlar_archive LIKE 'soat_turi'");
        if ($archiveSoatTuriCol && mysqli_num_rows($archiveSoatTuriCol) === 0) {
            mysqli_query($this->link, "ALTER TABLE taqsimotlar_archive ADD COLUMN soat_turi VARCHAR(50) NULL AFTER type");
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

    private function sanitizeAuditValue(mixed $value): mixed
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

    private function encodeAuditPayload(mixed $payload): ?string
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

    public function query(string $query): mysqli_result|bool {
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
    public function get_data_by_table(string $table, array $arr, string $con = 'no'): ?array {
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
    public function get_data_by_table_all(string $table, string $con = 'no'): array {
        $sql = "SELECT * FROM ".$table;
        if ($con != 'no'){
            $sql .= " ".$con;
        }
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            if ($this->isMasofaviyEducationForm($row['oquv_shakli'] ?? '')) {
                $row['oraliq_nazorat'] = 0;
            }
            $data[] = $row;
        }
        return $data;
    }
    public function insert(string $table, array $arr): int {
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
    public function update(string $table, array $arr, string $con = 'no'): bool {
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

    public function delete(string $table, string $con = 'no'): bool {
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
            if ($this->isMasofaviyEducationForm($row['oquv_shakli'] ?? '')) {
                $row['oraliq_nazorat'] = 0;
            }
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
    public function get_oqtuvchi_total_hours(int $teacher_id): array {
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
    public function get_talaba_soni(int $semestr_id): ?array {
        $sql = "SELECT COALESCE(SUM(g.soni),0) AS talabalar_soni
        FROM guruhlar g
        WHERE g.yonalish_id = (
            SELECT yonalish_id FROM semestrlar WHERE id = $semestr_id
        );";
        $result = $this->query($sql);
        $data = mysqli_fetch_assoc($result);
        return $data;
    }
    public function get_taqsimot_by_teacher(int $oquvreja_id, string $type, string $soatTuri = ''): array {
        $sql = "SELECT t.id, t.teacher_id, t.soat as soat_soni, t.type, o.fio, o.lavozim, t.oquv_reja_id
        FROM `taqsimotlar` t 
        JOIN oqituvchilar o ON o.id = t.teacher_id
        WHERE t.oquv_reja_id=$oquvreja_id AND t.type='$type'";
        if (legacy_is_scoped_taqsimot_soat_turi($soatTuri)) {
            $safeSoatTuri = mysqli_real_escape_string($this->link, $soatTuri);
            $sql .= " AND COALESCE(t.soat_turi, '') = '{$safeSoatTuri}'";
        }
        if (legacy_is_kafedra_mudiri()) {
            $sql .= " AND o.kafedra_id = " . legacy_user_kafedra_id();
        }
        $sql .= ";";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }
    public function get_maxsus_oquv_reja_created_list($filters = []){
        $where = [];
        if (!empty($filters['kafedra_id'])) {
            $where[] = "mr.kafedra_id = " . (int)$filters['kafedra_id'];
        }
        if (!empty($filters['semestr_id'])) {
            $where[] = "mr.semestr_id = " . (int)$filters['semestr_id'];
        }
        if (!empty($filters['yonalish_id'])) {
            $where[] = "mr.yonalish_id = " . (int)$filters['yonalish_id'];
        }
        if (!empty($filters['guruh_id'])) {
            $where[] = "mr.guruh_id = " . (int)$filters['guruh_id'];
        }
        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT
                mr.id AS maxsus_reja_id,
                mr.fan_code,
                mr.fan_name,
                mr.kafedra_id,
                k.name AS kafedra_name,
                mr.semestr_id,
                s.semestr,
                mr.yonalish_id,
                y.name AS yonalish_name,
                y.code AS yonalish_code,
                mr.guruh_id,
                g.guruh_nomer,
                g.soni AS talabalar_soni,
                mr.izoh,
                mr.create_at
            FROM maxsus_oquv_rejalar mr
            JOIN semestrlar s ON s.id = mr.semestr_id
            JOIN yonalishlar y ON y.id = mr.yonalish_id
            JOIN guruhlar g ON g.id = mr.guruh_id
            LEFT JOIN kafedralar k ON k.id = mr.kafedra_id
            {$whereSql}
            ORDER BY s.semestr DESC, mr.id DESC
        ";
        $result = $this->query($sql);
        $rows = [];
        $ids = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['dars'] = [];
            $rows[] = $row;
            $ids[] = (int)($row['maxsus_reja_id'] ?? 0);
        }

        if (!empty($ids)) {
            $idSql = implode(',', array_map('intval', array_unique($ids)));
            $darsRes = $this->query("
                SELECT
                    maxsus_reja_id,
                    dars_tur_id,
                    SUM(dars_soat) AS soat
                FROM maxsus_oquv_reja_soatlar
                WHERE maxsus_reja_id IN ({$idSql})
                GROUP BY maxsus_reja_id, dars_tur_id
            ");
            $map = [];
            while ($darsRow = mysqli_fetch_assoc($darsRes)) {
                $rid = (int)($darsRow['maxsus_reja_id'] ?? 0);
                $turId = (int)($darsRow['dars_tur_id'] ?? 0);
                if ($rid <= 0 || $turId <= 0) {
                    continue;
                }
                if (!isset($map[$rid])) {
                    $map[$rid] = [];
                }
                $map[$rid][(string)$turId] = (float)($darsRow['soat'] ?? 0);
            }

            foreach ($rows as $index => $row) {
                $rid = (int)($row['maxsus_reja_id'] ?? 0);
                $rows[$index]['dars'] = $map[$rid] ?? [];
            }
        }

        return $rows;
    }
    public function get_maxsus_oquv_yuklamalar($filters = []){
        $limit = !empty($filters['limit']) ? max(1, (int)$filters['limit']) : 0;
        $where = [];

        if (!empty($filters['kafedra_id'])) {
            $where[] = "mr.kafedra_id = " . (int)$filters['kafedra_id'];
        }
        if (!empty($filters['yonalish_id'])) {
            $where[] = "mr.yonalish_id = " . (int)$filters['yonalish_id'];
        }
        if (!empty($filters['semestr'])) {
            $where[] = "s.semestr = " . (int)$filters['semestr'];
        } elseif (!empty($filters['oquv_yil_start'])) {
            $startYear = (int)$filters['oquv_yil_start'];
            $semType = !empty($filters['semestr_turi']) ? $filters['semestr_turi'] : '';
            $fallExpr = "GREATEST(1, LEAST(10, (({$startYear} - y.kirish_yili + 1) * 2) - 1))";
            $springExpr = "GREATEST(1, LEAST(10, (({$startYear} - y.kirish_yili + 1) * 2)))";
            if ($semType === 'fall') {
                $where[] = "s.semestr = {$fallExpr}";
            } elseif ($semType === 'spring') {
                $where[] = "s.semestr = {$springExpr}";
            } else {
                $where[] = "s.semestr IN ({$fallExpr}, {$springExpr})";
            }
        } elseif (!empty($filters['semestr_turi'])) {
            if ($filters['semestr_turi'] === 'fall') {
                $where[] = "MOD(s.semestr, 2) = 1";
            } elseif ($filters['semestr_turi'] === 'spring') {
                $where[] = "MOD(s.semestr, 2) = 0";
            }
        }
        if (!empty($filters['kurs'])) {
            $kurs = (int)$filters['kurs'];
            if ($kurs > 0) {
                $where[] = "FLOOR((s.semestr + 1)/2) = {$kurs}";
            }
        }

        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
        $limitSql = $limit > 0 ? "LIMIT {$limit}" : '';

        $sql = "
            SELECT
                mr.id AS maxsus_reja_id,
                mr.fan_name AS fan_name,
                1 AS is_maxsus,
                y.name AS talim_yonalishi,
                y.code AS yonalish_code,
                COALESCE(k.name, 'Kafedra belgilanmagan') AS kafedra_nomi,
                tsh.name AS oquv_shakli,
                s.semestr,
                FLOOR((s.semestr + 1)/2) AS kurs,
                g.guruh_nomer AS guruh_raqami,
                1 AS guruhlar_soni,
                COALESCE(g.soni, 0) AS talabalar_soni,
                1 AS patok_soni,
                1 AS kattaguruh_soni,
                1 AS kichikguruh_soni,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 1 THEN ms.dars_soat ELSE 0 END), 0) AS maruza_soat,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 2 THEN ms.dars_soat ELSE 0 END), 0) AS amaliy_soat,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 3 THEN ms.dars_soat ELSE 0 END), 0) AS laboratoriya_soat,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 4 THEN ms.dars_soat ELSE 0 END), 0) AS seminar_soat,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 1 THEN ms.dars_soat ELSE 0 END), 0) AS amalda_maruz,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 2 THEN ms.dars_soat ELSE 0 END), 0) AS amalda_amaliy,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 3 THEN ms.dars_soat ELSE 0 END), 0) AS amalda_lab,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 4 THEN ms.dars_soat ELSE 0 END), 0) AS amalda_seminar,
                (
                    COALESCE(SUM(CASE WHEN ms.dars_tur_id = 1 THEN ms.dars_soat ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN ms.dars_tur_id = 2 THEN ms.dars_soat ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN ms.dars_tur_id = 3 THEN ms.dars_soat ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN ms.dars_tur_id = 4 THEN ms.dars_soat ELSE 0 END), 0)
                ) AS jami_soat,
                '' AS biriktirilgan_yonalish_code,
                '' AS biriktirilgan_yonalishlar,
                0 AS is_birlashtirilgan
            FROM maxsus_oquv_rejalar mr
            JOIN semestrlar s ON s.id = mr.semestr_id
            JOIN yonalishlar y ON y.id = mr.yonalish_id
            JOIN guruhlar g ON g.id = mr.guruh_id
            JOIN talim_shakllar tsh ON tsh.id = y.talim_shakli_id
            LEFT JOIN kafedralar k ON k.id = mr.kafedra_id
            LEFT JOIN maxsus_oquv_reja_soatlar ms ON ms.maxsus_reja_id = mr.id
            {$whereSql}
            GROUP BY
                mr.id, mr.fan_name, y.name, y.code, k.name, tsh.name, s.semestr,
                g.guruh_nomer, g.soni
            ORDER BY s.semestr, mr.fan_name
            {$limitSql}
        ";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }
    public function get_maxsus_oquv_taqsimotlar($filters = []){
        $limit = !empty($filters['limit']) ? max(1, (int)$filters['limit']) : 0;
        $where = [];
        $having = '';

        if (!empty($filters['kafedra_id'])) {
            $where[] = "mr.kafedra_id = " . (int)$filters['kafedra_id'];
        }
        if (!empty($filters['yonalish_id'])) {
            $where[] = "mr.yonalish_id = " . (int)$filters['yonalish_id'];
        }
        if (!empty($filters['semestr'])) {
            $s = (int)$filters['semestr'];
            $pairStart = ($s % 2 === 0) ? $s - 1 : $s;
            $pairEnd = $pairStart + 1;
            $where[] = "s.semestr IN ({$pairStart}, {$pairEnd})";
        } elseif (!empty($filters['oquv_yil_start'])) {
            $startYear = (int)$filters['oquv_yil_start'];
            $fallExpr = "GREATEST(1, LEAST(10, (({$startYear} - y.kirish_yili + 1) * 2) - 1))";
            $springExpr = "GREATEST(1, LEAST(10, (({$startYear} - y.kirish_yili + 1) * 2)))";
            $where[] = "s.semestr IN ({$fallExpr}, {$springExpr})";
        }
        if (!empty($filters['maxsus_oquv_reja_id'])) {
            $rid = (int)$filters['maxsus_oquv_reja_id'];
            $having = "HAVING {$rid} IN (maruza_reja_id, amaliy_reja_id, laboratoriya_reja_id, seminar_reja_id)";
        }

        $whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';
        $limitSql = $limit > 0 ? "LIMIT {$limit}" : '';

        $sql = "
            SELECT
                COALESCE(MAX(CASE WHEN ms.dars_tur_id = 1 THEN ms.id END), 0) AS maruza_reja_id,
                COALESCE(MAX(CASE WHEN ms.dars_tur_id = 2 THEN ms.id END), 0) AS amaliy_reja_id,
                COALESCE(MAX(CASE WHEN ms.dars_tur_id = 3 THEN ms.id END), 0) AS laboratoriya_reja_id,
                COALESCE(MAX(CASE WHEN ms.dars_tur_id = 4 THEN ms.id END), 0) AS seminar_reja_id,
                1 AS is_maxsus,
                mr.yonalish_id,
                mr.fan_name AS fan_nomi,
                y.name AS talim_yonalishi,
                y.code AS yonalish_code,
                COALESCE(k.name, 'Kafedra belgilanmagan') AS kafedra_nomi,
                tsh.name AS oquv_shakli,
                s.semestr,
                FLOOR((s.semestr + 1)/2) AS kurs,
                g.guruh_nomer AS guruh_raqami,
                1 AS guruhlar_soni,
                COALESCE(g.soni, 0) AS talabalar_soni,
                1 AS patok_soni,
                1 AS kattaguruh_soni,
                1 AS kichikguruh_soni,
                EXISTS (
                    SELECT 1
                    FROM taqsimot_resync_events tre
                    WHERE tre.yonalish_id = mr.yonalish_id
                      AND tre.status = 'pending'
                ) AS needs_resync,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 1 THEN ms.dars_soat ELSE 0 END), 0) AS reja_maruz,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 2 THEN ms.dars_soat ELSE 0 END), 0) AS reja_amaliy,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 3 THEN ms.dars_soat ELSE 0 END), 0) AS reja_laboratoriya,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 4 THEN ms.dars_soat ELSE 0 END), 0) AS reja_seminar,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 1 THEN ms.dars_soat ELSE 0 END), 0) AS amalda_maruz,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 2 THEN ms.dars_soat ELSE 0 END), 0) AS amalda_amaliy,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 3 THEN ms.dars_soat ELSE 0 END), 0) AS amalda_laboratoriya,
                COALESCE(SUM(CASE WHEN ms.dars_tur_id = 4 THEN ms.dars_soat ELSE 0 END), 0) AS amalda_seminar,
                (
                    COALESCE(SUM(CASE WHEN ms.dars_tur_id = 1 THEN ms.dars_soat ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN ms.dars_tur_id = 2 THEN ms.dars_soat ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN ms.dars_tur_id = 3 THEN ms.dars_soat ELSE 0 END), 0)
                    + COALESCE(SUM(CASE WHEN ms.dars_tur_id = 4 THEN ms.dars_soat ELSE 0 END), 0)
                ) AS jami_soat,
                'M' AS taqsimot_type
            FROM maxsus_oquv_rejalar mr
            JOIN semestrlar s ON s.id = mr.semestr_id
            JOIN yonalishlar y ON y.id = mr.yonalish_id
            JOIN guruhlar g ON g.id = mr.guruh_id
            JOIN talim_shakllar tsh ON tsh.id = y.talim_shakli_id
            LEFT JOIN kafedralar k ON k.id = mr.kafedra_id
            LEFT JOIN maxsus_oquv_reja_soatlar ms ON ms.maxsus_reja_id = mr.id
            {$whereSql}
            GROUP BY
                mr.id, mr.yonalish_id, mr.fan_name, y.name, y.code, k.name, tsh.name,
                s.semestr, g.guruh_nomer, g.soni
            {$having}
            ORDER BY s.semestr, mr.fan_name
            {$limitSql}
        ";
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
        $filterYonalishBase = '';
        $filterSemestr = '';
        $filterSemestrBase = '';
        $filterSemestrLecture = '';
        $filterCurrentSemestr = '';
        $filterCurrentLecture = '';
        $filterSemestrType = '';
        $filterSemestrTypeBase = '';
        $filterSemestrTypeLecture = '';
        $filterOquvYil = '';
        $filterOquvYilBase = '';
        $filterOquvYilLecture = '';
        $filterKurs = '';
        $filterKursBase = '';
        $filterKursLecture = '';
        $filterVariantDept = '';
        if (!empty($filters['kafedra_id'])) {
            $kid = (int)$filters['kafedra_id'];
            $filterVariantDept = " AND fv.kafedra_id = {$kid}";
            $filterKafedraBase = " AND (
                (ctbg.variant_fan_id IS NOT NULL AND ctbg.kafedra_id = {$kid})
                OR (
                    ctbg.variant_fan_id IS NULL
                    AND (
                        (tft.variant_fan_id IS NOT NULL AND vf.kafedra_id = {$kid})
                        OR (
                            tft.variant_fan_id IS NULL
                            AND (
                                fr.kafedra_id = {$kid}
                                OR EXISTS (
                                    SELECT 1
                                    FROM ishchi_variant_dept ivd
                                    WHERE ivd.base_fan_id = fr.fan_id
                                      AND ivd.variant_kafedra_id = {$kid}
                                )
                            )
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM chet_tili_biriktirilgan_guruhlar bgk
                            JOIN fanlar fbgk ON fbgk.id = bgk.fan_id
                            JOIN semestrlar sbgk ON sbgk.id = bgk.semestr_id
                            WHERE sbgk.id = fr.semestr_id
                              AND fbgk.kafedra_id = {$kid}
                              AND fbgk.fan_code = fr.fan_code
                        )
                    )
                )
            )";
            $filterKafedraMerged = " AND k.id = {$kid}";
        }
        if (!empty($filters['yonalish_id'])) {
            $yid = (int)$filters['yonalish_id'];
            $filterYonalish = " AND y.id = {$yid}";
            $filterYonalishBase = " AND (
                (ctbg.variant_fan_id IS NOT NULL AND FIND_IN_SET({$yid}, ctbg.yonalish_ids))
                OR (ctbg.variant_fan_id IS NULL AND y.id = {$yid})
            )";
        }
        if (!empty($filters['semestr'])) {
            $sem = (int)$filters['semestr'];
            $filterSemestr = " AND s.semestr = {$sem}";
            $filterSemestrBase = " AND (
                (ctbg.variant_fan_id IS NOT NULL AND ctbg.semestr_num = {$sem})
                OR (ctbg.variant_fan_id IS NULL AND s.semestr = {$sem})
            )";
            $filterSemestrLecture = " AND ul.semestr = {$sem}";
        } elseif (!empty($filters['oquv_yil_start'])) {
            // Izoh: O'quv yili bo'yicha filter:
            // - semestr_turi berilsa mos (fall/spring) semestr;
            // - bo'sh bo'lsa shu o'quv yilidagi ikkala semestr (fall+spring).
            $startYear = (int)$filters['oquv_yil_start'];
            $semType = !empty($filters['semestr_turi']) ? $filters['semestr_turi'] : '';
            $fallExpr = "GREATEST(1, LEAST(10, (({$startYear} - y.kirish_yili + 1) * 2) - 1))";
            $springExpr = "GREATEST(1, LEAST(10, (({$startYear} - y.kirish_yili + 1) * 2)))";
            if ($semType === 'fall') {
                $filterOquvYil = " AND s.semestr = {$fallExpr}";
                $filterOquvYilBase = " AND (
                    (
                        ctbg.variant_fan_id IS NOT NULL
                        AND ctbg.semestr_num = GREATEST(1, LEAST(10, (({$startYear} - ctbg.kirish_yili + 1) * 2) - 1))
                    )
                    OR (ctbg.variant_fan_id IS NULL AND s.semestr = {$fallExpr})
                )";
            } elseif ($semType === 'spring') {
                $filterOquvYil = " AND s.semestr = {$springExpr}";
                $filterOquvYilBase = " AND (
                    (
                        ctbg.variant_fan_id IS NOT NULL
                        AND ctbg.semestr_num = GREATEST(1, LEAST(10, (({$startYear} - ctbg.kirish_yili + 1) * 2)))
                    )
                    OR (ctbg.variant_fan_id IS NULL AND s.semestr = {$springExpr})
                )";
            } else {
                $filterOquvYil = " AND s.semestr IN ({$fallExpr}, {$springExpr})";
                $filterOquvYilBase = " AND (
                    (
                        ctbg.variant_fan_id IS NOT NULL
                        AND ctbg.semestr_num IN (
                            GREATEST(1, LEAST(10, (({$startYear} - ctbg.kirish_yili + 1) * 2) - 1)),
                            GREATEST(1, LEAST(10, (({$startYear} - ctbg.kirish_yili + 1) * 2)))
                        )
                    )
                    OR (ctbg.variant_fan_id IS NULL AND s.semestr IN ({$fallExpr}, {$springExpr}))
                )";
            }
            // Izoh: Umumta'lim ma'ruza CTE ichida y mavjud, shu yerda filter ishlatiladi.
            $filterOquvYilLecture = '';
        } elseif (!empty($filters['semestr_turi'])) {
            // Izoh: Semestr turi bo'yicha filter (Kuzgi/Bahorgi).
            if ($filters['semestr_turi'] === 'fall') {
                $filterSemestrType = " AND MOD(s.semestr, 2) = 1";
                $filterSemestrTypeBase = " AND (
                    (ctbg.variant_fan_id IS NOT NULL AND MOD(ctbg.semestr_num, 2) = 1)
                    OR (ctbg.variant_fan_id IS NULL AND MOD(s.semestr, 2) = 1)
                )";
                $filterSemestrTypeLecture = " AND MOD(ul.semestr, 2) = 1";
            } elseif ($filters['semestr_turi'] === 'spring') {
                $filterSemestrType = " AND MOD(s.semestr, 2) = 0";
                $filterSemestrTypeBase = " AND (
                    (ctbg.variant_fan_id IS NOT NULL AND MOD(ctbg.semestr_num, 2) = 0)
                    OR (ctbg.variant_fan_id IS NULL AND MOD(s.semestr, 2) = 0)
                )";
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
                $filterKursBase = " AND (
                    (ctbg.variant_fan_id IS NOT NULL AND ctbg.kurs = {$kurs})
                    OR (ctbg.variant_fan_id IS NULL AND FLOOR((s.semestr + 1)/2) = {$kurs})
                )";
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
            fan_unassigned_guruh_agg AS (
                SELECT
                    f.id AS fan_id,
                    GROUP_CONCAT(g.guruh_nomer ORDER BY g.guruh_nomer SEPARATOR ' | ') AS guruh_raqami,
                    COUNT(g.id) AS guruhlar_soni,
                    SUM(g.soni) AS talabalar_soni
                FROM fanlar f
                JOIN semestrlar s ON s.id = f.semestr_id
                JOIN guruhlar g ON g.yonalish_id = s.yonalish_id
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM umumtalim_fan_biriktirish_guruhlar ubg
                    JOIN umumtalim_fan_biriktirish ub ON ub.id = ubg.biriktirish_id
                    WHERE ubg.source_fan_id = f.id
                      AND ubg.guruh_id = g.id
                      AND ub.semestr_id = f.semestr_id
                )
                GROUP BY f.id
            ),
            chet_tili_assignment_rows AS (
                SELECT
                    bg.semestr_id,
                    bg.yonalish_id,
                    bg.guruh_id,
                    bg.fan_id,
                    bg.talabalar_soni
                FROM chet_tili_biriktirilgan_guruhlar bg

                UNION ALL

                SELECT DISTINCT
                    t.semestr_id,
                    t.yonalish_id,
                    t.guruh_id,
                    t.fan_id,
                    t.talabalar_soni
                FROM chet_tili_guruhlar ct
                JOIN chet_tili_talablar t
                    ON t.semestr_id = ct.semestr_id
                   AND FIND_IN_SET(t.fan_id, REPLACE(COALESCE(ct.source_fan_ids, ''), ' ', ''))
                JOIN fanlar ft ON ft.id = t.fan_id
                JOIN semestrlar st ON st.id = t.semestr_id
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM chet_tili_biriktirilgan_guruhlar bgm
                    WHERE bgm.semestr_id = t.semestr_id
                      AND bgm.guruh_id = t.guruh_id
                      AND bgm.fan_id = t.fan_id
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM chet_tili_biriktirilgan_guruhlar bgm2
                    JOIN fanlar fm2 ON fm2.id = bgm2.fan_id
                    JOIN semestrlar sm2 ON sm2.id = bgm2.semestr_id
                    WHERE sm2.semestr = st.semestr
                      AND fm2.fan_code = ft.fan_code
                      AND COALESCE(fm2.kafedra_id, 0) = COALESCE(ft.kafedra_id, 0)
                )
            ),
            chet_tili_biriktirilgan_agg AS (
                SELECT
                    MIN(sct.id) AS semestr_id,
                    sct.semestr AS semestr_num,
                    FLOOR((sct.semestr + 1)/2) AS kurs,
                    MIN(vbm.base_fan_id) AS base_fan_id,
                    MIN(bg.fan_id) AS variant_fan_id,
                    fbg.fan_code,
                    LOWER(TRIM(MAX(fbg.fan_name))) AS fan_name_key,
                    GROUP_CONCAT(DISTINCT bg.fan_id ORDER BY bg.fan_id SEPARATOR ',') AS variant_fan_ids,
                    GROUP_CONCAT(DISTINCT y.id ORDER BY y.id SEPARATOR ',') AS yonalish_ids,
                    MIN(y.kirish_yili) AS kirish_yili,
                    MAX(fbg.kafedra_id) AS kafedra_id,
                    MAX(fbg.fan_name) AS variant_fan_name,
                    MAX(kbg.name) AS kafedra_nomi,
                    GROUP_CONCAT(DISTINCT y.code ORDER BY y.code SEPARATOR ', ') AS yonalish_code,
                    GROUP_CONCAT(
                        DISTINCT CONCAT(y.name, ' - ', y.kirish_yili)
                        ORDER BY y.code
                        SEPARATOR ' | '
                    ) AS talim_yonalishi,
                    GROUP_CONCAT(
                        DISTINCT CONCAT(y.name, ' / ', g.guruh_nomer)
                        ORDER BY y.name, g.guruh_nomer
                        SEPARATOR ' | '
                    ) AS guruh_raqami,
                    GREATEST(COUNT(DISTINCT bg.guruh_id), 1) AS guruhlar_soni,
                    SUM(bg.talabalar_soni) AS talabalar_soni,
                    GREATEST(
                        CASE
                            WHEN COALESCE(SUM(bg.talabalar_soni), 0) <= 23 THEN 1
                            ELSE CEIL((COALESCE(SUM(bg.talabalar_soni), 0) - 23) / 12) + 1
                        END,
                        1
                    ) AS kichikguruhlar_soni_12
                FROM chet_tili_assignment_rows bg
                JOIN fanlar fbg ON fbg.id = bg.fan_id
                JOIN semestrlar sct ON sct.id = bg.semestr_id
                LEFT JOIN kafedralar kbg ON kbg.id = fbg.kafedra_id
                LEFT JOIN (
                    SELECT
                        iv.fan_id AS variant_fan_id,
                        MIN(ir.base_fan_id) AS base_fan_id
                    FROM ishchi_oquv_reja_variants iv
                    JOIN ishchi_oquv_reja ir ON ir.id = iv.ishchi_reja_id
                    GROUP BY iv.fan_id
                ) vbm ON vbm.variant_fan_id = bg.fan_id
                JOIN guruhlar g ON g.id = bg.guruh_id
                JOIN yonalishlar y ON y.id = bg.yonalish_id
                GROUP BY
                    sct.semestr,
                    fbg.fan_code,
                    LOWER(TRIM(fbg.fan_name)),
                    COALESCE(fbg.kafedra_id, 0)
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
                WHERE 1=1
                $filterVariantDept
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
                    ub.id AS biriktirish_id,
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
            umumtalim_guruh_agg AS (
                SELECT
                    ub.umumtalim_fan_id,
                    ubg.yonalish_id,
                    s.semestr,
                    GROUP_CONCAT(DISTINCT g.guruh_nomer ORDER BY g.guruh_nomer SEPARATOR ' | ') AS guruh_raqami,
                    COUNT(DISTINCT g.id) AS guruhlar_soni,
                    SUM(g.soni) AS talabalar_soni
                FROM umumtalim_fan_biriktirish_guruhlar ubg
                JOIN umumtalim_fan_biriktirish ub ON ub.id = ubg.biriktirish_id
                JOIN semestrlar s ON s.id = ubg.semestr_id
                JOIN guruhlar g ON g.id = ubg.guruh_id
                GROUP BY ub.umumtalim_fan_id, ubg.yonalish_id, s.semestr
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
            umumtalim_amalda_soat AS (
                SELECT
                    ub.umumtalim_fan_id,
                    s.semestr,
                    SUM(
                        COALESCE(fr_src.amaliy_soat, fr_fb.amaliy_soat, fr_name.amaliy_soat, 0)
                        * COALESCE(uga.guruhlar_soni, ga.guruhlar_soni, y.kattaguruh_soni, 0)
                    ) AS amalda_amaliy,
                    SUM(
                        COALESCE(fr_src.laboratoriya_soat, fr_fb.laboratoriya_soat, fr_name.laboratoriya_soat, 0)
                        * COALESCE(uga.guruhlar_soni, ga.guruhlar_soni, y.kichikguruh_soni, 0)
                    ) AS amalda_lab,
                    SUM(
                        COALESCE(fr_src.seminar_soat, fr_fb.seminar_soat, fr_name.seminar_soat, 0)
                        * COALESCE(uga.guruhlar_soni, ga.guruhlar_soni, y.kattaguruh_soni, 0)
                    ) AS amalda_seminar
                FROM umumtalim_birik ub
                JOIN semestrlar s ON s.id = ub.semestr_id
                JOIN yonalishlar y ON y.id = ub.yonalish_id
                LEFT JOIN guruh_agg ga ON ga.yonalish_id = y.id
                LEFT JOIN umumtalim_guruh_agg uga
                    ON uga.umumtalim_fan_id = ub.umumtalim_fan_id
                   AND uga.yonalish_id = ub.yonalish_id
                   AND uga.semestr = s.semestr
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
                    COALESCE(GROUP_CONCAT(DISTINCT uga.guruh_raqami ORDER BY uga.guruh_raqami SEPARATOR ' | '), GROUP_CONCAT(DISTINCT ga.guruh_raqami ORDER BY ga.guruh_raqami SEPARATOR ' | ')) AS guruh_raqami,
                    COALESCE(SUM(uga.guruhlar_soni), SUM(COALESCE(ga.guruhlar_soni, 0))) AS guruhlar_soni,
                    COALESCE(SUM(uga.talabalar_soni), SUM(COALESCE(ga.talabalar_soni, 0))) AS talabalar_soni,
                    GROUP_CONCAT(DISTINCT y.code ORDER BY y.code SEPARATOR ', ') AS yonalish_code,
                    GROUP_CONCAT(DISTINCT CONCAT(y.name, ' - ', y.kirish_yili) ORDER BY y.code SEPARATOR ' | ') AS talim_yonalishi,
                    GROUP_CONCAT(DISTINCT tsh.name ORDER BY tsh.name SEPARATOR ' | ') AS oquv_shakli
                FROM umumtalim_birik ub
                JOIN semestrlar s ON s.id = ub.semestr_id
                JOIN yonalishlar y ON y.id = ub.yonalish_id
                JOIN talim_shakllar tsh ON tsh.id = y.talim_shakli_id
                LEFT JOIN guruh_agg ga ON ga.yonalish_id = y.id
                LEFT JOIN umumtalim_guruh_agg uga
                    ON uga.umumtalim_fan_id = ub.umumtalim_fan_id
                   AND uga.yonalish_id = ub.yonalish_id
                   AND uga.semestr = s.semestr
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
                    COALESCE(SUM(uga.guruhlar_soni), SUM(y.kattaguruh_soni)) AS kattaguruh_soni,
                    CASE
                        WHEN COUNT(uga.guruhlar_soni) > 0 THEN
                            GREATEST(
                                CASE
                                    WHEN COALESCE(SUM(uga.talabalar_soni), 0) <= 23 THEN 1
                                    ELSE CEIL((COALESCE(SUM(uga.talabalar_soni), 0) - 23) / 12) + 1
                                END,
                                1
                            )
                        ELSE COALESCE(SUM(y.kichikguruh_soni), 0)
                    END AS kichikguruh_soni
                FROM umumtalim_birik ub
                JOIN semestrlar s ON s.id = ub.semestr_id
                JOIN yonalishlar y ON y.id = ub.yonalish_id
                LEFT JOIN umumtalim_guruh_agg uga
                    ON uga.umumtalim_fan_id = ub.umumtalim_fan_id
                   AND uga.yonalish_id = ub.yonalish_id
                   AND uga.semestr = s.semestr
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
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN COALESCE(NULLIF(ctbg.variant_fan_name, ''), fr.fan_name)
                        WHEN tft.variant_fan_id IS NOT NULL THEN CONCAT(fr.fan_name, ' | ', COALESCE(vf.fan_name, 'Variant'))
                        ELSE COALESCE(NULLIF(ivi.variant_names, ''), fr.fan_name)
                    END AS fan_name,
                    COALESCE(NULLIF(ctbg.talim_yonalishi, ''), y.name) AS talim_yonalishi,
                    COALESCE(NULLIF(ctbg.yonalish_code, ''), y.code) AS yonalish_code,
                    COALESCE(NULLIF(ctbg.kafedra_nomi, ''), NULLIF(vk.name, ''), NULLIF(k.name, ''), NULLIF(ivi.kafedra_names, ''), 'Kafedra belgilanmagan') AS kafedra_nomi,
                    tsh.name AS oquv_shakli,
                    s.semestr,
                    FLOOR((s.semestr + 1)/2) AS kurs,

                    COALESCE(ctbg.guruh_raqami, ga.guruh_raqami) AS guruh_raqami,
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.guruhlar_soni
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE ga.guruhlar_soni
                    END AS guruhlar_soni,
                    COALESCE(ctbg.talabalar_soni, tft.talabalar_soni, ga.talabalar_soni) AS talabalar_soni,

                    CASE
                        WHEN tft.variant_fan_id IS NOT NULL THEN CEIL(tft.talabalar_soni / 120)
                        ELSE y.patok_soni
                    END AS patok_soni,
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.guruhlar_soni
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kattaguruh_soni
                    END AS kattaguruh_soni,
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.kichikguruhlar_soni_12
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kichikguruh_soni
                    END AS kichikguruh_soni,

                    fr.maruza_soat,
                    fr.amaliy_soat,
                    fr.laboratoriya_soat,
                    fr.seminar_soat,
                    fr.maruza_soat *
                    CASE
                        WHEN tft.variant_fan_id IS NOT NULL THEN CEIL(tft.talabalar_soni / 120)
                        ELSE y.patok_soni
                    END AS amalda_maruz,
                    fr.amaliy_soat *
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.guruhlar_soni
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kattaguruh_soni
                    END AS amalda_amaliy,
                    fr.laboratoriya_soat *
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.kichikguruhlar_soni_12
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kichikguruh_soni
                    END AS amalda_lab,
                    fr.seminar_soat *
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.guruhlar_soni
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kattaguruh_soni
                    END AS amalda_seminar,
                    fr.maruza_soat *
                    CASE
                        WHEN tft.variant_fan_id IS NOT NULL THEN CEIL(tft.talabalar_soni / 120)
                        ELSE y.patok_soni
                    END
                    + fr.amaliy_soat *
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.guruhlar_soni
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kattaguruh_soni
                    END
                    + fr.laboratoriya_soat *
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.kichikguruhlar_soni_12
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kichikguruh_soni
                    END
                    + fr.seminar_soat *
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.guruhlar_soni
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kattaguruh_soni
                    END
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
                LEFT JOIN tanlov_fan_talablar tft
                    ON tft.base_fan_id = fr.fan_id
                   AND tft.semestr_id = fr.semestr_id
                   AND tft.talabalar_soni > 0
                LEFT JOIN fanlar vf ON vf.id = tft.variant_fan_id
                LEFT JOIN kafedralar vk ON vk.id = vf.kafedra_id
                LEFT JOIN chet_tili_biriktirilgan_agg ctbg
                    ON ctbg.semestr_num = s.semestr
                   AND (
                        ctbg.base_fan_id = fr.fan_id
                        OR (ctbg.base_fan_id IS NULL AND FIND_IN_SET(fr.fan_id, ctbg.variant_fan_ids))
                        OR (
                            ctbg.base_fan_id IS NULL
                            AND ctbg.fan_code = fr.fan_code
                            AND COALESCE(ctbg.kafedra_id, 0) = COALESCE(fr.kafedra_id, 0)
                        )
                   )
                LEFT JOIN fan_unassigned_guruh_agg ga ON ga.fan_id = fr.fan_id
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM umumtalim_birik ub
                    WHERE (
                            ub.source_fan_id = fr.fan_id
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
                       AND NOT EXISTS (
                           SELECT 1
                           FROM umumtalim_fan_biriktirish_guruhlar ubg
                           WHERE ubg.biriktirish_id = ub.biriktirish_id
                       )
                )
                AND (
                    tft.variant_fan_id IS NOT NULL
                    OR NOT EXISTS (
                        SELECT 1
                        FROM tanlov_fan_talablar tft_any
                        WHERE tft_any.base_fan_id = fr.fan_id
                          AND tft_any.semestr_id = fr.semestr_id
                    )
                )
                AND (
                    ctbg.variant_fan_id IS NOT NULL
                    OR NOT EXISTS (
                        SELECT 1
                        FROM chet_tili_biriktirilgan_guruhlar bgx
                        JOIN fanlar fbg ON fbg.id = bgx.fan_id
                        LEFT JOIN (
                            SELECT
                                iv.fan_id AS variant_fan_id,
                                MIN(ir.base_fan_id) AS base_fan_id
                            FROM ishchi_oquv_reja_variants iv
                            JOIN ishchi_oquv_reja ir ON ir.id = iv.ishchi_reja_id
                            GROUP BY iv.fan_id
                        ) bgvm ON bgvm.variant_fan_id = bgx.fan_id
                        WHERE bgx.semestr_id = fr.semestr_id
                          AND (
                              fbg.fan_code = fr.fan_code
                              OR bgvm.base_fan_id = fr.fan_id
                          )
                    )
                )
                AND (
                    ctbg.variant_fan_id IS NOT NULL
                    OR ga.fan_id IS NOT NULL
                )
                AND (
                    ctbg.variant_fan_id IS NULL
                    OR ctbg.base_fan_id = fr.fan_id
                    OR (
                        ctbg.base_fan_id IS NULL
                        AND fr.fan_id = (
                        SELECT MIN(fm.id)
                        FROM fanlar fm
                        JOIN semestrlar sm ON sm.id = fm.semestr_id
                        WHERE sm.semestr = ctbg.semestr_num
                          AND fm.fan_code = ctbg.fan_code
                          AND COALESCE(fm.kafedra_id, 0) = COALESCE(ctbg.kafedra_id, 0)
                          AND (
                              ctbg.fan_name_key = ''
                              OR LOWER(TRIM(fm.fan_name)) = ctbg.fan_name_key
                          )
                        )
                    )
                )
                $filterKafedraBase
                $filterSemestrBase
                $filterYonalishBase
                $filterCurrentSemestr
                $filterSemestrTypeBase
                $filterOquvYilBase
                $filterKursBase

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
                    COALESCE(ufs.maruza_soat, 0) * COALESCE(uk.patok_soni, 1) AS amalda_maruz,
                    COALESCE(uas.amalda_amaliy, 0) AS amalda_amaliy,
                    COALESCE(uas.amalda_lab, 0) AS amalda_lab,
                    COALESCE(uas.amalda_seminar, 0) AS amalda_seminar,
                    (COALESCE(ufs.maruza_soat, 0) * COALESCE(uk.patok_soni, 1))
                    + COALESCE(uas.amalda_amaliy, 0)
                    + COALESCE(uas.amalda_lab, 0)
                    + COALESCE(uas.amalda_seminar, 0) AS jami_soat,
                    COALESCE(ubi.biriktirilgan_yonalish_code, '') AS biriktirilgan_yonalish_code,
                    COALESCE(ubi.biriktirilgan_yonalishlar, '') AS biriktirilgan_yonalishlar,
                    1 AS is_birlashtirilgan
                FROM umumtalim_lecture ul
                LEFT JOIN umumtalim_fan_soat ufs
                    ON ufs.umumtalim_fan_id = ul.umumtalim_fan_id
                   AND ufs.semestr = ul.semestr
                LEFT JOIN umumtalim_amalda_soat uas
                    ON uas.umumtalim_fan_id = ul.umumtalim_fan_id
                   AND uas.semestr = ul.semestr
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
            $fallExpr = "GREATEST(1, LEAST(10, (({$startYear} - y.kirish_yili + 1) * 2) - 1))";
            $springExpr = "GREATEST(1, LEAST(10, (({$startYear} - y.kirish_yili + 1) * 2)))";
            if ($semType === 'fall') {
                $where[] = "s.semestr = {$fallExpr}";
            } elseif ($semType === 'spring') {
                $where[] = "s.semestr = {$springExpr}";
            } else {
                $where[] = "s.semestr IN ({$fallExpr}, {$springExpr})";
            }
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
                qf.qoshimcha_dars_id,
                qf.subtype_code,
                qf.formula_meta,
                CASE
                    WHEN qdt.id = 1 THEN
                        CASE
                            WHEN TRIM(COALESCE(qf.fan_name, '')) <> '' THEN CONCAT(qf.fan_name, ' (Kurs ishi)')
                            ELSE qdt.name
                        END
                    WHEN qdt.id = 2 THEN
                        CASE
                            WHEN TRIM(COALESCE(qf.fan_name, '')) <> '' THEN CONCAT(qf.fan_name, ' (Kurs loyihasi)')
                            ELSE qdt.name
                        END
                    ELSE qdt.name
                END AS fan_nomi,
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

            ORDER BY
                s.semestr,
                qdt.name,
                CASE qf.subtype_code
                    WHEN 'bmi_rahbarligi' THEN 1
                    WHEN 'bmi_himoyasi' THEN 2
                    WHEN 'konsultatsiya' THEN 3
                    WHEN 'yozma_ish' THEN 4
                    ELSE 9
                END,
                qf.id
            {$limitSQL};
        ";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }

    public function get_magistr_doktorant_yuklamalar($filters = []){
        $limit = !empty($filters['limit']) ? max(1, (int)$filters['limit']) : 0;
        $where = [];
        if (!empty($filters['kafedra_id'])) {
            $where[] = "k.id = " . (int)$filters['kafedra_id'];
        }
        if (!empty($filters['yonalish_id'])) {
            $where[] = "1 = 0";
        }
        if (!empty($filters['semestr'])) {
            $where[] = "1 = 0";
        } elseif (!empty($filters['oquv_yil_start'])) {
            // Magistr/doktorant shajarasi o'quv yili/semestrga bog'lanmaydi.
        } elseif (!empty($filters['semestr_turi'])) {
            // Magistr/doktorant shajarasi semestr turiga bog'lanmaydi.
        }
        if (!empty($filters['kurs'])) {
            $kurs = (int)$filters['kurs'];
            if ($kurs > 0) {
                $where[] = "mdy.kurs = {$kurs}";
            }
        }
        $whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $limitSQL = $limit > 0 ? "LIMIT {$limit}" : '';

        $sql = "
            SELECT
                CONCAT(
                    mdy.kod,
                    ' - ',
                    mdy.ism_familiya,
                    ' (',
                    CASE WHEN mdy.turi = 'doktorant' THEN 'Doktorant' ELSE 'Magistr' END,
                    CASE WHEN mdy.kirish_yili > 0 THEN CONCAT(', ', mdy.kirish_yili) ELSE '' END,
                    ')'
                ) AS fan_nomi,
                '' AS talim_yonalishi,
                '' AS yonalish_code,
                k.name AS kafedra_nomi,
                '' AS oquv_shakli,
                0 AS semestr,
                mdy.kurs,
                '' AS guruh_raqami,
                0 AS guruhlar_soni,
                0 AS talabalar_soni,
                0 AS patok_soni,
                0 AS kattaguruh_soni,
                0 AS kichikguruh_soni,
                0 AS oraliq_nazorat,
                0 AS yakuniy_nazorat,
                0 AS kurs_ishi,
                0 AS kurs_loyiha,
                0 AS oquv_ped_amaliyot,
                0 AS uzluksiz_malakaviy,
                0 AS dala_amaliyoti_otm,
                0 AS dala_amaliyoti_tashqarida,
                0 AS ishlab_chiqarish,
                0 AS bmi_rahbarligi,
                CASE WHEN mdqr.qoshimcha_dars_id = 9 THEN mdqr.dars_soati ELSE 0 END AS ilmiy_tadqiqot_ishi,
                CASE WHEN mdqr.qoshimcha_dars_id = 10 THEN mdqr.dars_soati ELSE 0 END AS ilmiy_pedagogik_ishi,
                CASE WHEN mdqr.qoshimcha_dars_id = 11 THEN mdqr.dars_soati ELSE 0 END AS ilmiy_stajirovka,
                CASE WHEN mdqr.qoshimcha_dars_id = 12 THEN mdqr.dars_soati ELSE 0 END AS tayanch_doktorantura,
                CASE WHEN mdqr.qoshimcha_dars_id = 13 THEN mdqr.dars_soati ELSE 0 END AS katta_ilmiy_tadqiqotchi,
                CASE WHEN mdqr.qoshimcha_dars_id = 14 THEN mdqr.dars_soati ELSE 0 END AS stajyor_tadqiqotchi,
                0 AS ochiq_dars,
                0 AS yadak,
                0 AS boshqa_soatlar,
                mdqr.dars_soati AS jami_soat
            FROM magistr_doktorant_qoshimcha_rejalar mdqr
            JOIN magistr_doktorant_yuklamalar mdy ON mdy.id = mdqr.magistr_doktorant_id
            JOIN kafedralar k ON k.id = mdy.kafedra_id
            JOIN qoshimcha_dars_turlar qdt ON qdt.id = mdqr.qoshimcha_dars_id
            $whereSQL
            ORDER BY mdy.kurs, mdy.turi, mdy.ism_familiya, qdt.id
            {$limitSQL}
        ";
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }

    public function get_oqtuvchilar($filters = []){
        $sql = "SELECT o.*, f.name AS fakultet_name, k.name AS kafedra_name, iu.name AS ilmiy_unvon_name, isht.name AS ishtur_name, id.name AS ilmiy_daraja_name
        FROM `oqituvchilar` o
        JOIN fakultetlar f ON f.id=o.fakultet_id
        JOIN kafedralar k ON k.id=o.kafedra_id
        JOIN ilmiy_unvonlar iu ON iu.id=o.ilmiy_unvon_id
        JOIN ilmiy_darajalar id ON id.id=o.ilmiy_daraja_id
        JOIN ish_turlar isht ON isht.id=o.ishtur_id";
        $where = [];
        if (!empty($filters['fakultet_id'])) {
            $where[] = 'o.fakultet_id = ' . (int)$filters['fakultet_id'];
        }
        if (!empty($filters['kafedra_id'])) {
            $where[] = 'o.kafedra_id = ' . (int)$filters['kafedra_id'];
        }
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY o.fio';
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
        $filterOquvYilCte = '';
        $filterVariantDept = '';
        $fanRejaWhereSql = '';
        $filterRejaFanIds = [];
        if (!empty($filters['kafedra_id'])) {
            $kid = (int)$filters['kafedra_id'];
            $filterVariantDept = " AND fv.kafedra_id = {$kid}";
            $whereBase[] = "(
                (ctbg.variant_fan_id IS NOT NULL AND ctbg.kafedra_id = {$kid})
                OR (
                    ctbg.variant_fan_id IS NULL
                    AND (
                        (tft.variant_fan_id IS NOT NULL AND vf.kafedra_id = {$kid})
                        OR (
                            tft.variant_fan_id IS NULL
                            AND (
                                fr.kafedra_id = {$kid}
                                OR EXISTS (
                                    SELECT 1
                                    FROM ishchi_variant_dept ivd
                                    WHERE ivd.base_fan_id = fr.fan_id
                                      AND ivd.variant_kafedra_id = {$kid}
                                )
                            )
                        )
                        OR EXISTS (
                            SELECT 1
                            FROM chet_tili_biriktirilgan_guruhlar bgk
                            JOIN fanlar fbgk ON fbgk.id = bgk.fan_id
                            JOIN semestrlar sbgk ON sbgk.id = bgk.semestr_id
                            WHERE sbgk.id = fr.semestr_id
                              AND fbgk.kafedra_id = {$kid}
                              AND fbgk.fan_code = fr.fan_code
                        )
                    )
                )
            )";
            $whereMerged[] = "k.id = {$kid}";
        }
        if (!empty($filters['semestr'])) {
            $s = (int)$filters['semestr'];
            $pairStart = ($s % 2 === 0) ? $s - 1 : $s;
            $pairEnd = $pairStart + 1;
            $whereBase[] = "s.semestr IN ({$pairStart}, {$pairEnd})";
            $whereMerged[] = "ul.semestr IN ({$pairStart}, {$pairEnd})";
        } elseif (!empty($filters['oquv_yil_start'])) {
            $startYear = (int)$filters['oquv_yil_start'];
            $fallExpr = "GREATEST(1, LEAST(10, (({$startYear} - y.kirish_yili + 1) * 2) - 1))";
            $springExpr = "GREATEST(1, LEAST(10, (({$startYear} - y.kirish_yili + 1) * 2)))";
            $whereBase[] = "s.semestr IN ({$fallExpr}, {$springExpr})";
            $filterOquvYilCte = " AND s.semestr IN ({$fallExpr}, {$springExpr})";
        }
        if (!empty($filters['oquv_reja_id'])) {
            $rid = (int)$filters['oquv_reja_id'];
            $targetFanRes = mysqli_query($this->link, "
                SELECT f.id AS fan_id
                FROM oquv_rejalar r
                JOIN fanlar f ON f.id = r.fan_id
                WHERE r.id = {$rid}
                LIMIT 1
            ");
            if ($targetFanRes) {
                $targetFanRow = mysqli_fetch_assoc($targetFanRes);
                $targetFanId = (int)($targetFanRow['fan_id'] ?? 0);
                if ($targetFanId > 0) {
                    $filterRejaFanIds[$targetFanId] = true;

                    $baseRes = mysqli_query($this->link, "
                        SELECT ir.base_fan_id
                        FROM ishchi_oquv_reja_variants iv
                        JOIN ishchi_oquv_reja ir ON ir.id = iv.ishchi_reja_id
                        WHERE iv.fan_id = {$targetFanId}
                    ");
                    if ($baseRes) {
                        while ($baseRow = mysqli_fetch_assoc($baseRes)) {
                            $baseFanId = (int)($baseRow['base_fan_id'] ?? 0);
                            if ($baseFanId > 0) {
                                $filterRejaFanIds[$baseFanId] = true;
                            }
                        }
                    }

                    $variantRes = mysqli_query($this->link, "
                        SELECT iv.fan_id
                        FROM ishchi_oquv_reja ir
                        JOIN ishchi_oquv_reja_variants iv ON iv.ishchi_reja_id = ir.id
                        WHERE ir.base_fan_id IN (" . implode(',', array_map('intval', array_keys($filterRejaFanIds))) . ")
                    ");
                    if ($variantRes) {
                        while ($variantRow = mysqli_fetch_assoc($variantRes)) {
                            $variantFanId = (int)($variantRow['fan_id'] ?? 0);
                            if ($variantFanId > 0) {
                                $filterRejaFanIds[$variantFanId] = true;
                            }
                        }
                    }
                }
            }

            if (!empty($filterRejaFanIds)) {
                $fanRejaWhereSql = "WHERE f.id IN (" . implode(',', array_map('intval', array_keys($filterRejaFanIds))) . ")";
            }
            $whereBase[] = "(
                {$rid} IN (fr.maruza_reja_id, fr.amaliy_reja_id, fr.laboratoriya_reja_id, fr.seminar_reja_id)
                OR {$rid} IN (vfr.maruza_reja_id, vfr.amaliy_reja_id, vfr.laboratoriya_reja_id, vfr.seminar_reja_id)
            )";
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
                $fanRejaWhereSql
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
            fan_unassigned_guruh_agg AS (
                SELECT
                    f.id AS fan_id,
                    GROUP_CONCAT(g.guruh_nomer ORDER BY g.guruh_nomer SEPARATOR ' | ') AS guruh_raqami,
                    COUNT(g.id) AS guruhlar_soni,
                    SUM(g.soni) AS talabalar_soni
                FROM fanlar f
                JOIN semestrlar s ON s.id = f.semestr_id
                JOIN guruhlar g ON g.yonalish_id = s.yonalish_id
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM umumtalim_fan_biriktirish_guruhlar ubg
                    JOIN umumtalim_fan_biriktirish ub ON ub.id = ubg.biriktirish_id
                    WHERE ubg.source_fan_id = f.id
                      AND ubg.guruh_id = g.id
                      AND ub.semestr_id = f.semestr_id
                )
                GROUP BY f.id
            ),
            chet_tili_assignment_rows AS (
                SELECT
                    bg.semestr_id,
                    bg.yonalish_id,
                    bg.guruh_id,
                    bg.fan_id,
                    bg.talabalar_soni
                FROM chet_tili_biriktirilgan_guruhlar bg

                UNION ALL

                SELECT DISTINCT
                    t.semestr_id,
                    t.yonalish_id,
                    t.guruh_id,
                    t.fan_id,
                    t.talabalar_soni
                FROM chet_tili_guruhlar ct
                JOIN chet_tili_talablar t
                    ON t.semestr_id = ct.semestr_id
                   AND FIND_IN_SET(t.fan_id, REPLACE(COALESCE(ct.source_fan_ids, ''), ' ', ''))
                JOIN fanlar ft ON ft.id = t.fan_id
                JOIN semestrlar st ON st.id = t.semestr_id
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM chet_tili_biriktirilgan_guruhlar bgm
                    WHERE bgm.semestr_id = t.semestr_id
                      AND bgm.guruh_id = t.guruh_id
                      AND bgm.fan_id = t.fan_id
                )
                AND NOT EXISTS (
                    SELECT 1
                    FROM chet_tili_biriktirilgan_guruhlar bgm2
                    JOIN fanlar fm2 ON fm2.id = bgm2.fan_id
                    JOIN semestrlar sm2 ON sm2.id = bgm2.semestr_id
                    WHERE sm2.semestr = st.semestr
                      AND fm2.fan_code = ft.fan_code
                      AND COALESCE(fm2.kafedra_id, 0) = COALESCE(ft.kafedra_id, 0)
                )
            ),
            chet_tili_biriktirilgan_agg AS (
                SELECT
                    MIN(sct.id) AS semestr_id,
                    sct.semestr AS semestr_num,
                    MIN(vbm.base_fan_id) AS base_fan_id,
                    MIN(bg.fan_id) AS variant_fan_id,
                    fbg.fan_code,
                    LOWER(TRIM(MAX(fbg.fan_name))) AS fan_name_key,
                    GROUP_CONCAT(DISTINCT bg.fan_id ORDER BY bg.fan_id SEPARATOR ',') AS variant_fan_ids,
                    MAX(fbg.kafedra_id) AS kafedra_id,
                    MAX(fbg.fan_name) AS variant_fan_name,
                    MAX(kbg.name) AS kafedra_nomi,
                    GROUP_CONCAT(DISTINCT y.code ORDER BY y.code SEPARATOR ', ') AS yonalish_code,
                    GROUP_CONCAT(
                        DISTINCT CONCAT(y.name, ' - ', y.kirish_yili)
                        ORDER BY y.code
                        SEPARATOR ' | '
                    ) AS talim_yonalishi,
                    GROUP_CONCAT(
                        DISTINCT CONCAT(y.name, ' / ', g.guruh_nomer)
                        ORDER BY y.name, g.guruh_nomer
                        SEPARATOR ' | '
                    ) AS guruh_raqami,
                    GREATEST(COUNT(DISTINCT bg.guruh_id), 1) AS guruhlar_soni,
                    SUM(bg.talabalar_soni) AS talabalar_soni,
                    GREATEST(
                        CASE
                            WHEN COALESCE(SUM(bg.talabalar_soni), 0) <= 23 THEN 1
                            ELSE CEIL((COALESCE(SUM(bg.talabalar_soni), 0) - 23) / 12) + 1
                        END,
                        1
                    ) AS kichikguruhlar_soni_12
                FROM chet_tili_assignment_rows bg
                JOIN fanlar fbg ON fbg.id = bg.fan_id
                JOIN semestrlar sct ON sct.id = bg.semestr_id
                LEFT JOIN kafedralar kbg ON kbg.id = fbg.kafedra_id
                LEFT JOIN (
                    SELECT
                        iv.fan_id AS variant_fan_id,
                        MIN(ir.base_fan_id) AS base_fan_id
                    FROM ishchi_oquv_reja_variants iv
                    JOIN ishchi_oquv_reja ir ON ir.id = iv.ishchi_reja_id
                    GROUP BY iv.fan_id
                ) vbm ON vbm.variant_fan_id = bg.fan_id
                JOIN guruhlar g ON g.id = bg.guruh_id
                JOIN yonalishlar y ON y.id = bg.yonalish_id
                GROUP BY
                    sct.semestr,
                    fbg.fan_code,
                    LOWER(TRIM(fbg.fan_name)),
                    COALESCE(fbg.kafedra_id, 0)
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
                WHERE 1=1
                $filterVariantDept
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
                    ub.id AS biriktirish_id,
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
            umumtalim_guruh_agg AS (
                SELECT
                    ub.umumtalim_fan_id,
                    ubg.yonalish_id,
                    s.semestr,
                    GROUP_CONCAT(DISTINCT g.guruh_nomer ORDER BY g.guruh_nomer SEPARATOR ' | ') AS guruh_raqami,
                    COUNT(DISTINCT g.id) AS guruhlar_soni,
                    SUM(g.soni) AS talabalar_soni
                FROM umumtalim_fan_biriktirish_guruhlar ubg
                JOIN umumtalim_fan_biriktirish ub ON ub.id = ubg.biriktirish_id
                JOIN semestrlar s ON s.id = ubg.semestr_id
                JOIN guruhlar g ON g.id = ubg.guruh_id
                GROUP BY ub.umumtalim_fan_id, ubg.yonalish_id, s.semestr
            ),
            umumtalim_fan_soat AS (
                SELECT
                    ub.umumtalim_fan_id,
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
                GROUP BY ub.umumtalim_fan_id, ub.fan_name_key, ub.kafedra_id, s.semestr
            ),
            umumtalim_amalda_soat AS (
                SELECT
                    ub.umumtalim_fan_id,
                    ub.fan_name_key,
                    ub.kafedra_id,
                    s.semestr,
                    SUM(
                        COALESCE(fr_src.amaliy_soat, fr_fb.amaliy_soat, fr_name.amaliy_soat, 0)
                        * COALESCE(uga.guruhlar_soni, ga.guruhlar_soni, y.kattaguruh_soni, 0)
                    ) AS amalda_amaliy,
                    SUM(
                        COALESCE(fr_src.laboratoriya_soat, fr_fb.laboratoriya_soat, fr_name.laboratoriya_soat, 0)
                        * COALESCE(uga.guruhlar_soni, ga.guruhlar_soni, y.kichikguruh_soni, 0)
                    ) AS amalda_laboratoriya,
                    SUM(
                        COALESCE(fr_src.seminar_soat, fr_fb.seminar_soat, fr_name.seminar_soat, 0)
                        * COALESCE(uga.guruhlar_soni, ga.guruhlar_soni, y.kattaguruh_soni, 0)
                    ) AS amalda_seminar
                FROM umumtalim_birik ub
                JOIN semestrlar s ON s.id = ub.semestr_id
                JOIN yonalishlar y ON y.id = ub.yonalish_id
                LEFT JOIN guruh_agg ga ON ga.yonalish_id = y.id
                LEFT JOIN umumtalim_guruh_agg uga
                    ON uga.umumtalim_fan_id = ub.umumtalim_fan_id
                   AND uga.yonalish_id = ub.yonalish_id
                   AND uga.semestr = s.semestr
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
                GROUP BY ub.umumtalim_fan_id, ub.fan_name_key, ub.kafedra_id, s.semestr
            ),
            umumtalim_lecture AS (
                SELECT
                    ub.umumtalim_fan_id,
                    ub.fan_name_key,
                    MAX(ub.fan_name) AS fan_name,
                    ub.kafedra_id,
                    s.semestr,
                    FLOOR((s.semestr + 1)/2) AS kurs,
                    COALESCE(GROUP_CONCAT(DISTINCT uga.guruh_raqami ORDER BY uga.guruh_raqami SEPARATOR ' | '), GROUP_CONCAT(DISTINCT ga.guruh_raqami ORDER BY ga.guruh_raqami SEPARATOR ' | ')) AS guruh_raqami,
                    COALESCE(SUM(uga.guruhlar_soni), SUM(COALESCE(ga.guruhlar_soni, 0))) AS guruhlar_soni,
                    COALESCE(SUM(uga.talabalar_soni), SUM(COALESCE(ga.talabalar_soni, 0))) AS talabalar_soni,
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
                LEFT JOIN umumtalim_guruh_agg uga
                    ON uga.umumtalim_fan_id = ub.umumtalim_fan_id
                   AND uga.yonalish_id = ub.yonalish_id
                   AND uga.semestr = s.semestr
                WHERE 1=1
                $filterOquvYilCte
                GROUP BY ub.umumtalim_fan_id, ub.fan_name_key, ub.kafedra_id, s.semestr
            ),
            umumtalim_kopaytirgich AS (
                SELECT
                    ub.umumtalim_fan_id,
                    ub.fan_name_key,
                    ub.kafedra_id,
                    s.semestr,
                    1 AS patok_soni,
                    COALESCE(SUM(uga.guruhlar_soni), SUM(y.kattaguruh_soni)) AS kattaguruh_soni,
                    CASE
                        WHEN COUNT(uga.guruhlar_soni) > 0 THEN
                            GREATEST(
                                CASE
                                    WHEN COALESCE(SUM(uga.talabalar_soni), 0) <= 23 THEN 1
                                    ELSE CEIL((COALESCE(SUM(uga.talabalar_soni), 0) - 23) / 12) + 1
                                END,
                                1
                            )
                        ELSE COALESCE(SUM(y.kichikguruh_soni), 0)
                    END AS kichikguruh_soni
                FROM umumtalim_birik ub
                JOIN semestrlar s ON s.id = ub.semestr_id
                JOIN yonalishlar y ON y.id = ub.yonalish_id
                LEFT JOIN umumtalim_guruh_agg uga
                    ON uga.umumtalim_fan_id = ub.umumtalim_fan_id
                   AND uga.yonalish_id = ub.yonalish_id
                   AND uga.semestr = s.semestr
                WHERE 1=1
                $filterOquvYilCte
                GROUP BY ub.umumtalim_fan_id, ub.fan_name_key, ub.kafedra_id, s.semestr
            )
            SELECT *
            FROM (
                SELECT
                    CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.maruza_reja_id, fr.maruza_reja_id) ELSE fr.maruza_reja_id END AS maruza_reja_id,
                    CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.amaliy_reja_id, fr.amaliy_reja_id) ELSE fr.amaliy_reja_id END AS amaliy_reja_id,
                    CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.laboratoriya_reja_id, fr.laboratoriya_reja_id) ELSE fr.laboratoriya_reja_id END AS laboratoriya_reja_id,
                    CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.seminar_reja_id, fr.seminar_reja_id) ELSE fr.seminar_reja_id END AS seminar_reja_id,
                    fr.maruza_reja_id AS legacy_maruza_reja_id,
                    fr.amaliy_reja_id AS legacy_amaliy_reja_id,
                    fr.laboratoriya_reja_id AS legacy_laboratoriya_reja_id,
                    fr.seminar_reja_id AS legacy_seminar_reja_id,
                    CASE
                        WHEN tft.variant_fan_id IS NOT NULL
                         AND tft.variant_fan_id = (
                            SELECT MIN(tft_min.variant_fan_id)
                            FROM tanlov_fan_talablar tft_min
                            WHERE tft_min.base_fan_id = fr.fan_id
                              AND tft_min.semestr_id = fr.semestr_id
                              AND tft_min.talabalar_soni > 0
                         )
                        THEN 1 ELSE 0
                    END AS is_legacy_tanlov_owner,
                    y.id AS yonalish_id,
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN COALESCE(NULLIF(ctbg.variant_fan_name, ''), fr.fan_name)
                        WHEN tft.variant_fan_id IS NOT NULL THEN CONCAT(fr.fan_name, ' | ', COALESCE(vf.fan_name, 'Variant'))
                        ELSE COALESCE(NULLIF(ivi.variant_names, ''), fr.fan_name)
                    END AS fan_nomi,
                    COALESCE(NULLIF(ctbg.talim_yonalishi, ''), y.name) AS talim_yonalishi,
                    COALESCE(NULLIF(ctbg.yonalish_code, ''), y.code) AS yonalish_code,
                    COALESCE(NULLIF(ctbg.kafedra_nomi, ''), NULLIF(vk.name, ''), NULLIF(k.name, ''), NULLIF(ivi.kafedra_names, ''), 'Kafedra belgilanmagan') AS kafedra_nomi,
                    tsh.name AS oquv_shakli,
                    s.semestr,
                    FLOOR((s.semestr + 1) / 2) AS kurs,
                    COALESCE(ctbg.guruh_raqami, ga.guruh_raqami) AS guruh_raqami,
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.guruhlar_soni
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE ga.guruhlar_soni
                    END AS guruhlar_soni,
                    COALESCE(ctbg.talabalar_soni, tft.talabalar_soni, ga.talabalar_soni) AS talabalar_soni,
                    CASE
                        WHEN tft.variant_fan_id IS NOT NULL THEN CEIL(tft.talabalar_soni / 120)
                        ELSE y.patok_soni
                    END AS patok_soni,
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.guruhlar_soni
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kattaguruh_soni
                    END AS kattaguruh_soni,
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.kichikguruhlar_soni_12
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kichikguruh_soni
                    END AS kichikguruh_soni,
                    EXISTS (
                        SELECT 1
                        FROM taqsimot_resync_events tre
                        WHERE tre.yonalish_id = y.id
                          AND tre.status = 'pending'
                    ) AS needs_resync,
                    CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.maruza_soat, fr.maruza_soat) ELSE fr.maruza_soat END AS reja_maruz,
                    CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.amaliy_soat, fr.amaliy_soat) ELSE fr.amaliy_soat END AS reja_amaliy,
                    CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.laboratoriya_soat, fr.laboratoriya_soat) ELSE fr.laboratoriya_soat END AS reja_laboratoriya,
                    CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.seminar_soat, fr.seminar_soat) ELSE fr.seminar_soat END AS reja_seminar,
                    (CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.maruza_soat, fr.maruza_soat) ELSE fr.maruza_soat END) *
                    CASE
                        WHEN tft.variant_fan_id IS NOT NULL THEN CEIL(tft.talabalar_soni / 120)
                        ELSE y.patok_soni
                    END AS amalda_maruz,
                    (CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.amaliy_soat, fr.amaliy_soat) ELSE fr.amaliy_soat END) *
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.guruhlar_soni
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kattaguruh_soni
                    END AS amalda_amaliy,
                    (CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.laboratoriya_soat, fr.laboratoriya_soat) ELSE fr.laboratoriya_soat END) *
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.kichikguruhlar_soni_12
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kichikguruh_soni
                    END AS amalda_laboratoriya,
                    (CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.seminar_soat, fr.seminar_soat) ELSE fr.seminar_soat END) *
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.guruhlar_soni
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kattaguruh_soni
                    END AS amalda_seminar,
                    (CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.maruza_soat, fr.maruza_soat) ELSE fr.maruza_soat END) *
                    CASE
                        WHEN tft.variant_fan_id IS NOT NULL THEN CEIL(tft.talabalar_soni / 120)
                        ELSE y.patok_soni
                    END
                    + (CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.amaliy_soat, fr.amaliy_soat) ELSE fr.amaliy_soat END) *
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.guruhlar_soni
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kattaguruh_soni
                    END
                    + (CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.laboratoriya_soat, fr.laboratoriya_soat) ELSE fr.laboratoriya_soat END) *
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.kichikguruhlar_soni_12
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kichikguruh_soni
                    END
                    + (CASE WHEN tft.variant_fan_id IS NOT NULL THEN COALESCE(vfr.seminar_soat, fr.seminar_soat) ELSE fr.seminar_soat END) *
                    CASE
                        WHEN ctbg.variant_fan_id IS NOT NULL THEN ctbg.guruhlar_soni
                        WHEN tft.variant_fan_id IS NOT NULL THEN
                            CASE
                                WHEN COALESCE(tft.talabalar_soni, 0) <= 23 THEN 1
                                ELSE CEIL((COALESCE(tft.talabalar_soni, 0) - 23) / 12) + 1
                            END
                        ELSE y.kattaguruh_soni
                    END
                    AS jami_soat
                FROM fan_reja fr
                JOIN semestrlar s ON s.id = fr.semestr_id
                JOIN yonalishlar y ON y.id = s.yonalish_id
                LEFT JOIN kafedralar k ON k.id = fr.kafedra_id
                LEFT JOIN ishchi_variant_info ivi ON ivi.base_fan_id = fr.fan_id
                LEFT JOIN tanlov_fan_talablar tft
                    ON tft.base_fan_id = fr.fan_id
                   AND tft.semestr_id = fr.semestr_id
                   AND tft.talabalar_soni > 0
                LEFT JOIN fanlar vf ON vf.id = tft.variant_fan_id
                LEFT JOIN fan_reja vfr ON vfr.fan_id = tft.variant_fan_id
                LEFT JOIN kafedralar vk ON vk.id = vf.kafedra_id
                LEFT JOIN chet_tili_biriktirilgan_agg ctbg
                    ON ctbg.semestr_num = s.semestr
                   AND (
                        ctbg.base_fan_id = fr.fan_id
                        OR (ctbg.base_fan_id IS NULL AND FIND_IN_SET(fr.fan_id, ctbg.variant_fan_ids))
                        OR (
                            ctbg.base_fan_id IS NULL
                            AND ctbg.fan_code = fr.fan_code
                            AND COALESCE(ctbg.kafedra_id, 0) = COALESCE(fr.kafedra_id, 0)
                        )
                   )
                JOIN talim_shakllar tsh ON tsh.id = y.talim_shakli_id
                LEFT JOIN fan_unassigned_guruh_agg ga ON ga.fan_id = fr.fan_id
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM umumtalim_birik ub
                    WHERE (
                            ub.source_fan_id = fr.fan_id
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
                       AND NOT EXISTS (
                           SELECT 1
                           FROM umumtalim_fan_biriktirish_guruhlar ubg
                           WHERE ubg.biriktirish_id = ub.biriktirish_id
                       )
                )
                AND (
                    tft.variant_fan_id IS NOT NULL
                    OR NOT EXISTS (
                        SELECT 1
                        FROM tanlov_fan_talablar tft_any
                        WHERE tft_any.base_fan_id = fr.fan_id
                          AND tft_any.semestr_id = fr.semestr_id
                    )
                )
                AND (
                    ctbg.variant_fan_id IS NOT NULL
                    OR NOT EXISTS (
                        SELECT 1
                        FROM chet_tili_biriktirilgan_guruhlar bgx
                        JOIN fanlar fbg ON fbg.id = bgx.fan_id
                        LEFT JOIN (
                            SELECT
                                iv.fan_id AS variant_fan_id,
                                MIN(ir.base_fan_id) AS base_fan_id
                            FROM ishchi_oquv_reja_variants iv
                            JOIN ishchi_oquv_reja ir ON ir.id = iv.ishchi_reja_id
                            GROUP BY iv.fan_id
                        ) bgvm ON bgvm.variant_fan_id = bgx.fan_id
                        WHERE bgx.semestr_id = fr.semestr_id
                          AND (
                              fbg.fan_code = fr.fan_code
                              OR bgvm.base_fan_id = fr.fan_id
                          )
                    )
                )
                AND (
                    ctbg.variant_fan_id IS NOT NULL
                    OR ga.fan_id IS NOT NULL
                )
                AND (
                    ctbg.variant_fan_id IS NULL
                    OR ctbg.base_fan_id = fr.fan_id
                    OR (
                        ctbg.base_fan_id IS NULL
                        AND fr.fan_id = (
                        SELECT MIN(fm.id)
                        FROM fanlar fm
                        JOIN semestrlar sm ON sm.id = fm.semestr_id
                        WHERE sm.semestr = ctbg.semestr_num
                          AND fm.fan_code = ctbg.fan_code
                          AND COALESCE(fm.kafedra_id, 0) = COALESCE(ctbg.kafedra_id, 0)
                          AND (
                              ctbg.fan_name_key = ''
                              OR LOWER(TRIM(fm.fan_name)) = ctbg.fan_name_key
                          )
                        )
                    )
                )
                $whereSQLBase

                UNION ALL

                SELECT
                    ufs.maruza_reja_id,
                    ufs.amaliy_reja_id,
                    ufs.laboratoriya_reja_id,
                    ufs.seminar_reja_id,
                    0 AS legacy_maruza_reja_id,
                    0 AS legacy_amaliy_reja_id,
                    0 AS legacy_laboratoriya_reja_id,
                    0 AS legacy_seminar_reja_id,
                    0 AS is_legacy_tanlov_owner,
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
                    COALESCE(ufs.maruza_soat, 0) * COALESCE(uk.patok_soni, 1) AS amalda_maruz,
                    COALESCE(uas.amalda_amaliy, 0) AS amalda_amaliy,
                    COALESCE(uas.amalda_laboratoriya, 0) AS amalda_laboratoriya,
                    COALESCE(uas.amalda_seminar, 0) AS amalda_seminar,
                    (COALESCE(ufs.maruza_soat, 0) * COALESCE(uk.patok_soni, 1))
                    + COALESCE(uas.amalda_amaliy, 0)
                    + COALESCE(uas.amalda_laboratoriya, 0)
                    + COALESCE(uas.amalda_seminar, 0) AS jami_soat
                FROM umumtalim_lecture ul
                LEFT JOIN umumtalim_fan_soat ufs
                    ON ufs.umumtalim_fan_id = ul.umumtalim_fan_id
                   AND ufs.fan_name_key = ul.fan_name_key
                   AND ufs.kafedra_id = ul.kafedra_id
                   AND ufs.semestr = ul.semestr
                LEFT JOIN umumtalim_amalda_soat uas
                    ON uas.umumtalim_fan_id = ul.umumtalim_fan_id
                   AND uas.fan_name_key = ul.fan_name_key
                   AND uas.kafedra_id = ul.kafedra_id
                   AND uas.semestr = ul.semestr
                LEFT JOIN umumtalim_kopaytirgich uk
                    ON uk.umumtalim_fan_id = ul.umumtalim_fan_id
                   AND uk.fan_name_key = ul.fan_name_key
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
        } elseif (!empty($filters['oquv_yil_start'])) {
            $startYear = (int)$filters['oquv_yil_start'];
            $fallExpr = "GREATEST(1, LEAST(10, (({$startYear} - y.kirish_yili + 1) * 2) - 1))";
            $springExpr = "GREATEST(1, LEAST(10, (({$startYear} - y.kirish_yili + 1) * 2)))";
            $where[] = "s.semestr IN ({$fallExpr}, {$springExpr})";
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
                qf.qoshimcha_dars_id,
                qf.subtype_code,
                qf.formula_meta,
                y.name AS talim_yonalishi,
                y.code AS yonalish_code,
                k.name AS kafedra_nomi,
                tsh.name AS oquv_shakli,
                s.semestr,
                FLOOR((s.semestr + 1)/2) AS kurs,

                ga.guruh_raqami,
                ga.guruhlar_soni,
                ga.talabalar_soni,
                CASE
                    WHEN LOWER(COALESCE(ga.guruh_raqami, '')) LIKE '%iqtidor%' THEN 0
                    WHEN qf.qoshimcha_dars_id = 20 THEN q.dars_soati
                    ELSE 0
                END AS oraliq_nazorat,
                CASE
                    WHEN LOWER(COALESCE(ga.guruh_raqami, '')) LIKE '%iqtidor%' THEN 0
                    WHEN qf.qoshimcha_dars_id = 21 THEN q.dars_soati
                    ELSE 0
                END AS yakuniy_nazorat,
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
            ORDER BY
                s.semestr,
                CASE qf.subtype_code
                    WHEN 'bmi_rahbarligi' THEN 1
                    WHEN 'bmi_himoyasi' THEN 2
                    WHEN 'konsultatsiya' THEN 3
                    WHEN 'yozma_ish' THEN 4
                    ELSE 9
                END,
                q.id
            {$limitSQL};
        ";
        
        $result = $this->query($sql);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;

    }
    public function get_magistr_doktorant_taqsimotlar($filters = []){
        $limit = !empty($filters['limit']) ? max(1, (int)$filters['limit']) : 0;
        $where = [];
        if (!empty($filters['kafedra_id'])) {
            $where[] = "k.id = " . (int)$filters['kafedra_id'];
        }
        if (!empty($filters['semestr'])) {
            $where[] = "s.semestr = " . (int)$filters['semestr'];
        }
        if (!empty($filters['yonalish_id'])) {
            $where[] = "y.id = " . (int)$filters['yonalish_id'];
        }
        $whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
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
                0 AS qoshimcha_reja_id,
                y.id AS yonalish_id,
                CONCAT(mdy.kod, ' - ', mdy.ism_familiya, ' (', CASE WHEN mdy.turi = 'doktorant' THEN 'Doktorant' ELSE 'Magistr' END, ')') AS fan_nomi,
                y.name AS talim_yonalishi,
                y.code AS yonalish_code,
                k.name AS kafedra_nomi,
                tsh.name AS oquv_shakli,
                s.semestr,
                FLOOR((s.semestr + 1)/2) AS kurs,
                COALESCE(ga.guruh_raqami, '') AS guruh_raqami,
                COALESCE(ga.guruhlar_soni, 0) AS guruhlar_soni,
                COALESCE(ga.talabalar_soni, 0) AS talabalar_soni,
                y.patok_soni,
                y.kattaguruh_soni,
                y.kichikguruh_soni,
                0 AS needs_resync,
                0 AS oraliq_nazorat,
                0 AS yakuniy_nazorat,
                0 AS kurs_ishi,
                0 AS kurs_loyiha,
                0 AS oquv_ped_amaliyot,
                0 AS uzluksiz_malakaviy,
                0 AS dala_amaliyoti_otm,
                0 AS dala_amaliyoti_tashqarida,
                0 AS ishlab_chiqarish,
                0 AS bmi_rahbarligi,
                0 AS ilmiy_tadqiqot_ishi,
                0 AS ilmiy_pedagogik_ishi,
                0 AS ilmiy_stajirovka,
                0 AS tayanch_doktorantura,
                0 AS katta_ilmiy_tadqiqotchi,
                0 AS stajyor_tadqiqotchi,
                0 AS ochiq_dars,
                0 AS yadak,
                0 AS boshqa_soatlar,
                0 AS jami_soat
            FROM magistr_doktorant_yuklamalar mdy
            JOIN semestrlar s ON s.id = mdy.semestr_id
            JOIN yonalishlar y ON y.id = s.yonalish_id
            JOIN talim_shakllar tsh ON tsh.id = y.talim_shakli_id
            JOIN kafedralar k ON k.id = mdy.kafedra_id
            LEFT JOIN guruh_agg ga ON ga.yonalish_id = y.id
            $whereSQL
            ORDER BY s.semestr, mdy.turi, mdy.ism_familiya
            {$limitSQL}
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
            $isMasofaviy = $this->isMasofaviyEducationForm($row['oquv_shakli'] ?? '');
            $guruhRaqami = trim((string)($row['guruh_raqami'] ?? ''));
            $guruhRaqamiLower = function_exists('mb_strtolower')
                ? (string)@mb_strtolower($guruhRaqami, 'UTF-8')
                : strtolower($guruhRaqami);
            $isIqtidorliYokiMaxsus = (!empty($row['is_maxsus']) || strpos($guruhRaqamiLower, 'iqtidor') !== false);

            if ($isIqtidorliYokiMaxsus) {
                $row['oraliq_nazorat'] = 0;
                $row['yakuniy_nazorat'] = 0;
            } else {
                if (($row['oraliq_nazorat'] ?? 0) <= 0 && $talaba > 0) {
                    if ($isMasofaviy) {
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
                    $row['yakuniy_nazorat'] = round($talaba * 0.3);
                }
            }

            if (($row['kurs_ishi'] ?? 0) <= 0 && $talaba > 0) {
                $row['kurs_ishi'] = round($talaba * 2.4);
            }
            if (($row['kurs_loyiha'] ?? 0) <= 0 && $talaba > 0) {
                $row['kurs_loyiha'] = round($talaba * 3.6);
            }
            if (($row['uzluksiz_malakaviy'] ?? 0) <= 0 && $talaba > 0) {
                $row['uzluksiz_malakaviy'] = round($talaba * ($isMasofaviy ? 0.4 : 2));
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
