<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use finfo;

/**
 * Entrega arquivos privados (storage/uploads), só para quem pode vê-los.
 */
final class MediaController
{
    /** Foto de treino: visível para o personal e para o próprio aluno. */
    public function photo(Request $request, string $id): Response
    {
        $photo = Database::select(
            'SELECT sp.file_path, ws.student_id FROM session_photos sp
             JOIN workout_sessions ws ON ws.id = sp.session_id WHERE sp.id = ?',
            [(int) $id],
        )[0] ?? throw new HttpException(404);

        $student = Auth::student();
        $allowed = Auth::admin() !== null || ($student !== null && (int) $student['id'] === (int) $photo['student_id']);
        if (!$allowed) {
            throw new HttpException(404);
        }

        $path = realpath(BASE_PATH . '/storage/uploads/' . $photo['file_path']);
        $root = realpath(BASE_PATH . '/storage/uploads');
        if ($path === false || $root === false || !str_starts_with($path, $root) || !is_file($path)) {
            throw new HttpException(404);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';

        return (new Response((string) file_get_contents($path), 200, [
            'Content-Type'   => $mime,
            'Content-Length' => (string) filesize($path),
        ]))
            // Cache só no navegador do usuário (privado), nunca em proxy.
            ->withHeader('Cache-Control', 'private, max-age=604800')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
