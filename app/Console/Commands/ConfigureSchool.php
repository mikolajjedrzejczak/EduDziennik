<?php

namespace App\Console\Commands;

use App\Services\School\CalendarService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use JsonException;

class ConfigureSchool extends Command
{
    protected $signature = 'school:configure {file : Lokalny plik JSON z konfiguracją}';

    protected $description = 'Tworzy lata i semestry, konfiguruje kategorie lub nieodwracalnie zamyka semestry';

    public function handle(CalendarService $calendar): int
    {
        $path = $this->argument('file');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Nie można odczytać pliku konfiguracji.');

            return self::FAILURE;
        }
        try {
            $input = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($input) || array_is_list($input) || array_diff(array_keys($input), ['years', 'categories', 'close_term_ids']) !== []) {
                $this->error('Nieprawidłowa struktura konfiguracji.');

                return self::FAILURE;
            }
            $calendar->configure($input);
        } catch (JsonException) {
            $this->error('Nieprawidłowy JSON konfiguracji.');

            return self::FAILURE;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        $this->info('Konfiguracja zapisana.');

        return self::SUCCESS;
    }
}
