<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/Autoload.php';
use App\Core\Database;

// La clave se guarda en un volumen Docker privado cuando APP_KEY_FILE está configurada.
// Se conserva la clave anterior si ya existe en storage/app.key, sin rotarla.
$projectRoot=dirname(__DIR__);
$legacyKeyFile=$projectRoot.'/storage/app.key';
$secretPath=trim((string)(getenv('APP_KEY_FILE') ?: ''));
if ($secretPath==='') $secretPath=$legacyKeyFile;
$secretDirectory=dirname($secretPath);
if(!is_dir($secretDirectory) && !mkdir($secretDirectory,0750,true) && !is_dir($secretDirectory)){
    throw new RuntimeException('No se pudo crear el directorio de claves.');
}
if(!is_file($secretPath)){
    $keyToSave=null;
    if($secretPath!==$legacyKeyFile && is_file($legacyKeyFile)){
        // El instalador se ejecuta como root: puede importar la clave anterior
        // incluso si el servidor web no podía leerla en el montaje de Windows.
        $previous=@file_get_contents($legacyKeyFile);
        if($previous===false || strlen(trim($previous))<32){
            throw new RuntimeException('La clave anterior existe pero no se puede importar. No se generó una clave nueva para evitar perder los códigos vigentes.');
        }
        $keyToSave=trim($previous);
    }
    $keyToSave ??= bin2hex(random_bytes(32));
    if(file_put_contents($secretPath,$keyToSave,LOCK_EX)===false){
        throw new RuntimeException('No se pudo guardar la clave de recuperación.');
    }
}
// En un volumen Docker de Linux los permisos 0750/0640 y el grupo www-data
// funcionan de forma estable, a diferencia de algunos bind mounts en Windows.
if (!chgrp($secretDirectory,'www-data') || !chmod($secretDirectory,0750)
    || !chgrp($secretPath,'www-data') || !chmod($secretPath,0640)) {
    throw new RuntimeException('No se pudieron ajustar los permisos de la clave (grupo www-data).');
}
$loadedKey=@file_get_contents($secretPath);
if($loadedKey===false || strlen(trim($loadedKey))<32){
    throw new RuntimeException('La clave de recuperación falta o es inválida.');
}
$db = Database::connect();
$db->exec(file_get_contents(dirname(__DIR__).'/database/schema.sql'));
$db->exec(file_get_contents(dirname(__DIR__).'/database/migrations/005_security.sql'));
function addUser(PDO $db, string $name, string $email, string $password, string $role): int {
    $stmt = $db->prepare('SELECT id FROM users WHERE email=?');
    $stmt->execute([$email]); $id=$stmt->fetchColumn();
    if ($id) { return (int)$id; }
    $stmt=$db->prepare('INSERT INTO users(name,email,password_hash,role) VALUES (?,?,?,?) RETURNING id');
    $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
    return (int)$stmt->fetchColumn();
}
function idFor(PDO $db, string $table, string $field, string|int $value): ?int {
    $allowed=['specialties'=>'name','patients'=>'user_id','doctors'=>'user_id'];
    if (($allowed[$table] ?? '')!==$field) { throw new RuntimeException('Consulta no permitida'); }
    $stmt=$db->prepare("SELECT id FROM {$table} WHERE {$field}=?");
    $stmt->execute([$value]); return ($v=$stmt->fetchColumn()) ? (int)$v : null;
}
$db->beginTransaction();
try {
    foreach (['Medicina general','Pediatría','Cardiología','Dermatología'] as $esp) {
        $stmt=$db->prepare('INSERT INTO specialties(name) VALUES(?) ON CONFLICT(name) DO NOTHING'); $stmt->execute([$esp]);
    }
    addUser($db,'Administrador Demo','admin@clinica.local','Admin123*','administrador');
    addUser($db,'Recepción Demo','recepcion@clinica.local','Recep123*','recepcionista');
    $patientUid=addUser($db,'Ana Paciente','paciente@clinica.local','Paciente123*','paciente');
    $doctorUid=addUser($db,'Dr. Luis Mendoza','medico@clinica.local','Medico123*','medico');
    $p=$db->prepare('INSERT INTO patients(user_id,document,birth_date,address) VALUES (?,?,?,?) ON CONFLICT (user_id) DO NOTHING');
    $p->execute([$patientUid,'DEMO-0001','1998-05-17','Santa Cruz de la Sierra']);
    $esp=idFor($db,'specialties','name','Medicina general');
    $p=$db->prepare('INSERT INTO doctors(user_id,specialty_id,consultation_price) VALUES(?,?,?) ON CONFLICT(user_id) DO NOTHING');
    $p->execute([$doctorUid,$esp,120]);
    $doctorId=idFor($db,'doctors','user_id',$doctorUid);
    $s=$db->prepare('INSERT INTO schedules(doctor_id,weekday,start_time,end_time) VALUES(?,?,?,?) ON CONFLICT DO NOTHING');
    foreach ([1,2,3,4,5] as $weekday) { $s->execute([$doctorId,$weekday,'08:00','12:00']); $s->execute([$doctorId,$weekday,'14:00','18:00']); }
    // Datos ficticios útiles para demostrar todos los perfiles sin esperar a una cita futura.
    $pacienteId=idFor($db,'patients','user_id',$patientUid);
    $tz=new DateTimeZone(getenv('APP_TZ') ?: 'America/La_Paz');
    $yesterday=(new DateTimeImmutable('today',$tz))->modify('-1 day');
    while (in_array((int)$yesterday->format('w'),[0,6],true)) { $yesterday=$yesterday->modify('-1 day'); }
    $tomorrow=(new DateTimeImmutable('today',$tz))->modify('+1 day');
    while (in_array((int)$tomorrow->format('w'),[0,6],true)) { $tomorrow=$tomorrow->modify('+1 day'); }
    $sampleAppointments=[
        [$yesterday->setTime(8,0)->format('Y-m-d H:i:s'),'Control de rutina','completada'],
        [$yesterday->setTime(9,0)->format('Y-m-d H:i:s'),'Dolor de cabeza y control','confirmada'],
        [$tomorrow->setTime(11,0)->format('Y-m-d H:i:s'),'Revisión médica general','pendiente']
    ];
    $appointmentIds=[];
    $find=$db->prepare('SELECT id FROM appointments WHERE patient_id=? AND doctor_id=? AND reason=?');
    $add=$db->prepare('INSERT INTO appointments(patient_id,doctor_id,scheduled_at,reason,status,created_by) VALUES(?,?,?,?,?,?) RETURNING id');
    foreach($sampleAppointments as $sample) {
        $find->execute([$pacienteId,$doctorId,$sample[1]]);
        $exist=$find->fetchColumn();
        if ($exist) { $appointmentIds[]=(int)$exist; }
        else {
            $add->execute([$pacienteId,$doctorId,$sample[0],$sample[1],$sample[2],$patientUid]);
            $appointmentIds[]=(int)$add->fetchColumn();
        }
    }
    $stmt=$db->prepare('INSERT INTO clinical_records(appointment_id,patient_id,doctor_id,symptoms,diagnosis,treatment,notes) VALUES(?,?,?,?,?,?,?) ON CONFLICT (appointment_id) DO NOTHING');
    $stmt->execute([$appointmentIds[0],$pacienteId,$doctorId,'Chequeo de rutina','Control preventivo sin hallazgos relevantes','Mantener alimentación saludable e hidratación','Registro ficticio para demostración']);
    $stmt=$db->prepare("INSERT INTO invoices(appointment_id,billing_type,subtotal,discount,total,status,paid_at) VALUES(?,'normal',120,0,120,'pagada',NOW()) ON CONFLICT (appointment_id) DO NOTHING");
    $stmt->execute([$appointmentIds[0]]);
    $stmt=$db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=?');
    $stmt->execute([$patientUid]);
    if ((int)$stmt->fetchColumn()===0) {
        $stmt=$db->prepare('INSERT INTO notifications(user_id,title,message) VALUES (?,?,?)');
        $stmt->execute([$patientUid,'Bienvenida a SaludCita','Ya puedes revisar tus citas, historial médico, facturas y notificaciones de ejemplo.']);
    }
    $db->commit();
    echo "Instalación realizada. Ingresa en http://localhost:8080\n";
    echo "Usuarios DEMO: admin@clinica.local, recepcion@clinica.local, medico@clinica.local, paciente@clinica.local\n";
} catch(Throwable $e) { $db->rollBack(); throw $e; }
