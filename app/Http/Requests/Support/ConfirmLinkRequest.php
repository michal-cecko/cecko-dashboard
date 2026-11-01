<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['confirm' => ['required', 'accepted']];
    }

    public function messages(): array
    {
        return ['confirm.accepted' => __('support.confirm_required'), 'confirm.required' => __('support.confirm_required')];
    }
}
