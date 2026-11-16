<?php

namespace App\Http\Requests;

use App\Models\Grade;
use App\Services\Grades\GradeFormContext;
use Illuminate\Support\Facades\Gate;

class DeleteGradeRequest extends MutationRequest
{
    public function authorize(): bool
    {
        /** @var Grade $grade */
        $grade = $this->route('grade');
        Gate::authorize('view', $grade);
        app(GradeFormContext::class)->resolve($this->user(), $this->input('assignment_id'), $grade->assessment_id, $grade->student_enrollment_id);
        Gate::authorize('delete', $grade);

        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'assignment_id' => ['required', 'integer'],
            'version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'confirmed' => ['required', 'accepted'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['version' => 'wersja', 'reason' => 'powód usunięcia', 'confirmed' => 'potwierdzenie'];
    }
}
