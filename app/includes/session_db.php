<?php
declare(strict_types=1);

/**
 * Penyimpanan sesi PHP di database. Wajib di Vercel: tiap request bisa dilayani instans
 * berbeda dengan disk sementara, sehingga sesi berbasis file akan hilang.
 * Tabel: php_sessions (lihat database/schema.sql).
 */
final class DbSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function __construct(private PDO $pdo, private int $lifetime = 86400) {}

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $st = $this->pdo->prepare('SELECT data FROM php_sessions WHERE id = ? AND expires_at > ?');
        $st->execute([$id, time()]);
        $d = $st->fetchColumn();
        return $d === false || $d === null ? '' : (string)$d;
    }

    public function write(string $id, string $data): bool
    {
        $this->pdo->prepare('REPLACE INTO php_sessions (id, data, expires_at) VALUES (?, ?, ?)')
            ->execute([$id, $data, time() + $this->lifetime]);
        return true;
    }

    public function destroy(string $id): bool
    {
        $this->pdo->prepare('DELETE FROM php_sessions WHERE id = ?')->execute([$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->pdo->exec('DELETE FROM php_sessions WHERE expires_at < ' . time());
    }

    public function validateId(string $id): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM php_sessions WHERE id = ? AND expires_at > ?');
        $st->execute([$id, time()]);
        return (bool)$st->fetchColumn();
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }
}

function use_db_sessions(): bool
{
    $v = env('SESSION_DRIVER');
    return $v !== null ? $v === 'db' : (bool)getenv('VERCEL');
}
