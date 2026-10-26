<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Multas manuais vinculadas ao termo (município/empresa) monitorado.
     */
    public function up(): void
    {
        Schema::create('multas', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('termo_id');
            $table->string('gestor', 120);
            $table->string('processo_numero', 30);
            $table->date('data_multa');
            $table->date('data_vencimento');
            $table->decimal('valor', 12, 2);
            $table->string('situacao', 20)->default('pendente');
            $table->string('comprovante')->nullable();
            $table->timestamps();

            $table->foreign('termo_id')->references('id')->on('termos')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('multas');
    }
};
