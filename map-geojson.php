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

add_action( 'init', 'sabra_maps_register_tables', 1 );
add_action( 'switch_blog', 'sabra_maps_register_tables' );
 
function sabra_maps_register_tables() {
    global $wpdb;
    $wpdb->sabra_maps = "{$wpdb->prefix}sabra_maps";
	$wpdb->sabra_maps_geo_json = "{$wpdb->prefix}sabra_maps_geo_json";
}

function sabra_maps_create_tables() {
	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	global $wpdb;
	global $charset_collate;
	// Call this manually as we may have missed the init hook
	sabra_maps_register_tables();

	$sql_create_table = "CREATE TABLE {$wpdb->sabra_maps_geo_json} (
          id bigint(20) unsigned NOT NULL auto_increment,
          postal_code tinytext NULL,
          date_created datetime NOT NULL default '0000-00-00 00:00:00',
          date_active datetime NOT NULL default '0000-00-00 00:00:00',
          date_replaced datetime NULL,
          type text NULL,
          geometry longtext NULL,
          PRIMARY KEY  (id)
     ) $charset_collate; ";
	dbDelta( $sql_create_table );
	
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

function sabra_maps_import_geo_json() {
	
//	geometry database -- import from json

	$file = file_get_contents( plugin_dir_path( __FILE__ ) . 'map.geojson' );
	// Generate md5 and compare against stored value
	$md5 = md5( $file );
	$option = 'sabra_maps_geo_json_md5';
	$compare = get_option( $option );

	if ($md5 == $compare) {
		return false;
	}
	$file = utf8_encode( $file );
	$file = json_decode( $file , true);
	if ( !isset( $file['features'] ) )
		return false;
		
	foreach ( $file['features'] as $feature ) {
		$data[] = array(
			'postal_code'	=>	$feature['properties']['postal'],
			'geometry'		=>	serialize( $feature['geometry']['coordinates'] ),
			'type'			=>	$feature['geometry']['type']
		);
	}
	//populate the database

	$insert = sabra_maps_geo_json_insert( $data );
	update_option( $option, $md5, '', 'yes');
}

/* create tables on plugin activation and import geoJSON data */
register_activation_hook( __FILE__, 'sabra_maps_create_tables' );
register_activation_hook( __FILE__, 'sabra_maps_import_geo_json' );


/* list all table columns here for whitelisting */
function sabra_maps_get_table_columns() {
	return array(
		'id'			=>	'%d',
        'post_id'		=>	'%d',
        'item_id'		=>	'%d',
        'date_created'	=>	'%s',
		'date_active'	=>	'%s',
        'date_replaced'	=>	'%s',
        'value1'		=>	'%s',
        'value2'		=>	'%s',
    );
}

function sabra_maps_insert( $data = array(), $timestamp = false, $country_id = false ){
    global $wpdb;        

	$current_timestamp = current_time('timestamp');

	//Prepare active timestamp
	if ( !$timestamp ) {
		$active_timestamp = $current_timestamp;
	}
	else {
		$active_timestamp = strtotime($timestamp);

	}
	//if ( !$country_id )
	
	//Query
	$current_queries = sabra_maps_query( $active_timestamp, $country_id, $data, 'ARRAY_A');

	//Convert timestamps to Mysql formats
	$current_timestamp = date_i18n( 'Y-m-d H:i:s', $current_timestamp, true );
	$active_timestamp = date_i18n( 'Y-m-d H:i:s', $active_timestamp, true );

	// convert array	
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

	$column_formats = sabra_maps_get_table_columns();
	$column_names_array = array();
	foreach( $column_formats as $name => $format ) {
		if ( $name != 'id' ) $column_names_array[] = $name;
	}
	$column_names = implode( ', ', $column_names_array );
	
	foreach( $data as $entry ) {

		if ( $entry['post_id'] == '' || $entry['item_id'] == '' || ( '' == $entry['value1'] || '' == $entry['value2'] ) ) continue;
	
		if ( isset( $data_array[ $entry['post_id'] ][ $entry['item_id'] ] ) ) {
			// Match Found - value1/value2 comparison
			if ( $data_array[ $entry['post_id'] ][ $entry['item_id'] ]['value1'] != $entry['value1'] || $data_array[ $entry['post_id'] ][ $entry['item_id'] ]['value2'] != $entry['value2'] ) {
				// Values are different, prepare update.
				$sql_update = $wpdb->prepare("
						UPDATE $wpdb->sabra_maps 
						SET date_replaced = %s 
						WHERE id = %d",
						$current_timestamp, $data_array[ $entry['post_id'] ][ $entry['item_id'] ]['id'] );
				if( !$wpdb->query( $sql_update ) )
					return false;
			}
			else {
			//Values are the same, move to next entry in loop
				continue;
			}
			
		} else {
			// New Entry
		}
		
		$sql_insert = $wpdb->prepare("
					INSERT INTO $wpdb->sabra_maps ($column_names)
					VALUES (%d, %d, %s, %s, NULL, %s, %s)",
					$entry['post_id'], $entry['item_id'], $current_timestamp, $active_timestamp, $entry['value1'], $entry['value2'] ); 

		if( !$wpdb->query( $sql_insert ) )
			return false;
	
	}
	return null;
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
	   'number'		=>	-1,
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
    wp_cache_add( $cache_key, $maps, 24*60*60 );
    $maps = apply_filters('sabra_maps_query', $maps, $query);
    return $maps;
}

