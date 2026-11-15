<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mutations use Laravel's redirect/session errors, never a custom 422 response.
 * Every concrete request must explicitly authorize its scoped operation.
 */
abstract class MutationRequest extends FormRequest
{
    abstract public function authorize(): bool;
}
