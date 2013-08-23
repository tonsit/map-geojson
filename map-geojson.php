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
		'id'=>'%d',
        'post_id'=>'%d',
        'item_id'=>'%d',
        'date_created'=>'%s',
		'date_active'=>'%s',
        'date_replaced'=>'%s',
        'value1'=>'%s',
        'value2'=>'%s',
    );
}

function sabra_maps_insert( $data = array(), $timestamp = false, $country_id = false ){
    global $wpdb;        
	$current_timestamp = current_time('timestamp');
	$current_timestamp = date_i18n( 'Y-m-d H:i:s', $current_timestamp, true );

	var_dump( $data );
	
    //Set default values
 
    //Convert activity date from local timestamp to GMT mysql format
	if ( !$timestamp ) {
		$active_timestamp = $current_timestamp;
	}
	else {
		$active_timestamp = strtotime($timestamp);
		$active_timestamp = date_i18n( 'Y-m-d H:i:s', $active_timestamp, true );
	}
	//if ( !$country_id )
	
	//Query
	$current_queries = sabra_maps_query( current_time('timestamp'), $country_id, $data, 'ARRAY_A');
	
	echo "<hr>";
	var_dump($current_queries);
	echo "<hr>";
	
	// convert array	newarray [post_id][item_id]
	$data_array = array();
	
	foreach( $current_queries as $query ) {
	
		if ( isset( $data_array[ $query['post_id'] ] ) ) {
		
			if ( !isset( $data_array[ $query['post_id'] ][ $query['item_id'] ] ) ) {
				$data_array[ $query['post_id'] ][ $query['item_id'] ] = $query;
			} else {
				//Duplicate Entry
			}
		
		} else {
		
			$data_array[$query['post_id']][$query['item_id']] = $query;
		
		}
	
	}
	
	var_dump( $data_array );
	
	$inserts = array();
	$updates = array();
	
	$column_formats = sabra_maps_get_table_columns();
	$column_names_array = array();
	foreach( $column_formats as $name => $format ) {
		if ( $name != 'id' ) $column_names_array[] = $name;
	}
	$column_names = implode( ', ', $column_names_array );
	
	foreach( $data as $entry ) {

		if ( $entry['post_id'] == '' || $entry['item_id'] == '' || ( '' == $entry['value1'] || '' == $entry['value2'] ) ) continue;
	
		//Initialise column format array
		$column_formats = sabra_maps_get_table_columns();
	
		//Force fields to lower case
		$entry = array_change_key_case ( $entry );
	 
		//White list columns
		$entry = array_intersect_key($entry, $column_formats);
	 
		//Reorder $column_formats to match the order of columns given in $data
		$entry_keys = array_keys($entry);
		$column_formats = array_merge(array_flip($entry_keys), $column_formats);
	
		if ( isset( $data_array[ $entry['post_id'] ][ $entry['item_id'] ] ) ) {
			// Match Found
			$updates[] = $wpdb->prepare("
					UPDATE $wpdb->sabra_maps 
					SET date_replaced = %s 
					WHERE id = %d",
					$current_timestamp, $data_array[ $entry['post_id'] ][ $entry['item_id'] ]['id'] );
						
			
		} else {
		
			// New Entry!
			
		
		}
		
		$inserts[] = $wpdb->prepare("
					INSERT INTO $wpdb->sabra_maps ($column_names)
					VALUES (%d, %d, %s, %s, NULL, %s, %s)",
					$entry['post_id'], $entry['item_id'], $current_timestamp, $active_timestamp, $entry['value1'], $entry['value2'] ); 
	
	}
	
	//Declare insert/updates
	
	//Compare Query to New Values

	//Loop through new values

	//Check value1 - value2 against existing

	//if different populate $inserts[] and $updates[]

	
	
    $data = wp_parse_args($data, array(
		'date_created'=> $current_timestamp,
		'date_active'=> $active_timestamp
	 ));    

	$sql = '';
	$insertsql = implode( ", ", $inserts);
	$sql .= ', ';
	$updatesql = implode( ', ', $updates);
	
	echo "<hr>";
	
	$wpdb->show_errors();
	
	var_dump( $sql );
	
	if( !$wpdb->query( $insertsql ) )
         return false;
	if( !$wpdb->query( $updatesql ) )
         return false;
    
    //$wpdb->insert($wpdb->sabra_maps, $data, $column_formats);
 
	
 
    //return $wpdb->insert_id;
	return null;
}

function sabra_maps_update( $post_id, $data=array() ){
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

function sabra_maps_query( $timestamp = false, $country_id = false, $query = array(), $output_type = 'OBJECT_K' ){
    global $wpdb;
	if ( !$timestamp ) 
		$timestamp = current_time('timestamp');


	/* Parse defaults */
     $defaults = array(
       'fields'		=>	array(),
	   'orderby'	=>	'post_id',
	   'order'		=>	'desc',
	   'number'		=>	3,
	   'offset'		=>	0,
	   'type'		=>	'point_in_time'
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
        $select_sql = "SELECT * FROM {$wpdb->sabra_maps}";
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
 
    if( !empty($country_id) )
       $where_sql .=  $wpdb->prepare(' AND post_id=%d', $country_id);

 
   $where_sql .=  $wpdb->prepare(' AND date_active <= %s', date_i18n( 'Y-m-d H:i:s', $timestamp, true));
   $where_sql .=  $wpdb->prepare(' AND ((date_replaced IS NULL) OR (date_replaced > %s))', date_i18n( 'Y-m-d H:i:s', $timestamp, true));
 
    /* SQL Order */
    //Whitelist order
    $order = strtoupper($order);
    $order = ( 'ASC' == $order ? 'ASC' : 'DESC' );
 
    switch( $orderby ){
       case 'post_id':
            $order_sql = "ORDER BY post_id $order";
       break;
       case 'datetime':
             $order_sql = "ORDER BY date_active $order";
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
    $maps = $wpdb->get_results($sql, $output_type);
 
    /* Add to cache and filter */
    wp_cache_add( $cache_key, $logs, 24*60*60 );
    $maps = apply_filters('sabra_maps_query', $maps, $query);
    return $maps;
}

 function sabra_maps_delete( $post_id ){
    global $wpdb;        
 
    //post_id must be positive integer
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