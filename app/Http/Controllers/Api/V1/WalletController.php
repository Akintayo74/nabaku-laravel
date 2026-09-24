<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Wallet;
use App\Services\Monnify;
use App\Support\Access;
use App\Support\Api;
use App\Support\Ids;
use App\Support\Money;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $r, Monnify $monnify, ?string $id = null)
    {
        $id ??= Access::organization($r);
        $org = filter_var($id, FILTER_VALIDATE_EMAIL) ? Organization::where('email', $id)->firstOrFail() : Access::find(Organization::class, $id);
        Access::owns($r, $org->getKey());
        $wallet = Wallet::where('organization', Ids::object((string) $org->getKey()))->firstOrFail();
        $data = $wallet->toArray();
        $data['reserved_balance'] = (float) ($wallet->reserved_balance ?? 0);
        $data['available_balance'] = Money::major(Money::minor($wallet->balance) - Money::minor($wallet->reserved_balance ?? 0));
        if ($org->isAdmin) {
            $data['monnifyBalance'] = $monnify->balance()['availableBalance'];
        }

        return Api::ok($data, 'Wallet fetched');
    }

    public function admin(Monnify $monnify)
    {
        return Api::ok($monnify->balance(), 'Balance fetched successfully');
    }
}
