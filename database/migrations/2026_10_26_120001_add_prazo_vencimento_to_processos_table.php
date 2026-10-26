<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Latest deadline of each process, kept on the row so the listing sorts by an index.
     */
    public function up(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            $table->string('prazo_vencimento', 10)->nullable()->index('idx_processos_prazo_vencimento');
        });
    }

    public function down(): void
    {
        Schema::table('processos', function (Blueprint $table) {
            $table->dropIndex('idx_processos_prazo_vencimento');
            $table->dropColumn('prazo_vencimento');
        });
    }
};
