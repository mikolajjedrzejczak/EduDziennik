<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Gate;

class DeleteAssessmentRequest extends MutationRequest
{
    public function authorize(): bool
    {
        Gate::forUser($this->user())->authorize('view', $this->route('assessment'));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['confirmed' => ['required', 'accepted']];
    }

    public function attributes(): array
    {
        return ['confirmed' => 'potwierdzenie usunięcia'];
    }
}
