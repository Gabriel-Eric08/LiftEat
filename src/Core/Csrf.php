<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf', $token);
        }

        return $token;
    }

    public static function verify(Request $request): void
    {
        $given = (string) ($request->input('_token') ?? $request->header('X-CSRF-Token') ?? '');

        if ($given === '' || !hash_equals(self::token(), $given)) {
            throw new HttpException(419);
        }
    }
}
