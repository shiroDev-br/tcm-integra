<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Schema do sistema TCM: mesmas tabelas, tipos e chaves do sistema original.
     */
    public function up(): void
    {
        Schema::create('edicoes', function (Blueprint $table) {
            $table->integer('id'); // PK = id externo da fonte EGBA
            $table->string('data', 10);
            $table->integer('numero')->nullable();
            $table->integer('paginas')->nullable();
            $table->boolean('suplemento')->default(false);
            $table->string('tipo_edicao_nome')->nullable();

            $table->primary('id');
        });

        Schema::create('termos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('termo', 120)->unique();
            $table->string('tipo', 20)->default('municipio');
            $table->boolean('ativo')->default(true);
            $table->text('variacoes');
            $table->string('created_at', 30);
        });

        Schema::create('ocorrencias', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('edicao_id');
            $table->integer('pagina');
            $table->integer('termo_id');
            $table->text('trecho');
            $table->text('contexto');
            $table->text('atos');
            $table->text('processos');
            $table->string('tipo', 20)->default('mencao');
            $table->integer('ocorrencias_na_pagina')->default(1);
            $table->string('pdf_url', 500)->default('');
            $table->string('periodo_inicio', 10);
            $table->string('periodo_fim', 10);
            $table->string('created_at', 30);

            $table->unique(['edicao_id', 'pagina', 'termo_id', 'periodo_inicio', 'periodo_fim'], 'uq_ocorrencia');
            $table->index(['periodo_inicio', 'periodo_fim'], 'idx_ocorrencias_periodo');
            $table->foreign('edicao_id')->references('id')->on('edicoes')->onDelete('cascade');
            $table->foreign('termo_id')->references('id')->on('termos')->onDelete('cascade');
        });

        Schema::create('processos', function (Blueprint $table) {
            $table->string('numero', 30)->primary();
            $table->boolean('encontrado')->default(false);
            $table->string('data_entrada', 10)->nullable();
            $table->string('autor', 255)->nullable();
            $table->text('complemento')->nullable();
            $table->string('situacao', 120)->nullable();
            $table->string('situacao_data', 10)->nullable();
            $table->string('meio', 60)->nullable();
            $table->text('andamento');
            $table->string('consultado_em', 30)->nullable();
        });

        Schema::create('termo_processos', function (Blueprint $table) {
            $table->integer('termo_id');
            $table->string('processo_numero', 30);
            $table->string('periodo_inicio', 10);
            $table->string('periodo_fim', 10);
            $table->string('created_at', 30);

            $table->primary(['termo_id', 'processo_numero', 'periodo_inicio', 'periodo_fim']);
            $table->index(['periodo_inicio', 'periodo_fim'], 'idx_termo_processos_periodo');
            $table->foreign('termo_id')->references('id')->on('termos')->onDelete('cascade');
        });

        Schema::create('buscas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('periodo_inicio', 10);
            $table->string('periodo_fim', 10);
            $table->integer('termos')->default(0);
            $table->integer('requests')->default(0);
            $table->integer('duracao_ms')->default(0);
            $table->string('status', 10)->default('ok');
            $table->text('erro')->nullable();
            $table->string('created_at', 30);

            $table->index(['periodo_inicio', 'periodo_fim'], 'idx_buscas_periodo');
        });

        Schema::create('usuarios', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username', 50)->unique();
            $table->string('senha', 255);
            $table->string('role', 20)->default('admin');
            $table->boolean('ativo')->default(true);
            $table->integer('tentativas')->default(0);
            $table->string('bloqueado_ate', 30)->nullable();
            $table->string('criado_em', 30);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('buscas');
        Schema::dropIfExists('termo_processos');
        Schema::dropIfExists('processos');
        Schema::dropIfExists('ocorrencias');
        Schema::dropIfExists('termos');
        Schema::dropIfExists('edicoes');
    }
};
