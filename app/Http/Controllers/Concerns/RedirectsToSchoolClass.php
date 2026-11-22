<?php

namespace App\Http\Controllers\Concerns;

use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;

trait RedirectsToSchoolClass
{
    /** Back to the class management list with the class selected in its academic year. */
    private function backToClass(SchoolClass $class): RedirectResponse
    {
        return redirect()->route('admin.classes.index', ['academic_year_id' => (string) $class->academic_year_id, 'selected_class_id' => (string) $class->id]);
    }
}
