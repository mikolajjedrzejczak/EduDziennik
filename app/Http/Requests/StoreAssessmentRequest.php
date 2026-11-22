<?php

namespace App\Http\Requests;

use App\Models\Assessment;
use App\Models\GradeCategory;
use App\Models\TeacherAssignment;
use App\Services\Authorization\GradeAccess;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreAssessmentRequest extends MutationRequest
{
    public function authorize(): bool
    {
        /** @var TeacherAssignment $assignment */
        $assignment = $this->route('assignment');
        $a = app(GradeAccess::class)->assignment($this->user(), $assignment->id);
        if (! $this->user()->can('create', [Assessment::class, $a->schoolClass, $a->subject, $a->term])) {
            throw ValidationException::withMessages(['form' => 'Kontekst jest tylko do odczytu. Nie można dodać kolumny.']);
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var TeacherAssignment $assignment */
        $assignment = $this->route('assignment');
        $term = $assignment->term;

        return [
            'category_id' => ['required', 'integer', Rule::in(GradeCategory::activeIds())],
            'topic' => ['required', 'string', 'min:1', 'max:120'],
            'default_weight' => ['required', 'integer', 'between:1,5'],
            'graded_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$term->starts_on->toDateString(), 'before_or_equal:'.$term->ends_on->toDateString(), 'before_or_equal:'.now('Europe/Warsaw')->toDateString()],
        ];
    }

    public function attributes(): array
    {
        return ['category_id' => 'kategoria', 'topic' => 'temat', 'default_weight' => 'waga domyślna', 'graded_on' => 'data kolumny'];
    }
}
