<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assunto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssuntoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Assunto::query();

        if ($request->has('ativo')) {
            $query->where('ativo', filter_var($request->ativo, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('time_atendimento_id')) {
            $query->where('time_atendimento_id', $request->time_atendimento_id);
        }

        return response()->json($query->orderBy('nome')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'time_atendimento_id' => 'required|integer|exists:times_atendimento,id',
            'ativo' => 'boolean',
        ]);

        $assunto = Assunto::create($validated);

        return response()->json($assunto->load('time'), 201);
    }

    public function show(Assunto $assunto): JsonResponse
    {
        return response()->json($assunto->load('time'));
    }

    public function update(Request $request, Assunto $assunto): JsonResponse
    {
        $validated = $request->validate([
            'nome' => 'sometimes|string|max:255',
            'time_atendimento_id' => 'sometimes|integer|exists:times_atendimento,id',
            'ativo' => 'sometimes|boolean',
        ]);

        $assunto->update($validated);

        return response()->json($assunto->fresh()->load('time'));
    }

    public function destroy(Assunto $assunto): JsonResponse
    {
        if ($assunto->atendimentos()->exists()) {
            return response()->json(
                ['error' => 'Não é possível excluir um assunto com atendimentos vinculados.'],
                422
            );
        }

        $assunto->delete();

        return response()->json(null, 204);
    }
}
