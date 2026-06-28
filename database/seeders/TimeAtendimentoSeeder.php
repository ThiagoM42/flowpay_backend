<?php

namespace Database\Seeders;

use App\Models\TimeAtendimento;
use Illuminate\Database\Seeder;

class TimeAtendimentoSeeder extends Seeder
{
    public function run(): void
    {
        $times = [
            ['nome' => 'Cartões',     'slug' => 'cartoes'],
            ['nome' => 'Empréstimos', 'slug' => 'emprestimos'],
            ['nome' => 'Outros',      'slug' => 'outros'],
        ];

        foreach ($times as $time) {
            TimeAtendimento::firstOrCreate(['slug' => $time['slug']], $time);
        }
    }
}
