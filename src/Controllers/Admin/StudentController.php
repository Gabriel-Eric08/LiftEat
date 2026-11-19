<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\Billing;
use App\Services\Workouts;

final class StudentController
{
    private const PER_PAGE = 20;

    /** Filtros de situação: chave => [rótulo, condição SQL] */
    private const SITUATIONS = [
        'todos'    => ['Todos', '1 = 1'],
        'ativos'   => ['Ativos', "status = 'active' AND access_until >= CURDATE()"],
        'vencendo' => ['Vencendo', "status = 'active' AND access_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"],
        'vencidos' => ['Vencidos', "status = 'active' AND (access_until IS NULL OR access_until < CURDATE())"],
        'inativos' => ['Inativos', "status = 'inactive'"],
    ];

    private const PAYMENT_METHODS = ['pix' => 'Pix', 'dinheiro' => 'Dinheiro', 'cartao' => 'Cartão', 'transferencia' => 'Transferência', 'outro' => 'Outro'];

    public function index(Request $request): Response
    {
        $situation = (string) $request->query('situacao', 'todos');
        $situation = array_key_exists($situation, self::SITUATIONS) ? $situation : 'todos';
        $search = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('pagina', 1));

        $where = ['deleted_at IS NULL', self::SITUATIONS[$situation][1]];
        $params = [];
        if ($search !== '') {
            $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            array_push($params, $like, $like, $like);
        }
        $whereSql = implode(' AND ', $where);

        $total = (int) Database::select("SELECT COUNT(*) AS c FROM students WHERE {$whereSql}", $params)[0]['c'];
        $offset = ($page - 1) * self::PER_PAGE;

        $students = Database::select(
            "SELECT s.*,
                    (SELECT MAX(started_at) FROM workout_sessions ws WHERE ws.student_id = s.id) AS last_session_at
             FROM students s WHERE {$whereSql}
             ORDER BY s.name ASC LIMIT " . self::PER_PAGE . " OFFSET {$offset}",
            $params,
        );

        // Contadores por situação (para os chips)
        $counts = [];
        foreach (self::SITUATIONS as $key => [, $cond]) {
            $counts[$key] = (int) Database::select("SELECT COUNT(*) AS c FROM students WHERE deleted_at IS NULL AND {$cond}")[0]['c'];
        }

