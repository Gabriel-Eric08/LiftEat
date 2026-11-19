<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;

/**
 * Seções do painel ainda em construção (treinos, exercícios, dietas).
 */
final class ComingSoonController
{
    private const SECTIONS = [
        'treinos' => [
            'title' => 'Treinos',
            'icon'  => 'dumbbell',
            'text'  => 'Monte a ficha de cada aluno por dia da semana, com séries, repetições, carga e descanso.',
        ],
        'exercicios' => [
            'title' => 'Exercícios',
            'icon'  => 'list-checks',
            'text'  => 'Sua biblioteca de exercícios: cadastre uma vez com vídeo e dicas e reutilize em todos os treinos.',
        ],
        'dietas' => [
            'title' => 'Dietas',
            'icon'  => 'salad',
            'text'  => 'Planos alimentares com refeições, horários e opções de substituição para cada aluno.',
        ],
    ];

    public function show(Request $request): Response
    {
        $key = basename($request->path());
        $section = self::SECTIONS[$key] ?? self::SECTIONS['treinos'];

        return view('admin/coming-soon', ['title' => $section['title'], 'section' => $section], 'layouts/admin');
    }
}
