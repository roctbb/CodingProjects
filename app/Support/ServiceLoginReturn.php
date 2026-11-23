<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

final class ServiceLoginReturn
{
    private const PREFIX = 'service-login-return:';

    public static function loginUrl(string $callback): string
    {
        return route('login', ['service_login' => Crypt::encryptString(self::PREFIX.$callback)]);
    }

    public static function issuerUrl(Request $request): ?string
    {
        $continuation = $request->query('service_login');
        if (!is_string($continuation) || $continuation === '' || strlen($continuation) > 8192) {
            return null;
        }

        try {
            $value = Crypt::decryptString($continuation);
        } catch (DecryptException) {
            return null;
        }

        if (!str_starts_with($value, self::PREFIX)) {
            return null;
        }

        // Only the existing JWT issuer validates the external destination and issues a token.
        $callback = substr($value, strlen(self::PREFIX));
        return route('service.jwt', ['redirect_url' => $callback], false);
    }
}
