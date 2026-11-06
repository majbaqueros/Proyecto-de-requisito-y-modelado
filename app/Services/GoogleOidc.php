<?php
declare(strict_types=1);
namespace App\Services;

use RuntimeException;

/**
 * Autenticación OpenID Connect sin dependencia de Google en el navegador.
 * Verifica criptográficamente la firma RS256 del ID Token con los certificados de Google.
 * Solo usa URL de Google predefinidas; nunca acepta direcciones remotas del usuario.
 */
final class GoogleOidc {
    public static function configured(): bool {
        return trim((string)getenv('GOOGLE_OAUTH_CLIENT_ID')) !== ''
            && trim((string)getenv('GOOGLE_OAUTH_CLIENT_SECRET')) !== ''
            && trim((string)getenv('GOOGLE_OAUTH_REDIRECT_URI')) !== '';
    }

    private static function cfg(string $key): string {
        $value = trim((string)(getenv($key) ?: ''));
        if ($value === '') throw new RuntimeException('Google OAuth no está configurado.');
        return $value;
    }

    public static function authorizationUrl(string $state, string $nonce, string $challenge): string {
        $redirect = self::cfg('GOOGLE_OAUTH_REDIRECT_URI');
        self::checkRedirect($redirect);
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => self::cfg('GOOGLE_OAUTH_CLIENT_ID'),
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account'
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private static function checkRedirect(string $redirect): void {
        $parts = parse_url($redirect);
        $host = strtolower((string)($parts['host'] ?? ''));
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        if (($scheme !== 'https' && !(in_array($host, ['localhost','127.0.0.1'], true) && $scheme === 'http'))
            || empty($parts['path']) || !str_ends_with($parts['path'], '/api/auth/google/callback')
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new RuntimeException('GOOGLE_OAUTH_REDIRECT_URI debe ser HTTPS y terminar en /api/auth/google/callback.');
        }
    }

    private static function request(string $url, ?array $form = null): array {
        $options = [
            'http' => [
                'method' => $form === null ? 'GET' : 'POST',
                'timeout' => 10,
                'follow_location' => 0,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\n".($form !== null ? "Content-Type: application/x-www-form-urlencoded\r\n" : ''),
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]
        ];
        if ($form !== null) $options['http']['content'] = http_build_query($form, '', '&', PHP_QUERY_RFC3986);
        $response = @file_get_contents($url, false, stream_context_create($options));
        if ($response === false || strlen($response) > 150000) {
            throw new RuntimeException('No se pudo contactar al servicio de identidad de Google.');
        }
        $json = json_decode($response, true);
        if (!is_array($json)) throw new RuntimeException('Respuesta de identidad inválida.');
        if (isset($json['error'])) throw new RuntimeException('Google rechazó la autorización.');
        return $json;
    }

    private static function b64(string $value): string {
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $value)) throw new RuntimeException('ID Token mal formado.');
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4-strlen($value)%4)%4), true);
        if ($decoded === false) throw new RuntimeException('ID Token mal formado.');
        return $decoded;
    }

    /** @return array{sub:string,email:string,name:string} */
    public static function identityFromCode(string $code, string $verifier, string $nonce): array {
        if (strlen($code) < 8 || strlen($code) > 4096) throw new RuntimeException('Código de Google inválido.');
        $redirect = self::cfg('GOOGLE_OAUTH_REDIRECT_URI');
        self::checkRedirect($redirect);
        $tokens = self::request('https://oauth2.googleapis.com/token', [
            'client_id' => self::cfg('GOOGLE_OAUTH_CLIENT_ID'),
            'client_secret' => self::cfg('GOOGLE_OAUTH_CLIENT_SECRET'),
            'redirect_uri' => $redirect,
            'code' => $code,
            'code_verifier' => $verifier,
            'grant_type' => 'authorization_code'
        ]);
        $idToken = (string)($tokens['id_token'] ?? '');
        if (strlen($idToken) < 100 || strlen($idToken) > 10000) {
            throw new RuntimeException('Google no devolvió una identidad válida.');
        }
        return self::verifyIdToken($idToken, $nonce);
    }

    /** Verifica firma y claims OIDC; no se confía en el email sin verificar. */
    public static function verifyIdToken(string $token, string $nonce): array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) throw new RuntimeException('ID Token mal formado.');
        [$h,$p,$s] = $parts;
        $header = json_decode(self::b64($h), true);
        $claims = json_decode(self::b64($p), true);
        if (!is_array($header) || !is_array($claims)
            || ($header['alg'] ?? '') !== 'RS256'
            || !is_string($header['kid'] ?? null)
            || !preg_match('/^[A-Za-z0-9_-]{5,200}$/D', $header['kid'])) {
            throw new RuntimeException('Cabecera de identidad inválida.');
        }
        // Google ofrece certificados PEM indexados por kid; firma comprobada localmente.
        $certificates = self::request('https://www.googleapis.com/oauth2/v1/certs');
        $pem = $certificates[$header['kid']] ?? null;
        if (!is_string($pem) || !str_contains($pem, 'BEGIN CERTIFICATE')
            || openssl_verify($h.'.'.$p, self::b64($s), $pem, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Firma de identidad de Google no válida.');
        }
        $now = time();
        if (!in_array(($claims['iss'] ?? ''), ['https://accounts.google.com','accounts.google.com'], true)
            || !hash_equals(self::cfg('GOOGLE_OAUTH_CLIENT_ID'), (string)($claims['aud'] ?? ''))
            || (int)($claims['exp'] ?? 0) <= $now
            || (int)($claims['iat'] ?? 0) > $now + 120
            || !hash_equals($nonce, (string)($claims['nonce'] ?? ''))
            || !in_array($claims['email_verified'] ?? false, [true,'true','1',1], true)) {
            throw new RuntimeException('Las verificaciones de seguridad de Google no coinciden.');
        }
        $sub = (string)($claims['sub'] ?? '');
        $email = strtolower(trim((string)($claims['email'] ?? '')));
        $name = trim((string)($claims['name'] ?? 'Usuario Google'));
        if (strlen($sub) < 2 || strlen($sub) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Google devolvió un perfil incompleto.');
        }
        return ['sub'=>$sub, 'email'=>$email, 'name'=>mb_substr($name,0,120)];
    }
}
