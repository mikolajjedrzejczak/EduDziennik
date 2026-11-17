<?php

namespace App\Services\Grades;

use App\Models\Grade;
use App\Models\Term;
use App\Models\User;

/** Explicit allowlist shared by list and details; never serialize an Eloquent graph. */
class GradePresenter
{
    /** @return array{id: string, label: string} */
    private function option(int $id, string $label): array
    {
        return ['id' => (string) $id, 'label' => $label];
    }

    /** @return array{id: string, label: string} */
    private function termOption(Term $term): array
    {
        return $this->option($term->id, $this->termLabel($term));
    }

    private function termLabel(Term $term): string
    {
        return 'Semestr '.($term->number === 1 ? 'I' : 'II').' ('.$term->academicYear->name.')';
    }

    /** @param array{update: bool, delete: bool, viewHistory: bool}|null $capabilities
     * @return array<string, mixed>
     */
    public function toArray(Grade $g, User $actor, bool $withHistory = true, bool $readOnly = false, ?array $capabilities = null): array
    {
        $a = $g->assessment;
        $student = $g->enrollment->student;

        return [
            'id' => (string) $g->id,
            'student' => ['id' => (string) $student->id, 'enrollmentId' => (string) $g->student_enrollment_id, 'name' => $student->name, 'historical' => $g->enrollment->isHistorical($a->term->academicYear->ends_on)],
            'assessmentId' => (string) $g->assessment_id,
            'context' => [
                'assignmentId' => null,
                'schoolClass' => $this->option($a->schoolClass->id, $a->schoolClass->name),
                'subject' => $this->option($a->subject->id, $a->subject->name),
                'term' => $this->termOption($a->term),
                'closed' => $a->term->closed_at !== null,
            ],
            'value' => $g->value, 'weight' => $g->weight,
            'category' => $this->option($g->category->id, $g->category->name),
            'topic' => $g->topic, 'comment' => $g->comment, 'gradedOn' => $g->graded_on->toDateString(),
            'issuedBy' => $this->option($g->issuer->id, $g->issuer->name),
            'createdAt' => $g->created_at?->toIso8601String(), 'updatedAt' => $g->updated_at?->toIso8601String(),
            'deletedAt' => $g->deleted_at?->toIso8601String(), 'version' => $g->version,
            'differsFromAssessment' => $g->weight !== $a->default_weight || $g->topic !== $a->topic
                || $g->category_id !== $a->category_id || ! $g->graded_on->equalTo($a->graded_on),
            'can' => $capabilities ?? ['update' => ! $readOnly && $actor->role !== 'student' && $actor->can('update', $g), 'delete' => ! $readOnly && $actor->role !== 'student' && $actor->can('delete', $g), 'viewHistory' => $withHistory && $actor->can('viewHistory', $g)],
        ];
    }
}
