<?php

declare(strict_types=1);

namespace Mphp\Database;

use Closure;
use InvalidArgumentException;
use LogicException;

/**
 * Fluent, injection-safe query builder:
 *   $db->table('names')->where('id', 3)->first();
 *   $db->table('names')->where('age', '>=', 18)->orderBy('name')->limit(10)->get();
 * Identifiers are validated + quoted, values are always bound parameters.
 */
final class QueryBuilder
{
    private const OPERATORS = ['=', '<', '>', '<=', '>=', '<>', '!=', 'like', 'not like'];

    /** @var list<string> */
    private array $columns = ['*'];

    /** @var list<array{0: string, 1: string}> [boolean, sql] */
    private array $wheres = [];

    /** @var list<mixed> */
    private array $bindings = [];

    /** @var list<string> */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    /** @param (Closure(array<string, mixed>): mixed)|null $hydrate */
    public function __construct(
        private readonly Connection $connection,
        private readonly string $table,
        private readonly ?Closure $hydrate = null,
    ) {}

    public function select(string ...$columns): self
    {
        $this->columns = $columns ?: ['*'];

        return $this;
    }

    /** where('id', 3) is "id = 3"; where('age', '>=', 18) picks the operator. */
    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        return $this->addWhere('AND', $column, $operatorOrValue, func_num_args() === 2, $value);
    }

    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        return $this->addWhere('OR', $column, $operatorOrValue, func_num_args() === 2, $value);
    }

    /** @param list<mixed> $values */
    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            $this->wheres[] = ['AND', '1 = 0'];

            return $this;
        }

        $this->wheres[] = ['AND', $this->connection->quoteIdentifier($column) . ' IN (' . implode(', ', array_fill(0, count($values), '?')) . ')'];
        array_push($this->bindings, ...array_values($values));

        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): self
    {
        $direction = strtolower($direction);
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Order direction must be asc or desc.');
        }
        $this->orders[] = $this->connection->quoteIdentifier($column) . ' ' . strtoupper($direction);

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    /** @return list<mixed> */
    public function get(): array
    {
        $rows = $this->connection->select($this->selectSql(), $this->bindings);

        return $this->hydrate === null ? $rows : array_map($this->hydrate, $rows);
    }

    public function first(): mixed
    {
        $row = $this->limit(1)->connection->first($this->selectSql(), $this->bindings);

        return ($row !== null && $this->hydrate !== null) ? ($this->hydrate)($row) : $row;
    }

    public function find(int|string $id, string $key = 'id'): mixed
    {
        return $this->where($key, $id)->first();
    }

    public function count(): int
    {
        $sql = 'SELECT COUNT(*) AS aggregate FROM ' . $this->connection->quoteIdentifier($this->table) . $this->whereSql();

        return (int) ($this->connection->first($sql, $this->bindings)['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /** @param array<string, mixed> $data @return int|string the new primary key */
    public function insert(array $data, string $key = 'id'): int|string
    {
        if ($data === []) {
            throw new InvalidArgumentException('Cannot insert an empty row.');
        }

        $columns = implode(', ', array_map($this->connection->quoteIdentifier(...), array_keys($data)));
        $marks = implode(', ', array_fill(0, count($data), '?'));
        $sql = 'INSERT INTO ' . $this->connection->quoteIdentifier($this->table) . " ($columns) VALUES ($marks)";

        if ($this->connection->driver() === 'pgsql') {
            $row = $this->connection->first($sql . ' RETURNING ' . $this->connection->quoteIdentifier($key), array_values($data));

            return $row[$key] ?? 0;
        }

        $this->connection->statement($sql, array_values($data));
        $id = $this->connection->lastInsertId();

        return ctype_digit($id) ? (int) $id : $id;
    }

    /** @param array<string, mixed> $data @return int affected rows */
    public function update(array $data): int
    {
        $this->requireWhere('update');
        if ($data === []) {
            throw new InvalidArgumentException('Cannot update with no data.');
        }

        $set = implode(', ', array_map(fn(string $c): string => $this->connection->quoteIdentifier($c) . ' = ?', array_keys($data)));
        $sql = 'UPDATE ' . $this->connection->quoteIdentifier($this->table) . " SET $set" . $this->whereSql();

        return $this->connection->statement($sql, [...array_values($data), ...$this->bindings]);
    }

    /** @return int affected rows */
    public function delete(): int
    {
        $this->requireWhere('delete');

        return $this->connection->statement('DELETE FROM ' . $this->connection->quoteIdentifier($this->table) . $this->whereSql(), $this->bindings);
    }

    private function addWhere(string $boolean, string $column, mixed $operatorOrValue, bool $implicitEquals, mixed $value): self
    {
        $operator = '=';
        if ($implicitEquals) {
            $value = $operatorOrValue;
        } else {
            $operator = strtolower((string) $operatorOrValue);
            if (!in_array($operator, self::OPERATORS, true)) {
                throw new InvalidArgumentException("Unsupported operator [$operator].");
            }
        }

        $quoted = $this->connection->quoteIdentifier($column);
        if ($value === null) {
            $this->wheres[] = [$boolean, $quoted . ($operator === '=' ? ' IS NULL' : ' IS NOT NULL')];
        } else {
            $this->wheres[] = [$boolean, "$quoted $operator ?"];
            $this->bindings[] = $value;
        }

        return $this;
    }

    private function requireWhere(string $action): void
    {
        if ($this->wheres === []) {
            throw new LogicException("Refusing to $action every row: add a where() clause.");
        }
    }

    private function whereSql(): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $sql = '';
        foreach ($this->wheres as $i => [$boolean, $clause]) {
            $sql .= ($i === 0 ? '' : " $boolean ") . $clause;
        }

        return " WHERE $sql";
    }

    private function selectSql(): string
    {
        $columns = implode(', ', array_map(
            fn(string $c): string => $c === '*' ? '*' : $this->connection->quoteIdentifier($c),
            $this->columns,
        ));

        $sql = "SELECT $columns FROM " . $this->connection->quoteIdentifier($this->table) . $this->whereSql();

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ($this->limit === null ? ' LIMIT ' . PHP_INT_MAX : '') . ' OFFSET ' . $this->offset;
        }

        return $sql;
    }
}
