<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $assuntos = DB::table('assuntos')
            ->where('ativo', true)
            ->orderBy('id')
            ->limit(3)
            ->get(['id', 'nome', 'time_atendimento_id']);

        $now = now();

        foreach ($assuntos as $index => $assunto) {
            DB::table('atendentes')->updateOrInsert(
                ['email' => 'atendente.ficticio.'.($index + 1).'@flowpay.local'],
                [
                    'nome' => 'Atendente Ficticio - '.$assunto->nome,
                    'time_atendimento_id' => $assunto->time_atendimento_id,
                    'status' => 'online',
                    'max_atendimentos_simultaneos' => 3,
                    'ativo' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('atendentes')
            ->whereIn('email', [
                'atendente.ficticio.1@flowpay.local',
                'atendente.ficticio.2@flowpay.local',
                'atendente.ficticio.3@flowpay.local',
            ])
            ->delete();
    }
};
