<?php

namespace App\Http\Requests\Concerns;

use App\Models\GradeCategory;
use Illuminate\Validation\Rule;

/** Grade value fields shared by the create and edit forms; GradeService re-validates them under lock. */
trait ValidatesGradeFields
{
    private const GRADE_FIELDS = ['value', 'weight', 'category_id', 'topic', 'comment', 'graded_on'];

    /**
     * @param  string  $from  first allowed grading date (Y-m-d)
     * @param  string  $to  last allowed grading date (Y-m-d)
     * @return array<string, list<mixed>>
     */
    protected function gradeFieldRules(string $from, string $to): array
    {
        return [
            'value' => ['required', 'integer', 'between:1,6'],
            'weight' => ['required', 'integer', 'between:1,5'],
            'category_id' => ['required', 'integer', Rule::in(GradeCategory::activeIds())],
            'topic' => ['required', 'string', 'min:1', 'max:120'],
            'comment' => ['nullable', 'string', 'max:500'],
            'graded_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$from, 'before_or_equal:'.$to],
        ];
    }

    /** @return array<string, string> */
    protected function gradeFieldAttributes(): array
    {
        return ['value' => 'ocena', 'weight' => 'waga', 'category_id' => 'kategoria', 'topic' => 'temat', 'comment' => 'komentarz', 'graded_on' => 'data oceny'];
    }

    /**
     * Validated grade fields with integer columns cast for GradeService.
     *
     * @return array<string, mixed>
     */
    public function gradeFields(): array
    {
        $fields = $this->safe()->only(self::GRADE_FIELDS);
        foreach (['value', 'weight', 'category_id'] as $key) {
            $fields[$key] = (int) $fields[$key];
        }

        return $fields;
    }
}
