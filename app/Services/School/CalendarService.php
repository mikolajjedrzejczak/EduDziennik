<?php

namespace App\Services\School;

use App\Models\AcademicYear;
use App\Models\GradeCategory;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CalendarService
{
    /** @param array<string, mixed> $input */
    public function configure(array $input): void
    {
        $data = Validator::make($input, [
            'years' => ['present', 'array'],
            'years.*' => ['array:name,starts_on,ends_on,terms'],
            'years.*.name' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/', 'distinct'],
            'years.*.starts_on' => ['required', 'date_format:Y-m-d'],
            'years.*.ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:years.*.starts_on'],
            'years.*.terms' => ['required', 'array', 'size:2'],
            'years.*.terms.*' => ['array:number,starts_on,ends_on'],
            'years.*.terms.*.number' => ['required', 'integer:strict', 'in:1,2'],
            'years.*.terms.*.starts_on' => ['required', 'date_format:Y-m-d'],
            'years.*.terms.*.ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:years.*.terms.*.starts_on'],
            'categories' => ['present', 'array'],
            'categories.*' => ['array:name,default_weight,is_active'],
            'categories.*.name' => ['required', 'string', 'max:60', 'distinct'],
            'categories.*.default_weight' => ['required', 'integer:strict', 'between:1,5'],
            'categories.*.is_active' => ['required', 'boolean:strict'],
            'close_term_ids' => ['sometimes', 'array'],
            'close_term_ids.*' => ['required', 'integer:strict', 'distinct', 'exists:terms,id'],
        ])->validate();

        DB::transaction(function () use ($data) {
            // Serialize even the first year insertion, where there is no year row to lock yet.
            DB::table('domain_locks')->where('key', 'calendar_configuration')->lockForUpdate()->firstOrFail();
            /** @var list<array{name: string, starts_on: string, ends_on: string, terms: list<array{number: int, starts_on: string, ends_on: string}>}> $years */
            $years = $data['years'];
            foreach ($years as $yearData) {
                if (AcademicYear::where('starts_on', '<=', $yearData['ends_on'])->where('ends_on', '>=', $yearData['starts_on'])->exists()) {
                    throw ValidationException::withMessages(['years' => 'Lata szkolne nie mogą się nakładać ani być nadpisywane.']);
                }
                $terms = collect($yearData['terms'])->sortBy('starts_on')->values();
                if ($terms->pluck('number')->unique()->count() !== 2 || $terms[0]['ends_on'] >= $terms[1]['starts_on']) {
                    throw ValidationException::withMessages(['years' => 'Semestry muszą mieć różne numery i nie mogą się nakładać.']);
                }
                foreach ($terms as $term) {
                    if ($term['starts_on'] < $yearData['starts_on'] || $term['ends_on'] > $yearData['ends_on']) {
                        throw ValidationException::withMessages(['years' => 'Semestr musi mieścić się w roku szkolnym.']);
                    }
                }
                $year = AcademicYear::create(collect($yearData)->except('terms')->all());
                foreach ($terms as $term) {
                    $year->terms()->create($term);
                }
            }
            foreach ($data['close_term_ids'] ?? [] as $id) {
                $term = Term::lockForUpdate()->findOrFail((int) $id);
                if ($term->closed_at === null) {
                    $term->update(['closed_at' => now()]);
                }
            }
            foreach ($data['categories'] as $category) {
                GradeCategory::updateOrCreate(['name' => $category['name']], $category);
            }
        }, 3);
    }
}
