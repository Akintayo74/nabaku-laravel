<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Project;
use App\Services\Cloudinary;
use App\Support\Access;
use App\Support\Api;
use App\Support\Ids;
use App\Support\Money;
use Illuminate\Http\Request;
use MongoDB\BSON\ObjectId;

class ProjectController extends Controller
{
    public static function visibleQuery()
    {
        return Project::where('is_deleted', false)->where('is_suspended', false)->whereIn('organization', Organization::where('isActive', true)->where('blocked', false)->whereNull('deletedAt')->get()->map(fn ($o) => Ids::object((string) $o->getKey()))->all());
    }

    public function index(Request $r)
    {
        return $this->listing($r, Project::where('organization', Ids::object(Access::organization($r)))->where('is_deleted', false)->where('is_suspended', false));
    }

    public function random(Request $r)
    {
        return $this->listing($r, self::visibleQuery());
    }

    public function organization(Request $r, string $organizationId)
    {
        return $this->listing($r, self::visibleQuery()->where('organization', Ids::object($organizationId)));
    }

    public function admin(Request $r)
    {
        return $this->listing($r, Project::where('is_deleted', false));
    }

    private function listing(Request $r, $q)
    {
        return response()->json(Api::page(Api::filters($q, $r, ['title', 'closed']), $r));
    }

    public function show(string $projectId)
    {
        Ids::object($projectId);

        return Api::ok(self::visibleQuery()->where('_id', $projectId)->firstOrFail(), 'Project fetched successfully');
    }

    public function create(Request $r, Cloudinary $cloud)
    {
        $v = $r->validate(['title' => 'sometimes|string|max:200', 'description' => 'required|string|max:50000', 'target_amount' => 'required|numeric|min:1|max:999999999999.99', 'cover_image' => 'required|image|max:10240', 'media' => 'sometimes|array|max:5', 'media.*' => 'file|mimes:jpg,jpeg,png,webp,gif,mp4,mov,webm|max:51200']);
        $amount = Money::major(Money::minor($v['target_amount']));
        $org = Access::organization($r);
        $cover = $cloud->upload($r->file('cover_image'), $org);
        $media = [];
        foreach ($r->file('media', []) as $file) {
            $media[] = ['_id' => new ObjectId, ...$cloud->upload($file, $org)];
        }
        $project = Project::create(['title' => $v['title'] ?? '', 'description' => $v['description'], 'target_amount' => $amount, 'organization' => $org, 'cover_image' => $cover['url'], 'media' => $media]);

        return Api::ok($project, 'Project created successfully');
    }

    public function update(Request $r, string $projectId, Cloudinary $cloud)
    {
        $project = Access::find(Project::class, $projectId);
        Access::owns($r, $project->organization);
        $v = $r->validate(['title' => 'sometimes|string|max:200', 'description' => 'sometimes|string|max:50000', 'target_amount' => 'sometimes|numeric|min:1|max:999999999999.99', 'closed' => 'sometimes|boolean', 'file' => 'sometimes|image|max:10240']);
        if (isset($v['target_amount'])) {
            $v['target_amount'] = Money::major(Money::minor($v['target_amount']));
        }
        unset($v['file']);
        if ($r->hasFile('file')) {
            $v['cover_image'] = $cloud->upload($r->file('file'), Access::organization($r))['url'];
        }
        $project->update($v);

        return Api::ok($project, 'Project updated successfully');
    }

    public function delete(Request $r, string $projectId)
    {
        $project = Access::find(Project::class, $projectId);
        Access::owns($r, $project->organization);
        $project->update(['is_deleted' => true]);

        return Api::ok(null, 'Project deleted successfully');
    }
}
