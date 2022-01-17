<?php

namespace Core\Interfaces;

interface DatabaseInterface {
    public function execute(string $query): bool;
    public function select(string $table, string $value = "*", string $cond = null): array;
    public function insert(string $table, array $data): bool;
    public function update(string $table, array $data, string $cond): bool;
    public function delete(string $table, string $cond = null): bool;
    public function getLastRow(string $table, string $value = "*"): array;
}