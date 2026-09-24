<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;
use MongoDB\BSON\Regex;

final class Api
{
    public static function ok(mixed $data = null, ?string $message = null, int $status = 200)
    {
        $body = ['success' => true];
        if ($message !== null) {
            $body['message'] = $message;
        }
        if ($data !== null) {
            $body['data'] = $data;
        }

        return response()->json($body, $status);
    }

    public static function filters($q, Request $r, array $allowed = [])
    {
        $r->validate(['startDate' => 'sometimes|date', 'endDate' => 'sometimes|date|after_or_equal:startDate']);
        foreach ($allowed as $field) {
            if (! $r->filled($field)) {
                continue;
            }
            $value = $r->input($field);
            abort_unless(is_scalar($value) && strlen((string) $value) <= 200, 400, 'Invalid filter');
            if (in_array($field, ['closed', 'is_suspended', 'isActive', 'blocked', 'anonymous'])) {
                abort_unless(in_array($value, ['true', 'false', '1', '0', true, false], true), 400, 'Invalid boolean');
                $q->where($field, filter_var($value, FILTER_VALIDATE_BOOLEAN));
            } else {
                $q->where($field, 'regex', new Regex(preg_quote((string) $value), 'i'));
            }
        }
        if ($r->filled('startDate')) {
            $q->where('createdAt', '>=', Carbon::parse($r->startDate));
        }
        if ($r->filled('endDate')) {
            $q->where('createdAt', '<=', Carbon::parse($r->endDate)->endOfDay());
        }

        return $q;
    }

    public static function page($q, Request $r, ?callable $map = null, int $default = 50): array
    {
        $v = $r->validate(['page' => 'sometimes|integer|min:1|max:100000', 'pageSize' => 'sometimes|integer|min:1|max:500']);
        $page = (int) ($v['page'] ?? 1);
        $size = (int) ($v['pageSize'] ?? $default);
        $count = (clone $q)->count();
        $total = (int) ceil($count / $size);
        $items = $q->orderBy('createdAt', 'desc')->skip(($page - 1) * $size)->limit($size)->get();

        return ['success' => true, 'pagination' => ['prevPage' => $page > 1 ? $page - 1 : null, 'currentPage' => $page, 'nextPage' => $page < $total ? $page + 1 : null, 'pageTotal' => $total, 'pageSize' => min($size, $count), 'total' => $count], 'data' => $map ? $items->map($map) : $items];
    }
}
