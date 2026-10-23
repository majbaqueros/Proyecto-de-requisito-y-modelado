<?php
declare(strict_types=1);
namespace App\Infrastructure;
use App\Contracts\CorreoInterface;
use RuntimeException;

/** Transporte SMTP ligero; con STARTTLS obligatorio para proveedores externos. */
final class CorreoSmtp implements CorreoInterface {
    private $socket;
    private function response(array $expected): string {
        $result='';
        do{
            $line=fgets($this->socket,2048);
            if($line===false)throw new RuntimeException('El servidor SMTP no respondió.');
            $result.=$line;
        }while(strlen($line)>=4 && $line[3]==='-');
        if(!in_array((int)substr($result,-strlen($line),3),$expected,true)){
            throw new RuntimeException('Servidor SMTP rechazó la operación ('.substr($line,0,3).').');
        }
        return $result;
    }
    private function command(string $command,array $expected): string {
        fwrite($this->socket,$command."\r\n");return $this->response($expected);
    }
    public function enviar(string $destino,string $asunto,string $mensaje): void {
        if(!filter_var($destino,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Correo de destino incorrecto.');
        $host=(string)(getenv('SMTP_HOST')?:'');
        $port=(int)(getenv('SMTP_PORT')?:587);
        $mode=strtolower((string)(getenv('SMTP_ENCRYPTION')?:'starttls'));
        $user=(string)(getenv('SMTP_USER')?:'');$pass=(string)(getenv('SMTP_PASSWORD')?:'');
        $from=(string)(getenv('SMTP_FROM')?:'');
        if(!$host || !filter_var($from,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Configura SMTP_HOST y SMTP_FROM.');
        if(!in_array($mode,['none','starttls','ssl'],true))throw new RuntimeException('SMTP_ENCRYPTION no válido.');
        // Solo el servicio Mailpit del Docker puede funcionar sin cifrado y credenciales.
        if($mode==='none' && ($host!=='mailpit' || $user!==''))throw new RuntimeException('SMTP sin TLS solo permitido con Mailpit local.');
        $context=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
        $this->socket=@stream_socket_client(($mode==='ssl'?'ssl://':'tcp://').$host.':'.$port,$errorCode,$error,10,STREAM_CLIENT_CONNECT,$context);
        if(!$this->socket)throw new RuntimeException('No se pudo conectar al servidor de correo.');
        stream_set_timeout($this->socket,10);
        try{
            $this->response([220]);
            $this->command('EHLO saludcita.local',[250]);
            if($mode==='starttls'){
                $this->command('STARTTLS',[220]);
                if(stream_socket_enable_crypto($this->socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)!==true)throw new RuntimeException('TLS no disponible en SMTP.');
                $this->command('EHLO saludcita.local',[250]);
            }
            if($user!==''){
                $this->command('AUTH LOGIN',[334]);
                $this->command(base64_encode($user),[334]);
                $this->command(base64_encode($pass),[235]);
            }
            $this->command('MAIL FROM:<'.$from.'>',[250]);
            $this->command('RCPT TO:<'.$destino.'>',[250,251]);
            $this->command('DATA',[354]);
            $safeBody=preg_replace('/^\./m','..',str_replace(["\r\n","\r"],"\n",$mensaje));
            $safeBody=str_replace("\n","\r\n",$safeBody);
            $safeSubject='=?UTF-8?B?'.base64_encode(str_replace(["\r","\n"],'',$asunto)).'?=';
            $payload="From: SaludCita <{$from}>\r\nTo: <{$destino}>\r\nSubject: {$safeSubject}\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$safeBody}\r\n.\r\n";
            fwrite($this->socket,$payload);$this->response([250]);
            $this->command('QUIT',[221]);
        }finally{fclose($this->socket);}
    }
}
