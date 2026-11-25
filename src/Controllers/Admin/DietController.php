<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
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

        $withActive = count(array_unique(array_column(
            array_filter($plans, static fn ($p) => (bool) $p['is_active']),
            'student_id',
        )));

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
        DietPlans::create((int) $student['id'], $data, $activate);

        Session::flash('success', $activate
            ? 'Plano criado e ativo. ' . first_name($student['name']) . ' já vê no app.'
            : 'Plano criado. Ative quando quiser que ' . first_name($student['name']) . ' veja no app.');

        return $this->back((int) $student['id']);
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

    public function destroy(Request $request, string $id): Response
    {
        $plan = $this->plan((int) $id);
        DietPlans::delete((int) $plan['id']);

        Session::flash('success', $plan['is_active']
            ? "{$plan['name']} excluído. O aluno fica sem plano no app até você ativar outro."
            : "{$plan['name']} excluído.");

        return $this->back((int) $plan['student_id']);
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
