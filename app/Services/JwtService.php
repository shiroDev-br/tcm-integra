<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\Unauthenticated;
use RuntimeException;

/**
 * JWT HS256 (same claims and format as the original PyJWT implementation).
 */
class JwtService
{
    public const ALGORITHM = 'HS256';

    /**
     * Creates an access token: sub (username), role, iat and exp.
     *
     * @return array{access_token: string, token_type: string}
     */
    public function create(string $username, Role $role): array
    {
        $now = time();
        $expira = $now + ((int) config('tcm.token_expiry_minutes') * 60);

        $header = self::base64UrlEncode(json_encode(['alg' => self::ALGORITHM, 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = self::base64UrlEncode(json_encode([
            'sub' => $username,
            'role' => $role->value,
            'iat' => $now,
            'exp' => $expira,
        ], JSON_THROW_ON_ERROR));

        $assinatura = self::base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $this->key(), true));

        return [
            'access_token' => "{$header}.{$payload}.{$assinatura}",
            'token_type' => 'bearer',
        ];
    }

    /**
     * Validates signature and expiration. Any failure is an Unauthenticated error (401).
     *
     * @return array{sub: string, role: string}
     */
    public function decode(string $token): array
    {
        $partes = explode('.', $token);

        if (count($partes) !== 3) {
            throw new Unauthenticated;
        }

        [$header, $payload, $assinatura] = $partes;
        $esperada = self::base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $this->key(), true));

        if (! hash_equals($esperada, $assinatura)) {
            throw new Unauthenticated;
        }

        $cabecalho = json_decode(self::base64UrlDecode($header), true);

        if (! is_array($cabecalho) || ($cabecalho['alg'] ?? null) !== self::ALGORITHM) {
            throw new Unauthenticated;
        }

        $claims = json_decode(self::base64UrlDecode($payload), true);

        if (! is_array($claims) || ! isset($claims['sub'], $claims['role'], $claims['exp'])) {
            throw new Unauthenticated;
        }

        if (! is_numeric($claims['exp']) || (int) $claims['exp'] <= time()) {
            throw new Unauthenticated;
        }

        return ['sub' => (string) $claims['sub'], 'role' => (string) $claims['role']];
    }

    private function key(): string
    {
        $chave = (string) config('tcm.secret_key');

        if (strlen($chave) < 32) {
            throw new RuntimeException(
                'TCM_SECRET_KEY ausente ou curta (mínimo 32 caracteres). '
                .'Gere uma com: openssl rand -hex 32'
            );
        }

        return $chave;
    }

    private static function base64UrlEncode(string $dados): string
    {
        return rtrim(strtr(base64_encode($dados), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $dados): string
    {
        $base64 = strtr($dados, '-_', '+/');
        $preenchido = str_pad($base64, (int) ceil(strlen($base64) / 4) * 4, '=', STR_PAD_RIGHT);

        return (string) base64_decode($preenchido, true);
    }
}
