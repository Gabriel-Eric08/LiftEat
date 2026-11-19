<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Telas de erro personalizadas (HTML) ou JSON, conforme a requisição.
 */
final class ErrorPage
{
    /** @var array<int, array{icon: string, title: string, message: string}> */
    private const PAGES = [
        403 => [
            'icon'    => 'shield-alert',
            'title'   => 'Área restrita',
            'message' => 'Essa porta é só para quem tem a chave. Se acha que deveria ter acesso, fale com seu personal.',
        ],
        404 => [
            'icon'    => 'compass',
            'title'   => 'Saiu da ficha',
            'message' => 'Essa página não existe ou mudou de lugar. Bora voltar para o treino?',
        ],
        405 => [
            'icon'    => 'ban',
            'title'   => 'Movimento inválido',
            'message' => 'Essa ação não pode ser feita desse jeito.',
        ],
        419 => [
            'icon'    => 'hourglass',
            'title'   => 'Sessão expirada',
            'message' => 'Você ficou um tempo parado e o formulário expirou. Volte, recarregue a página e tente de novo.',
        ],
        429 => [
            'icon'    => 'timer',
            'title'   => 'Calma, respira',
            'message' => 'Muitas tentativas em pouco tempo. Descanse uns minutos, como entre as séries, e tente de novo.',
        ],
        500 => [
            'icon'    => 'server-crash',
            'title'   => 'Deu cãibra no servidor',
            'message' => 'Algo deu errado do nosso lado. Já ficou registrado; tente de novo em instantes.',
        ],
        503 => [
            'icon'    => 'refresh-cw',
            'title'   => 'Em manutenção',
            'message' => 'Estamos ajustando alguns pesos. Volte daqui a pouco.',
        ],
    ];

    /** @param array<string, string> $headers */
    public static function response(
        int $status,
        Request $request,
        string $message = '',
        array $headers = [],
        ?Throwable $debug = null,
    ): Response {
        $page = self::PAGES[$status] ?? self::PAGES[500];
        $message = $message !== '' ? $message : $page['message'];

        if ($request->wantsJson()) {
            $response = Response::json(['error' => $page['title'], 'message' => $message], $status);
        } else {
            $area = str_starts_with($request->path(), '/admin') ? 'admin' : 'app';
            $home = $area === 'admin' ? '/admin' : '/app';

            try {
                $html = View::render('errors/error', [
                    'title'   => $page['title'],
                    'status'  => $status,
                    'icon'    => $page['icon'],
                    'heading' => $page['title'],
                    'message' => $message,
                    'home'    => $home,
                    'debug'   => $debug,
                ], 'layouts/blank');
            } catch (Throwable) {
                // Último recurso caso a própria view quebre.
                $html = '<h1>' . $status . '</h1><p>' . e($message) . '</p>';
            }

            $response = Response::html($html, $status);
        }

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
