<?php

namespace App\Support;

use App\Models\Document;
use App\Models\Organization;
use Illuminate\Http\Request;

final class Access
{
    public static function admin(Request $r): bool
    {
        return $r->user() instanceof Organization && (bool) $r->user()->isAdmin;
    }

    public static function organization(Request $r): string
    {
        abort_unless($r->user(), 401, 'Unauthorized access');

        return (string) ($r->user()->organization ?? $r->user()->getKey());
    }

    public static function owns(Request $r, mixed $organization): void
    {
        abort_unless(self::admin($r) || self::organization($r) === (string) $organization, 403, 'Forbidden');
    }

    public static function owner(Request $r): void
    {
        abort_unless($r->user() instanceof Organization, 403, 'Organization owner access required');
    }

    public static function find(string $model, string $id): Document
    {
        Ids::object($id);

        return $model::findOrFail($id);
    }
}
