<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Organizações de prestação de contas (zip enviado → zip organizado) e os arquivos gerados em cada uma.
     */
    public function up(): void
    {
        Schema::create('organizacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entidade', 150)->nullable();
            $table->string('status', 20)->default('pendente')->index();
            $table->string('arquivo_saida')->nullable();
            $table->json('avisos')->nullable();
            $table->text('erro')->nullable();
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('concluido_em')->nullable();
            $table->timestamps();
        });

        Schema::create('organizacao_arquivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizacao_id')->constrained('organizacoes')->cascadeOnDelete();
            $table->string('pasta', 40);
            $table->string('nome');
            $table->unsignedInteger('paginas');
            $table->unsignedBigInteger('tamanho_bytes');
            $table->json('avisos')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizacao_arquivos');
        Schema::dropIfExists('organizacoes');
    }
};
