<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atendentes', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('email')->unique();
            $table->foreignId('time_atendimento_id')->constrained('times_atendimento');
            $table->enum('status', ['online', 'offline', 'pausado'])->default('offline');
            $table->unsignedTinyInteger('max_atendimentos_simultaneos')->default(3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atendentes');
    }
};
