<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEnrollmentRequest;
use App\Http\Requests\Admin\UpdateEnrollmentRequest;
use App\Models\SchoolClass;
use App\Models\StudentEnrollment;
use App\Services\School\EnrollmentService;
use Illuminate\Http\RedirectResponse;

class EnrollmentController extends Controller
{
    public function store(StoreEnrollmentRequest $request, SchoolClass $schoolClass, EnrollmentService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->enroll((int) $data['student_id'], $schoolClass->id, $data['starts_on'], $data['ends_on'] ?? null,
            isset($data['transfer_from_enrollment_id']) ? (int) $data['transfer_from_enrollment_id'] : null, $request->user()->id);

        return $this->backToClass($schoolClass)->with('success', 'Zapisano członkostwo ucznia.');
    }

    public function update(UpdateEnrollmentRequest $request, SchoolClass $schoolClass, StudentEnrollment $enrollment, EnrollmentService $service): RedirectResponse
    {
        $data = $request->validated();
        $service->update($request->user()->id, $schoolClass->id, $enrollment->id, $data['starts_on'], $data['ends_on'] ?? null);

        return $this->backToClass($schoolClass)->with('success', 'Zaktualizowano daty członkostwa.');
    }

    private function backToClass(SchoolClass $class): RedirectResponse
    {
        return redirect()->route('admin.classes.index', ['academic_year_id' => (string) $class->academic_year_id, 'selected_class_id' => (string) $class->id]);
    }
}
