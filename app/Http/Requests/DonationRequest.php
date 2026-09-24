<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => 'required_if:anonymous,false|string|max:200', 'email' => 'required|email|max:254', 'phone' => 'required_if:anonymous,false|string|max:30', 'amount' => 'required|numeric|min:1000|max:999999999999.99', 'projectId' => 'required|string', 'anonymous' => 'required|boolean'];
    }
}
