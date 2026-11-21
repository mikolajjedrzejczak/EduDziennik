<?php

namespace App\Http\Requests\Concerns;

use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\ValidationException;

/**
 * GET filters: invalid input redirects (303) to the list without the offending
 * parameters, so a direct visit never loops (ERROR_HANDLING).
 */
trait RedirectsInvalidFilters
{
    /** @param array<string, mixed> $query valid, known filter parameters */
    abstract protected function filterRedirectUrl(array $query): string;

    protected function failedValidation(Validator $validator): void
    {
        $known = $validator instanceof \Illuminate\Validation\Validator ? $validator->getRules() : $this->rules();
        $query = array_intersect_key($this->except($this->rejectedFilters($validator)), $known);

        $exception = new ValidationException($validator);
        $exception->response = redirect()->to($this->filterRedirectUrl($query), 303)->withErrors($validator->errors());

        throw $exception;
    }

    /**
     * Rule::in of the actor's selectable ids, queried only when the filter is sent:
     * a missing or empty nullable filter never reaches the rule.
     *
     * @param  Closure(): array<int, int|string>  $allowed
     */
    protected function inWhenFilled(string $key, Closure $allowed): In
    {
        return Rule::in($this->filled($key) ? $allowed() : []);
    }

    /** @return list<string> */
    protected function rejectedFilters(Validator $validator): array
    {
        return array_keys($validator->errors()->messages());
    }
}
