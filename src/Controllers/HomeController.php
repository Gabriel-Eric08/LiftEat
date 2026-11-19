<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

final class HomeController
{
    public function index(Request $request): Response
    {
        return Response::html(View::render('home', ['title' => 'LiftEat']));
    }
}
