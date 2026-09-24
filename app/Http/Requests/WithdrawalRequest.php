<?php

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;

class WithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof Organization;
    }

    public function rules(): array
    {
        return ['amount' => 'required|numeric|min:10|max:999999999999.99', 'bankId' => 'required|string'];
    }
}
