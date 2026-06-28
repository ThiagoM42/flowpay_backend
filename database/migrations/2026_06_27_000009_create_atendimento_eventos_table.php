<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atendimento_eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atendimento_id')->constrained('atendimentos');
            $table->string('tipo', 50);
            $table->string('descricao');
            $table->json('dados')->nullable();
            $table->timestamp('criado_em')->useCurrent();
            $table->index(['atendimento_id', 'criado_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atendimento_eventos');
    }
};
