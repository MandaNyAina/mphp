<?php

declare(strict_types=1);

namespace Mphp\Database;

/** Runs database/migrations/*.php (each returns an anonymous Migration) in filename order. */
final class Migrator
{
    public function __construct(private readonly Connection $db, private readonly string $path) {}

    /** @return list<string> names of the migrations that were run */
    public function migrate(): array
    {
        $this->ensureTable();
        $ran = $this->ran();
        $batch = (int) ($this->db->first('SELECT MAX(batch) AS b FROM migrations')['b'] ?? 0) + 1;
        $done = [];

        foreach ($this->files() as $name => $file) {
            if (in_array($name, $ran, true)) {
                continue;
            }
            $this->load($file)->up($this->db);
            $this->db->statement('INSERT INTO migrations (migration, batch) VALUES (?, ?)', [$name, $batch]);
            $done[] = $name;
        }

        return $done;
    }

    /** @return list<string> names of the migrations that were rolled back (last batch) */
    public function rollback(): array
    {
        $this->ensureTable();
        $batch = (int) ($this->db->first('SELECT MAX(batch) AS b FROM migrations')['b'] ?? 0);
        $files = $this->files();
        $undone = [];

        $rows = $this->db->select('SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC', [$batch]);
        foreach ($rows as $row) {
            $name = $row['migration'];
            if (isset($files[$name])) {
                $this->load($files[$name])->down($this->db);
            }
            $this->db->statement('DELETE FROM migrations WHERE migration = ?', [$name]);
            $undone[] = $name;
        }

        return $undone;
    }

    /** @return list<array{name: string, ran: bool}> */
    public function status(): array
    {
        $this->ensureTable();
        $ran = $this->ran();

        return array_map(
            static fn(string $name): array => ['name' => $name, 'ran' => in_array($name, $ran, true)],
            array_keys($this->files()),
        );
    }

    private function ensureTable(): void
    {
        $this->db->statement(sprintf(
            'CREATE TABLE IF NOT EXISTS migrations (%s, migration VARCHAR(255) NOT NULL, batch INTEGER NOT NULL)',
            $this->db->idColumn(),
        ));
    }

    /** @return list<string> */
    private function ran(): array
    {
        return array_column($this->db->select('SELECT migration FROM migrations'), 'migration');
    }

    /** @return array<string, string> name => absolute path, sorted */
    private function files(): array
    {
        $files = [];
        // scandir, not glob(): glob() cannot read inside a PHAR.
        foreach (is_dir($this->path) ? scandir($this->path) : [] as $file) {
            if (str_ends_with($file, '.php')) {
                $files[basename($file, '.php')] = rtrim($this->path, '/') . '/' . $file;
            }
        }
        ksort($files);

        return $files;
    }

    private function load(string $file): Migration
    {
        $migration = require $file;

        return $migration instanceof Migration ? $migration : throw new \LogicException("$file must return a Migration instance.");
    }
}
