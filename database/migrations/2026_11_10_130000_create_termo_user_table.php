<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When each user last opened each term (moves it out of "found today" for that user).
     */
    public function up(): void
    {
        Schema::create('termo_user', function (Blueprint $table) {
            $table->unsignedInteger('termo_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('visto_em', 30);

            $table->primary(['user_id', 'termo_id']);
            $table->foreign('termo_id')->references('id')->on('termos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('termo_user');
    }
};
