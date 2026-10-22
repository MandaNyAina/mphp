<?php

declare(strict_types=1);

namespace Mphp\Database;

use JsonSerializable;
use LogicException;

/**
 * Small active-record base class.
 *
 *   final class Name extends Model {
 *       protected static string $table = 'names';
 *       protected static array $fillable = ['firstname'];   // mass-assignment allow-list (required)
 *   }
 *   Name::create(['firstname' => 'Ada']);  Name::find(1)->delete();
 */
abstract class Model implements JsonSerializable
{
    protected static string $table = '';

    protected static string $primaryKey = 'id';

    /** @var list<string> */
    protected static array $fillable = [];

    private static ?Connection $connection = null;

    /** @param array<string, mixed> $attributes */
    final public function __construct(protected array $attributes = []) {}

    public static function setConnection(?Connection $connection): void
    {
        self::$connection = $connection;
    }

    public static function query(): QueryBuilder
    {
        $connection = self::$connection ?? throw new LogicException('No database connection set on Model.');

        return $connection->table(static::$table, static fn(array $row): static => new static($row));
    }

    /** @return list<static> */
    public static function all(): array
    {
        return static::query()->orderBy(static::$primaryKey)->get();
    }

    public static function find(int|string $id): ?static
    {
        return static::query()->find($id, static::$primaryKey);
    }

    /** Only attributes listed in $fillable are accepted, everything else is dropped. */
    public static function create(array $attributes): static
    {
        if (static::$fillable === []) {
            throw new LogicException(static::class . ' must declare $fillable before create()/update() can be used.');
        }

        $data = array_intersect_key($attributes, array_flip(static::$fillable));
        $id = static::query()->insert($data, static::$primaryKey);

        return static::find($id) ?? new static($data + [static::$primaryKey => $id]);
    }

    /** @param array<string, mixed> $attributes */
    public function update(array $attributes): static
    {
        if (static::$fillable === []) {
            throw new LogicException(static::class . ' must declare $fillable before create()/update() can be used.');
        }

        $data = array_intersect_key($attributes, array_flip(static::$fillable));
        if ($data !== []) {
            static::query()->where(static::$primaryKey, $this->attributes[static::$primaryKey])->update($data);
            $this->attributes = $data + $this->attributes;
        }

        return $this;
    }

    public function delete(): bool
    {
        return static::query()->where(static::$primaryKey, $this->attributes[static::$primaryKey])->delete() > 0;
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }
}
