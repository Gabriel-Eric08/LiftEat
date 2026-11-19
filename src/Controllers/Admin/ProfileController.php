<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;

final class ProfileController
{
    public function show(Request $request): Response
    {
        return view('admin/profile', [
            'title' => 'Pessoal',
            'admin' => Auth::admin(),
        ], 'layouts/admin');
    }

    public function update(Request $request): Response
    {
        $admin = Auth::admin();
        $errors = Validator::check($request->all(), [
            'name'  => 'required|max:120',
            'email' => "required|email|max:190|unique:admins,email,{$admin['id']}",
        ]);

        if ($errors !== []) {
            Session::flashErrors($errors);
            Session::flashInput($request->all());

            return redirect('/admin/perfil');
        }

        Database::execute(
            'UPDATE admins SET name = ?, email = ? WHERE id = ?',
            [$request->string('name'), mb_strtolower($request->string('email')), $admin['id']],
        );
        Session::flash('success', 'Dados atualizados.');

        return redirect('/admin/perfil');
    }

    public function password(Request $request): Response
    {
        $admin = Auth::admin();
        $errors = Validator::check($request->all(), [
            'current_password' => 'required',
            'password'         => 'required|min:8|max:72|confirmed',
        ]);

        if (!isset($errors['current_password']) && !password_verify((string) $request->input('current_password'), $admin['password_hash'])) {
            $errors['current_password'] = 'Senha atual incorreta.';
        }

        if ($errors !== []) {
            Session::flashErrors($errors);

            return redirect('/admin/perfil#senha');
        }

        Database::execute(
            'UPDATE admins SET password_hash = ? WHERE id = ?',
            [password_hash((string) $request->input('password'), PASSWORD_DEFAULT), $admin['id']],
        );
        Session::regenerate();
        Session::flash('success', 'Senha alterada.');

        return redirect('/admin/perfil');
    }
}
