<?php
declare(strict_types=1);

/**
 * PDO untuk SQLite (DB_DRIVER=sqlite). Menerjemahkan sebagian kecil sintaks MySQL yang dipakai
 * aplikasi (NOW(), INTERVAL, DATE_FORMAT, LEFT, CAST AS UNSIGNED, FOR UPDATE) agar kode lain tidak berubah.
 * Hanya untuk demo: di Vercel berkas disimpan di /tmp (sementara dan terpisah per instans).
 */
class SqlitePDO extends PDO
{
    public static function translate(string $sql): string
    {
        $now = "datetime('now','+7 hours')";
        $sql = preg_replace_callback(
            '/NOW\(\)\s*-\s*INTERVAL\s+(\d+)\s+(MINUTE|HOUR|DAY)S?/i',
            fn($m) => "datetime('now','+7 hours','-{$m[1]} " . strtolower($m[2]) . "s')",
            $sql
        );
        $sql = preg_replace('/DATE_FORMAT\(((?:[^(),]|\([^()]*\))+),\s*(\'[^\']*\')\)/i', 'strftime($2, $1)', $sql);
        $sql = preg_replace('/\bLEFT\(((?:[^(),]|\([^()]*\))+),\s*(\d+)\)/i', 'substr($1, 1, $2)', $sql);
        $sql = preg_replace('/AS\s+UNSIGNED\b/i', 'AS INTEGER', $sql);
        $sql = preg_replace('/\s+FOR\s+UPDATE\b/i', '', $sql);
        return preg_replace('/\bNOW\(\)/i', $now, $sql);
    }

    #[\ReturnTypeWillChange]
    public function prepare(string $query, array $options = [])
    {
        return parent::prepare(self::translate($query), $options);
    }

    #[\ReturnTypeWillChange]
    public function exec(string $statement)
    {
        return parent::exec(self::translate($statement));
    }

    #[\ReturnTypeWillChange]
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs)
    {
        $query = self::translate($query);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}

/** Koneksi PDO (prepared statements asli, error sebagai exception). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if (strtolower((string)env('DB_DRIVER', 'mysql')) === 'sqlite') {
        $file = env('SQLITE_PATH') ?: sys_get_temp_dir() . '/rakit.sqlite';
        $pdo = new SqlitePDO('sqlite:' . $file, null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $has = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'posyandu'")->fetchColumn();
        if (!$has) {
            $pdo->exec((string)file_get_contents(__DIR__ . '/../database/schema_sqlite.sql'));
        }
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        env('DB_HOST', '127.0.0.1'),
        env('DB_PORT', '3306'),
        env('DB_NAME', 'kader_cukup_foto')
    );
    // Database hosting (TiDB Cloud, PlanetScale, Aiven, dll.) mewajibkan TLS: DB_SSL=true
    if (in_array(strtolower((string)env('DB_SSL', 'false')), ['true', '1'], true)) {
        $opts[PDO::MYSQL_ATTR_SSL_CA] = env('DB_SSL_CA') ?: __DIR__ . '/cacert.pem';
        $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), $opts);
    $pdo->exec("SET time_zone = '+07:00'");
    return $pdo;
}
