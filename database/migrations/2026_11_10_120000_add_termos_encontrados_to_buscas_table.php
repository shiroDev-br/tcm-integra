<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Terms that had occurrences in each collection (JSON list), shown as the run's notice.
     */
    public function up(): void
    {
        Schema::table('buscas', function (Blueprint $table) {
            $table->text('termos_encontrados')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('buscas', function (Blueprint $table) {
            $table->dropColumn('termos_encontrados');
        });
    }
};
