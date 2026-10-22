<?php

declare(strict_types=1);

namespace App\Models;

use Mphp\Database\Model;

/**
 * @property int $id
 * @property string $firstname
 * @property string $created_at
 */
final class Name extends Model
{
    protected static string $table = 'names';

    /** Mass-assignment allow-list: create()/update() silently drop any other key. */
    protected static array $fillable = ['firstname'];
}
