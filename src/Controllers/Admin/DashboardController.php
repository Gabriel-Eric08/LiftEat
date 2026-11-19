<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use DateTimeImmutable;

final class DashboardController
{
    /** Filtros de período: chave => rótulo */
    private const PERIODS = [
        'mes'         => 'Este mês',
        'mes-passado' => 'Mês passado',
        '3m'          => '3 meses',
        '6m'          => '6 meses',
        '12m'         => '12 meses',
    ];

    public function index(Request $request): Response
    {
        $period = (string) $request->query('periodo', 'mes');
        $period = array_key_exists($period, self::PERIODS) ? $period : 'mes';
        [$from, $to, $prevFrom, $prevTo] = $this->range($period);

        $today = date('Y-m-d');
        $in7 = date('Y-m-d', strtotime('+7 days'));

        $revenue = $this->revenue($from, $to);
        $prevRevenue = $this->revenue($prevFrom, $prevTo);

        $students = Database::select(
            'SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = \'active\' AND access_until >= ?), 0) AS with_access,
                COALESCE(SUM(status = \'active\' AND access_until BETWEEN ? AND ?), 0) AS expiring,
                COALESCE(SUM(status = \'active\' AND (access_until IS NULL OR access_until < ?)), 0) AS expired,
                COALESCE(SUM(CASE WHEN status = \'active\' AND access_until >= ? THEN monthly_fee END), 0) AS mrr,
                COALESCE(SUM(created_at BETWEEN ? AND ?), 0) AS new_in_period
             FROM students WHERE deleted_at IS NULL',
            [$today, $today, $in7, $today, $today, $from . ' 00:00:00', $to . ' 23:59:59'],
        )[0];

        $pending = Database::select(
            'SELECT COUNT(*) AS qty, COALESCE(SUM(amount), 0) AS total FROM payments WHERE status = \'pending\''
        )[0];

        $sessions = Database::select(
            'SELECT COUNT(*) AS qty FROM workout_sessions WHERE status = \'completed\' AND started_at BETWEEN ? AND ?',
            [$from . ' 00:00:00', $to . ' 23:59:59'],
        )[0];

        return view('admin/dashboard', [
            'title'        => 'Dashboard',
            'periods'      => self::PERIODS,
            'period'       => $period,
            'from'         => $from,
            'to'           => $to,
            'revenue'      => $revenue,
            'prevRevenue'  => $prevRevenue,
            'students'     => $students,
            'pending'      => $pending,
            'sessionsQty'  => (int) $sessions['qty'],
            'chart'        => $this->chartData(),
            'expiringList' => $this->expiringList($today, $in7),
            'pendingList'  => $this->pendingList(),
            'recentList'   => $this->recentSessions(),
            'scripts'      => ['https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js'],
        ], 'layouts/admin');
    }

    /** @return array{0: string, 1: string, 2: string, 3: string} [de, até, de anterior, até anterior] */
    private function range(string $period): array
    {
        $first = new DateTimeImmutable('first day of this month');

        [$from, $to] = match ($period) {
            'mes-passado' => [$first->modify('-1 month'), $first->modify('-1 day')],
            '3m'          => [$first->modify('-2 months'), new DateTimeImmutable('today')],
            '6m'          => [$first->modify('-5 months'), new DateTimeImmutable('today')],
            '12m'         => [$first->modify('-11 months'), new DateTimeImmutable('today')],
            default       => [$first, new DateTimeImmutable('today')],
        };

        // Período anterior com o mesmo número de meses (comparação justa).
        $months = match ($period) {
            '3m' => 3, '6m' => 6, '12m' => 12, default => 1,
        };
        $prevFrom = $from->modify("-{$months} months");
        $prevTo = $from->modify('-1 day');

        // "Este mês" compara com o mesmo intervalo de dias do mês anterior.
        if ($period === 'mes') {
            $prevTo = min($prevTo, $to->modify('-1 month'));
        }

        return [$from->format('Y-m-d'), $to->format('Y-m-d'), $prevFrom->format('Y-m-d'), $prevTo->format('Y-m-d')];
    }

    private function revenue(string $from, string $to): float
    {
        return (float) Database::select(
            'SELECT COALESCE(SUM(amount), 0) AS total FROM payments
             WHERE status = \'paid\' AND paid_at BETWEEN ? AND ?',
            [$from . ' 00:00:00', $to . ' 23:59:59'],
        )[0]['total'];
    }

    /** Receita e treinos dos últimos 12 meses. @return array{labels: list<string>, revenue: list<float>, sessions: list<int>} */
    private function chartData(): array
    {
        $start = (new DateTimeImmutable('first day of this month'))->modify('-11 months');

        $revenue = [];
        foreach (Database::select(
            'SELECT DATE_FORMAT(paid_at, \'%Y-%m\') AS ym, SUM(amount) AS total FROM payments
             WHERE status = \'paid\' AND paid_at >= ? GROUP BY ym',
            [$start->format('Y-m-d')],
        ) as $row) {
            $revenue[$row['ym']] = (float) $row['total'];
        }

        $sessions = [];
        foreach (Database::select(
            'SELECT DATE_FORMAT(started_at, \'%Y-%m\') AS ym, COUNT(*) AS qty FROM workout_sessions
             WHERE status = \'completed\' AND started_at >= ? GROUP BY ym',
            [$start->format('Y-m-d')],
        ) as $row) {
            $sessions[$row['ym']] = (int) $row['qty'];
        }

        $out = ['labels' => [], 'revenue' => [], 'sessions' => []];
        for ($i = 0; $i < 12; $i++) {
            $m = $start->modify("+{$i} months");
            $key = $m->format('Y-m');
            $out['labels'][] = ucfirst(month_name((int) $m->format('n'), true)) . ($m->format('n') === '1' ? '/' . $m->format('y') : '');
            $out['revenue'][] = $revenue[$key] ?? 0.0;
            $out['sessions'][] = $sessions[$key] ?? 0;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function expiringList(string $today, string $in7): array
    {
        return Database::select(
            'SELECT id, name, access_until, monthly_fee, auto_renew FROM students
             WHERE deleted_at IS NULL AND status = \'active\'
               AND (access_until IS NULL OR access_until <= ?)
             ORDER BY access_until IS NULL DESC, access_until ASC LIMIT 6',
            [$in7],
        );
    }

    /** @return list<array<string, mixed>> */
    private function pendingList(): array
    {
        return Database::select(
            'SELECT p.id, p.amount, p.due_date, p.origin, s.id AS student_id, s.name
             FROM payments p JOIN students s ON s.id = p.student_id
             WHERE p.status = \'pending\' ORDER BY p.due_date ASC LIMIT 6'
        );
    }

    /** @return list<array<string, mixed>> */
    private function recentSessions(): array
    {
        return Database::select(
            'SELECT ws.id, ws.workout_name, ws.started_at, ws.finished_at, ws.status, s.id AS student_id, s.name
             FROM workout_sessions ws JOIN students s ON s.id = ws.student_id
             ORDER BY ws.started_at DESC LIMIT 6'
        );
    }
}
