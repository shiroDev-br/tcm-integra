<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Municipality reported by the TCM portal (confirms or contradicts the term link).
     */
    public function up(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            $table->string('municipio', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            $table->dropColumn('municipio');
        });
    }
};
