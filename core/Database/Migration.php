<?php

declare(strict_types=1);

namespace Mphp\Database;

/**
 * database/migrations/2026_01_01_000000_create_x_table.php returns:
 *
 *   return new class extends Migration {
 *       public function up(Connection $db): void   { $db->statement("CREATE TABLE ..."); }
 *       public function down(Connection $db): void { $db->statement("DROP TABLE ..."); }
 *   };
 */
abstract class Migration
{
    abstract public function up(Connection $db): void;

    abstract public function down(Connection $db): void;
}
