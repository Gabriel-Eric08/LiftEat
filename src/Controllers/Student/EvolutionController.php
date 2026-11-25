<?php

declare(strict_types=1);

namespace App\Controllers\Student;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\BodyMeasurements;
use App\Services\ProgressCharts;

/**
 * Evolução do aluno: registro de peso e medidas.
 * A validação fica em App\Services\BodyMeasurements.
 */
final class EvolutionController
{
    private const UNAVAILABLE = 'O registro de medidas ainda não está disponível. Avise seu personal.';

    public function index(Request $request): Response
    {
        $student = Auth::student();
        $records = BodyMeasurements::forStudent((int) $student['id']);

        return view('app/evolution', [
            'title'     => 'Evolução',
            'eyebrow'   => 'Peso e medidas',
            'heading'   => 'Evolução',
            'records'   => $records ?? [],
            'available' => $records !== null,
            'variation' => BodyMeasurements::variation($records ?? []),
            'charts'    => ProgressCharts::forStudent((int) $student['id'], $records ?? [], $request->query('exercicio')),
        ], 'layouts/app');
    }

    public function store(Request $request): Response
    {
        $student = Auth::student();
        if (BodyMeasurements::forStudent((int) $student['id']) === null) {
            Session::flash('error', self::UNAVAILABLE);

            return redirect('/app/evolucao');
        }

        [$data, $errors] = BodyMeasurements::validate($request->all(), date('Y-m-d'));
        if ($errors !== []) {
            return $this->invalid($request, $errors);
        }

        BodyMeasurements::save((int) $student['id'], $data);
        Session::flash('success', 'Medidas registradas.');

        return redirect('/app/evolucao');
    }

    public function update(Request $request, string $id): Response
    {
        $student = Auth::student();
        $record = $this->own((int) $id, (int) $student['id']);

        [$data, $errors] = BodyMeasurements::validate($request->all(), date('Y-m-d'));
        if ($errors !== []) {
            return $this->invalid($request, $errors);
        }

        if (!BodyMeasurements::save((int) $student['id'], $data, (int) $record['id'])) {
            return $this->invalid($request, ['Já existe um registro em ' . date_br($data['measured_on']) . '. Edite aquele registro.']);
        }

        Session::flash('success', 'Registro atualizado.');

        return redirect('/app/evolucao');
    }

    public function destroy(Request $request, string $id): Response
    {
        $student = Auth::student();
        $record = $this->own((int) $id, (int) $student['id']);
        BodyMeasurements::delete((int) $record['id'], (int) $student['id']);
        Session::flash('success', 'Registro de ' . date_br($record['measured_on']) . ' excluído.');

        return redirect('/app/evolucao');
    }

    /** @param list<string> $errors */
    private function invalid(Request $request, array $errors): Response
    {
        Session::flash('error', implode(' ', array_slice($errors, 0, 3)));
        Session::flashInput($request->all());

        return redirect('/app/evolucao');
    }

    /** @return array<string, mixed> */
    private function own(int $id, int $studentId): array
    {
        return BodyMeasurements::find($id, $studentId) ?? throw new HttpException(404, 'Registro não encontrado.');
    }
}
