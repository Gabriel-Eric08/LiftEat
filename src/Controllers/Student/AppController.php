<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Pagination;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\DietMeals;
use App\Services\Workouts;

/**
 * Web app do aluno (mobile first): Hoje, Treinos, Dieta, Histórico e Perfil.
 */
final class AppController
{
    private const HISTORY_PER_PAGE = 30;

    public function home(Request $request): Response
    {
        $student = Auth::student();
        $today = (int) date('N');
        $schedule = $this->schedule((int) $student['id']);
        $todayWorkout = $schedule[$today] ?? null;

        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $doneThisWeek = [];
        foreach (Database::select(
            'SELECT DISTINCT WEEKDAY(started_at) + 1 AS wd FROM workout_sessions
             WHERE student_id = ? AND status = \'completed\' AND started_at >= ?',
            [$student['id'], $weekStart],
        ) as $row) {
            $doneThisWeek[(int) $row['wd']] = true;
        }

        $monthCount = (int) Database::select(
            'SELECT COUNT(*) AS c FROM workout_sessions WHERE student_id = ? AND status = \'completed\' AND started_at >= ?',
            [$student['id'], date('Y-m-01')],
        )[0]['c'];

        $plan = $this->activeDiet((int) $student['id']);
        $nextMeal = null;
        if ($plan !== null) {
            $now = date('H:i:s');
            foreach ($plan['meals'] as $meal) {
                if ($meal['meal_time'] === null || $meal['meal_time'] >= $now) {
                    $nextMeal = $meal;
                    break;
                }
            }
            $nextMeal ??= $plan['meals'][0] ?? null;
        }

        return view('app/home', [
            'title'        => 'Hoje',
            'eyebrow'      => greeting() . ',',
            'heading'      => first_name($student['name']),
            'student'      => $student,
            'today'        => $today,
            'schedule'     => $schedule,
            'todayWorkout' => $todayWorkout,
            'doneThisWeek' => $doneThisWeek,
            'monthCount'   => $monthCount,
            'nextMeal'     => $nextMeal,
            'active'       => Workouts::activeSession((int) $student['id']),
        ], 'layouts/app');
    }

    public function workouts(Request $request): Response
    {
        $student = Auth::student();
        $schedule = $this->schedule((int) $student['id']);
        $day = (int) $request->query('dia', date('N'));
        $day = $day >= 1 && $day <= 7 ? $day : (int) date('N');
        $workout = $schedule[$day] ?? null;

        $exercises = $workout === null ? [] : Database::select(
            'SELECT we.*, e.name, e.muscle_group, e.equipment, e.video_url
             FROM workout_exercises we JOIN exercises e ON e.id = we.exercise_id
             WHERE we.workout_id = ? ORDER BY we.position, we.id',
            [$workout['id']],
        );

        return view('app/workouts', [
            'title'     => 'Treinos',
            'eyebrow'   => 'Sua semana',
            'heading'   => 'Treinos',
            'schedule'  => $schedule,
            'day'       => $day,
            'today'     => (int) date('N'),
            'workout'   => $workout,
            'exercises' => $exercises,
            'active'    => Workouts::activeSession((int) $student['id']),
            'last'      => Workouts::lastPerformance((int) $student['id'], array_map(static fn ($x) => (int) $x['exercise_id'], $exercises)),
        ], 'layouts/app');
    }

    public function diet(Request $request): Response
    {
        $student = Auth::student();

        return view('app/diet', [
            'title'   => 'Dieta',
            'eyebrow' => 'Plano alimentar',
            'heading' => 'Dieta',
            'plan'    => $this->activeDiet((int) $student['id']),
        ], 'layouts/app');
    }

