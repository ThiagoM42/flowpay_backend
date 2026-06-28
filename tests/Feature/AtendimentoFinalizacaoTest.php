<?php

namespace Tests\Feature;

use App\Models\Assunto;
use App\Models\Atendente;
use App\Models\Atendimento;
use App\Models\AtendimentoAtribuicao;
use App\Models\Cliente;
use App\Models\FilaAtendimento;
use App\Models\TimeAtendimento;
use App\Services\AtendimentoDistribuicaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtendimentoFinalizacaoTest extends TestCase
{
    use RefreshDatabase;

    private TimeAtendimento $time;
    private Assunto $assunto;
    private Atendente $atendente;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->time = TimeAtendimento::create(['nome' => 'Cartões', 'slug' => 'cartoes']);
        $this->assunto = Assunto::create(['nome' => 'Bloqueio', 'time_atendimento_id' => $this->time->id]);
        $this->atendente = Atendente::create([
            'nome'                        => 'Ana',
            'email'                       => 'ana@test.com',
            'time_atendimento_id'         => $this->time->id,
            'status'                      => Atendente::STATUS_ONLINE,
            'max_atendimentos_simultaneos' => 1,
        ]);
        $this->cliente = Cliente::create([
            'nome'  => 'João Silva',
            'email' => 'joao@test.com',
        ]);
    }

    private function criarEDistribuir(): Atendimento
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

    public function test_finalizar_atendimento_via_api(): void
    {
        $atendimento = $this->criarEDistribuir();
        $this->assertEquals(Atendimento::STATUS_EM_ATENDIMENTO, $atendimento->status);

        $response = $this->postJson("/api/v1/atendimentos/{$atendimento->id}/finalizar");

        $response->assertStatus(200)
                 ->assertJsonPath('status', Atendimento::STATUS_FINALIZADO);

        $atendimento->refresh();
        $this->assertNotNull($atendimento->finalizado_em);
        $this->assertDatabaseHas('atendimento_atribuicoes', [
            'atendimento_id' => $atendimento->id,
            'status'         => AtendimentoAtribuicao::STATUS_FINALIZADO,
        ]);
        $this->assertDatabaseHas('atendimento_eventos', [
            'atendimento_id' => $atendimento->id,
            'tipo'           => 'finalizado',
        ]);
    }

    public function test_finalizar_consome_fila_e_atribui_proximo(): void
    {
        // Attendant has max=1: first is assigned, second goes to queue
        $primeiro = $this->criarEDistribuir();
        $this->assertEquals(Atendimento::STATUS_EM_ATENDIMENTO, $primeiro->status);

        $segundo = Atendimento::create([
            'cliente_id'          => $this->cliente->id,
            'assunto_id'          => $this->assunto->id,
            'time_atendimento_id' => $this->time->id,
            'status'              => Atendimento::STATUS_AGUARDANDO,
        ]);
        app(AtendimentoDistribuicaoService::class)->distribuir($segundo);
        $segundo->refresh();
        $this->assertEquals(Atendimento::STATUS_AGUARDANDO, $segundo->status);

        // Finalize first → job runs synchronously (QUEUE_CONNECTION=sync) → second gets assigned
        $this->postJson("/api/v1/atendimentos/{$primeiro->id}/finalizar")->assertStatus(200);

        $segundo->refresh();
        $this->assertEquals(Atendimento::STATUS_EM_ATENDIMENTO, $segundo->status);
        $this->assertDatabaseHas('atendimento_atribuicoes', [
            'atendimento_id' => $segundo->id,
            'atendente_id'   => $this->atendente->id,
            'status'         => AtendimentoAtribuicao::STATUS_ATIVO,
        ]);
        $this->assertDatabaseHas('fila_atendimentos', [
            'atendimento_id' => $segundo->id,
            'status'         => FilaAtendimento::STATUS_PROCESSADO,
        ]);
    }

    public function test_retorna_422_ao_finalizar_atendimento_nao_ativo(): void
    {
        $atendimento = Atendimento::create([
            'cliente_id'          => $this->cliente->id,
            'assunto_id'          => $this->assunto->id,
            'time_atendimento_id' => $this->time->id,
            'status'              => Atendimento::STATUS_AGUARDANDO,
        ]);

        $this->postJson("/api/v1/atendimentos/{$atendimento->id}/finalizar")
             ->assertStatus(422)
             ->assertJsonPath('error', 'Atendimento não está em andamento.');
    }
}
