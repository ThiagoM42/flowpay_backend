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

class AtendimentoDistribuicaoTest extends TestCase
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
            'max_atendimentos_simultaneos' => 3,
        ]);
        $this->cliente = Cliente::create([
            'nome'      => 'João Silva',
            'email'     => 'joao@test.com',
            'documento' => '12345678901',
        ]);
    }

    private function criarAtendimento(): Atendimento
    {
        return Atendimento::create([
            'cliente_id'          => $this->cliente->id,
            'assunto_id'          => $this->assunto->id,
            'time_atendimento_id' => $this->time->id,
            'status'              => Atendimento::STATUS_AGUARDANDO,
        ]);
    }

    public function test_distribui_diretamente_quando_atendente_esta_disponivel(): void
    {
        $atendimento = $this->criarAtendimento();

        app(AtendimentoDistribuicaoService::class)->distribuir($atendimento);

        $atendimento->refresh();
        $this->assertEquals(Atendimento::STATUS_EM_ATENDIMENTO, $atendimento->status);
        $this->assertNotNull($atendimento->iniciado_em);
        $this->assertDatabaseHas('atendimento_atribuicoes', [
            'atendimento_id' => $atendimento->id,
            'atendente_id'   => $this->atendente->id,
            'status'         => AtendimentoAtribuicao::STATUS_ATIVO,
        ]);
        $this->assertDatabaseHas('atendimento_eventos', [
            'atendimento_id' => $atendimento->id,
            'tipo'           => 'atribuido',
        ]);
    }

    public function test_enfileira_quando_nao_ha_atendente_disponivel(): void
    {
        // Max out the attendant (max=3)
        for ($i = 0; $i < 3; $i++) {
            $a = $this->criarAtendimento();
            app(AtendimentoDistribuicaoService::class)->distribuir($a);
        }

        $atendimentoNaFila = $this->criarAtendimento();
        app(AtendimentoDistribuicaoService::class)->distribuir($atendimentoNaFila);

        $atendimentoNaFila->refresh();
        $this->assertEquals(Atendimento::STATUS_AGUARDANDO, $atendimentoNaFila->status);
        $this->assertNotNull($atendimentoNaFila->entrou_na_fila_em);
        $this->assertDatabaseHas('fila_atendimentos', [
            'atendimento_id'      => $atendimentoNaFila->id,
            'time_atendimento_id' => $this->time->id,
            'status'              => FilaAtendimento::STATUS_AGUARDANDO,
        ]);
        $this->assertDatabaseHas('atendimento_eventos', [
            'atendimento_id' => $atendimentoNaFila->id,
            'tipo'           => 'enfileirado',
        ]);
    }

    public function test_bloqueia_atribuicao_quando_limite_ja_atingido(): void
    {
        // Max out the attendant
        for ($i = 0; $i < 3; $i++) {
            $a = $this->criarAtendimento();
            app(AtendimentoDistribuicaoService::class)->distribuir($a);
        }

        $atendimento = $this->criarAtendimento();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/limite/i');

        app(AtendimentoDistribuicaoService::class)->atribuirAtendente($atendimento, $this->atendente);
    }

    public function test_atendente_offline_nao_recebe_atendimento(): void
    {
        $this->atendente->update(['status' => Atendente::STATUS_OFFLINE]);

        $atendimento = $this->criarAtendimento();
        app(AtendimentoDistribuicaoService::class)->distribuir($atendimento);

        $atendimento->refresh();
        $this->assertEquals(Atendimento::STATUS_AGUARDANDO, $atendimento->status);
        $this->assertDatabaseHas('fila_atendimentos', ['atendimento_id' => $atendimento->id]);
    }

    public function test_cria_via_api_e_distribui_diretamente(): void
    {
        $response = $this->postJson('/api/v1/atendimentos', [
            'nome'       => 'Maria Souza',
            'email'      => 'maria@test.com',
            'documento'  => '98765432100',
            'assunto_id' => $this->assunto->id,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', Atendimento::STATUS_EM_ATENDIMENTO);
    }
}
