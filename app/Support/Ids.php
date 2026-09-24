<?php

namespace App\Support;

use MongoDB\BSON\ObjectId;

final class Ids
{
    public static function object(mixed $id): ObjectId
    {
        abort_unless(is_string($id) && preg_match('/^[a-f0-9]{24}$/i', $id) || $id instanceof ObjectId, 404, 'Resource Not Found');

        return $id instanceof ObjectId ? $id : new ObjectId($id);
    }
}
