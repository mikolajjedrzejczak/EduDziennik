<?php

namespace App\Support;

/** Shared transport rules; callers still authorize and scope every domain selector. */
final class ListFilters
{
    /** @param list<string> $sorts
     * @return array<string, list<string>>
     */
    public static function rules(array $sorts): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', $sorts)],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    /** @return array<string, list<string>> */
    public static function gradebookRules(): array
    {
        return [
            ...self::rules(['last_name']),
            'assessment_page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /** Literal substring pattern for bound MariaDB LIKE parameters. */
    public static function contains(string $query): string
    {
        return '%'.addcslashes(trim($query), '\\%_').'%';
    }
}
