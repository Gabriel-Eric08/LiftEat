<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\DietAdherence;
use App\Services\DietMeals;
use App\Services\DietPlans;

/**
 * Planos alimentares dos alunos. A regra (validação e plano ativo único)
 * fica em App\Services\DietPlans; aqui só buscamos dados e redirecionamos.
 */
final class DietController
{
    /** Lista de todos os planos, agrupada por aluno. */
    public function index(Request $request): Response
    {
        $plans = DietPlans::all();
        $sort = DietAdherence::parseSort($request->query('ordem'));

        $activeMealCounts = [];
        foreach ($plans as $p) {
            if ($p['is_active']) {
                $activeMealCounts[(int) $p['student_id']] = (int) $p['meal_count'];
            }
        }
        $withActive = count($activeMealCounts);

        // null = check-ins ainda indisponíveis (migration 002 pendente).
        $adherence = DietAdherence::forStudents($activeMealCounts, date('Y-m-d'));
        foreach ($plans as &$p) {
            $p['adherence'] = $p['is_active'] && $adherence !== null ? $adherence[(int) $p['student_id']]['summary'] : null;
        }
        unset($p);
        $plans = DietAdherence::sortRows($plans, $sort);

        $without = Database::select(
            'SELECT s.id, s.name, s.goal FROM students s
             WHERE s.deleted_at IS NULL AND s.status = \'active\'
               AND NOT EXISTS (SELECT 1 FROM diet_plans p WHERE p.student_id = s.id AND p.is_active = 1)
             ORDER BY s.name'
        );

        return view('admin/diets/index', [
            'title'      => 'Dietas',
            'plans'      => $plans,
            'withActive' => $withActive,
            'without'    => $without,
            'sort'       => $sort,
            'checkins'   => $adherence !== null,
        ], 'layouts/admin');
    }

    public function store(Request $request, string $id): Response
    {
        $student = $this->student((int) $id);
        [$data, $errors] = DietPlans::validate($request->all());

        if ($errors !== []) {
            return $this->invalid($errors, (int) $student['id']);
        }

        $activate = DietPlans::shouldActivate($request->boolean('activate'), DietPlans::hasActive((int) $student['id']));
        $newId = DietPlans::create((int) $student['id'], $data, $activate);

        // "Começar a partir de": copia as refeições de um plano existente.
        $templateId = (int) $request->input('template_id', 0);
        $template = $templateId > 0 ? DietPlans::find($templateId) : null;
        if ($template !== null) {
            DietPlans::copyMeals((int) $template['id'], $newId);
        }

        Session::flash('success', ($activate
            ? 'Plano criado e ativo. ' . first_name($student['name']) . ' já vê no app.'
            : 'Plano criado. Ative quando quiser que ' . first_name($student['name']) . ' veja no app.')
            . ($template !== null ? ' Refeições copiadas de ' . $template['name'] . '.' : ''));

        return $template !== null ? redirect("/admin/dietas/{$newId}/montar") : $this->back((int) $student['id']);
    }

    /** Duplica no mesmo aluno ou copia para outro, e abre o montador da cópia. */
    public function duplicate(Request $request, string $id): Response
    {
        $plan = $this->plan((int) $id);
        $target = $this->student((int) $request->input('student_id', $plan['student_id']));
        $same = (int) $target['id'] === (int) $plan['student_id'];

        [$newId, $active] = DietPlans::duplicate((int) $plan['id'], (int) $target['id']);

        Session::flash('success', ($same ? 'Plano duplicado.' : 'Plano copiado para ' . first_name($target['name']) . '.')
            . ($active ? ' A cópia já está ativa no app.' : ' A cópia está inativa; ative quando quiser.'));

        return redirect("/admin/dietas/{$newId}/montar");
    }

    public function update(Request $request, string $id): Response
    {
        $plan = $this->plan((int) $id);
        [$data, $errors] = DietPlans::validate($request->all());

        if ($errors !== []) {
            return $this->invalid($errors, (int) $plan['student_id']);
        }

        DietPlans::update((int) $plan['id'], $data);
        Session::flash('success', 'Plano atualizado.');

        return $this->back((int) $plan['student_id']);
    }

    public function activate(Request $request, string $id): Response
    {
        $plan = $this->plan((int) $id);
        DietPlans::activate((int) $plan['id'], (int) $plan['student_id']);
        Session::flash('success', "{$plan['name']} agora é o plano ativo. Os outros planos do aluno foram desativados.");

        return $this->back((int) $plan['student_id']);
    }

    /** Montador de refeições, opções e alimentos do plano. */
    public function builder(Request $request, string $id): Response
    {
        $plan = $this->plan((int) $id);
        $student = $this->student((int) $plan['student_id']);

        return view('admin/diets/builder', [
            'title'       => $plan['name'],
            'breadcrumbs' => ['Alunos' => '/admin/alunos', $student['name'] => "/admin/alunos/{$student['id']}?aba=dieta", 'Montar refeições' => null],
            'plan'        => $plan,
            'student'     => $student,
            'meals'       => DietMeals::tree((int) $plan['id']),
            'students'    => self::studentOptions(),
            'scripts'     => [asset('assets/js/diet-builder.js')],
        ], 'layouts/admin');
    }

    public function saveMeals(Request $request, string $id): Response
    {
        $plan = $this->plan((int) $id);
        $back = "/admin/dietas/{$plan['id']}/montar";
        $raw = json_decode((string) $request->input('meals', ''), true);

        [$meals, $errors] = DietMeals::validate($raw);

        if ($errors !== []) {
            Session::flash('error', implode(' ', array_slice($errors, 0, 3)) . (count($errors) > 3 ? ' (e mais ' . (count($errors) - 3) . ')' : ''));
            if (is_array($raw)) {
                Session::flash('diet_draft', $raw);
            }

            return redirect($back);
        }

        DietMeals::save((int) $plan['id'], $meals);
        Session::flash('success', 'Refeições salvas.' . ($plan['is_active'] ? ' O aluno já vê no app.' : ''));

        return redirect($request->input('after') === 'aluno' ? "/admin/alunos/{$plan['student_id']}?aba=dieta" : $back);
    }

    public function destroy(Request $request, string $id): Response
    {
        $plan = $this->plan((int) $id);
        DietPlans::delete((int) $plan['id']);

        Session::flash('success', $plan['is_active']
            ? "{$plan['name']} excluído. O aluno fica sem plano no app até você ativar outro."
            : "{$plan['name']} excluído.");

        return $this->back((int) $plan['student_id']);
    }

    /**
     * Alunos que podem receber uma cópia de plano.
     *
     * @return list<array{id: int|string, name: string}>
     */
    public static function studentOptions(): array
    {
        return Database::select('SELECT id, name FROM students WHERE deleted_at IS NULL ORDER BY name');
    }

    /** @param list<string> $errors */
    private function invalid(array $errors, int $studentId): Response
    {
        Session::flash('error', implode(' ', array_slice($errors, 0, 3)));

        return $this->back($studentId);
    }

    private function back(int $studentId): Response
    {
        return redirect("/admin/alunos/{$studentId}?aba=dieta");
    }

    /** @return array<string, mixed> */
    private function plan(int $id): array
    {
        $plan = DietPlans::find($id) ?? throw new HttpException(404, 'Plano alimentar não encontrado.');
        $this->student((int) $plan['student_id']);

        return $plan;
    }

    /** @return array<string, mixed> */
    private function student(int $id): array
    {
        return Database::select('SELECT * FROM students WHERE id = ? AND deleted_at IS NULL', [$id])[0]
            ?? throw new HttpException(404, 'Aluno não encontrado.');
    }
}
