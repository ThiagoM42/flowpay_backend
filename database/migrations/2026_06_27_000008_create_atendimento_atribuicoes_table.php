<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atendimento_atribuicoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atendimento_id')->constrained('atendimentos');
            $table->foreignId('atendente_id')->constrained('atendentes');
            $table->enum('status', ['ativo', 'finalizado', 'transferido'])->default('ativo');
            $table->timestamp('criado_em')->useCurrent();
            $table->timestamp('finalizado_em')->nullable();
            $table->index(['atendente_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atendimento_atribuicoes');
    }
};
