<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atendimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('assunto_id')->constrained('assuntos');
            $table->foreignId('time_atendimento_id')->constrained('times_atendimento');
            $table->enum('status', ['aguardando', 'em_atendimento', 'finalizado', 'cancelado'])->default('aguardando');
            $table->timestamp('criado_em')->useCurrent();
            $table->timestamp('entrou_na_fila_em')->nullable();
            $table->timestamp('iniciado_em')->nullable();
            $table->timestamp('finalizado_em')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atendimentos');
    }
};
