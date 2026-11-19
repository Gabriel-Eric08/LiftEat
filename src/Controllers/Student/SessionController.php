<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Workouts;
use finfo;

/**
 * Execução do treino pelo aluno: iniciar, marcar séries, fotos, finalizar.
 */
final class SessionController
{
    private const MAX_PHOTOS = 6;
    private const MAX_PHOTO_BYTES = 8 * 1024 * 1024;
    private const PHOTO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function start(Request $request, string $id): Response
    {
        $student = Auth::student();
        $workout = Database::select(
            'SELECT * FROM workouts WHERE id = ? AND student_id = ? AND is_active = 1',
            [(int) $id, $student['id']],
        )[0] ?? throw new HttpException(404, 'Esse treino não está disponível.');

        $active = Workouts::activeSession((int) $student['id']);
        if ($active !== null) {
            if ((int) $active['workout_id'] !== (int) $workout['id']) {
                Session::flash('info', 'Você já tem um treino em andamento. Finalize ou descarte antes de começar outro.');
            }

            return redirect("/app/sessao/{$active['id']}");
        }

        Database::execute(
            'INSERT INTO workout_sessions (student_id, workout_id, workout_name, status, started_at) VALUES (?, ?, ?, \'in_progress\', ?)',
            [$student['id'], $workout['id'], $workout['name'], date('Y-m-d H:i:s')],
        );

        return redirect('/app/sessao/' . Database::connection()->lastInsertId());
    }

    public function show(Request $request, string $id): Response
    {
        $session = $this->own((int) $id);

        if ($session['status'] !== 'in_progress') {
            return redirect("/app/historico/{$session['id']}");
        }

        $items = $session['workout_id'] ? Workouts::items((int) $session['workout_id']) : [];

        $saved = [];
        foreach (Database::select('SELECT * FROM session_sets WHERE session_id = ? ORDER BY set_number', [$session['id']]) as $s) {
            $saved[(int) $s['exercise_id']][(int) $s['set_number']] = $s;
        }

        $student = Auth::student();
        $last = Workouts::lastPerformance((int) $student['id'], array_map(static fn ($i) => (int) $i['exercise_id'], $items), (int) $session['id']);

        return view('app/session', [
            'title'    => $session['workout_name'],
            'eyebrow'  => 'Treinando agora',
            'session'  => $session,
            'items'    => $items,
            'saved'    => $saved,
            'last'     => $last,
            'photos'   => Database::select('SELECT id FROM session_photos WHERE session_id = ? ORDER BY id', [$session['id']]),
            'hideNav'  => true,
        ], 'layouts/app');
    }

    /** Salva (ou desmarca) uma série. JSON. */
    public function saveSet(Request $request, string $id): Response
    {
        $session = $this->own((int) $id, true);

        $exerciseId = (int) $request->input('exercise_id', 0);
        $setNumber = (int) $request->input('set_number', 0);
        $reps = $request->input('reps');
        $load = $request->input('load_kg');
        $completed = filter_var($request->input('completed', true), FILTER_VALIDATE_BOOL) ? 1 : 0;

        // O exercício precisa fazer parte do treino desta sessão.
        $valid = Database::select(
            'SELECT id FROM workout_exercises WHERE workout_id = ? AND exercise_id = ?',
            [(int) $session['workout_id'], $exerciseId],
        )[0]['id'] ?? null;

        if ($valid === null || $setNumber < 1 || $setNumber > 30) {
            return Response::json(['error' => 'Série inválida.'], 422);
        }

        $reps = $reps === null || $reps === '' ? null : max(0, min(999, (int) $reps));
        $load = $load === null || $load === '' ? null : max(0, min(999, round((float) str_replace(',', '.', (string) $load), 2)));

        Database::execute(
            'INSERT INTO session_sets (session_id, exercise_id, workout_exercise_id, set_number, reps, load_kg, completed, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE reps = VALUES(reps), load_kg = VALUES(load_kg), completed = VALUES(completed)',
            [$session['id'], $exerciseId, (int) $valid, $setNumber, $reps, $load, $completed, date('Y-m-d H:i:s')],
        );

        $done = (int) Database::select(
            'SELECT COUNT(*) AS c FROM session_sets WHERE session_id = ? AND completed = 1',
            [$session['id']],
        )[0]['c'];

        return Response::json(['ok' => true, 'done' => $done]);
    }

