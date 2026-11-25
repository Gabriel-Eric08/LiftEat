<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\DietCheckins;
use App\Services\DietMeals;
use App\Services\DietPlans;

/**
 * Check-in da dieta no app: marcar e desmarcar refeições feitas hoje.
 * A validação fica em App\Services\DietCheckins.
 */
final class DietController
{
    public function check(Request $request): Response
    {
        $student = Auth::student();
        $plan = DietPlans::activeFor((int) $student['id']);

        if ($plan === null) {
            Session::flash('error', 'Você não tem um plano alimentar ativo.');

            return $this->back($request);
        }

        $meals = DietMeals::tree((int) $plan['id']);
        $checkin = DietCheckins::resolve($meals, $request->input('meal'), $request->input('option'));

        if (is_string($checkin)) {
            Session::flash('error', $checkin);

            return $this->back($request);
        }

        if (!DietCheckins::check((int) $student['id'], (int) $plan['id'], date('Y-m-d'), $checkin, count($meals))) {
            Session::flash('error', self::UNAVAILABLE);
        }

        return $this->back($request, $checkin['meal_name']);
    }

    public function uncheck(Request $request): Response
    {
        $student = Auth::student();
        $meal = $request->string('meal');
        $mealKey = DietCheckins::key($meal);

        // Só o dia de hoje pode ser desfeito.
        if ($mealKey !== '' && !DietCheckins::uncheck((int) $student['id'], date('Y-m-d'), $mealKey)) {
            Session::flash('error', self::UNAVAILABLE);
        }

        return $this->back($request, $meal);
    }

    private const UNAVAILABLE = 'O check-in da dieta ainda não está disponível. Avise seu personal.';

    /** Volta para a tela de origem (dieta ou home), rolando até a refeição. */
    private function back(Request $request, string $mealName = ''): Response
    {
        if ($request->input('from') === 'home') {
            return redirect('/app');
        }

        return redirect('/app/dieta' . ($mealName !== '' ? '#' . DietCheckins::anchor($mealName) : ''));
    }
}
