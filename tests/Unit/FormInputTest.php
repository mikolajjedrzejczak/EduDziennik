<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\Forms\ProbeRequest;
use Tests\TestCase;

class FormInputTest extends TestCase
{
    #[DataProvider('inputs')]
    public function test_scalar_rules_do_not_coerce_invalid_input(mixed $quantity, mixed $comment, bool $valid): void
    {
        $rules = (new ProbeRequest)->rules();
        $validator = Validator::make(compact('quantity', 'comment'), [
            'quantity' => $rules['quantity'], 'comment' => $rules['comment'],
        ]);
        $this->assertSame($valid, $validator->passes());
    }

    public static function inputs(): array
    {
        return [
            ['1', null, true], ['5', '', true], [2, str_repeat('ą', 500), true],
            ['', null, false], [null, null, false], ['0', null, false],
            ['6', null, false], ['1.5', null, false], ['2+', null, false],
            [[], null, false], ['2', ['text'], false], ['2', str_repeat('ą', 501), false],
        ];
    }
}
