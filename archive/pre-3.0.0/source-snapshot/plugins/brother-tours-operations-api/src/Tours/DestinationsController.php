<?php

declare(strict_types=1);

namespace BrotherTours\OperationsApi\Tours;

use BrotherTours\OperationsApi\Auth\Csrf;
use WP_Query;
use WP_REST_Request;

use function BrotherTours\OperationsApi\error;
use function BrotherTours\OperationsApi\response;

final class DestinationsController {
	public function register(): void { add_action( 'rest_api_init', array( $this, 'routes' ) ); }

	public function routes(): void {
		register_rest_route( BTOA_NAMESPACE, '/destinations', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'list' ), 'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'bt_manage_operations', false ) ),
			array( 'methods' => 'POST', 'callback' => array( $this, 'create' ), 'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'bt_manage_operations', true ) ),
		) );
		register_rest_route( BTOA_NAMESPACE, '/destinations/(?P<id>\d+)', array(
			array( 'methods' => 'GET', 'callback' => array( $this, 'get' ), 'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'bt_manage_operations', false ) ),
			array( 'methods' => 'PATCH', 'callback' => array( $this, 'update' ), 'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'bt_manage_operations', true ) ),
			array( 'methods' => 'DELETE', 'callback' => array( $this, 'delete' ), 'permission_callback' => static fn( WP_REST_Request $r ) => Csrf::authorize( $r, 'delete_posts', true ) ),
		) );
	}

	public function list( WP_REST_Request $r ) {
		$page=max(1,(int)$r->get_param('page')); $per=min(100,max(1,(int)($r->get_param('per_page')?:20)));
		$q=new WP_Query(array('post_type'=>'wpistic_destination','post_status'=>array('publish','draft','pending','private'),'paged'=>$page,'posts_per_page'=>$per,'s'=>sanitize_text_field((string)$r->get_param('search')),'orderby'=>'title','order'=>'ASC'));
		return response(array('items'=>array_map(fn($p)=>$this->format($p->ID),$q->posts),'total'=>(int)$q->found_posts,'page'=>$page,'perPage'=>$per,'totalPages'=>(int)$q->max_num_pages));
	}
	public function get( WP_REST_Request $r ) { $id=(int)$r['id']; return $this->valid($id)?response($this->format($id)):error('bt_ops_destination_not_found',__('Destination not found.','brother-tours-operations-api'),404); }
	public function create( WP_REST_Request $r ) {
		$p=$this->params($r); if(empty($p['title']))return error('bt_ops_destination_title_required',__('A destination title is required.','brother-tours-operations-api'),422);
		$id=wp_insert_post(array('post_type'=>'wpistic_destination','post_title'=>sanitize_text_field((string)$p['title']),'post_content'=>wp_kses_post((string)($p['content']??'')),'post_excerpt'=>sanitize_textarea_field((string)($p['excerpt']??'')),'post_status'=>$this->status((string)($p['status']??'draft')),'post_name'=>sanitize_title((string)($p['slug']??''))),true);
		if(is_wp_error($id))return $id; $this->save((int)$id,$p); return response($this->format((int)$id),201);
	}
	public function update( WP_REST_Request $r ) {
		$id=(int)$r['id']; if(!$this->valid($id))return error('bt_ops_destination_not_found',__('Destination not found.','brother-tours-operations-api'),404); if(!current_user_can('edit_post',$id))return error('bt_ops_destination_forbidden',__('You cannot edit this destination.','brother-tours-operations-api'),403);
		$p=$this->params($r); $patch=array('ID'=>$id); foreach(array('title'=>'post_title','content'=>'post_content','excerpt'=>'post_excerpt','slug'=>'post_name') as $k=>$f){if(array_key_exists($k,$p))$patch[$f]='content'===$k?wp_kses_post((string)$p[$k]):('excerpt'===$k?sanitize_textarea_field((string)$p[$k]):('slug'===$k?sanitize_title((string)$p[$k]):sanitize_text_field((string)$p[$k])));} if(array_key_exists('status',$p))$patch['post_status']=$this->status((string)$p['status']); $res=wp_update_post($patch,true); if(is_wp_error($res))return $res; $this->save($id,$p); return response($this->format($id));
	}
	public function delete( WP_REST_Request $r ) { $id=(int)$r['id']; if(!$this->valid($id))return error('bt_ops_destination_not_found',__('Destination not found.','brother-tours-operations-api'),404); if(!current_user_can('delete_post',$id))return error('bt_ops_destination_forbidden',__('You cannot delete this destination.','brother-tours-operations-api'),403); $force=filter_var($r->get_param('force'),FILTER_VALIDATE_BOOLEAN); return wp_delete_post($id,$force)?response(array('id'=>$id,'deleted'=>true)):error('bt_ops_destination_delete_failed',__('Delete failed.','brother-tours-operations-api'),500); }
	private function save(int $id,array $p):void { if(isset($p['oneLine']))update_post_meta($id,'wpistic_one_line',sanitize_text_field((string)$p['oneLine'])); if(isset($p['featuredMedia'])){ $m=absint($p['featuredMedia']); if(0===$m||'attachment'===get_post_type($m))set_post_thumbnail($id,$m);} if(isset($p['taxonomies'])&&is_array($p['taxonomies']))foreach(array('country','region') as $tax)if(array_key_exists($tax,$p['taxonomies'])&&taxonomy_exists($tax))wp_set_object_terms($id,array_filter(array_map('absint',(array)$p['taxonomies'][$tax])),$tax,false); }
	private function format(int $id):array { $p=get_post($id); $thumb=get_post_thumbnail_id($id); $tax=[]; foreach(array('country','region') as $t){$terms=get_the_terms($id,$t);$tax[$t]=is_array($terms)?array_map(static fn($x)=>array('id'=>(int)$x->term_id,'name'=>(string)$x->name,'slug'=>(string)$x->slug),$terms):array();} return array('id'=>$id,'title'=>(string)$p->post_title,'slug'=>(string)$p->post_name,'content'=>(string)$p->post_content,'excerpt'=>(string)$p->post_excerpt,'status'=>(string)$p->post_status,'oneLine'=>(string)get_post_meta($id,'wpistic_one_line',true),'featuredMedia'=>(int)$thumb,'featuredImage'=>$thumb?(string)wp_get_attachment_image_url($thumb,'large'):'','permalink'=>(string)get_permalink($id),'taxonomies'=>$tax,'modifiedAt'=>get_post_modified_time(DATE_ATOM,true,$p)); }
	private function valid(int $id):bool { return 'wpistic_destination'===get_post_type($id); }
	private function params(WP_REST_Request $r):array { $j=$r->get_json_params(); return is_array($j)?$j:$r->get_params(); }
	private function status(string $s):string { return in_array($s,array('publish','draft','pending','private'),true)?$s:'draft'; }
}
