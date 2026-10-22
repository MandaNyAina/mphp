<?php

declare(strict_types=1);

use Mphp\Database\Connection;
use Mphp\Database\Migrator;
use Mphp\Database\Model;

final class DbFixtureThing extends Model
{
    protected static string $table = 'things';

    protected static array $fillable = ['name'];
}

final class DbFixtureLocked extends Model
{
    protected static string $table = 'things';
}

function memoryDb(): Connection
{
    $db = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
    $db->statement(sprintf('CREATE TABLE things (%s, name VARCHAR(50), age INTEGER)', $db->idColumn()));

    return $db;
}

describe('QueryBuilder', function () {
    it('inserts, reads, updates and deletes', function () {
        $db = memoryDb();
        $id = $db->table('things')->insert(['name' => 'Ada', 'age' => 36]);
        $db->table('things')->insert(['name' => 'Linus', 'age' => 55]);

        expect($id)->toBe(1)
            ->and($db->table('things')->find($id)['name'])->toBe('Ada')
            ->and($db->table('things')->where('age', '>=', 40)->get())->toHaveCount(1)
            ->and($db->table('things')->orderBy('age', 'desc')->first()['name'])->toBe('Linus')
            ->and($db->table('things')->whereIn('id', [1, 2])->count())->toBe(2)
            ->and($db->table('things')->whereIn('id', [])->count())->toBe(0)
            ->and($db->table('things')->where('name', 'Ada')->orWhere('name', 'Linus')->count())->toBe(2);

        expect($db->table('things')->where('id', 1)->update(['age' => 37]))->toBe(1)
            ->and($db->table('things')->find(1)['age'])->toBe(37)
            ->and($db->table('things')->where('id', 2)->delete())->toBe(1)
            ->and($db->table('things')->count())->toBe(1);
    });

    it('supports limit/offset and null checks', function () {
        $db = memoryDb();
        foreach ([['a', null], ['b', 2], ['c', 3]] as [$name, $age]) {
            $db->table('things')->insert(['name' => $name, 'age' => $age]);
        }

        expect($db->table('things')->orderBy('id')->limit(1)->offset(1)->get()[0]['name'])->toBe('b')
            ->and($db->table('things')->where('age', null)->first()['name'])->toBe('a')
            ->and($db->table('things')->where('age', '<>', null)->count())->toBe(2);
    });

    it('treats hostile values as plain data', function () {
        $db = memoryDb();
        $db->table('things')->insert(['name' => 'safe', 'age' => 1]);

        $hostile = "x' OR '1'='1";

        expect($db->table('things')->where('name', $hostile)->get())->toBe([])
            ->and($db->table('things')->insert(['name' => "Robert'); DROP TABLE things;--"]))->toBe(2)
            ->and($db->table('things')->count())->toBe(2);
    });

    it('rejects hostile identifiers, operators and mass writes', function () {
        $db = memoryDb();

        expect(fn() => $db->table('things')->where('name; DROP TABLE things', 1))->toThrow(InvalidArgumentException::class)
            ->and(fn() => $db->table('things; --')->get())->toThrow(InvalidArgumentException::class)
            ->and(fn() => $db->table('things')->where('age', '= 1 OR 1=1 --', 1))->toThrow(InvalidArgumentException::class)
            ->and(fn() => $db->table('things')->orderBy('id', 'asc; DROP'))->toThrow(InvalidArgumentException::class)
            ->and(fn() => $db->table('things')->delete())->toThrow(LogicException::class)
            ->and(fn() => $db->table('things')->update(['age' => 1]))->toThrow(LogicException::class);
    });

    it('rolls a transaction back on error', function () {
        $db = memoryDb();

        try {
            $db->transaction(function (Connection $db) {
                $db->table('things')->insert(['name' => 'ghost']);
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
        }

        expect($db->table('things')->count())->toBe(0);
    });
});

describe('Model', function () {
    it('only mass-assigns $fillable attributes', function () {
        Model::setConnection($db = memoryDb());

        $thing = DbFixtureThing::create(['name' => 'Ada', 'id' => 999, 'age' => 99]);

        expect($thing->id)->toBe(1)->and($thing->name)->toBe('Ada')->and($thing->age)->toBeNull();
    });

    it('refuses create() when $fillable is not declared', function () {
        Model::setConnection(memoryDb());

        expect(fn() => DbFixtureLocked::create(['name' => 'x']))->toThrow(LogicException::class, 'fillable');
    });

    it('finds, updates, deletes and serialises to JSON', function () {
        Model::setConnection(memoryDb());
        $thing = DbFixtureThing::create(['name' => 'Ada']);

        $thing->update(['name' => 'Grace']);

        expect(DbFixtureThing::find($thing->id)->name)->toBe('Grace')
            ->and(json_encode($thing))->toContain('"name":"Grace"')
            ->and($thing->delete())->toBeTrue()
            ->and(DbFixtureThing::find($thing->id))->toBeNull()
            ->and(DbFixtureThing::all())->toBe([]);
    });
});

describe('Migrator', function () {
    it('runs pending migrations once and rolls back the last batch', function () {
        $dir = tempDir();
        file_put_contents($dir . '/2026_01_01_000001_create_widgets.php', <<<'PHP'
            <?php
            return new class extends Mphp\Database\Migration {
                public function up(Mphp\Database\Connection $db): void { $db->statement('CREATE TABLE widgets (id INTEGER)'); }
                public function down(Mphp\Database\Connection $db): void { $db->statement('DROP TABLE widgets'); }
            };
            PHP);

        $db = new Connection(['driver' => 'sqlite', 'database' => ':memory:']);
        $migrator = new Migrator($db, $dir);

        expect($migrator->migrate())->toBe(['2026_01_01_000001_create_widgets'])
            ->and($migrator->migrate())->toBe([])
            ->and($migrator->status()[0]['ran'])->toBeTrue()
            ->and($db->select('SELECT name FROM sqlite_master WHERE name = ?', ['widgets']))->toHaveCount(1)
            ->and($migrator->rollback())->toBe(['2026_01_01_000001_create_widgets'])
            ->and($db->select('SELECT name FROM sqlite_master WHERE name = ?', ['widgets']))->toBe([]);
    });
});