    public function history(Request $request): Response
    {
        $student = Auth::student();

        $count = (int) Database::select(
            'SELECT COUNT(*) AS c FROM workout_sessions WHERE student_id = ?',
            [$student['id']],
        )[0]['c'];
        $pagination = new Pagination($request->query('pagina'), $count, self::HISTORY_PER_PAGE);

        $sessions = Database::select(
            'SELECT ws.*,
                    (SELECT COUNT(*) FROM session_photos sp WHERE sp.session_id = ws.id) AS photos,
                    (SELECT COUNT(*) FROM session_sets ss WHERE ss.session_id = ws.id AND ss.completed = 1) AS sets_done
             FROM workout_sessions ws WHERE ws.student_id = ?
             ORDER BY ws.started_at DESC, ws.id DESC
             LIMIT ' . $pagination->perPage . ' OFFSET ' . $pagination->offset,
            [$student['id']],
        );

        $totals = Database::select(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(started_at >= ?), 0) AS month,
                    COALESCE(SUM(TIMESTAMPDIFF(MINUTE, started_at, finished_at)), 0) AS minutes
             FROM workout_sessions WHERE student_id = ? AND status = \'completed\'',
            [date('Y-m-01'), $student['id']],
        )[0];

        return view('app/history', [
            'title'      => 'Histórico',
            'eyebrow'    => 'Sua evolução',
            'heading'    => 'Histórico',
            'grouped'    => self::groupByMonth($sessions),
            'totals'     => $totals,
            'pagination' => $pagination,
        ], 'layouts/app');
    }

    /**
     * Agrupa as sessões por mês ("AAAA-MM"), mantendo a ordem recebida.
     *
     * @param list<array<string, mixed>> $sessions
     * @return array<string, list<array<string, mixed>>>
     */
    public static function groupByMonth(array $sessions): array
    {
        $grouped = [];
        foreach ($sessions as $s) {
            $grouped[substr((string) $s['started_at'], 0, 7)][] = $s;
        }

        return $grouped;
    }

    public function profile(Request $request): Response
    {
        return view('app/profile', [
            'title'   => 'Perfil',
            'eyebrow' => 'Sua conta',
            'heading' => 'Perfil',
            'student' => Auth::student(),
        ], 'layouts/app');
    }

    public function password(Request $request): Response
    {
        $student = Auth::student();
        $errors = Validator::check($request->all(), [
            'current_password' => 'required',
            'password'         => 'required|min:6|max:72|confirmed',
        ]);

        if (!isset($errors['current_password']) && !password_verify((string) $request->input('current_password'), $student['password_hash'])) {
            $errors['current_password'] = 'Senha atual incorreta.';
        }

        if ($errors !== []) {
            Session::flashErrors($errors);
            Session::flash('error', reset($errors));

            return redirect('/app/perfil#senha');
        }

        Database::execute(
            'UPDATE students SET password_hash = ? WHERE id = ?',
            [password_hash((string) $request->input('password'), PASSWORD_DEFAULT), $student['id']],
        );
        Session::regenerate();
        Session::flash('success', 'Senha alterada.');

        return redirect('/app/perfil');
    }

    /** Tela de acesso bloqueado (inativo ou mensalidade vencida). */
    public function access(Request $request): Response
    {
        $student = Auth::student();

        if (Auth::studentHasAccess($student)) {
            return redirect('/app');
        }

        return view('app/access', [
            'title'   => 'Acesso pausado',
            'heading' => 'Olá, ' . first_name($student['name']),
            'student' => $student,
            'hideNav' => true,
        ], 'layouts/app');
    }

    /**
     * Treino de cada dia da semana: [weekday => ['id', 'name', 'notes', 'exercise_count']]
     *
     * @return array<int, array<string, mixed>>
     */
    private function schedule(int $studentId): array
    {
        $rows = Database::select(
            'SELECT ws.weekday, w.id, w.name, w.notes,
                    (SELECT COUNT(*) FROM workout_exercises we WHERE we.workout_id = w.id) AS exercise_count
             FROM workout_schedule ws JOIN workouts w ON w.id = ws.workout_id
             WHERE ws.student_id = ? AND w.is_active = 1',
            [$studentId],
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['weekday']] = $row;
        }

        return $out;
    }

    /**
     * Plano alimentar ativo com refeições > opções > itens.
     *
     * @return array<string, mixed>|null
     */
    private function activeDiet(int $studentId): ?array
    {
        $plan = Database::select(
            'SELECT * FROM diet_plans WHERE student_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1',
            [$studentId],
        )[0] ?? null;

        if ($plan === null) {
            return null;
        }

        // Na ordem definida pelo personal no montador do plano.
        $plan['meals'] = DietMeals::tree((int) $plan['id']);

        return $plan;
    }
}
