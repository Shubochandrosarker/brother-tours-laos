<?php

declare(strict_types=1);

namespace BrotherTours\OperationsApi\Team;

use BrotherTours\OperationsApi\Auth\Csrf;
use WP_REST_Request;
use WP_User_Query;

use function BrotherTours\OperationsApi\error;
use function BrotherTours\OperationsApi\response;
use function BrotherTours\OperationsApi\table_exists;

final class TeamController {
	public function register():void{add_action('rest_api_init',array($this,'routes'));}
	public function routes():void{
		$read=static fn(WP_REST_Request $r)=>Csrf::authorize($r,'bt_manage_operations',false);
		register_rest_route(BTOA_NAMESPACE,'/team',array('methods'=>'GET','callback'=>array($this,'list'),'permission_callback'=>$read));
		register_rest_route(BTOA_NAMESPACE,'/team/(?P<id>\d+)',array('methods'=>'GET','callback'=>array($this,'get'),'permission_callback'=>$read));
	}
	public function list(WP_REST_Request $r){$page=max(1,(int)$r->get_param('page'));$per=min(100,max(1,(int)($r->get_param('per_page')?:50)));$q=new WP_User_Query(array('number'=>$per,'offset'=>($page-1)*$per,'search'=>sanitize_text_field((string)$r->get_param('search'))? '*'.sanitize_text_field((string)$r->get_param('search')).'*':'','search_columns'=>array('user_login','user_email','display_name'),'orderby'=>'display_name','order'=>'ASC'));$items=array_map(fn($u)=>$this->format((int)$u->ID),(array)$q->get_results());return response(array('items'=>$items,'total'=>(int)$q->get_total(),'page'=>$page,'perPage'=>$per,'totalPages'=>(int)ceil((int)$q->get_total()/$per)));}
	public function get(WP_REST_Request $r){$id=(int)$r['id'];if(!get_userdata($id))return error('bt_ops_user_not_found',__('Team member not found.','brother-tours-operations-api'),404);return response($this->format($id,true));}
	private function format(int $id,bool $detail=false):array{global $wpdb;$u=get_userdata($id);$assigned=0;$open=0;$table=$wpdb->prefix.'wpistic_bookings';if(table_exists($table)){$assigned=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE assigned_to=%d",$id));$open=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE assigned_to=%d AND portal_status!='closed'",$id));}$data=array('id'=>$id,'displayName'=>(string)$u->display_name,'email'=>(string)$u->user_email,'roles'=>array_values($u->roles),'avatar'=>get_avatar_url($id,array('size'=>96)),'assignedTotal'=>$assigned,'openAssignments'=>$open);if($detail){$data['registeredAt']=(string)$u->user_registered;$data['capabilities']=array_keys(array_filter($u->allcaps));}return $data;}
}
