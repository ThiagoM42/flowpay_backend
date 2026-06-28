<?php

namespace Database\Seeders;

use App\Models\Atendente;
use App\Models\TimeAtendimento;
use Illuminate\Database\Seeder;

class AtendenteSeeder extends Seeder
{
    public function run(): void
    {
        $atendentes = [
            ['nome' => 'Ana Silva',   'email' => 'ana@flowpay.com',   'time' => 'cartoes'],
            ['nome' => 'Bruno Costa', 'email' => 'bruno@flowpay.com', 'time' => 'cartoes'],
            ['nome' => 'Carla Nunes', 'email' => 'carla@flowpay.com', 'time' => 'emprestimos'],
            ['nome' => 'Diego Rocha', 'email' => 'diego@flowpay.com', 'time' => 'emprestimos'],
            ['nome' => 'Eva Martins', 'email' => 'eva@flowpay.com',   'time' => 'outros'],
        ];

        foreach ($atendentes as $data) {
            $time = TimeAtendimento::where('slug', $data['time'])->firstOrFail();

            Atendente::firstOrCreate(
                ['email' => $data['email']],
                [
                    'nome'                       => $data['nome'],
                    'time_atendimento_id'        => $time->id,
                    'status'                     => Atendente::STATUS_OFFLINE,
                    'max_atendimentos_simultaneos' => 3,
                ]
            );
        }
    }
}
