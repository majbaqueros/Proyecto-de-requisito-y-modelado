<?php
declare(strict_types=1);
namespace App\Http\Controllers;

use App\Core\{Auth,Http};
use App\Services\GoogleOidc;
use PDO;
use RuntimeException;

/** Login Google OpenID Connect: vinculación explícita sin promoción de roles. */
final class GoogleAuthController extends BaseController {
    private const LIFETIME = 600;

    public function iniciar(array $params=[]): void {
        if (!GoogleOidc::configured()) Http::fail('Ingreso con Google no configurado.',503);
        if (Auth::user()) Http::fail('Cierra sesión para iniciar otra cuenta.',409);
        $role=(string)($_GET['role']??'paciente');
        if (!in_array($role,Auth::ROLES,true)) Http::fail('Perfil inválido.',422);
        $this->start('login',$role);
    }

    public function vincular(array $params=[]): void {
        if (!GoogleOidc::configured()) Http::fail('Ingreso con Google no configurado.',503);
        $user=Auth::requireLogin();
        $this->start('link',(string)$user['role']);
    }

    private function start(string $mode,string $role): void {
        $state=bin2hex(random_bytes(32));
        $nonce=bin2hex(random_bytes(32));
        $verifier=rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'=');
        $challenge=rtrim(strtr(base64_encode(hash('sha256',$verifier,true)),'+/','-_'),'=');
        $_SESSION['google_oauth']=[
            'state'=>$state,'nonce'=>$nonce,'verifier'=>$verifier,
            'mode'=>$mode,'role'=>$role,'expires'=>time()+self::LIFETIME,
            'user_id'=>(int)(Auth::user()['id']??0)
        ];
        $url=GoogleOidc::authorizationUrl($state,$nonce,$challenge);
        if ($mode==='link') Http::json(['url'=>$url]);
        header('Location: '.$url,true,302);
        header('Cache-Control: no-store');exit;
    }

    public function callback(array $params=[]): void {
        $pending=$_SESSION['google_oauth']??null;
        unset($_SESSION['google_oauth']);
        $role=is_array($pending)?(string)($pending['role']??'paciente'):'paciente';
        if(!in_array($role,Auth::ROLES,true))$role='paciente';
        $fallback='/'.$role;
        $state=(string)($_GET['state']??'');
        $code=(string)($_GET['code']??'');
        if(!is_array($pending) || ($pending['expires']??0)<time()
            || strlen($state)!==64 || !hash_equals((string)($pending['state']??''),$state)
            || isset($_GET['error'])) $this->redirect($fallback,'google_error=cancelado');
        try{
            $profile=GoogleOidc::identityFromCode($code,(string)$pending['verifier'],(string)$pending['nonce']);
            if($pending['mode']==='link'){
                $actor=Auth::requireLogin();
                if((int)$actor['id']!==(int)$pending['user_id'])throw new RuntimeException('La sesión cambió durante la vinculación.');
                $this->linkIdentity((int)$actor['id'],$profile);
                $this->redirect('/'.$actor['role'],'google_linked=1');
            }
            if($pending['mode']!=='login')throw new RuntimeException('Modo de autenticación inválido.');
            $stmt=$this->db->prepare(
                "SELECT u.* FROM oauth_identities o JOIN users u ON u.id=o.user_id
                  WHERE o.provider='google' AND o.provider_sub=? AND u.active=TRUE"
            );
            $stmt->execute([$profile['sub']]);$user=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$user)$this->redirect($fallback,'google_error=sin_vincular');
            if($user['role']!==$role)$this->redirect($fallback,'google_error=perfil');
            Auth::login($user);
            $this->redirect('/'.$role);
        }catch(\Throwable $e){
            error_log('Google OAuth: '.get_class($e));
            $this->redirect($fallback,'google_error=configuracion');
        }
    }

    private function linkIdentity(int $uid,array $profile): void {
        $this->db->beginTransaction();
        try{
            $stmt=$this->db->prepare('SELECT email,active FROM users WHERE id=? FOR UPDATE');
            $stmt->execute([$uid]);$user=$stmt->fetch(PDO::FETCH_ASSOC);
            if(!$user || !in_array($user['active'],[true,1,'1','t'],true)
              || strtolower($user['email'])!==$profile['email']){
                throw new RuntimeException('El correo verificado por Google debe coincidir con el de SaludCita.');
            }
            $stmt=$this->db->prepare("SELECT user_id FROM oauth_identities WHERE provider='google' AND provider_sub=?");
            $stmt->execute([$profile['sub']]);$owner=$stmt->fetchColumn();
            if($owner && (int)$owner!==$uid)throw new RuntimeException('Google ya vinculado a otra cuenta.');
            $stmt=$this->db->prepare("SELECT provider_sub FROM oauth_identities WHERE provider='google' AND user_id=?");
            $stmt->execute([$uid]);$existing=$stmt->fetchColumn();
            if($existing && !hash_equals((string)$existing,$profile['sub']))throw new RuntimeException('La cuenta ya tiene otro Google vinculado.');
            if(!$existing){
                $stmt=$this->db->prepare("INSERT INTO oauth_identities(provider,provider_sub,user_id) VALUES('google',?,?)");
                $stmt->execute([$profile['sub'],$uid]);
            }
            $this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    private function redirect(string $path,string $query=''): never {
        header('Cache-Control: no-store');
        header('Location: '.$path.($query!==''?'?'.$query:''),true,303);exit;
    }
}