function sabra_maps_delete( $data ){
    global $wpdb;        
    //id must be positive integer
	if ( is_array( $data ) ) {
		if ( isset( $data['post_id'] ) && isset( $data['item_id'] ) && isset( $data['date_active'] ) ) {
				$where = $wpdb->prepare('WHERE post_id = %d AND item_id = %d AND date_replaced = %s', $data['post_id'], $data['item_id'], $data['date_active'] );
				$update_timestamp = $data['date_replaced'];
		}
	}
	else {
		$id = absint($data);     
		if( empty($id) )
			 return false;
		$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->sabra_maps} WHERE id = %d", $id);
		$row = $wpdb->get_results( $sql , 'ARRAY_A');
		$where = $wpdb->prepare('WHERE post_id = %d AND item_id = %d AND date_replaced = %s', $row[0]['post_id'], $row[0]['item_id'], $row[0]['date_active']);
		$update_timestamp = $row[0]['date_replaced'];
	}
		
	// Query all rows
	$sql = "SELECT * FROM {$wpdb->sabra_maps} $where";
	$queries = $wpdb->get_results( $sql , 'ARRAY_A');

	// Update previous entry with new date_replaced
	if ( NULL == $update_timestamp ) {
		$sql_update = $wpdb->prepare("
					UPDATE $wpdb->sabra_maps 
					SET date_replaced = DEFAULT 
					WHERE id = %d",
					$queries[0]['id'] );
	}
	else {
		$sql_update = $wpdb->prepare("
					UPDATE $wpdb->sabra_maps 
					SET date_replaced = %s
					WHERE id = %d",
					$update_timestamp, $queries[0]['id'] );
	}
					if( !$wpdb->query( $sql_update ) ) {
	}
	// Delete row
	do_action('sabra_maps_delete',$id);
    $sql = $wpdb->prepare( "DELETE from {$wpdb->sabra_maps} WHERE id = %d", $id );
 
    if( !$wpdb->query( $sql ) )
         return false;
 
    do_action('sabra_maps_deleted',$id);
 
    return true;
}


function sabra_maps_get_geo_json_table_columns() {
	return array(
		'id'			=>	'%d',
        'postal_code'	=>	'%s',
        'date_created'	=>	'%s',
		'date_active'	=>	'%s',
        'date_replaced'	=>	'%s',
        'type'			=>	'%s',
        'geometry'		=>	'%s',
    );
}

function sabra_maps_geo_json_insert( $data = array(), $timestamp = false, $country_id = false ){
    global $wpdb;        
	
	$current_timestamp = current_time('timestamp');

	//Prepare active timestamp
	if ( !$timestamp ) {
		$active_timestamp = $current_timestamp;
	}
	else {
		$active_timestamp = strtotime($timestamp);
	}
	//if ( !$country_id )
	
	//Query
	$current_queries = sabra_maps_geo_json_query( $active_timestamp, $country_id, $data, 'ARRAY_A');

	//Convert timestamps to Mysql formats
	$current_timestamp = date_i18n( 'Y-m-d H:i:s', $current_timestamp, true );
	$active_timestamp = date_i18n( 'Y-m-d H:i:s', $active_timestamp, true );

	// convert array	
	$data_array = array();
	
	foreach( $current_queries as $query ) {
	
		if ( !isset( $data_array[ $query['postal_code'] ] ) ) {
				$data_array[ $query['postal_code'] ] = $query;
		} else {
			$data_array[$query['postal_code'] ]= $query;
		
		}
	
	}

	$column_formats = sabra_maps_get_geo_json_table_columns();
	$column_names_array = array();
	foreach( $column_formats as $name => $format ) {
		if ( $name != 'id' ) $column_names_array[] = $name;
	}
	$column_names = implode( ', ', $column_names_array );
	
	foreach( $data as $entry ) {

		if ( $entry['postal_code'] == '' || $entry['type'] == '' || ( '' == $entry['geometry'] ) ) continue;
	
		if ( isset( $data_array[ $entry['postal_code'] ] ) ) {
			// Match Found - Some check for value1/value2 comparison to see if anything changed might be needed here.
			if ( $data_array[ $entry['postal_code'] ]['type'] != $entry['type'] || $data_array[ $entry['postal_code'] ]['geometry'] != $entry['geometry'] ) {
			// Values are different, prepare update.

				$sql_update = $wpdb->prepare("
						UPDATE $wpdb->sabra_maps_geo_json
						SET date_replaced = %s 
						WHERE id = %d",
						$current_timestamp, $data_array[ $entry['postal_code'] ]['id'] );
				if( !$wpdb->query( $sql_update ) )
					return false;
			}
			else {
				continue;
			}
			
		} else {
			// New Entry
		}
		
		$sql_insert = $wpdb->prepare("
					INSERT INTO $wpdb->sabra_maps_geo_json ($column_names)
					VALUES (%s, %s, %s, NULL, %s, %s)",
					$entry['postal_code'], $current_timestamp, $active_timestamp, $entry['type'], $entry['geometry'] ); 

		if( !$wpdb->query( $sql_insert ) )
			return false;
	
	}
	return null;
}

