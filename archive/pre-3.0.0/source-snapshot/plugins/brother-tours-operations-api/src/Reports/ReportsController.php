<?php

declare(strict_types=1);

namespace BrotherTours\OperationsApi\Reports;

use BrotherTours\OperationsApi\Auth\Csrf;
use WP_REST_Request;

use function BrotherTours\OperationsApi\response;
use function BrotherTours\OperationsApi\table_exists;

final class ReportsController {
	public function register():void{add_action('rest_api_init',array($this,'routes'));}
	public function routes():void{
		$read=static fn(WP_REST_Request $r)=>Csrf::authorize($r,'bt_manage_operations',false);
		register_rest_route(BTOA_NAMESPACE,'/reports/overview',array('methods'=>'GET','callback'=>array($this,'overview'),'permission_callback'=>$read));
		register_rest_route(BTOA_NAMESPACE,'/reports/bookings',array('methods'=>'GET','callback'=>array($this,'bookings'),'permission_callback'=>$read));
		register_rest_route(BTOA_NAMESPACE,'/reports/forms',array('methods'=>'GET','callback'=>array($this,'forms'),'permission_callback'=>$read));
	}
	public function overview(WP_REST_Request $r){$range=$this->range($r);$book=$this->booking_report($range['from'],$range['to']);$forms=$this->form_report();return response(array('range'=>$range,'bookings'=>$book,'forms'=>$forms,'content'=>array('toursPublished'=>(int)(wp_count_posts('wpistic_tour')->publish??0),'destinationsPublished'=>(int)(wp_count_posts('wpistic_destination')->publish??0),'experiencesPublished'=>(int)(wp_count_posts('wpistic_experience')->publish??0))));}
	public function bookings(WP_REST_Request $r){$range=$this->range($r);return response(array('range'=>$range,'report'=>$this->booking_report($range['from'],$range['to'])));}
	public function forms(){return response(array('report'=>$this->form_report()));}
	private function booking_report(string $from,string $to):array{global $wpdb;$b=$wpdb->prefix.'wpistic_bookings';$t=$wpdb->prefix.'wpistic_transactions';if(!table_exists($b))return array('available'=>false);$args=array($from.' 00:00:00',$to.' 23:59:59');$total=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$b} WHERE created_at BETWEEN %s AND %s",$args));$by_status=$wpdb->get_results($wpdb->prepare("SELECT status,COUNT(*) total FROM {$b} WHERE created_at BETWEEN %s AND %s GROUP BY status ORDER BY total DESC",$args),ARRAY_A);$by_type=$wpdb->get_results($wpdb->prepare("SELECT type,COUNT(*) total FROM {$b} WHERE created_at BETWEEN %s AND %s GROUP BY type ORDER BY total DESC",$args),ARRAY_A);$top_tours=$wpdb->get_results($wpdb->prepare("SELECT tour_id,COUNT(*) total FROM {$b} WHERE created_at BETWEEN %s AND %s AND tour_id IS NOT NULL AND tour_id>0 GROUP BY tour_id ORDER BY total DESC LIMIT 10",$args),ARRAY_A);foreach($top_tours as &$row){$row['tourId']=(int)$row['tour_id'];$row['tour']=(string)get_the_title((int)$row['tour_id']);$row['total']=(int)$row['total'];unset($row['tour_id']);}unset($row);$revenue=array();if(table_exists($t)){$rows=$wpdb->get_results($wpdb->prepare("SELECT currency,SUM(CASE WHEN status='paid' THEN CAST(amount AS DECIMAL(18,2)) ELSE 0 END) paid_amount,COUNT(CASE WHEN status='paid' THEN 1 END) paid_transactions FROM {$t} WHERE created_at BETWEEN %s AND %s GROUP BY currency",$args),ARRAY_A);foreach((array)$rows as $row)$revenue[]=array('currency'=>(string)$row['currency'],'paidAmount'=>(string)$row['paid_amount'],'paidTransactions'=>(int)$row['paid_transactions']);}return array('available'=>true,'total'=>$total,'byStatus'=>array_map(static fn($x)=>array('status'=>(string)$x['status'],'total'=>(int)$x['total']),(array)$by_status),'byType'=>array_map(static fn($x)=>array('type'=>(string)$x['type'],'total'=>(int)$x['total']),(array)$by_type),'topTours'=>$top_tours,'revenue'=>$revenue);}
	private function form_report():array{if(!class_exists('\\Wpistic_Formistic_Database'))return array('available'=>false);$days=\Wpistic_Formistic_Database::submissions_by_day(30);return array('available'=>true,'today'=>(int)\Wpistic_Formistic_Database::today_count(),'last7Days'=>(int)array_sum(array_slice($days,-7,7,true)),'last30Days'=>(int)array_sum($days),'statusCounts'=>\Wpistic_Formistic_Database::status_counts(),'topForms'=>\Wpistic_Formistic_Database::top_forms(10),'avgReplySeconds'=>(int)\Wpistic_Formistic_Database::avg_reply_time_seconds(),'medianReplySeconds'=>(int)\Wpistic_Formistic_Database::p50_reply_time_seconds(),'repliedRate'=>(float)\Wpistic_Formistic_Database::replied_rate(),'overdue24h'=>(int)\Wpistic_Formistic_Database::overdue_submissions_count(24),'conversionByForm'=>\Wpistic_Formistic_Database::conversion_by_form(30));}
	private function range(WP_REST_Request $r):array{$from=$this->date((string)$r->get_param('from'))?:gmdate('Y-m-d',strtotime('-29 days'));$to=$this->date((string)$r->get_param('to'))?:gmdate('Y-m-d');return array('from'=>$from,'to'=>$to);}private function date(string $v):string{$v=trim($v);return preg_match('/^\d{4}-\d{2}-\d{2}$/',$v)?$v:'';}
}