    /** Upload de foto (já comprimida no celular). JSON. */
    public function uploadPhoto(Request $request, string $id): Response
    {
        $session = $this->own((int) $id);
        $file = $request->file('photo');

        $count = (int) Database::select('SELECT COUNT(*) AS c FROM session_photos WHERE session_id = ?', [$session['id']])[0]['c'];
        if ($count >= self::MAX_PHOTOS) {
            return Response::json(['error' => 'Limite de ' . self::MAX_PHOTOS . ' fotos por treino.'], 422);
        }

        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            $tooBig = in_array($file['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);

            return Response::json(['error' => $tooBig ? 'Foto grande demais.' : 'Não foi possível receber a foto.'], 422);
        }

        if ($file['size'] > self::MAX_PHOTO_BYTES) {
            return Response::json(['error' => 'Foto grande demais (máx. 8 MB).'], 422);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $ext = self::PHOTO_TYPES[$mime] ?? null;
        if ($ext === null) {
            return Response::json(['error' => 'Envie uma imagem JPG, PNG ou WEBP.'], 422);
        }

        $relative = "sessions/{$session['id']}/" . bin2hex(random_bytes(10)) . ".{$ext}";
        $target = BASE_PATH . '/storage/uploads/' . $relative;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            return Response::json(['error' => 'Não foi possível salvar a foto.'], 500);
        }

        Database::execute('INSERT INTO session_photos (session_id, file_path) VALUES (?, ?)', [$session['id'], $relative]);
        $photoId = (int) Database::connection()->lastInsertId();

        return Response::json(['id' => $photoId, 'url' => "/media/fotos/{$photoId}"]);
    }

    public function deletePhoto(Request $request, string $id): Response
    {
        $student = Auth::student();
        $photo = Database::select(
            'SELECT sp.* FROM session_photos sp JOIN workout_sessions ws ON ws.id = sp.session_id
             WHERE sp.id = ? AND ws.student_id = ?',
            [(int) $id, $student['id']],
        )[0] ?? throw new HttpException(404);

        Database::execute('DELETE FROM session_photos WHERE id = ?', [$photo['id']]);
        @unlink(BASE_PATH . '/storage/uploads/' . $photo['file_path']);

        return $request->wantsJson() ? Response::json(['ok' => true]) : back('/app/historico');
    }

    public function finish(Request $request, string $id): Response
    {
        $session = $this->own((int) $id, true);
        $notes = mb_substr($request->string('notes'), 0, 2000);

        Database::execute(
            'UPDATE workout_sessions SET status = \'completed\', finished_at = ?, notes = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $notes === '' ? null : $notes, $session['id']],
        );

        return redirect("/app/historico/{$session['id']}?novo=1");
    }

    /** Descarta: sem séries feitas, apaga; com séries, guarda como abandonado. */
    public function discard(Request $request, string $id): Response
    {
        $session = $this->own((int) $id, true);
        $done = (int) Database::select(
            'SELECT COUNT(*) AS c FROM session_sets WHERE session_id = ? AND completed = 1',
            [$session['id']],
        )[0]['c'];

        if ($done === 0) {
            foreach (Database::select('SELECT file_path FROM session_photos WHERE session_id = ?', [$session['id']]) as $p) {
                @unlink(BASE_PATH . '/storage/uploads/' . $p['file_path']);
            }
            Database::execute('DELETE FROM workout_sessions WHERE id = ?', [$session['id']]);
            Session::flash('info', 'Treino descartado.');
        } else {
            Database::execute(
                'UPDATE workout_sessions SET status = \'abandoned\', finished_at = ? WHERE id = ?',
                [date('Y-m-d H:i:s'), $session['id']],
            );
            Session::flash('info', 'Treino encerrado sem concluir. As séries feitas ficaram no histórico.');
        }

        return redirect('/app/treinos');
    }

    /** Detalhe de um treino realizado (também é o resumo logo após finalizar). */
    public function detail(Request $request, string $id): Response
    {
        $own = $this->own((int) $id);
        if ($own['status'] === 'in_progress') {
            return redirect("/app/sessao/{$own['id']}");
        }

        $session = Workouts::sessionDetail((int) $own['id']);

        return view('app/session-detail', [
            'title'        => $session['workout_name'],
            'eyebrow'      => date_br($session['started_at'], 'd/m/Y'),
            'heading'      => $session['workout_name'],
            'session'      => $session,
            'celebrate'    => $request->query('novo') === '1' && $session['status'] === 'completed',
            'headerAction' => '<a href="/app/historico" class="btn btn-ghost btn-icon" aria-label="Voltar">' . icon('arrow-left') . '</a>',
        ], 'layouts/app');
    }

    /** @return array<string, mixed> sessão do aluno logado */
    private function own(int $id, bool $mustBeActive = false): array
    {
        $student = Auth::student();
        $session = Database::select(
            'SELECT * FROM workout_sessions WHERE id = ? AND student_id = ?',
            [$id, $student['id']],
        )[0] ?? throw new HttpException(404, 'Treino não encontrado.');

        if ($mustBeActive && $session['status'] !== 'in_progress') {
            throw new HttpException(404, 'Esse treino já foi encerrado.');
        }

        return $session;
    }
}
