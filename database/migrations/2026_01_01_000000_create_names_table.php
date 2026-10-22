<?php

declare(strict_types=1);

use Mphp\Database\Connection;
use Mphp\Database\Migration;

return new class extends Migration {
    public function up(Connection $db): void
    {
        $db->statement(sprintf(
            'CREATE TABLE names (%s, firstname VARCHAR(50) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)',
            $db->idColumn(),
        ));
    }

    public function down(Connection $db): void
    {
        $db->statement('DROP TABLE names');
    }
};
