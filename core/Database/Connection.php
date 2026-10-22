<?php

declare(strict_types=1);

namespace Mphp\Database;

use Closure;
use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * PDO wrapper. Every value goes through bound parameters, so SQL injection is
 * impossible as long as you never concatenate user input into the SQL string.
 */
final class Connection
{
    private ?PDO $pdo = null;

    /** Filled while the query log is on (debug bar): every statement with its duration in ms. @var list<array{sql: string, bindings: list<mixed>, ms: float}> */
    public private(set) array $queries = [];

    private bool $logging = false;

    /** @param array{driver: string, host?: string, port?: int|string, database?: string, username?: string, password?: string} $config */
    public function __construct(private readonly array $config) {}

    public function driver(): string
    {
        return $this->config['driver'];
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= new PDO(
            $this->dsn(),
            $this->config['username'] ?? null,
            $this->config['password'] ?? null,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }

    /**
     * @param list<mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public function select(string $sql, array $bindings = []): array
    {
        $start = hrtime(true);
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($bindings);
        $rows = $statement->fetchAll();
        $this->record($sql, $bindings, $start);

        return $rows;
    }

    public function enableQueryLog(): void
    {
        $this->logging = true;
        $this->queries = [];
    }

    /** @param list<mixed> $bindings @return array<string, mixed>|null */
    public function first(string $sql, array $bindings = []): ?array
    {
        return $this->select($sql, $bindings)[0] ?? null;
    }

    /**
     * Run INSERT/UPDATE/DELETE/DDL. Returns the affected row count.
     *
     * @param list<mixed> $bindings
     */
    public function statement(string $sql, array $bindings = []): int
    {
        $start = hrtime(true);
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($bindings);
        $this->record($sql, $bindings, $start);

        return $statement->rowCount();
    }

    /** @param list<mixed> $bindings */
    private function record(string $sql, array $bindings, int $start): void
    {
        if ($this->logging) {
            $this->queries[] = ['sql' => $sql, 'bindings' => $bindings, 'ms' => (hrtime(true) - $start) / 1e6];
        }
    }

    public function lastInsertId(): string
    {
        return $this->pdo()->lastInsertId();
    }

    /** @template T @param Closure(self): T $callback @return T */
    public function transaction(Closure $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();

        try {
            $result = $callback($this);
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @param (Closure(array<string, mixed>): mixed)|null $hydrate */
    public function table(string $table, ?Closure $hydrate = null): QueryBuilder
    {
        return new QueryBuilder($this, $table, $hydrate);
    }

    /** Quote a table/column name (letters, digits, underscores, optional table.column). */
    public function quoteIdentifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $identifier)) {
            throw new InvalidArgumentException("Invalid SQL identifier [$identifier].");
        }

        $quote = $this->driver() === 'mysql' ? '`' : '"';

        return implode('.', array_map(static fn(string $part): string => $quote . $part . $quote, explode('.', $identifier)));
    }

    /** Portable auto-increment primary key definition for migrations. */
    public function idColumn(string $name = 'id'): string
    {
        $column = $this->quoteIdentifier($name);

        return match ($this->driver()) {
            'mysql' => "$column BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY",
            'pgsql' => "$column BIGSERIAL PRIMARY KEY",
            default => "$column INTEGER PRIMARY KEY AUTOINCREMENT",
        };
    }

    private function dsn(): string
    {
        $c = $this->config;

        return match ($c['driver']) {
            'sqlite' => 'sqlite:' . ($c['database'] ?? ':memory:'),
            'mysql' => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $c['host'] ?? '127.0.0.1', $c['port'] ?? 3306, $c['database'] ?? ''),
            'pgsql' => sprintf('pgsql:host=%s;port=%s;dbname=%s', $c['host'] ?? '127.0.0.1', $c['port'] ?? 5432, $c['database'] ?? ''),
            default => throw new InvalidArgumentException("Unsupported database driver [{$c['driver']}]. Use sqlite, mysql or pgsql."),
        };
    }
}
