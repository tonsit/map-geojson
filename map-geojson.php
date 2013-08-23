<?php
/*
Plugin Name: Map geoJSON
Plugin URI: http://sabradesign.me/map-geojson/
Description: Displays geoJSON format on maps.
Version: 0.1
Author: SabraDesign
Author URI: http://sabradesign.me/
License: Restricted
*/


add_action( 'init', 'sabra_maps_register_table', 1 );
add_action( 'switch_blog', 'sabra_maps_register_table' );
 
function sabra_maps_register_table() {
    global $wpdb;
    $wpdb->sabra_maps = "{$wpdb->prefix}sabra_maps";
}

function sabra_maps_create_tables() {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $charset_collate;
	// Call this manually as we may have missed the init hook
	sabra_maps_register_table();
	$sql_create_table = "CREATE TABLE {$wpdb->sabra_maps} (
          id bigint(20) unsigned NOT NULL auto_increment,
          post_id bigint(20) unsigned NOT NULL default '0',
          item_id bigint(20) unsigned NOT NULL default '0',
          date_created datetime NOT NULL default '0000-00-00 00:00:00',
          date_active datetime NOT NULL default '0000-00-00 00:00:00',
          date_replaced datetime NULL,
          value1 varchar(40) NOT NULL default 'updated',
          value2 varchar(40) NULL,
          PRIMARY KEY  (id),
          KEY post_id (post_id)
     ) $charset_collate; ";
	dbDelta( $sql_create_table );
}

/* create tables on plugin activation */
register_activation_hook( __FILE__, 'sabra_maps_create_tables' );

/* list all table columns here for whitelisting */
function sabra_maps_get_table_columns() {
	return array(
       'id'=> '%d',
        'post_id'=> '%d',
        'item_id'=>'%d',
        'date_created'=>'%s',
		'date_active'=>'%s',
        'date_replaced'=>'%s',
        'value1'=>'%s',
        'value2'=>'%s',
    );
}

function sabra_maps_insert( $data=array() ){
    global $wpdb;        
 
    //Set default values
    $data = wp_parse_args($data, array(
                 'date'=> current_time('timestamp'),
    ));    
 
    //Check date validity
    if( !is_float($data['date']) || $data['date'] <= 0 )
        return 0;
 
    //Convert activity date from local timestamp to GMT mysql format
    $data['activity_date'] = date_i18n( 'Y-m-d H:i:s', $data['date'], true );
 
    //Initialise column format array
    $column_formats = sabra_maps_get_table_columns();
 
    //Force fields to lower case
    $data = array_change_key_case ( $data );
 
    //White list columns
    $data = array_intersect_key($data, $column_formats);
 
    //Reorder $column_formats to match the order of columns given in $data
    $data_keys = array_keys($data);
    $column_formats = array_merge(array_flip($data_keys), $column_formats);
 
    $wpdb->insert($wpdb->sabra_maps, $data, $column_formats);
 
    return $wpdb->insert_id;
}

function sabra_maps_update( $map_id, $data=array() ){
    global $wpdb;        
 
    //Log ID must be positive integer
    $post_id = absint($post_id);     
    if( empty($post_id) )
         return false;
 
    //Convert activity date from local timestamp to GMT mysql format
    if( isset($data['activity_date']) )
         $data['activity_date'] = date_i18n( 'Y-m-d H:i:s', $data['date'], true );
    //Initialise column format array
    $column_formats = sabra_maps_get_table_columns();
 
    //Force fields to lower case
    $data = array_change_key_case ( $data );
 
    //White list columns
    $data = array_intersect_key($data, $column_formats);
 
    //Reorder $column_formats to match the order of columns given in $data
    $data_keys = array_keys($data);
    $column_formats = array_merge(array_flip($data_keys), $column_formats);
 
    if ( false === $wpdb->update($wpdb->sabra_maps, $data, array('post_id'=>$post_id), $column_formats) ) {
         return false;
    }
 
    return true;
}

