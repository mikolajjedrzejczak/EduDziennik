<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('f002_probe', function (Blueprint $table): void {
            $table->id();
            $table->string('marker');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('f002_probe');
    }
};
