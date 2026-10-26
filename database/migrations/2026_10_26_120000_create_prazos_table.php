<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Response deadlines read from the Diário Oficial acts that cite each process.
     */
    public function up(): void
    {
        Schema::create('prazos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('processo_numero', 30);
            $table->integer('edicao_id');
            $table->integer('pagina');
            $table->integer('dias');
            $table->string('contagem', 10);
            $table->string('data_inicio', 10);
            $table->string('data_vencimento', 10);
            $table->boolean('estimado')->default(false);
            $table->text('trecho');
            $table->string('created_at', 30);

            $table->unique(['processo_numero', 'edicao_id'], 'uq_prazo');
            $table->index(['processo_numero', 'data_vencimento'], 'idx_prazos_vencimento');
            $table->foreign('edicao_id')->references('id')->on('edicoes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prazos');
    }
};
