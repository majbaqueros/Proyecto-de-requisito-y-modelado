<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/Autoload.php';
use App\Services\GoogleOidc;

putenv('GOOGLE_OAUTH_CLIENT_ID=cliente-de-pruebas.apps.googleusercontent.com');
putenv('GOOGLE_OAUTH_CLIENT_SECRET=solo-una-prueba');
putenv('GOOGLE_OAUTH_REDIRECT_URI=http://localhost:8080/api/auth/google/callback');
$state=str_repeat('a',64);
$nonce=str_repeat('b',64);
$challenge=str_repeat('c',43);
$url=GoogleOidc::authorizationUrl($state,$nonce,$challenge);
parse_str((string)parse_url($url,PHP_URL_QUERY),$params);
foreach([
    'response_type'=>'code','scope'=>'openid email profile',
    'state'=>$state,'nonce'=>$nonce,'code_challenge'=>$challenge,
    'code_challenge_method'=>'S256'
] as $key=>$value) {
    if(($params[$key]??null)!==$value)throw new RuntimeException('Falló parámetro '.$key);
}
if(parse_url($url,PHP_URL_HOST)!=='accounts.google.com')throw new RuntimeException('Host de Google incorrecto.');
try {
    GoogleOidc::verifyIdToken('a.b.c',$nonce);
    throw new RuntimeException('El token falso no fue rechazado.');
} catch(\RuntimeException $e) {
    if($e->getMessage()==='El token falso no fue rechazado.')throw $e;
}
putenv('GOOGLE_OAUTH_REDIRECT_URI=http://evil.example/api/auth/google/callback');
try {
    GoogleOidc::authorizationUrl($state,$nonce,$challenge);
    throw new RuntimeException('Redirect no seguro no fue rechazado.');
} catch(\RuntimeException $e) {
    if($e->getMessage()==='Redirect no seguro no fue rechazado.')throw $e;
}
echo "OK: OAuth Google - URL, estado, nonce, PKCE y rechazos locales.\n";
