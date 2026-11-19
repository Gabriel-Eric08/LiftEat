<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\Workouts;

final class WorkoutController
{
    /** Visão geral: quem está sem treino, quem está treinando e o que foi feito recentemente. */
    public function overview(Request $request): Response
    {
        $without = Database::select(
            'SELECT s.id, s.name, s.goal, s.access_until FROM students s
             WHERE s.deleted_at IS NULL AND s.status = \'active\'
               AND NOT EXISTS (SELECT 1 FROM workout_schedule ws JOIN workouts w ON w.id = ws.workout_id
                               WHERE ws.student_id = s.id AND w.is_active = 1)
             ORDER BY s.name'
        );

        $live = Database::select(
            'SELECT ws.id, ws.workout_name, ws.started_at, s.id AS student_id, s.name,
                    (SELECT COUNT(*) FROM session_sets ss WHERE ss.session_id = ws.id AND ss.completed = 1) AS sets_done
             FROM workout_sessions ws JOIN students s ON s.id = ws.student_id
             WHERE ws.status = \'in_progress\' AND ws.started_at >= ?
             ORDER BY ws.started_at DESC',
            [date('Y-m-d H:i:s', time() - 6 * 3600)],
        );

        $recent = Database::select(
            'SELECT ws.*, s.name AS student_name,
                    (SELECT COUNT(*) FROM session_sets ss WHERE ss.session_id = ws.id AND ss.completed = 1) AS sets_done,
                    (SELECT COUNT(*) FROM session_photos sp WHERE sp.session_id = ws.id) AS photos
             FROM workout_sessions ws JOIN students s ON s.id = ws.student_id
             WHERE ws.status <> \'in_progress\'
             ORDER BY ws.started_at DESC LIMIT 30'
        );

        $stats = Database::select(
            'SELECT
                COALESCE(SUM(status = \'completed\' AND started_at >= ?), 0) AS today,
                COALESCE(SUM(status = \'completed\' AND started_at >= ?), 0) AS week,
                COUNT(DISTINCT CASE WHEN status = \'completed\' AND started_at >= ? THEN student_id END) AS active_students
             FROM workout_sessions',
            [date('Y-m-d 00:00:00'), date('Y-m-d 00:00:00', strtotime('monday this week')), date('Y-m-d 00:00:00', strtotime('-7 days'))],
        )[0];

        $withWorkout = (int) Database::select(
            'SELECT COUNT(DISTINCT ws.student_id) AS c FROM workout_schedule ws
             JOIN workouts w ON w.id = ws.workout_id JOIN students s ON s.id = ws.student_id
             WHERE w.is_active = 1 AND s.deleted_at IS NULL AND s.status = \'active\''
        )[0]['c'];

        return view('admin/workouts/overview', [
            'title'       => 'Treinos',
            'without'     => $without,
            'live'        => $live,
            'recent'      => $recent,
            'stats'       => $stats,
            'withWorkout' => $withWorkout,
        ], 'layouts/admin');
    }

    /** Cria um treino (vazio ou copiado de outro) e abre o montador. */
    public function store(Request $request, string $id): Response
    {
        $student = $this->student((int) $id);
        $name = $request->string('name');

        if ($name === '' || mb_strlen($name) > 120) {
            Session::flash('error', 'Dê um nome ao treino (até 120 caracteres).');

            return redirect("/admin/alunos/{$student['id']}?aba=treinos");
        }

        $templateId = (int) $request->input('template_id', 0);
        if ($templateId > 0 && Workouts::find($templateId) !== null) {
            $id = Workouts::duplicate($templateId, (int) $student['id'], $name);
        } else {
            Database::execute('INSERT INTO workouts (student_id, name) VALUES (?, ?)', [$student['id'], $name]);
            $id = (int) Database::connection()->lastInsertId();
        }

        return redirect("/admin/treinos/{$id}/editar");
    }

    public function edit(Request $request, string $id): Response
    {
        $workout = $this->workout((int) $id);
        $student = $this->student((int) $workout['student_id']);

        $library = Database::select(
            'SELECT id, name, muscle_group, equipment FROM exercises WHERE is_active = 1 ORDER BY muscle_group, name'
        );

        // Em quais dias há OUTRO treino (para avisar que será substituído).
        $otherDays = [];
        foreach (Workouts::schedule((int) $student['id'], false) as $day => $w) {
            if ((int) $w['id'] !== (int) $workout['id']) {
                $otherDays[$day] = $w['name'];
            }
        }

        return view('admin/workouts/builder', [
            'title'       => $workout['name'],
            'breadcrumbs' => ['Alunos' => '/admin/alunos', $student['name'] => "/admin/alunos/{$student['id']}?aba=treinos", 'Montar treino' => null],
            'workout'     => $workout,
            'student'     => $student,
            'items'       => Workouts::items((int) $workout['id']),
            'days'        => Workouts::days((int) $workout['id']),
            'otherDays'   => $otherDays,
            'library'     => $library,
            'groups'      => Workouts::MUSCLE_GROUPS,
            'techniques'  => Workouts::TECHNIQUES,
            'students'    => Database::select('SELECT id, name FROM students WHERE deleted_at IS NULL ORDER BY name'),
            'scripts'     => [asset('assets/js/workout-builder.js')],
        ], 'layouts/admin');
    }

    public function update(Request $request, string $id): Response
    {
        $workout = $this->workout((int) $id);
        $back = "/admin/treinos/{$workout['id']}/editar";

        $name = $request->string('name');
        $notes = $request->string('notes');
        $days = array_map('intval', (array) $request->input('days', []));
        $raw = json_decode((string) $request->input('items', '[]'), true);

        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors[] = 'Dê um nome ao treino (até 120 caracteres).';
        }

        $items = [];
        $seen = [];
        $exerciseIds = array_map('intval', array_column(Database::select('SELECT id FROM exercises'), 'id'));

        foreach (is_array($raw) ? $raw : [] as $i => $row) {
            $n = $i + 1;
            $exerciseId = (int) ($row['exercise_id'] ?? 0);
            if (!in_array($exerciseId, $exerciseIds, true)) {
                $errors[] = "Exercício {$n}: não encontrado na biblioteca.";
                continue;
            }
            if (isset($seen[$exerciseId])) {
                $errors[] = "Exercício {$n}: já está no treino. Aumente as séries em vez de repetir.";
                continue;
            }
            $seen[$exerciseId] = true;

            $sets = (int) ($row['sets'] ?? 0);
            $reps = trim((string) ($row['reps'] ?? ''));
            $load = trim((string) ($row['load_kg'] ?? ''));
            $rest = trim((string) ($row['rest_seconds'] ?? ''));

            if ($sets < 1 || $sets > 20) {
                $errors[] = "Exercício {$n}: séries entre 1 e 20.";
            }
            if ($reps === '' || mb_strlen($reps) > 20) {
                $errors[] = "Exercício {$n}: informe as repetições (ex.: 10, 8-12, até a falha).";
            }
            $loadValue = $load === '' ? null : (float) Validator::decimal($load);
            if ($load !== '' && (!is_numeric(Validator::decimal($load)) || $loadValue < 0 || $loadValue > 999)) {
                $errors[] = "Exercício {$n}: carga inválida.";
            }
            $restValue = $rest === '' ? null : (int) $rest;
            if ($restValue !== null && ($restValue < 0 || $restValue > 900)) {
                $errors[] = "Exercício {$n}: descanso entre 0 e 900 segundos.";
            }

            $technique = mb_substr(trim((string) ($row['technique'] ?? '')), 0, 60);
            $itemNotes = mb_substr(trim((string) ($row['notes'] ?? '')), 0, 255);

            $items[] = [
                'id'           => (int) ($row['id'] ?? 0),
                'exercise_id'  => $exerciseId,
                'sets'         => $sets,
                'reps'         => $reps,
                'load_kg'      => $loadValue,
                'rest_seconds' => $restValue,
                'technique'    => $technique === '' ? null : $technique,
                'notes'        => $itemNotes === '' ? null : $itemNotes,
            ];
        }

        if ($errors !== []) {
            Session::flash('error', implode(' ', array_slice($errors, 0, 3)));
            Session::flash('builder_draft', ['name' => $name, 'notes' => $notes, 'days' => $days, 'items' => $raw]);

            return redirect($back);
        }

        Workouts::save((int) $workout['id'], (int) $workout['student_id'], $name, $notes === '' ? null : $notes, $items, $days);
        Session::flash('success', 'Treino salvo.');

        return redirect($request->input('after') === 'aluno' ? "/admin/alunos/{$workout['student_id']}?aba=treinos" : $back);
    }

    public function duplicate(Request $request, string $id): Response
    {
        $workout = $this->workout((int) $id);
        $target = $this->student((int) $request->input('student_id', $workout['student_id']));
        $sameStudent = (int) $target['id'] === (int) $workout['student_id'];

        $newId = Workouts::duplicate((int) $workout['id'], (int) $target['id'], $sameStudent ? $workout['name'] . ' (cópia)' : null);
        Session::flash('success', $sameStudent
            ? 'Treino duplicado. Ajuste o que precisar.'
            : 'Treino copiado para ' . first_name($target['name']) . '. Defina os dias dele.');

        return redirect("/admin/treinos/{$newId}/editar");
    }

    public function toggle(Request $request, string $id): Response
    {
        $workout = $this->workout((int) $id);
        $active = $workout['is_active'] ? 0 : 1;
        Database::execute('UPDATE workouts SET is_active = ? WHERE id = ?', [$active, $workout['id']]);
        Session::flash('success', $active ? 'Treino reativado.' : 'Treino arquivado. Ele some do app do aluno, mas o histórico continua.');

        return redirect("/admin/alunos/{$workout['student_id']}?aba=treinos");
    }

    public function destroy(Request $request, string $id): Response
    {
        $workout = $this->workout((int) $id);
        Database::execute('DELETE FROM workouts WHERE id = ?', [$workout['id']]);
        Session::flash('success', "{$workout['name']} excluído. Treinos já realizados continuam no histórico.");

        return redirect("/admin/alunos/{$workout['student_id']}?aba=treinos");
    }

    /** Agenda da semana do aluno: day_1..day_7 => workout_id ou vazio (descanso). */
    public function schedule(Request $request, string $id): Response
    {
        $student = $this->student((int) $id);
        $valid = array_map('intval', array_column(
            Database::select('SELECT id FROM workouts WHERE student_id = ?', [$student['id']]),
            'id',
        ));

        $pdo = Database::connection();
        $pdo->beginTransaction();
        Database::execute('DELETE FROM workout_schedule WHERE student_id = ?', [$student['id']]);
        for ($day = 1; $day <= 7; $day++) {
            $wid = (int) $request->input("day_{$day}", 0);
            if ($wid > 0 && in_array($wid, $valid, true)) {
                Database::execute(
                    'INSERT INTO workout_schedule (student_id, weekday, workout_id) VALUES (?, ?, ?)',
                    [$student['id'], $day, $wid],
                );
            }
        }
        $pdo->commit();

        if ($request->wantsJson()) {
            return Response::json(['ok' => true]);
        }

        Session::flash('success', 'Agenda da semana atualizada.');

        return redirect("/admin/alunos/{$student['id']}?aba=treinos");
    }

    /** Detalhe de um treino realizado pelo aluno. */
    public function session(Request $request, string $id): Response
    {
        $session = Workouts::sessionDetail((int) $id) ?? throw new HttpException(404, 'Treino realizado não encontrado.');

        return view('admin/workouts/session', [
            'title'       => $session['workout_name'],
            'breadcrumbs' => ['Alunos' => '/admin/alunos', $session['student_name'] => "/admin/alunos/{$session['student_id']}?aba=treinos", 'Treino realizado' => null],
            'session'     => $session,
        ], 'layouts/admin');
    }

    /** @return array<string, mixed> */
    private function workout(int $id): array
    {
        return Workouts::find($id) ?? throw new HttpException(404, 'Treino não encontrado.');
    }

    /** @return array<string, mixed> */
    private function student(int $id): array
    {
        return Database::select('SELECT * FROM students WHERE id = ? AND deleted_at IS NULL', [$id])[0]
            ?? throw new HttpException(404, 'Aluno não encontrado.');
    }
}
