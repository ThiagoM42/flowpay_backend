<?php

namespace Tests\Feature;

use App\Models\Assunto;
use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\Cliente;
use App\Models\TimeAtendimento;
use App\Services\AtendimentoDistribuicaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_retorna_indicadores_corretos(): void
    {
        $time    = TimeAtendimento::create(['nome' => 'Cartões', 'slug' => 'cartoes']);
        $assunto = Assunto::create(['nome' => 'Bloqueio', 'time_atendimento_id' => $time->id]);
        $atendente = Atendente::create([
            'nome'                        => 'Ana',
            'email'                       => 'ana@test.com',
            'time_atendimento_id'         => $time->id,
            'status'                      => Atendente::STATUS_ONLINE,
            'max_atendimentos_simultaneos' => 3,
        ]);
        $cliente = Cliente::create([
            'nome' => 'João', 'email' => 'joao@test.com', 'documento' => '11111111111',
        ]);

        $atendimento = Atendimento::create([
            'cliente_id'          => $cliente->id,
            'assunto_id'          => $assunto->id,
            'time_atendimento_id' => $time->id,
            'status'              => Atendimento::STATUS_AGUARDANDO,
        ]);
        app(AtendimentoDistribuicaoService::class)->distribuir($atendimento);

        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'total_criados_hoje',
                     'em_andamento',
                     'aguardando',
                     'finalizados_hoje',
                     'tempo_medio_espera_minutos',
                     'tempo_medio_atendimento_minutos',
                     'atendentes_online',
                     'volume_por_time',
                     'volume_por_assunto',
                 ])
                 ->assertJsonPath('total_criados_hoje', 1)
                 ->assertJsonPath('em_andamento', 1)
                 ->assertJsonPath('aguardando', 0);

        $onlineList = $response->json('atendentes_online');
        $this->assertCount(1, $onlineList);
        $this->assertEquals($atendente->id, $onlineList[0]['id']);
        $this->assertEquals(1, $onlineList[0]['ativas_count']);
    }

    public function test_dashboard_retorna_estrutura_quando_vazio(): void
    {
        $response = $this->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
                 ->assertJsonPath('total_criados_hoje', 0)
                 ->assertJsonPath('em_andamento', 0)
                 ->assertJsonPath('aguardando', 0)
                 ->assertJsonPath('finalizados_hoje', 0)
                 ->assertJsonPath('tempo_medio_espera_minutos', null)
                 ->assertJsonPath('tempo_medio_atendimento_minutos', null)
                 ->assertJsonPath('atendentes_online', [])
                 ->assertJsonPath('volume_por_time', [])
                 ->assertJsonPath('volume_por_assunto', []);
    }
}
