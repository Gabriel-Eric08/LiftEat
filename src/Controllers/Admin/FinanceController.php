<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Billing;
use DateTimeImmutable;
use RuntimeException;

final class FinanceController
{
    private const STATUSES = ['todos' => 'Todos', 'paid' => 'Pagos', 'pending' => 'Pendentes', 'cancelled' => 'Cancelados'];
    private const METHODS = ['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'cartao' => 'Cartão', 'transferencia' => 'Transferência', 'outro' => 'Outro'];

    public function index(Request $request): Response
    {
        $month = (string) $request->query('mes', date('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $month = date('Y-m');
        }
        $status = (string) $request->query('status', 'todos');
        $status = array_key_exists($status, self::STATUSES) ? $status : 'todos';

        $first = new DateTimeImmutable($month . '-01');
        $from = $first->format('Y-m-d');
        $to = $first->modify('last day of this month')->format('Y-m-d');
        $fromTs = $from . ' 00:00:00';
        $toTs = $to . ' 23:59:59';

        // Recebido no mês = pagos com paid_at no mês. Pendentes/cancelados = pelo vencimento.
        $base = '(p.status = \'paid\' AND p.paid_at BETWEEN ? AND ?)
              OR (p.status <> \'paid\' AND p.due_date BETWEEN ? AND ?)';
        $params = [$fromTs, $toTs, $from, $to];

        $summary = Database::select(
            "SELECT
                COALESCE(SUM(CASE WHEN p.status = 'paid' THEN p.amount END), 0)    AS received,
                COALESCE(SUM(p.status = 'paid'), 0)                                AS received_qty,
                COALESCE(SUM(CASE WHEN p.status = 'pending' THEN p.amount END), 0) AS pending,
                COALESCE(SUM(p.status = 'pending'), 0)                             AS pending_qty
             FROM payments p WHERE {$base}",
            $params,
        )[0];

        $overdue = Database::select(
            'SELECT COUNT(*) AS qty, COALESCE(SUM(amount), 0) AS total FROM payments WHERE status = \'pending\' AND due_date < CURDATE()'
        )[0];

        $forecast = (float) Database::select(
            'SELECT COALESCE(SUM(monthly_fee), 0) AS total FROM students
             WHERE deleted_at IS NULL AND status = \'active\' AND access_until >= CURDATE()'
        )[0]['total'];

        $byMethod = Database::select(
            "SELECT COALESCE(p.method, 'outro') AS method, SUM(p.amount) AS total, COUNT(*) AS qty
             FROM payments p WHERE p.status = 'paid' AND p.paid_at BETWEEN ? AND ?
             GROUP BY COALESCE(p.method, 'outro') ORDER BY total DESC",
            [$fromTs, $toTs],
        );

        $filter = $status === 'todos' ? '' : ' AND p.status = ' . Database::connection()->quote($status);
        $payments = Database::select(
            "SELECT p.*, s.name AS student_name, s.deleted_at AS student_deleted
             FROM payments p JOIN students s ON s.id = p.student_id
             WHERE ({$base}){$filter}
             ORDER BY COALESCE(p.paid_at, p.due_date) DESC, p.id DESC",
            $params,
        );

        return view('admin/finance/index', [
            'title'    => 'Financeiro',
            'month'    => $first,
            'prev'     => $first->modify('-1 month')->format('Y-m'),
            'next'     => $first->modify('+1 month')->format('Y-m'),
            'isFuture' => $first->format('Y-m') >= date('Y-m'),
            'status'   => $status,
            'statuses' => self::STATUSES,
            'methods'  => self::METHODS,
            'summary'  => $summary,
            'overdue'  => $overdue,
            'forecast' => $forecast,
            'byMethod' => $byMethod,
            'payments' => $payments,
        ], 'layouts/admin');
    }

    public function markPaid(Request $request, string $id): Response
    {
        try {
            Billing::markAsPaid((int) $id, $request->string('method') ?: null);
            Session::flash('success', 'Mensalidade marcada como paga.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        return back('/admin/financeiro');
    }

    public function cancel(Request $request, string $id): Response
    {
        try {
            Billing::cancel((int) $id);
            Session::flash('success', 'Mensalidade cancelada.');
        } catch (RuntimeException $e) {
            Session::flash('error', $e->getMessage());
        }

        return back('/admin/financeiro');
    }
}
