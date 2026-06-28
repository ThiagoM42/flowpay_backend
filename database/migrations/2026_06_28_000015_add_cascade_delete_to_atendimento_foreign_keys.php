<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fila_atendimentos', function (Blueprint $table) {
            $table->dropForeign(['atendimento_id']);
            $table->foreign('atendimento_id')->references('id')->on('atendimentos')->cascadeOnDelete();
        });

        Schema::table('atendimento_atribuicoes', function (Blueprint $table) {
            $table->dropForeign(['atendimento_id']);
            $table->foreign('atendimento_id')->references('id')->on('atendimentos')->cascadeOnDelete();
        });

        Schema::table('atendimento_eventos', function (Blueprint $table) {
            $table->dropForeign(['atendimento_id']);
            $table->foreign('atendimento_id')->references('id')->on('atendimentos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fila_atendimentos', function (Blueprint $table) {
            $table->dropForeign(['atendimento_id']);
            $table->foreign('atendimento_id')->references('id')->on('atendimentos');
        });

        Schema::table('atendimento_atribuicoes', function (Blueprint $table) {
            $table->dropForeign(['atendimento_id']);
            $table->foreign('atendimento_id')->references('id')->on('atendimentos');
        });

        Schema::table('atendimento_eventos', function (Blueprint $table) {
            $table->dropForeign(['atendimento_id']);
            $table->foreign('atendimento_id')->references('id')->on('atendimentos');
        });
    }
};
