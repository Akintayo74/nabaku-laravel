<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Project;
use App\Models\WithdrawalRequest;
use App\Services\WithdrawalService;
use App\Support\Access;
use App\Support\Api;
use App\Support\Ids;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function create(\App\Http\Requests\WithdrawalRequest $r, WithdrawalService $service)
    {
        Access::owner($r);
        $v = $r->validated();
        $service->create(Access::organization($r), $v);

        return response()->json(['status' => true, 'message' => 'Withdrawal request submitted successfully'], 201);
    }

    public function update(Request $r, string $requestId, WithdrawalService $service)
    {
        $v = $r->validate(['status' => 'required|in:pending,cancelled,in-progress,completed']);

        return Api::ok($service->transition($requestId, $v['status']), 'Withdrawal request updated successfully');
    }

    public function show(Request $r, string $requestId)
    {
        $w = Access::find(WithdrawalRequest::class, $requestId);
        Access::owns($r, $w->organization);

        return Api::ok($this->view($w), 'Request fetched successfully');
    }

    private function view($w): array
    {
        $d = $w->toArray();
        $org = Organization::find((string) $w->organization);
        $p = $w->project ? Project::find((string) $w->project) : null;
        $d['organization'] = $org ? array_intersect_key($org->toArray(), array_flip(['_id', 'id', 'name', 'email', 'phone', 'type', 'profile_image', 'isActive', 'blocked', 'createdAt'])) : null;
        $d['project'] = $p ? array_intersect_key($p->toArray(), array_flip(['_id', 'title', 'description', 'cover_image', 'target_amount', 'donated_amount', 'createdAt'])) : null;

        return $d;
    }

    public function index(Request $r)
    {
        return $this->listing($r, WithdrawalRequest::where('organization', Ids::object(Access::organization($r))));
    }

    public function admin(Request $r)
    {
        return $this->listing($r, WithdrawalRequest::query());
    }

    private function listing(Request $r, $q)
    {
        return response()->json([...Api::page(Api::filters($q, $r, ['status', 'reference']), $r, $this->view(...)), 'message' => 'Withdrawal requests fetched successfully']);
    }
}
