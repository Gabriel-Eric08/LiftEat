<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

/** Escapa texto para saída HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Atalho para responder com uma view. */
function view(string $template, array $data = [], ?string $layout = null, int $status = 200): Response
{
    return Response::html(View::render($template, $data, $layout), $status);
}

/** Ícone Lucide embutido (SVG de resources/icons). */
function icon(string $name, string $class = '', int $size = 20): string
{
    static $cache = [];

    if (!isset($cache[$name])) {
        $file = BASE_PATH . '/resources/icons/' . basename($name) . '.svg';
        $svg = is_file($file) ? (string) file_get_contents($file) : '';
        $svg = trim((string) preg_replace('/\s+/', ' ', preg_replace('/<!--.*?-->/s', '', $svg) ?? ''));
        // Remove class/width/height só da tag <svg> raiz (os <rect> internos precisam dos seus).
        $svg = preg_replace_callback(
            '/^<svg\b[^>]*>/',
            static fn (array $m): string => preg_replace('/\s+(class|width|height)="[^"]*"/', '', $m[0]) ?? $m[0],
            $svg,
        ) ?? '';
        $cache[$name] = $svg;
    }

    if ($cache[$name] === '') {
        return '';
    }

    $attrs = sprintf(
        '<svg class="icon %s" width="%d" height="%d" aria-hidden="true" focusable="false"',
        e($class),
        $size,
        $size,
    );

    return preg_replace('/^<svg/', $attrs, $cache[$name], 1) ?? '';
}

/** URL de arquivo em public_html com versão (quebra cache a cada deploy). */
function asset(string $path): string
{
    $path = '/' . ltrim($path, '/');
    $file = BASE_PATH . '/public_html' . $path;

    return is_file($file) ? $path . '?v=' . filemtime($file) : $path;
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

/** Valor enviado no último formulário (após erro de validação). */
function old(string $key, mixed $default = ''): mixed
{
    $old = Session::getFlash('_old', []);

    return $old[$key] ?? $default;
}

/** Mensagem de erro de validação de um campo. */
function error_for(string $key): ?string
{
    return Session::getFlash('_errors', [])[$key] ?? null;
}

/** @return array<string, string> */
function errors(): array
{
    return Session::getFlash('_errors', []);
}

function flash(string $key): mixed
{
    return Session::getFlash($key);
}

function redirect(string $to): Response
{
    return Response::redirect($to);
}

/** Volta para a página anterior (mesmo site) ou para $fallback. */
function back(string $fallback = '/'): Response
{
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');

    $ok = $referer !== '' && parse_url($referer, PHP_URL_HOST) === parse_url('//' . $host, PHP_URL_HOST);

    return Response::redirect($ok ? $referer : $fallback);
}

/** Tema atual: escuro por padrão; claro se o usuário escolheu (cookie). */
function theme(): string
{
    return ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light' : 'dark';
}

function money(float|int|string|null $value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

function money_short(float|int|string|null $value): string
{
    $v = (float) $value;

    return match (true) {
        $v >= 1_000_000 => 'R$ ' . number_format($v / 1_000_000, 1, ',', '.') . ' mi',
        $v >= 10_000    => 'R$ ' . number_format($v / 1_000, 1, ',', '.') . ' mil',
        default         => money($v),
    };
}

function date_br(?string $date, string $format = 'd/m/Y'): string
{
    if ($date === null || $date === '') {
        return '—';
    }

    return (new DateTimeImmutable($date))->format($format);
}

/** "hoje", "ontem", "há 3 dias", "em 5 dias"... */
function relative_days(?string $date): string
{
    if ($date === null || $date === '') {
        return '—';
    }

    $days = (int) (new DateTimeImmutable('today'))->diff(new DateTimeImmutable(substr($date, 0, 10)))->format('%r%a');

    return match (true) {
        $days === 0  => 'hoje',
        $days === 1  => 'amanhã',
        $days === -1 => 'ontem',
        $days > 1    => "em {$days} dias",
        default      => 'há ' . abs($days) . ' dias',
    };
}

const MONTHS_PT = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
const WEEKDAYS_PT = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];

function month_name(int $month, bool $short = false): string
{
    $name = MONTHS_PT[$month] ?? '';

    return $short ? mb_substr($name, 0, 3) : $name;
}

function weekday_name(int $weekday, bool $short = false): string
{
    $name = WEEKDAYS_PT[$weekday] ?? '';

    return $short ? mb_substr($name, 0, 3) : $name;
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = $parts[0] ?? '';
    $last = count($parts) > 1 ? end($parts) : '';

    return mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
}

function first_name(string $name): string
{
    return explode(' ', trim($name))[0] ?? $name;
}

/** Saudação conforme a hora. */
function greeting(): string
{
    $h = (int) date('G');

    return match (true) {
        $h < 5  => 'Boa madrugada',
        $h < 12 => 'Bom dia',
        $h < 18 => 'Boa tarde',
        default => 'Boa noite',
    };
}

/** Link de navegação ativo? */
function nav_active(string $prefix, bool $exact = false): bool
{
    $path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';
    $prefix = rtrim($prefix, '/') ?: '/';

    return $exact ? $path === $prefix : ($path === $prefix || str_starts_with($path, $prefix . '/'));
}

/**
 * Situação de acesso do aluno para exibição.
 *
 * @param array<string, mixed> $student
 * @return array{key: string, label: string, tone: string}
 */
function student_situation(array $student): array
{
    $today = date('Y-m-d');
    $until = $student['access_until'] ?? null;

    if (($student['status'] ?? '') !== 'active') {
        return ['key' => 'inactive', 'label' => 'Inativo', 'tone' => 'neutral'];
    }
    if ($until === null) {
        return ['key' => 'unpaid', 'label' => 'Sem pagamento', 'tone' => 'warning'];
    }
    if ($until < $today) {
        return ['key' => 'expired', 'label' => 'Vencido', 'tone' => 'danger'];
    }
    if ($until <= date('Y-m-d', strtotime('+7 days'))) {
        return ['key' => 'expiring', 'label' => 'Vence ' . relative_days($until), 'tone' => 'warning'];
    }

    return ['key' => 'active', 'label' => 'Ativo', 'tone' => 'success'];
}
