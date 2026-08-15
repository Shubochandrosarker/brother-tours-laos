<?php

declare(strict_types=1);

namespace BrotherTours\OperationsApi\System;

use BrotherTours\OperationsApi\Auth\Csrf;
use WP_REST_Request;

use function BrotherTours\OperationsApi\response;
use function BrotherTours\OperationsApi\table_exists;

final class HealthController {
	public function register():void{add_action('rest_api_init',array($this,'routes'));}
	public function routes():void{register_rest_route(BTOA_NAMESPACE,'/system/health',array('methods'=>'GET','callback'=>array($this,'get'),'permission_callback'=>static fn(WP_REST_Request $r)=>Csrf::authorize($r,'bt_view_health',false)));}
	public function get(){global $wpdb;$prefix=$wpdb->prefix.'wpistic_';$required=array('bookings','transactions','webhook_events','audit_log','connections','connection_log','form_ingestions');$tables=array();foreach($required as $name)$tables[$name]=table_exists($prefix.$name);$cron=array();foreach(array('wpistic_tm_connection_retry','wpistic_formistic_retention_cleanup') as $hook){$next=wp_next_scheduled($hook);$cron[]=array('hook'=>$hook,'nextRunAt'=>$next?gmdate('c',$next):null);}$conn_failures=0;if($tables['connection_log'])$conn_failures=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}connection_log WHERE created_at >= %s AND (status_code < 200 OR status_code >= 300)",gmdate('Y-m-d H:i:s',time()-DAY_IN_SECONDS)));$plugin=array('tourManager'=>array('active'=>class_exists('\\Wpistic\\TourManager\\Booking\\BookingService'),'version'=>defined('WPISTIC_TM_VERSION')?(string)WPISTIC_TM_VERSION:null),'formistic'=>array('active'=>class_exists('\\Wpistic_Formistic_Database'),'version'=>defined('WPISTIC_FORMISTIC_VERSION')?(string)WPISTIC_FORMISTIC_VERSION:null,'brotherToursFork'=>defined('BROTHER_TOURS_FORMISTIC')?(string)BROTHER_TOURS_FORMISTIC:null));$cpts=array();foreach(array('wpistic_tour','wpistic_destination','wpistic_experience','wpistic_departure') as $type)$cpts[$type]=post_type_exists($type);return response(array('status'=>in_array(false,$tables,true)||!$plugin['tourManager']['active']?'warning':'healthy','wordpress'=>array('version'=>get_bloginfo('version'),'php'=>PHP_VERSION,'siteUrl'=>home_url(),'restUrl'=>rest_url(BTOA_NAMESPACE)),'plugins'=>$plugin,'postTypes'=>$cpts,'tables'=>$tables,'cron'=>$cron,'connections'=>array('failures24h'=>$conn_failures),'security'=>array('https'=>is_ssl(),'sessionCookie'=>BTOA_SESSION_COOKIE,'csrfHeader'=>'X-BT-CSRF')));}
}
