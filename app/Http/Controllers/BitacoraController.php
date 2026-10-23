<?php
declare(strict_types=1);
namespace App\Http\Controllers;
use App\Core\{Auth,Http};

final class BitacoraController extends BaseController {
    public function index(array $params=[]): void {
        Auth::roles('administrador');
        $limit=max(1,min(200,(int)($_GET['limit']??75)));
        $table=(string)($_GET['tabla']??'');
        $allowed=['','users','patients','doctors','specialties','schedules','appointments','clinical_records','invoices','notifications'];
        if(!in_array($table,$allowed,true)) Http::fail('Tabla no válida.',422);
        $sql='SELECT id,occurred_at,actor_user_id,db_user,source,table_name,record_id,operation,previous_data,new_data FROM audit_logs';
        $args=[];if($table!==''){$sql.=' WHERE table_name=?';$args[]=$table;}
        $sql.=' ORDER BY id DESC LIMIT '.$limit;
        Http::json($this->many($this->db,$sql,$args));
    }
}
