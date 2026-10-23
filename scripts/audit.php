<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Core/Autoload.php';
use App\Core\Database;
$limit=min(200,max(1,(int)($argv[1]??30)));
$table=$argv[2]??'';
$allowed=['','users','patients','doctors','specialties','schedules','appointments','clinical_records','invoices','notifications'];
if(!in_array($table,$allowed,true)){fwrite(STDERR,"Tabla desconocida.\n");exit(1);}
$sql='SELECT id,to_char(occurred_at,\'YYYY-MM-DD HH24:MI:SS\') AS fecha,source,COALESCE(actor_user_id::TEXT,db_user) AS actor,table_name,record_id,operation,previous_data,new_data FROM audit_logs';
$args=[];
if($table!==''){$sql.=' WHERE table_name=?';$args[]=$table;}
$sql.=' ORDER BY id DESC LIMIT '.$limit;
$st=Database::connect()->prepare($sql);$st->execute($args);
foreach($st as $row){echo json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n";}
