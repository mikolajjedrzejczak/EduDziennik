<?php

namespace App\Http\Requests\Concerns;

/** Shared account field rules: trimmed names and a trimmed, lower-case e-mail. */
trait NormalizesAccountInput
{
    /** Letters (any script), spaces, apostrophes and hyphens, with at least one letter. */
    protected const NAME_RULES = ['bail', 'required', 'string', 'max:80', "regex:/^(?=.*\p{L})[\p{L}\p{M} '’-]+$/u"];

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['first_name', 'last_name', 'email'] as $key) {
            $value = $this->input($key);
            if (is_string($value)) {
                $normalized[$key] = $key === 'email' ? mb_strtolower(trim($value)) : trim($value);
            }
        }
        $this->merge($normalized);
    }
}
