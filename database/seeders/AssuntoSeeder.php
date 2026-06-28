<?php

namespace Database\Seeders;

use App\Models\Assunto;
use App\Models\TimeAtendimento;
use Illuminate\Database\Seeder;

class AssuntoSeeder extends Seeder
{
    public function run(): void
    {
        $assuntos = [
            'cartoes'     => ['Segunda via de cartão', 'Bloqueio de cartão', 'Limite de crédito'],
            'emprestimos' => ['Simulação de empréstimo', 'Renegociação de dívida', 'Quitação antecipada'],
            'outros'      => ['Dados cadastrais', 'Extrato de conta', 'Reclamação geral'],
        ];

        foreach ($assuntos as $slug => $nomes) {
            $time = TimeAtendimento::where('slug', $slug)->firstOrFail();

            foreach ($nomes as $nome) {
                Assunto::firstOrCreate(
                    ['nome' => $nome, 'time_atendimento_id' => $time->id],
                    ['ativo' => true]
                );
            }
        }
    }
}
