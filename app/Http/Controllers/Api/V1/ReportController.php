<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\WithdrawalRequest;
use App\Support\Access;
use App\Support\Api;
use App\Support\Ids;
use App\Support\OrganizationView;
use Carbon\Carbon;
use Illuminate\Http\Request;
use MongoDB\BSON\Regex;

class ReportController extends Controller
{
    private function donations(Request $r)
    {
        return Transaction::where('organization', Ids::object(Access::organization($r)))->where('status', 'successful');
    }

    private function withdrawals(Request $r)
    {
        return WithdrawalRequest::where('organization', Ids::object(Access::organization($r)))->where('status', 'completed');
    }

    private function change(float $now, float $old): float
    {
        return $old > 0 ? round(($now - $old) / $old * 100, 2) : 0;
    }

    public function dashboard(Request $r)
    {
        $r->validate(['start' => 'sometimes|date', 'end' => 'sometimes|date|after_or_equal:start']);
        $end = Carbon::parse($r->input('end', now()));
        $start = Carbon::parse($r->input('start', $end->copy()->subDays(7)));
        $org = Organization::findOrFail(Access::organization($r));
        $donations = $this->donations($r);
        $withdrawals = $this->withdrawals($r);
        $projects = Project::where('organization', Ids::object(Access::organization($r)))->where('is_deleted', false);
        $total = (clone $donations)->sum('amount');
        $today = (clone $donations)->where('createdAt', '>=', today())->sum('amount');
        $yesterday = (clone $donations)->whereBetween('createdAt', [today()->subDay(), today()->subMicrosecond()])->sum('amount');
        $running = (clone $projects)->where('closed', false)->where('is_suspended', false)->count();
        $projectCount = $projects->count();

        return Api::ok(['runningProjects' => ['number' => $running, 'percentageOfOpenProjects' => $projectCount ? round($running / $projectCount * 100, 2) : 0], 'fundsRaised' => ['number' => $total, 'percentage' => $total ? $today / $total * 100 : 0, 'transactionsByRange' => (clone $donations)->whereBetween('createdAt', [$start, $end])->sum('amount')], 'todaysFunds' => ['number' => $today, 'percentage' => $this->change($today, $yesterday)], 'numberOfDonors' => (clone $donations)->whereNotNull('email')->distinct('email')->count('email'), 'numberOfDaysFromCreation' => (int) Carbon::parse($org->createdAt)->diffInDays(now()), 'withdrawals' => ['totalAmountWithdrawn' => (clone $withdrawals)->sum('amount'), 'totalNumberOfWithdrawals' => $withdrawals->count()]], 'Organization Dashboard fetched successfully');
    }

    public function overview(Request $r)
    {
        $d = $this->donations($r);
        $w = $this->withdrawals($r);
        $start = now()->subDays(7);
        $previous = now()->subDays(14);
        $activity = (clone $d)->where('createdAt', '>=', $start)->sum('amount');
        $prior = (clone $d)->where('createdAt', '>=', $previous)->where('createdAt', '<', $start)->sum('amount');
        $income = (clone $d)->sum('amount');
        $oldIncome = (clone $d)->where('createdAt', '<', $start)->sum('amount');
        $spent = (clone $w)->sum('amount');
        $oldSpent = (clone $w)->where('createdAt', '<', $start)->sum('amount');

        return Api::ok(['activity' => ['amount' => $activity, 'percentageChange' => $this->change($activity, $prior), 'period' => 'Last 7 days'], 'income' => ['amount' => $income, 'numberOfDonors' => (clone $d)->distinct('email')->count('email'), 'percentageChange' => $this->change($income, $oldIncome)], 'spending' => ['amount' => $spent, 'numberOfWithdrawals' => $w->count(), 'percentageChange' => $this->change($spent, $oldSpent)]], 'Overview data fetched successfully');
    }

    public function graph(Request $r)
    {
        $v = $r->validate(['startDate' => 'required|date', 'endDate' => 'required|date|after_or_equal:startDate']);
        $start = Carbon::parse($v['startDate']);
        $end = Carbon::parse($v['endDate'])->endOfDay();
        abort_unless($start->year === $end->year, 400, 'Select a range within one calendar year');
        $results = [];
        for ($m = 1; $m <= 12; $m++) {
            $results[$m] = ['month' => $m, 'donations' => 0, 'withdrawals' => 0];
        }
        foreach (['donations' => $this->donations($r), 'withdrawals' => $this->withdrawals($r)] as $field => $q) {
            foreach ($q->whereBetween('createdAt', [$start, $end])->get(['createdAt', 'amount']) as $t) {
                $results[Carbon::parse($t->createdAt)->month][$field] += $t->amount;
            }
        }

        return response()->json(['statuse' => true, 'message' => 'Graph data fetched successfully', 'results' => array_values($results)]);
    }

    public function admin(Request $r)
    {
        $v = $r->validate(['max' => 'sometimes|integer|min:1|max:100']);
        $recent = Transaction::where('status', 'successful')->orderBy('createdAt', 'desc')->limit($v['max'] ?? 10)->get()->map(function ($t) {
            $d = array_intersect_key($t->toArray(), array_flip(['_id', 'amount', 'currency', 'email', 'fullname', 'createdAt']));
            $p = Project::find((string) $t->project);
            $o = Organization::find((string) $t->organization);
            $d['project'] = $p ? ['_id' => (string) $p->getKey(), 'title' => $p->title, 'cover_image' => $p->cover_image] : null;
            $d['organization'] = $o ? ['_id' => (string) $o->getKey(), 'name' => $o->name, 'email' => $o->email] : null;

            return $d;
        });

        return Api::ok(['totals' => ['projects' => Project::where('is_deleted', false)->where('is_suspended', false)->count(), 'donations' => Transaction::where('status', 'successful')->sum('amount'), 'activeUsers' => Organization::where('isActive', true)->where('blocked', false)->whereNull('deletedAt')->count()], 'recentActivity' => $recent]);
    }

    public function search(Request $r)
    {
        $v = $r->validate(['name' => 'sometimes|string|max:200', 'page' => 'sometimes|integer|min:1', 'pageSize' => 'sometimes|integer|min:1|max:500']);
        $oq = Api::filters(Organization::where('isActive', true)->where('blocked', false)->whereNull('deletedAt'), $r, ['name', 'type']);
        $pq = ProjectController::visibleQuery();
        if ($r->filled('name')) {
            $pq->where('title', 'regex', new Regex(preg_quote($r->name), 'i'));
        }
        $orgs = Api::page($oq, $r, OrganizationView::public(...), 20);
        $projects = Api::page($pq, $r, null, 20);
        $shape = fn ($p) => ['data' => $p['data'], 'total' => $p['pagination']['total'], 'page' => $p['pagination']['currentPage'], 'pageSize' => (int) ($v['pageSize'] ?? 20), 'totalPages' => $p['pagination']['pageTotal']];

        return response()->json(['organizations' => $shape($orgs), 'projects' => $shape($projects)]);
    }
}
