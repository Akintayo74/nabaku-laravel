<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Organization;
use App\Services\Cloudinary;
use App\Support\Access;
use App\Support\Api;
use App\Support\Ids;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function index(Request $r)
    {
        return $this->listing($r, Activity::where('organization', Ids::object(Access::organization($r))), 500);
    }

    public function public(Request $r, ?string $organizationId = null)
    {
        $q = Activity::whereIn('organization', Organization::where('isActive', true)->where('blocked', false)->whereNull('deletedAt')->get()->map(fn ($o) => Ids::object((string) $o->getKey()))->all());
        if ($organizationId) {
            $q->where('organization', Ids::object($organizationId));
        }

        return $this->listing($r, $q);
    }

    private function listing(Request $r, $q, int $size = 50)
    {
        return response()->json([...Api::page(Api::filters($q, $r, ['name', 'location']), $r, null, $size), 'message' => 'Activities fetched successfully']);
    }

    public function show(Request $r, string $activityId)
    {
        $a = Access::find(Activity::class, $activityId);
        Access::owns($r, $a->organization);

        return Api::ok($a, 'Activity fetched successfully');
    }

    private function values(Request $r, bool $create, Cloudinary $cloud): array
    {
        $required = $create ? 'required' : 'sometimes';
        $v = $r->validate(['name' => $required.'|string|min:3|max:200', 'start' => $required.'|date', 'end' => $required.'|date|after_or_equal:start', 'location' => 'sometimes|string|max:300', 'file' => 'sometimes|image|max:10240']);
        unset($v['file']);
        if ($r->hasFile('file')) {
            $v['cover_image'] = $cloud->upload($r->file('file'), Access::organization($r))['url'];
        }

        return $v;
    }

    public function create(Request $r, Cloudinary $cloud)
    {
        $v = $this->values($r, true, $cloud);

        return Api::ok(Activity::create([...$v, 'organization' => Access::organization($r)]), 'Activity added Successfully', 201);
    }

    public function update(Request $r, string $activityId, Cloudinary $cloud): JsonResponse
    {
        $a = Access::find(Activity::class, $activityId);
        Access::owns($r, $a->organization);
        if ($r->has('start') || $r->has('end')) {
            $r->merge(['start' => $r->input('start', $a->start), 'end' => $r->input('end', $a->end)]);
        }
        $a->update($this->values($r, false, $cloud));

        return Api::ok($a, 'Activity updated successfully');
    }

    public function delete(Request $r, string $activityId)
    {
        $a = Access::find(Activity::class, $activityId);
        Access::owns($r, $a->organization);
        $a->delete();

        return Api::ok(null, 'Activity deleted successfully');
    }
}
