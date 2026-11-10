<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Rol;
use App\Views\JsonView;

class RolController extends Controller
{
    public function index(Request $req): Response
    {
        [$roles] = Rol::listar([], 1, 50);
        return Response::ok(array_map(fn ($r) => JsonView::render($r), $roles));
    }
}
