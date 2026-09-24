<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Organization;
use App\Services\Flutterwave;
use App\Support\Access;
use App\Support\Atomic;
use App\Support\Ids;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function banks(Flutterwave $flw)
    {
        return response()->json(['status' => 'success', 'message' => 'Bank list found', 'data' => $flw->banks()['data']]);
    }

    public function verify(Request $r, Flutterwave $flw)
    {
        return response()->json($flw->resolve($r->validate(['account_number' => 'required|string|regex:/^[0-9]{10}$/', 'account_bank' => 'required|string|regex:/^[0-9]+$/'])));
    }

    public function show(Request $r)
    {
        return response()->json(['status' => 'success', 'message' => 'Bank fetched successfully', 'data' => Bank::where('organization', Ids::object(Access::organization($r)))->firstOrFail()]);
    }

    public function create(Request $r, Flutterwave $flw)
    {
        return $this->save($r, $flw, true);
    }

    public function update(Request $r, Flutterwave $flw, ?string $bankId = null)
    {
        return $this->save($r, $flw, false, $bankId);
    }

    private function save(Request $r, Flutterwave $flw, bool $create, ?string $bankId = null)
    {
        Access::owner($r);
        $v = $r->validate(['bankCode' => 'required', 'bankName' => 'required|string|max:100', 'accountName' => 'required|string|max:200', 'accountNumber' => 'required|string|regex:/^[0-9]{10}$/']);
        abort_unless(is_scalar($v['bankCode']) && preg_match('/^[0-9]{1,20}$/', (string) $v['bankCode']), 400, 'Invalid bank code');
        $v['bankCode'] = (string) $v['bankCode'];
        $orgId = Access::organization($r);
        $bank = $bankId ? Access::find(Bank::class, $bankId) : Bank::where('organization', Ids::object($orgId))->first();
        if ($bank) {
            Access::owns($r, $bank->organization);
        }
        abort_if($create && $bank, 422, 'Bank already exists');
        abort_if(! $create && ! $bank, 404, 'Bank not found');
        $resolved = $flw->resolve(['account_number' => $v['accountNumber'], 'account_bank' => $v['bankCode']]);
        $v['accountName'] = $resolved['data']['account_name'] ?? $v['accountName'];
        Atomic::run(function () use ($bank, $v, $orgId) {
            $doc = $bank ? Bank::findOrFail((string) $bank->getKey()) : new Bank(['organization' => $orgId]);
            $doc->fill([...$v, 'isBankVerified' => true]);
            $doc->save();
            Organization::findOrFail((string) $doc->organization)->update(['bank' => $doc->getKey()]);
        });

        return response()->json(['status' => 'success', 'message' => 'Bank updated successfully']);
    }
}
