# FlowPay — Backend de Atendimento

API REST + painel administrativo para gerenciamento de atendimentos ao cliente, construído com Laravel 11 e Filament 5.

## O que faz

Quando um cliente abre um atendimento, o sistema encontra automaticamente o atendente mais ocioso do departamento responsável pelo assunto escolhido e faz a atribuição. Se todos os atendentes estiverem lotados (3 atendimentos simultâneos), o cliente entra na fila e é atribuído assim que um slot liberar. Cada atendimento dura 30 segundos e é finalizado automaticamente.

## Stack

- **Laravel 11** — API e lógica de negócio
- **Filament 5** — painel administrativo
- **PostgreSQL** — banco de dados
- **Redis** — cache e filas (fallback: `database`)

## Rodando com Docker

```bash
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan queue:work
```

Painel admin: `http://localhost/admin`

## API

Base URL: `http://localhost/api/v1`

| Método | Rota | Descrição |
|--------|------|-----------|
| `POST` | `/atendimentos` | Abre um novo atendimento |
| `GET` | `/atendimentos/{id}` | Retorna detalhes do atendimento |
| `POST` | `/atendimentos/{id}/finalizar` | Finaliza o atendimento |
| `POST` | `/atendimentos/{id}/transferir` | Transfere para outro atendente |
| `GET` | `/dashboard` | Métricas gerais |

### Criar atendimento

```bash
POST /api/v1/atendimentos
Content-Type: application/json

{
  "nome": "João Silva",
  "email": "joao@email.com",
  "telefone": "11999999999",  # opcional
  "assunto_id": 1
}
```

Retorna `201` com o atendimento e atendente atribuído, ou `503` se nenhum atendente estiver disponível.

## Fluxo de status

```
aguardando → em_atendimento → finalizado
                           ↘ cancelado
```

- `aguardando` — na fila, sem atendente disponível
- `em_atendimento` — atribuído a um atendente (finaliza automaticamente em 30s)
- `finalizado` / `cancelado` — encerrado

## Comandos úteis

```bash
# Processar fila manualmente
php artisan atendimento:processar-filas

# Rodar scheduler (processa fila a cada minuto)
php artisan schedule:run
```
