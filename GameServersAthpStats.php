<?php
/**
 * Created by PhpStorm.
 * User: Artem
 * Date: 24.11.2018
 * Time: 18:29
 */

ini_set( 'date.timezone', 'UTC' );

function GameServersAthpStats_config() {
	$config = [
		"name"        => "ATHP статистика через ftp (game-servers)",
		"description" => "",
		"version"     => "1",
		"author"      => "service-voice",
		"fields"      => []
	];

	return $config;
}

function GameServersAthpStats_output( $var ) {
	$Month_r   = array(
		"январь",
		"февраль",
		"март",
		"апрель",
		"май",
		"июнь",
		"июль",
		"август",
		"сентябрь",
		"октябрь",
		"ноябрь",
		"декабрь"
	);
	$cache_dir = ROOTDIR . '/modules/addons/GameServersAthpStats/cache';

	$year  = array_key_exists( 'year', $_REQUEST ) ? $_REQUEST['year'] : null;
	$month = array_key_exists( 'month', $_REQUEST ) ? $_REQUEST['month'] : null;

	if ( empty( $year ) ) {
		array_walk( scandir( $cache_dir ), function ( $value, $key ) use ( $cache_dir ) {
			if ( is_dir( realpath( $cache_dir . DIRECTORY_SEPARATOR . $value ) ) && is_numeric( $value ) ) {
				echo '<a href="' . $_SERVER['REQUEST_URI'] . '&year=' . $value . '">' . $value . '</a>' . '<br/>';
			}
		} );

		return;
	}

	if ( empty( $month ) ) {
		$files = array_sort( scandir( $cache_dir . DIRECTORY_SEPARATOR . $_REQUEST['year'] ), function ( $value ) {
			return $value;
		} );

		array_walk( $files, function ( $value, $key ) use ( $cache_dir, $year, $Month_r ) {
			if ( is_dir( realpath( $cache_dir . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $value ) )
			     && is_numeric( $value )
			) {
				echo '<a href="' . $_SERVER['REQUEST_URI'] . '&month=' . $value . '">' . $Month_r[ $value - 1 ] . '</a>' . '<br/>';
			}
		} );

		return;
	}
	$stats_array  = [];
	$day_in_month = cal_days_in_month( CAL_GREGORIAN, $_REQUEST['month'], $_REQUEST['year'] );

	for ( $i = 1; $i <= $day_in_month; $i ++ ) {
		foreach ( scandir( $cache_dir . DIRECTORY_SEPARATOR . 'instance_max_slots_by_virtual_server' . DIRECTORY_SEPARATOR . $_REQUEST['year'] . DIRECTORY_SEPARATOR . $_REQUEST['month'] . DIRECTORY_SEPARATOR . $i ) as $key => $value ) {
			$path = realpath( $cache_dir . DIRECTORY_SEPARATOR . 'instance_max_slots_by_virtual_server' . DIRECTORY_SEPARATOR . $_REQUEST['year'] . DIRECTORY_SEPARATOR . $_REQUEST['month'] . DIRECTORY_SEPARATOR . $i . DIRECTORY_SEPARATOR . $value );

			if ( is_file( $path )
			     && $value != '.'
			     && $value != '..'
			) {
				$ip = substr( $value, 0, - 5 );
				array_walk( json_decode( file_get_contents( $path ), true ), function ( $val, $uid ) use ( &$stats_array, $ip ) {
					if ( array_key_exists( $uid, $stats_array[ $ip ] ) ) {
						$stats_array[ $ip ][ $uid ] += $val;
					} else {
						$stats_array[ $ip ][ $uid ] = $val;
					}
				} );
			}
		}
	}

	array_walk( $stats_array, function ( &$servers, $ip ) use ( $day_in_month ) {
		array_walk( $servers, function ( &$slots, $uid ) use ( $day_in_month ) {
			$slots = ceil( $slots / $day_in_month );
		} );
	} );

	$all_slots_total = 0;

	array_walk( $stats_array, function ( $stats, $ip ) use ( &$all_slots_total ) {
		echo $ip;
		$all_slots_total += $slots_total = array_sum( $stats );
		echo ' слотов: ' . $slots_total . '<br/>';
	} );

	echo 'Всего слотов: ' . $all_slots_total;
}