        return view('admin/students/index', [
            'title'      => 'Alunos',
            'students'   => $students,
            'situations' => array_map(static fn ($s) => $s[0], self::SITUATIONS),
            'situation'  => $situation,
            'counts'     => $counts,
            'search'     => $search,
            'page'       => $page,
            'pages'      => max(1, (int) ceil($total / self::PER_PAGE)),
            'total'      => $total,
        ], 'layouts/admin');
    }

    public function create(Request $request): Response
    {
        return view('admin/students/form', [
            'title'       => 'Novo aluno',
            'breadcrumbs' => ['Alunos' => '/admin/alunos', 'Novo aluno' => null],
            'student'     => null,
            'methods'     => self::PAYMENT_METHODS,
        ], 'layouts/admin');
    }

    public function store(Request $request): Response
    {
        $data = $this->input($request);
        $errors = Validator::check($request->all(), $this->rules(null));

        if ($errors !== []) {
            return $this->invalid($request, $errors, '/admin/alunos/novo');
        }

        Database::execute(
            'INSERT INTO students (name, email, password_hash, phone, birth_date, height_cm, weight_kg, goal, notes, status, monthly_fee, auto_renew)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['name'], $data['email'], password_hash((string) $request->input('password'), PASSWORD_DEFAULT),
                $data['phone'], $data['birth_date'], $data['height_cm'], $data['weight_kg'], $data['goal'], $data['notes'],
                $data['status'], $data['monthly_fee'], $data['auto_renew'],
            ],
        );
        $id = (int) Database::connection()->lastInsertId();

        $message = 'Aluno cadastrado.';
        if ($request->boolean('first_payment')) {
            Billing::registerPayment($id, $data['monthly_fee'], 1, $this->method($request));
            $message = 'Aluno cadastrado com o primeiro mês já liberado.';
        }

        Session::flash('success', $message);

        return redirect("/admin/alunos/{$id}");
    }

    public function show(Request $request, string $id): Response
    {
        $student = $this->find((int) $id);

        $payments = Database::select(
            'SELECT * FROM payments WHERE student_id = ? ORDER BY period_start DESC, id DESC',
            [$student['id']],
        );

        $stats = Database::select(
            'SELECT
                COUNT(*) AS total,
                COALESCE(SUM(status = \'completed\'), 0) AS completed,
                COALESCE(SUM(status = \'completed\' AND started_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)), 0) AS last30,
                MAX(started_at) AS last_at
             FROM workout_sessions WHERE student_id = ?',
            [$student['id']],
        )[0];

        $paidTotal = array_sum(array_map(
            static fn ($p) => $p['status'] === 'paid' ? (float) $p['amount'] : 0.0,
            $payments,
        ));

        $sessions = Database::select(
            'SELECT ws.*,
                    (SELECT COUNT(*) FROM session_sets ss WHERE ss.session_id = ws.id AND ss.completed = 1) AS sets_done,
                    (SELECT COUNT(*) FROM session_photos sp WHERE sp.session_id = ws.id) AS photos
             FROM workout_sessions ws WHERE ws.student_id = ? ORDER BY ws.started_at DESC LIMIT 20',
            [$student['id']],
        );

        // Modelos para "novo treino a partir de": treinos de todos os alunos ativos.
        $templates = Database::select(
            'SELECT w.id, w.name, s.name AS student_name,
                    (SELECT COUNT(*) FROM workout_exercises we WHERE we.workout_id = w.id) AS exercise_count
             FROM workouts w JOIN students s ON s.id = w.student_id
             WHERE s.deleted_at IS NULL
             ORDER BY s.id = ? DESC, s.name, w.name',
            [$student['id']],
        );

        return view('admin/students/show', [
            'workouts'    => Workouts::forStudent((int) $student['id']),
            'schedule'    => Workouts::schedule((int) $student['id']),
            'sessions'    => $sessions,
            'templates'   => $templates,
            'title'       => $student['name'],
            'breadcrumbs' => ['Alunos' => '/admin/alunos', $student['name'] => null],
            'student'     => $student,
            'payments'    => $payments,
            'stats'       => $stats,
            'paidTotal'   => $paidTotal,
            'methods'     => self::PAYMENT_METHODS,
            'nextPeriod'  => Billing::period(Billing::nextPeriodStart($student['access_until'])),
        ], 'layouts/admin');
    }

    public function edit(Request $request, string $id): Response
    {
        $student = $this->find((int) $id);

        return view('admin/students/form', [
            'title'       => 'Editar aluno',
            'breadcrumbs' => ['Alunos' => '/admin/alunos', $student['name'] => "/admin/alunos/{$student['id']}", 'Editar' => null],
            'student'     => $student,
            'methods'     => self::PAYMENT_METHODS,
        ], 'layouts/admin');
    }

    public function update(Request $request, string $id): Response
    {
        $student = $this->find((int) $id);
        $data = $this->input($request);
        $errors = Validator::check($request->all(), $this->rules((int) $student['id']));

        if ($errors !== []) {
            return $this->invalid($request, $errors, "/admin/alunos/{$student['id']}/editar");
        }

        Database::execute(
            'UPDATE students SET name = ?, email = ?, phone = ?, birth_date = ?, height_cm = ?, weight_kg = ?, goal = ?, notes = ?,
                    status = ?, monthly_fee = ?, auto_renew = ?
             WHERE id = ?',
            [
                $data['name'], $data['email'], $data['phone'], $data['birth_date'], $data['height_cm'], $data['weight_kg'],
                $data['goal'], $data['notes'], $data['status'], $data['monthly_fee'], $data['auto_renew'], $student['id'],
            ],
        );

        $password = (string) $request->input('password', '');
        if ($password !== '') {
            Database::execute('UPDATE students SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $student['id']]);
        }

        Session::flash('success', 'Dados atualizados.');

        return redirect("/admin/alunos/{$student['id']}");
    }

    public function toggleStatus(Request $request, string $id): Response
    {
        $student = $this->find((int) $id);
        $new = $student['status'] === 'active' ? 'inactive' : 'active';

        Database::execute('UPDATE students SET status = ? WHERE id = ?', [$new, $student['id']]);
        Session::flash('success', $new === 'active' ? 'Aluno reativado.' : 'Aluno desativado. O acesso ao app foi bloqueado.');

        return back("/admin/alunos/{$student['id']}");
    }

    public function destroy(Request $request, string $id): Response
    {
        $student = $this->find((int) $id);

        // Exclusão lógica: mantém o histórico financeiro e libera o e-mail para novo cadastro.
        Database::execute(
            'UPDATE students SET deleted_at = NOW(), status = \'inactive\', auto_renew = 0,
                    email = LEFT(CONCAT(\'excluido-\', id, \'-\', email), 190)
             WHERE id = ?',
            [$student['id']],
        );

        Session::flash('success', "{$student['name']} foi removido.");

        return redirect('/admin/alunos');
    }

    public function storePayment(Request $request, string $id): Response
    {
        $student = $this->find((int) $id);

        $errors = Validator::check($request->all(), [
            'amount'  => 'required|numeric',
            'months'  => 'required|integer',
            'paid_at' => 'required|date',
            'notes'   => 'max:255',
        ]);
        $months = (int) $request->input('months', 1);
        if ($months < 1 || $months > 12) {
            $errors['months'] = 'Escolha entre 1 e 12 meses.';
        }

        if ($errors !== []) {
            Session::flash('error', reset($errors));

            return redirect("/admin/alunos/{$student['id']}?aba=financeiro");
        }

        $paidAt = $request->string('paid_at') . ' ' . date('H:i:s');
        Billing::registerPayment(
            (int) $student['id'],
            (float) Validator::decimal($request->string('amount')),
            $months,
            $this->method($request),
            $paidAt,
            $request->string('notes') ?: null,
        );

        $fresh = $this->find((int) $student['id']);
        Session::flash('success', 'Pagamento registrado. Acesso liberado até ' . date_br($fresh['access_until']) . '.');

        return redirect("/admin/alunos/{$student['id']}?aba=financeiro");
    }

    /** @return array<string, mixed> */
    private function find(int $id): array
    {
        $student = Database::select('SELECT * FROM students WHERE id = ? AND deleted_at IS NULL', [$id])[0] ?? null;

        if ($student === null) {
            throw new HttpException(404, 'Esse aluno não existe ou foi removido.');
        }

        return $student;
    }

    /** @return array<string, string> */
    private function rules(?int $ignoreId): array
    {
        return [
            'name'        => 'required|max:120',
            'email'       => 'required|email|max:190|unique:students,email' . ($ignoreId ? ",{$ignoreId}" : ''),
            'password'    => ($ignoreId ? '' : 'required|') . 'min:6|max:72',
            'phone'       => 'max:30',
            'birth_date'  => 'date',
            'height_cm'   => 'integer',
            'weight_kg'   => 'numeric',
            'goal'        => 'max:255',
            'monthly_fee' => 'required|numeric',
            'status'      => 'required|in:active,inactive',
        ];
    }

    /** @return array<string, mixed> */
    private function input(Request $request): array
    {
        $nullable = static fn (string $v): ?string => $v === '' ? null : $v;
        $weight = $request->string('weight_kg');

        return [
            'name'        => $request->string('name'),
            'email'       => mb_strtolower($request->string('email')),
            'phone'       => $nullable($request->string('phone')),
            'birth_date'  => $nullable($request->string('birth_date')),
            'height_cm'   => $nullable($request->string('height_cm')),
            'weight_kg'   => $weight === '' ? null : Validator::decimal($weight),
            'goal'        => $nullable($request->string('goal')),
            'notes'       => $nullable($request->string('notes')),
            'status'      => $request->string('status', 'active'),
            'monthly_fee' => (float) Validator::decimal($request->string('monthly_fee', '0')),
            'auto_renew'  => $request->boolean('auto_renew') ? 1 : 0,
        ];
    }

    private function method(Request $request): ?string
    {
        $m = $request->string('method');

        return array_key_exists($m, self::PAYMENT_METHODS) ? $m : null;
    }

    /** @param array<string, string> $errors */
    private function invalid(Request $request, array $errors, string $to): Response
    {
        Session::flashErrors($errors);
        Session::flashInput($request->all());
        Session::flash('error', 'Revise os campos destacados.');

        return redirect($to);
    }
}
