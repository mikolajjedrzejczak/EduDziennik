<?php

namespace App\Services\School;

use App\Models\Assessment;
use App\Models\Term;
use App\Models\User;
use App\Services\Grades\WriteContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AssessmentService
{
    /** @param array<string, mixed> $input */
    public function create(int $actorId, array $input): Assessment
    {
        $data = Validator::make($input, [
            'school_class_id' => ['required', 'integer:strict', 'exists:school_classes,id'],
            'subject_id' => ['required', 'integer:strict', 'exists:subjects,id'],
            'term_id' => ['required', 'integer:strict', 'exists:terms,id'],
            'category_id' => ['required', 'integer:strict', 'exists:grade_categories,id'],
            'topic' => ['required', 'string', 'max:120'],
            'default_weight' => ['required', 'integer:strict', 'between:1,5'],
            'graded_on' => ['required', 'date_format:Y-m-d'],
        ])->validate();

        return DB::transaction(function () use ($actorId, $data) {
            $term = Term::lockForUpdate()->findOrFail((int) $data['term_id']);
            $actor = User::lockForUpdate()->findOrFail($actorId);
            $context = new WriteContext;
            $context->authorize($actor, $term, (int) $data['school_class_id'], (int) $data['subject_id']);
            $context->dateAndCategory($term, $data['graded_on'], (int) $data['category_id']);

            return Assessment::create([...$data, 'created_by' => $actor->id]);
        }, 3);
    }

    public function delete(int $actorId, int $assessmentId): void
    {
        DB::transaction(function () use ($actorId, $assessmentId) {
            $header = Assessment::findOrFail($assessmentId);
            $term = Term::lockForUpdate()->findOrFail($header->term_id);
            $actor = User::lockForUpdate()->findOrFail($actorId);
            (new WriteContext)->authorize($actor, $term, $header->school_class_id, $header->subject_id);
            $header = Assessment::lockForUpdate()->findOrFail($assessmentId);
            if ($header->grades()->withTrashed()->exists()) {
                throw ValidationException::withMessages(['assessment' => 'Kolumna z historią ocen nie może być usunięta.']);
            }
            $header->delete();
        }, 3);
    }
}
