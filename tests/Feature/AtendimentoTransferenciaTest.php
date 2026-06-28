<?php

namespace Tests\Feature;

use App\Models\Assunto;
use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use App\Models\Cliente;
use App\Models\TimeAtendimento;
use App\Services\AtendimentoDistribuicaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtendimentoTransferenciaTest extends TestCase
{
    use RefreshDatabase;

    private TimeAtendimento $time;
    private Assunto $assunto;
    private Atendente $atendente1;
    private Atendente $atendente2;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->time = TimeAtendimento::create(['nome' => 'Cartões', 'slug' => 'cartoes']);
        $this->assunto = Assunto::create(['nome' => 'Bloqueio', 'time_atendimento_id' => $this->time->id]);

        $this->atendente1 = Atendente::create([
            'nome' => 'Ana', 'email' => 'ana@test.com',
            'time_atendimento_id'         => $this->time->id,
            'status'                      => Atendente::STATUS_ONLINE,
            'max_atendimentos_simultaneos' => 3,
        ]);
        $this->atendente2 = Atendente::create([
            'nome' => 'Bruno', 'email' => 'bruno@test.com',
            'time_atendimento_id'         => $this->time->id,
            'status'                      => Atendente::STATUS_ONLINE,
            'max_atendimentos_simultaneos' => 3,
        ]);
        $this->cliente = Cliente::create([
            'nome' => 'João', 'email' => 'joao@test.com',
        ]);
    }

    private function criarEAtribuir(Atendente $atendente): Atendimento
    {
        $atendimento = Atendimento::create([
            'cliente_id'          => $this->cliente->id,
            'assunto_id'          => $this->assunto->id,
            'time_atendimento_id' => $this->time->id,
            'status'              => Atendimento::STATUS_AGUARDANDO,
        ]);
        app(AtendimentoDistribuicaoService::class)->distribuir($atendimento);
        return $atendimento->fresh();
    }

    public function test_transfere_atendimento_para_outro_atendente(): void
    {
        $atendimento = $this->criarEAtribuir($this->atendente1);
        $this->assertEquals($this->atendente1->id, $atendimento->atribuicaoAtiva->atendente_id);

        $response = $this->postJson("/api/v1/atendimentos/{$atendimento->id}/transferir", [
            'atendente_id' => $this->atendente2->id,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('atendimento_atribuicoes', [
            'atendimento_id' => $atendimento->id,
            'atendente_id'   => $this->atendente1->id,
            'status'         => AtendimentoAtribuicao::STATUS_TRANSFERIDO,
        ]);
        $this->assertDatabaseHas('atendimento_atribuicoes', [
            'atendimento_id' => $atendimento->id,
            'atendente_id'   => $this->atendente2->id,
            'status'         => AtendimentoAtribuicao::STATUS_ATIVO,
        ]);
        $this->assertDatabaseHas('atendimento_eventos', [
            'atendimento_id' => $atendimento->id,
            'tipo'           => 'transferido',
        ]);
    }

    public function test_rejeita_transferencia_para_atendente_no_limite(): void
    {
        // Max out atendente2 with direct atribuicoes
        for ($i = 0; $i < 3; $i++) {
            $extra = Atendimento::create([
                'cliente_id'          => $this->cliente->id,
                'assunto_id'          => $this->assunto->id,
                'time_atendimento_id' => $this->time->id,
                'status'              => Atendimento::STATUS_EM_ATENDIMENTO,
                'iniciado_em'         => now(),
            ]);
            AtendimentoAtribuicao::create([
                'atendimento_id' => $extra->id,
                'atendente_id'   => $this->atendente2->id,
                'status'         => AtendimentoAtribuicao::STATUS_ATIVO,
            ]);
        }

        $atendimento = $this->criarEAtribuir($this->atendente1);

        $this->postJson("/api/v1/atendimentos/{$atendimento->id}/transferir", [
            'atendente_id' => $this->atendente2->id,
        ])->assertStatus(422);
    }

    public function test_rejeita_transferencia_de_atendimento_nao_ativo(): void
    {
        $atendimento = Atendimento::create([
            'cliente_id'          => $this->cliente->id,
            'assunto_id'          => $this->assunto->id,
            'time_atendimento_id' => $this->time->id,
            'status'              => Atendimento::STATUS_AGUARDANDO,
        ]);

        $this->postJson("/api/v1/atendimentos/{$atendimento->id}/transferir", [
            'atendente_id' => $this->atendente2->id,
        ])->assertStatus(422);
    }

    public function test_rejeita_transferencia_para_mesmo_atendente(): void
    {
        $atendimento = $this->criarEAtribuir($this->atendente1);

        $this->postJson("/api/v1/atendimentos/{$atendimento->id}/transferir", [
            'atendente_id' => $this->atendente1->id,
        ])->assertStatus(422);
    }
}