function sabra_maps_query( $query=array() ){
 
     global $wpdb;
     /* Parse defaults */
     $defaults = array(
       'fields'=>array(),'orderby'=>'datetime','order'=>'desc', 'user_id'=>false,
       'since'=>false,'until'=>false,'number'=>10,'offset'=>0
     );
 
    $query = wp_parse_args($query, $defaults);
 
    /* Form a cache key from the query */
    $cache_key = 'sabra_maps:'.md5( serialize($query));
    $cache = wp_cache_get( $cache_key );
 
    if ( false !== $cache ) {
            $cache = apply_filters('sabra_maps_query', $cache, $query);
            return $cache;
    }
 
     extract($query);
 
    /* SQL Select */
    //Whitelist of allowed fields
    $allowed_fields = sabra_maps_get_table_columns();
     
    if( is_array($fields) ){
        //Convert fields to lowercase (as our column names are all lower case)
        $fields = array_map('strtolower',$fields);
 
            //Sanitize by white listing
           $fields = array_intersect($fields, $allowed_fields);
    }else{
        $fields = strtolower($fields);
    }
 
    //Return only selected fields. Empty is interpreted as all
    if( empty($fields) ){
        $select_sql = "SELECT* FROM {$wpdb->sabra_maps}";
    }elseif( 'count' == $fields ) {
        $select_sql = "SELECT COUNT(*) FROM {$wpdb->sabra_maps}";
    }else{
        $select_sql = "SELECT ".implode(',',$fields)." FROM {$wpdb->sabra_maps}";
    }
 
     /*SQL Join */
     //We don't need this, but we'll allow it be filtered
     $join_sql='';
 
    /* SQL Where */
    //Initialise WHERE
    $where_sql = 'WHERE 1=1';
 
    if( !empty($post_id) )
       $where_sql .=  $wpdb->prepare(' AND post_id=%d', $post_id);
 
    if( !empty($user_id) ){
 
       //Force $user_id to be an array
       if( !is_array( $user_id) )
           $user_id = array($user_id);
 
       $user_id = array_map('absint',$user_id); //Cast as positive integers
       $user_id__in = implode(',',$user_id);
       $where_sql .=  " AND user_id IN($user_id__in)";
    }
 
    $since = absint($since);
    $until = absint($until);
 
    if( !empty($since) )
       $where_sql .=  $wpdb->prepare(' AND activity_date >= %s', date_i18n( 'Y-m-d H:i:s', $since, true));
 
    if( !empty($until) )
       $where_sql .=  $wpdb->prepare(' AND activity_date <= %s', date_i18n( 'Y-m-d H:i:s', $until, true));
 
    /* SQL Order */
    //Whitelist order
    $order = strtoupper($order);
    $order = ( 'ASC' == $order ? 'ASC' : 'DESC' );
 
    switch( $orderby ){
       case 'post_id':
            $order_sql = "ORDER BY post_id $order";
       break;
       case 'user_id':
            $order_sql = "ORDER BY user_id $order";
       break;
       case 'datetime':
             $order_sql = "ORDER BY activity_date $order";
       default:
       break;
    }
 
    /* SQL Limit */
    $offset = absint($offset); //Positive integer
    if( $number == -1 ){
         $limit_sql = "";
    }else{
         $number = absint($number); //Positive integer
         $limit_sql = "LIMIT $offset, $number";
    }
 
    /* Filter SQL */
    $pieces = array( 'select_sql', 'join_sql', 'where_sql', 'order_sql', 'limit_sql' );
    $clauses = apply_filters( 'sabra_maps_clauses', compact( $pieces ), $query );
    foreach ( $pieces as $piece )
          $$piece = isset( $clauses[ $piece ] ) ? $clauses[ $piece ] : '';
 
    /* Form SQL statement */
    $sql = "$select_sql $where_sql $order_sql $limit_sql";
 
    if( 'count' == $fields ){
        return $wpdb->get_var($sql);
    }
 
    /* Perform query */
    $logs = $wpdb->get_results($sql);
 
    /* Add to cache and filter */
    wp_cache_add( $cache_key, $logs, 24*60*60 );
    $logs = apply_filters('sabra_maps_query', $logs, $query);
    return $logs;
 }
 function sabra_maps_delete( $post_id ){
    global $wpdb;        
 
    //Log ID must be positive integer
    $post_id = absint($post_id);     
    if( empty($post_id) )
         return false;
 
    do_action('sabra_maps_delete',$post_id);
    $sql = $wpdb->prepare("DELETE from {$wpdb->wptuts_activity_log} WHERE post_id = %d", $post_id);
 
    if( !$wpdb->query( $sql ) )
         return false;
 
    do_action('sabra_maps_deleted',$post_id);
 
    return true;
}
