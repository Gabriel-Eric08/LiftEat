<?php

declare(strict_types=1);

use App\Controllers\Admin;
use App\Controllers\HealthController;
use App\Controllers\InstallController;
use App\Controllers\Student;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\AdminAuth;
use App\Middleware\AdminGuest;
use App\Middleware\StudentAccess;
use App\Middleware\StudentAuth;
use App\Middleware\StudentGuest;

return static function (Router $router): void {
    $router->get('/', static fn () => Response::redirect('/app'));
    $router->get('/health', [HealthController::class, 'show']);

    // Instalação do banco (só ativa com INSTALL_TOKEN no .env)
    $router->get('/install', [InstallController::class, 'show']);
    $router->post('/install/migrate', [InstallController::class, 'migrate']);
    $router->post('/install/admin', [InstallController::class, 'createAdmin']);

    /* ---------------- Painel do personal ---------------- */
    $router->group('/admin', [AdminGuest::class], static function (Router $r): void {
        $r->get('/login', [Admin\AuthController::class, 'show']);
        $r->post('/login', [Admin\AuthController::class, 'login']);
    });

    $router->group('/admin', [AdminAuth::class], static function (Router $r): void {
        $r->post('/logout', [Admin\AuthController::class, 'logout']);

        $r->get('', [Admin\DashboardController::class, 'index']);

        $r->get('/alunos', [Admin\StudentController::class, 'index']);
        $r->get('/alunos/novo', [Admin\StudentController::class, 'create']);
        $r->post('/alunos', [Admin\StudentController::class, 'store']);
        $r->get('/alunos/{id}', [Admin\StudentController::class, 'show']);
        $r->get('/alunos/{id}/editar', [Admin\StudentController::class, 'edit']);
        $r->post('/alunos/{id}', [Admin\StudentController::class, 'update']);
        $r->post('/alunos/{id}/status', [Admin\StudentController::class, 'toggleStatus']);
        $r->post('/alunos/{id}/excluir', [Admin\StudentController::class, 'destroy']);
        $r->post('/alunos/{id}/pagamentos', [Admin\StudentController::class, 'storePayment']);

        $r->get('/financeiro', [Admin\FinanceController::class, 'index']);
        $r->post('/pagamentos/{id}/pagar', [Admin\FinanceController::class, 'markPaid']);
        $r->post('/pagamentos/{id}/cancelar', [Admin\FinanceController::class, 'cancel']);

        $r->get('/treinos', [Admin\ComingSoonController::class, 'show']);
        $r->get('/exercicios', [Admin\ComingSoonController::class, 'show']);
        $r->get('/dietas', [Admin\ComingSoonController::class, 'show']);

        $r->get('/perfil', [Admin\ProfileController::class, 'show']);
        $r->post('/perfil', [Admin\ProfileController::class, 'update']);
        $r->post('/perfil/senha', [Admin\ProfileController::class, 'password']);
    });

    /* ---------------- App do aluno ---------------- */
    $router->group('/app', [StudentGuest::class], static function (Router $r): void {
        $r->get('/login', [Student\AuthController::class, 'show']);
        $r->post('/login', [Student\AuthController::class, 'login']);
    });

    $router->group('/app', [StudentAuth::class], static function (Router $r): void {
        $r->post('/logout', [Student\AuthController::class, 'logout']);
        $r->get('/acesso', [Student\AppController::class, 'access']);

        $r->group('', [StudentAccess::class], static function (Router $r): void {
            $r->get('', [Student\AppController::class, 'home']);
            $r->get('/treinos', [Student\AppController::class, 'workouts']);
            $r->get('/dieta', [Student\AppController::class, 'diet']);
            $r->get('/historico', [Student\AppController::class, 'history']);
            $r->get('/perfil', [Student\AppController::class, 'profile']);
            $r->post('/perfil/senha', [Student\AppController::class, 'password']);
        });
    });
};
