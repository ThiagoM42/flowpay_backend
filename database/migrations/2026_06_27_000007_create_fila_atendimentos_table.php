<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fila_atendimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atendimento_id')->constrained('atendimentos');
            $table->foreignId('time_atendimento_id')->constrained('times_atendimento');
            $table->enum('status', ['aguardando', 'processado', 'cancelado'])->default('aguardando');
            $table->timestamp('entrou_em')->useCurrent();
            $table->index(['time_atendimento_id', 'status', 'entrou_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fila_atendimentos');
    }
};
