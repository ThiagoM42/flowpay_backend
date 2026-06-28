<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('atendente')->after('password');
            $table->foreignId('time_atendimento_id')
                ->nullable()
                ->after('role')
                ->constrained('times_atendimento')
                ->nullOnDelete();
            $table->foreignId('atendente_id')
                ->nullable()
                ->after('time_atendimento_id')
                ->constrained('atendentes')
                ->nullOnDelete();
        });

        DB::table('users')
            ->where('email', 'admin@hotmail.com')
            ->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('atendente_id');
            $table->dropConstrainedForeignId('time_atendimento_id');
            $table->dropColumn('role');
        });
    }
};