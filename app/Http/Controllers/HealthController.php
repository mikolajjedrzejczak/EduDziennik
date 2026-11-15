<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): Response
    {
        try {
            DB::select('SELECT 1');
        } catch (Throwable) {
            // No raw connection exception, credentials, session or debug page.
            return response('Baza danych jest niedostępna.', 503);
        }

        return response('OK', 200);
    }
}