function sabra_maps_geo_json_query( $timestamp = false, $postal_code = false, $query = array(), $output_type = 'OBJECT' ){
    global $wpdb;
	if ( !$timestamp ) 
		$timestamp = current_time('timestamp');


	/* Parse defaults */
     $defaults = array(
       'fields'		=>	array(),
	   'orderby'	=>	'id',
	   'order'		=>	'desc',
	   'number'		=>	-1,
	   'offset'		=>	0,
	   'type'		=>	'point_in_time'
     );
 
    $query = wp_parse_args($query, $defaults);
 
    /* Form a cache key from the query */
    $cache_key = 'sabra_maps_geo_json:'.md5( serialize($query));
    $cache = wp_cache_get( $cache_key );
 
    if ( false !== $cache ) {
            $cache = apply_filters('sabra_maps_geo_json_query', $cache, $query);
            return $cache;
    }
 
     extract($query);
    /* SQL Select */
    //Whitelist of allowed fields
    $allowed_fields = sabra_maps_get_geo_json_table_columns();

    if( is_array($fields) ){
        //Convert fields to lowercase (as our column names are all lower case)
        $fields = array_map('strtolower',$fields);
 
            //Sanitize by white listing
			$fields = array_flip($fields);
			$fields = array_intersect_key($fields, $allowed_fields);
			$fields = array_flip($fields);
    } else{
        $fields = strtolower($fields);
	}
	
    //Return only selected fields. Empty is interpreted as all
    if( empty($fields) ){
        $select_sql = "SELECT * FROM {$wpdb->sabra_maps_geo_json}";
    }elseif( 'count' == $fields ) {
        $select_sql = "SELECT COUNT(*) FROM {$wpdb->sabra_maps_geo_json}";
    }else{
        $select_sql = "SELECT ".implode(',',$fields)." FROM {$wpdb->sabra_maps_geo_json}";
    }
 
     /*SQL Join */
     //We don't need this, but we'll allow it be filtered
     $join_sql='';
 
    /* SQL Where */
    //Initialise WHERE
    $where_sql = 'WHERE 1=1';
 
    if( !empty($postal_code) ) {
		if (is_array($postal_code) ) {
			$postal_code = "'". implode("','", $postal_code ) . "'";
			$where_sql .= " AND postal_code IN ( $postal_code )";
		} elseif (is_string($postal_code) ) {
			$postal_code = explode(',', $postal_code );
			$postal_code = "'". implode("','", $postal_code ) . "'";
			$where_sql .= " AND postal_code IN ( $postal_code )";
		}
	}
	$where_sql .=  $wpdb->prepare(' AND date_active <= %s', date_i18n( 'Y-m-d H:i:s', $timestamp, true));
	$where_sql .=  $wpdb->prepare(' AND ((date_replaced IS NULL) OR (date_replaced > %s))', date_i18n( 'Y-m-d H:i:s', $timestamp, true));
	
    /* SQL Order */
    //Whitelist order
    $order = strtoupper($order);
    $order = ( 'ASC' == $order ? 'ASC' : 'DESC' );
 
    switch( $orderby ){
       case 'id':
            $order_sql = "ORDER BY id $order";
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
    $clauses = apply_filters( 'sabra_maps_geo_json_clauses', compact( $pieces ), $query );
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
    wp_cache_add( $cache_key, $maps, 24*60*60 );
    $maps = apply_filters('sabra_maps_geo_json_query', $maps, $query);
    return $maps;
}

class sabra_geoJSON_Features
{

	public $type = 'FeatureCollection';
	public $timestamp = 'something';
	public $features = array();
	
	function __construct( $countries, $data ) {
		
		$countries = "AZ,AO,ZM";
		$data = array(
			'fields'=> array( 'type', 'geometry', 'postal_code')
		);

		$current_entries = sabra_maps_geo_json_query( current_time('timestamp'), $countries, $data );
		
		foreach( $current_entries as $entry ) {
			$entry->coordinates = unserialize( $entry->geometry );
			unset( $entry->geometry );
			$this->features[] = new sabra_geoJSON_feature( $entry );
		
		}
		
		// CACHE THIS OBJECT
		
	}
	
	function output_geoJSON() {
	
		return json_encode( $this );
	
	}

}

class sabra_geoJSON_feature {
	
	public $type = "Feature";
	
	public $geometry;
	
	function __construct( $geometry_obj ) {
	
		$this->geometry = $geometry_obj;
	
	}
	
}