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

/**
 * Biblioteca de exercícios do personal.
 */
final class ExerciseController
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $group = trim((string) $request->query('grupo', ''));
        $showInactive = $request->query('inativos') === '1';

        $where = [$showInactive ? '1 = 1' : 'e.is_active = 1'];
        $params = [];
        if ($search !== '') {
            $where[] = '(e.name LIKE ? OR e.equipment LIKE ?)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            array_push($params, $like, $like);
        }
        if ($group !== '') {
            $where[] = 'e.muscle_group = ?';
            $params[] = $group;
        }

        $exercises = Database::select(
            'SELECT e.*,
                    (SELECT COUNT(DISTINCT we.workout_id) FROM workout_exercises we WHERE we.exercise_id = e.id) AS used_in,
                    (SELECT COUNT(*) FROM session_sets ss WHERE ss.exercise_id = e.id) AS history
             FROM exercises e WHERE ' . implode(' AND ', $where) . ' ORDER BY e.muscle_group, e.name',
            $params,
        );

        $groups = Database::select(
            'SELECT muscle_group, COUNT(*) AS qty FROM exercises WHERE ' . ($showInactive ? '1 = 1' : 'is_active = 1') . '
             GROUP BY muscle_group ORDER BY muscle_group'
        );

        return view('admin/exercises/index', [
            'title'        => 'Exercícios',
            'exercises'    => $exercises,
            'groups'       => $groups,
            'group'        => $group,
            'search'       => $search,
            'showInactive' => $showInactive,
            'total'        => (int) Database::select('SELECT COUNT(*) AS c FROM exercises WHERE is_active = 1')[0]['c'],
            'suggestions'  => Workouts::MUSCLE_GROUPS,
        ], 'layouts/admin');
    }

    public function store(Request $request): Response
    {
        $data = $this->validated($request);

        if (is_string($data)) {
            return $this->fail($request, $data);
        }

        Database::execute(
            'INSERT INTO exercises (name, muscle_group, equipment, description, video_url) VALUES (?, ?, ?, ?, ?)',
            [$data['name'], $data['muscle_group'], $data['equipment'], $data['description'], $data['video_url']],
        );
        $id = (int) Database::connection()->lastInsertId();

        // Criação rápida a partir do montador de treino (fetch).
        if ($request->wantsJson()) {
            return Response::json(['id' => $id, ...$data]);
        }

        Session::flash('success', "{$data['name']} adicionado à biblioteca.");

        return back('/admin/exercicios');
    }

    public function update(Request $request, string $id): Response
    {
        $exercise = $this->find((int) $id);
        $data = $this->validated($request, (int) $exercise['id']);

        if (is_string($data)) {
            return $this->fail($request, $data);
        }

        Database::execute(
            'UPDATE exercises SET name = ?, muscle_group = ?, equipment = ?, description = ?, video_url = ? WHERE id = ?',
            [$data['name'], $data['muscle_group'], $data['equipment'], $data['description'], $data['video_url'], $exercise['id']],
        );
        Session::flash('success', 'Exercício atualizado.');

        return back('/admin/exercicios');
    }

    public function toggle(Request $request, string $id): Response
    {
        $exercise = $this->find((int) $id);
        $active = $exercise['is_active'] ? 0 : 1;

        Database::execute('UPDATE exercises SET is_active = ? WHERE id = ?', [$active, $exercise['id']]);
        Session::flash('success', $active
            ? "{$exercise['name']} reativado."
            : "{$exercise['name']} desativado. Treinos que já usam continuam funcionando.");

        return back('/admin/exercicios');
    }

    public function destroy(Request $request, string $id): Response
    {
        $exercise = $this->find((int) $id);

        $inUse = (int) Database::select(
            'SELECT (SELECT COUNT(*) FROM workout_exercises WHERE exercise_id = ?) + (SELECT COUNT(*) FROM session_sets WHERE exercise_id = ?) AS c',
            [$exercise['id'], $exercise['id']],
        )[0]['c'];

        if ($inUse > 0) {
            Session::flash('error', 'Esse exercício já foi usado em treinos ou no histórico. Desative em vez de excluir.');

            return back('/admin/exercicios');
        }

        Database::execute('DELETE FROM exercises WHERE id = ?', [$exercise['id']]);
        Session::flash('success', "{$exercise['name']} excluído.");

        return back('/admin/exercicios');
    }

    /** @return array<string, mixed>|string dados limpos, ou a mensagem de erro */
    private function validated(Request $request, ?int $ignoreId = null): array|string
    {
        $errors = Validator::check($request->all(), [
            'name'         => 'required|max:120',
            'muscle_group' => 'required|max:60',
            'equipment'    => 'max:60',
            'video_url'    => 'max:255',
        ]);

        $name = $request->string('name');
        $video = $request->string('video_url');

        if ($video !== '' && !preg_match('#^https?://#i', $video)) {
            $errors['video_url'] = 'O link do vídeo precisa começar com http:// ou https://';
        }

        if (!isset($errors['name'])) {
            $dupe = Database::select(
                'SELECT id FROM exercises WHERE name = ?' . ($ignoreId ? ' AND id <> ?' : '') . ' LIMIT 1',
                $ignoreId ? [$name, $ignoreId] : [$name],
            );
            if ($dupe !== []) {
                $errors['name'] = 'Já existe um exercício com esse nome.';
            }
        }

        if ($errors !== []) {
            return (string) reset($errors);
        }

        $nullable = static fn (string $v): ?string => $v === '' ? null : $v;
        $group = $request->string('muscle_group');

        return [
            'name'         => $name,
            // Só a primeira letra maiúscula ("corpo inteiro" -> "Corpo inteiro"), para os filtros agruparem certo.
            'muscle_group' => mb_strtoupper(mb_substr($group, 0, 1)) . mb_substr($group, 1),
            'equipment'    => $nullable($request->string('equipment')),
            'description'  => $nullable($request->string('description')),
            'video_url'    => $nullable($video),
        ];
    }

    private function fail(Request $request, string $message): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['error' => $message], 422);
        }

        Session::flash('error', $message);

        return back('/admin/exercicios');
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        return Database::select('SELECT * FROM exercises WHERE id = ?', [$id])[0]
            ?? throw new HttpException(404, 'Exercício não encontrado.');
    }
}
