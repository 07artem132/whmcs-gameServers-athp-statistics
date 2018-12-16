<?php
/**
 * Created by PhpStorm.
 * User: Artem
 * Date: 08.12.2018
 * Time: 12:29
 */

ini_set( "memory_limit", "-1" );
set_time_limit( 0 );

set_error_handler( function ( $errno, $errstr, $errfile, $errline, array $errcontext ) {
	// error was suppressed with the @-operator
	if ( 0 === error_reporting() ) {
		return false;
	}

	throw new ErrorException( $errstr, 0, $errno, $errfile, $errline );
} );


$ftp_server       = "ftp1.igrofiles.ru";
$ftp_login        = "f61109";
$ftp_password     = "SaOfTxuSgQ";
$ftp_file_map     = [];
$local_cache_path = __DIR__ . '/cache';

if ( ! file_exists( __DIR__ . '/cache' ) ) {
	mkdir( __DIR__ . '/cache', 0777 );
}

$conn_id = ftp_connect( $ftp_server ) or die( "Не удалось установить соединение с $ftp_server" );
ftp_login( $conn_id, $ftp_login, $ftp_password );

$list_files_recursive = ftp_list_files_recursive( $conn_id, '' );
foreach ( $list_files_recursive as &$file ) {
	$key = substr( substr( $file, 1 ), 0, - strlen( basename( $file ) ) );
	ftp_file_map_set_file( $ftp_file_map, $key, basename( $file ) );
}
unset( $file, $list_files_recursive );

foreach ( $ftp_file_map['ts3nlogs']['61109'] as $year => $months ) {

	if ( ! file_exists( $local_cache_path . '/' . $year ) ) {
		mkdir( $local_cache_path . '/' . $year, 0777 );
	}

	foreach ( $months as $month => $days ) {
		if ( ! file_exists( $local_cache_path . '/' . $year . '/' . $month ) ) {
			mkdir( $local_cache_path . '/' . $year . '/' . $month, 0777 );
		}

		foreach ( $days as $day => $files ) {
			if ( ! is_array( $files ) && strcmp( $files, 'tsinvoice.csv' ) === 0 ) {
				ftp_get( $conn_id, $local_cache_path . '/' . $year . '/' . $month . '/tsinvoice.csv', '/ts3nlogs/61109/' . $year . '/' . $month . '/tsinvoice.csv', FTP_BINARY );
				continue;
			}

			if ( ! file_exists( $local_cache_path . '/' . $year . '/' . $month . '/' . $day ) ) {
				mkdir( $local_cache_path . '/' . $year . '/' . $month . '/' . $day, 0777 );
			}

			foreach ( $files as $file ) {
				switch ( $file ) {
					case  'details.csv.gz':
						$instance_max_slots_by_virtual_server  = [];
						$instance_max_online_by_virtual_server = [];
						$virtual_server_online_stats_day       = [];
						$virtual_server_slots_stats_day        = [];

						if ( ! file_exists( $local_cache_path . '/' . $year . '/' . $month . '/' . $day . '/details.csv.gz' ) ) {
							ftp_get( $conn_id, $local_cache_path . '/' . $year . '/' . $month . '/' . $day . '/details.csv.gz', '/ts3nlogs/61109/' . $year . '/' . $month . '/' . $day . '/details.csv.gz', FTP_BINARY );
						}

						if ( file_exists( $local_cache_path . '/instance_max_slots_by_virtual_server/' . $year . '/' . $month . '/' . $day ) &&
						     file_exists( $local_cache_path . '/instance_max_online_by_virtual_server/' . $year . '/' . $month . '/' . $day ) &&
						     file_exists( $local_cache_path . '/virtual_server_online_history/' . $year . '/' . $month . '/' . $day ) &&
						     file_exists( $local_cache_path . '/virtual_server_slot_history/' . $year . '/' . $month . '/' . $day )
						) {
							echo 'skip ' . $year . '/' . $month . '/' . $day . PHP_EOL;
							continue;
						}

						$fp = gzopen( $local_cache_path . '/' . $year . '/' . $month . '/' . $day . '/details.csv.gz', "r" );
						while ( ! gzeof( $fp ) ) {
							$buffer = gzgets( $fp, 4096 );
							$buffer = str_getcsv( $buffer );
							$ip     = substr( $buffer[1], 0, - 6 );
							/*
							 * 0 - timestamp
							 * 1 - ip:port
							 * 2 - uid
							 * 3 - slots
							 * 4 - online
							*/
							if ( filter_var( $ip, FILTER_VALIDATE_IP ) === false ) {
								continue;
							}
							if ( array_key_exists( 3, $buffer ) && ! array_key_exists( $ip, $instance_max_slots_by_virtual_server )
							     || array_key_exists( 3, $buffer ) && ! array_key_exists( $buffer[2], $instance_max_slots_by_virtual_server[ $ip ] )
							     || array_key_exists( 3, $buffer ) && intval( $buffer[3] ) > $instance_max_slots_by_virtual_server[ $ip ][ $buffer[2] ] ) {
								$instance_max_slots_by_virtual_server[ $ip ][ $buffer[2] ] = intval( $buffer[3] );
							}

							if ( array_key_exists( 4, $buffer ) && ! array_key_exists( $ip, $instance_max_online_by_virtual_server )
							     || array_key_exists( 4, $buffer ) && ! array_key_exists( $buffer[2], $instance_max_online_by_virtual_server[ $ip ] )
							     || array_key_exists( 4, $buffer ) && intval( $buffer[4] ) > $instance_max_online_by_virtual_server[ $ip ][ $buffer[2] ] ) {
								$instance_max_online_by_virtual_server[ $ip ][ $buffer[2] ] = intval( $buffer[4] );
							}

							if ( array_key_exists( 4, $buffer ) && ! array_key_exists( $ip, $virtual_server_online_stats_day )
							     || array_key_exists( 4, $buffer ) && ! array_key_exists( $buffer[2], $virtual_server_online_stats_day[ $ip ] )
							     || array_key_exists( 4, $buffer ) && intval( $buffer[4] ) !== $virtual_server_online_stats_day[ $ip ][ $buffer[2] ][ count( $virtual_server_online_stats_day[ $ip ][ $buffer[2] ] ) - 1 ]['val'] ) {

								$virtual_server_online_stats_day[ $ip ][ $buffer[2] ][] = [
									'val'       => intval( $buffer[4] ),
									'timestamp' => intval( $buffer[0] )
								];
							}

							if ( array_key_exists( 3, $buffer ) && ! array_key_exists( $ip, $virtual_server_slots_stats_day )
							     || array_key_exists( 3, $buffer ) && ! array_key_exists( $buffer[2], $virtual_server_slots_stats_day[ $ip ] )
							     || array_key_exists( 3, $buffer ) && intval( $buffer[3] ) !== $virtual_server_slots_stats_day[ $ip ][ $buffer[2] ][ count( $virtual_server_slots_stats_day[ $ip ][ $buffer[2] ] ) - 1 ]['val'] ) {

								$virtual_server_slots_stats_day[ $ip ][ $buffer[2] ][] = [
									'val'       => intval( $buffer[3] ),
									'timestamp' => intval( $buffer[0] )
								];
							}
						}

						gzclose( $fp );
						unset( $fp, $buffer, $ip );

						foreach ( $instance_max_slots_by_virtual_server as $ip => $data ) {
							if ( ! file_exists( $local_cache_path . '/instance_max_slots_by_virtual_server' ) ) {
								mkdir( $local_cache_path . '/instance_max_slots_by_virtual_server', 0777 );
							}

							if ( ! file_exists( $local_cache_path . '/instance_max_slots_by_virtual_server/' . $year ) ) {
								mkdir( $local_cache_path . '/instance_max_slots_by_virtual_server/' . $year, 0777 );
							}

							if ( ! file_exists( $local_cache_path . '/instance_max_slots_by_virtual_server/' . $year . '/' . $month ) ) {
								mkdir( $local_cache_path . '/instance_max_slots_by_virtual_server/' . $year . '/' . $month, 0777 );
							}

							if ( ! file_exists( $local_cache_path . '/instance_max_slots_by_virtual_server/' . $year . '/' . $month . '/' . $day ) ) {
								mkdir( $local_cache_path . '/instance_max_slots_by_virtual_server/' . $year . '/' . $month . '/' . $day, 0777 );
							}

							if ( ! $handle = fopen( $local_cache_path . '/instance_max_slots_by_virtual_server/' . $year . '/' . $month . '/' . $day . '/' . $ip . '.json', 'w' ) ) {
								echo "Не могу открыть файл ($filename)";
								exit;
							}

							if ( fwrite( $handle, json_encode( $data ) ) === false ) {
								echo "Не могу произвести запись в файл ($filename)";
								exit;
							}

							fclose( $handle );
							unset( $handle );
						}
						unset( $instance_max_slots_by_virtual_server );

						foreach ( $instance_max_online_by_virtual_server as $ip => $data ) {
							if ( ! file_exists( $local_cache_path . '/instance_max_online_by_virtual_server' ) ) {
								mkdir( $local_cache_path . '/instance_max_online_by_virtual_server', 0777 );
							}

							if ( ! file_exists( $local_cache_path . '/instance_max_online_by_virtual_server/' . $year ) ) {
								mkdir( $local_cache_path . '/instance_max_online_by_virtual_server/' . $year, 0777 );
							}

							if ( ! file_exists( $local_cache_path . '/instance_max_online_by_virtual_server/' . $year . '/' . $month ) ) {
								mkdir( $local_cache_path . '/instance_max_online_by_virtual_server/' . $year . '/' . $month, 0777 );
							}

							if ( ! file_exists( $local_cache_path . '/instance_max_online_by_virtual_server/' . $year . '/' . $month . '/' . $day ) ) {
								mkdir( $local_cache_path . '/instance_max_online_by_virtual_server/' . $year . '/' . $month . '/' . $day, 0777 );
							}

							if ( ! $handle = fopen( $local_cache_path . '/instance_max_online_by_virtual_server/' . $year . '/' . $month . '/' . $day . '/' . $ip . '.json', 'w' ) ) {
								echo "Не могу открыть файл ($filename)";
								exit;
							}

							if ( fwrite( $handle, json_encode( $data ) ) === false ) {
								echo "Не могу произвести запись в файл ($filename)";
								exit;
							}

							fclose( $handle );
							unset( $handle );
						}
						unset( $instance_max_online_by_virtual_server );

						foreach ( $virtual_server_online_stats_day as $ip => $virtual_servers ) {
							foreach ( $virtual_servers as $uid => $data ) {
								if ( ! file_exists( $local_cache_path . '/' . 'virtual_server_online_history' ) ) {
									mkdir( $local_cache_path . '/' . 'virtual_server_online_history', 0777 );
								}

								if ( ! file_exists( $local_cache_path . '/' . 'virtual_server_online_history/' . $year ) ) {
									mkdir( $local_cache_path . '/' . 'virtual_server_online_history/' . $year, 0777 );
								}

								if ( ! file_exists( $local_cache_path . '/' . 'virtual_server_online_history/' . $year . '/' . $month ) ) {
									mkdir( $local_cache_path . '/' . 'virtual_server_online_history/' . $year . '/' . $month, 0777 );
								}

								if ( ! file_exists( $local_cache_path . '/virtual_server_online_history/' . $year . '/' . $month . '/' . $day ) ) {
									mkdir( $local_cache_path . '/virtual_server_online_history/' . $year . '/' . $month . '/' . $day, 0777 );
								}

								if ( ! $handle = fopen( $local_cache_path . '/virtual_server_online_history/' . $year . '/' . $month . '/' . $day . '/' . base64_encode( $uid ) . '.json', 'w' ) ) {
									echo "Не могу открыть файл ($filename)";
									exit;
								}

								if ( fwrite( $handle, json_encode( $data ) ) === false ) {
									echo "Не могу произвести запись в файл ($filename)";
									exit;
								}

								fclose( $handle );
								unset( $handle );
							}
						}
						unset( $virtual_server_online_stats_day );

						foreach ( $virtual_server_slots_stats_day as $ip => $virtual_servers ) {
							foreach ( $virtual_servers as $uid => $data ) {
								if ( ! file_exists( $local_cache_path . '/' . 'virtual_server_slot_history' ) ) {
									mkdir( $local_cache_path . '/' . 'virtual_server_slot_history', 0777 );
								}

								if ( ! file_exists( $local_cache_path . '/' . 'virtual_server_slot_history/' . $year ) ) {
									mkdir( $local_cache_path . '/' . 'virtual_server_slot_history/' . $year, 0777 );
								}

								if ( ! file_exists( $local_cache_path . '/' . 'virtual_server_slot_history/' . $year . '/' . $month ) ) {
									mkdir( $local_cache_path . '/' . 'virtual_server_slot_history/' . $year . '/' . $month, 0777 );
								}

								if ( ! file_exists( $local_cache_path . '/virtual_server_slot_history/' . $year . '/' . $month . '/' . $day ) ) {
									mkdir( $local_cache_path . '/virtual_server_slot_history/' . $year . '/' . $month . '/' . $day, 0777 );
								}

								if ( ! $handle = fopen( $local_cache_path . '/virtual_server_slot_history/' . $year . '/' . $month . '/' . $day . '/' . base64_encode( $uid ) . '.json', 'w' ) ) {
									echo "Не могу открыть файл ($filename)";
									exit;
								}

								if ( fwrite( $handle, json_encode( $data ) ) === false ) {
									echo "Не могу произвести запись в файл ($filename)";
									exit;
								}

								fclose( $handle );
								unset( $handle );
							}
						}
						unset( $virtual_server_slots_stats_day );

						break;
					case  'max_slots_by_ts.csv.gz':
						if ( ! file_exists( $local_cache_path . '/' . $year . '/' . $month . '/' . $day . '/max_slots_by_ts.csv.gz' ) ) {
							ftp_get( $conn_id, $local_cache_path . '/' . $year . '/' . $month . '/' . $day . '/max_slots_by_ts.csv.gz', '/ts3nlogs/61109/' . $year . '/' . $month . '/' . $day . '/max_slots_by_ts.csv.gz', FTP_BINARY );
						}
						break;
					case  'total.csv.gz':
						$slot_stats_day   = [];
						$online_stats_day = [];

						if ( ! file_exists( $local_cache_path . '/' . $year . '/' . $month . '/' . $day . '/total.csv.gz' ) ) {
							ftp_get( $conn_id, $local_cache_path . '/' . $year . '/' . $month . '/' . $day . '/total.csv.gz', '/ts3nlogs/61109/' . $year . '/' . $month . '/' . $day . '/total.csv.gz', FTP_BINARY );
						}

						if ( file_exists( $local_cache_path . '/instance_slot_history/' . $year . '/' . $month . '/' . $day ) &&
						     file_exists( $local_cache_path . '/instance_online_history/' . $year . '/' . $month . '/' . $day ) ) {
							echo 'skip ' . $year . '/' . $month . '/' . $day . PHP_EOL;
							continue;
						}

						$fp = gzopen( $local_cache_path . '/' . $year . '/' . $month . '/' . $day . '/total.csv.gz', "r" );
						while ( ! gzeof( $fp ) ) {
							$buffer = gzgets( $fp, 4096 );
							$buffer = str_getcsv( $buffer );
							$ip     = substr( $buffer[1], 0, - 6 );
							/*
							 * 0 - timestamp
							 * 1 - ip:port
							 * 2 - slots
							 * 3 - online
							*/
							if ( filter_var( $ip, FILTER_VALIDATE_IP ) === false ) {
								continue;
							}

							if ( ! array_key_exists( $ip, $slot_stats_day )
							     || intval( $buffer[2] ) !== $slot_stats_day[ $ip ][ count( $slot_stats_day[ $ip ] ) - 1 ]['val'] ) {
								$slot_stats_day[ $ip ][] = [
									'val'       => intval( $buffer[2] ),
									'timestamp' => intval( $buffer[0] )
								];
							}

							if ( array_key_exists( 3, $buffer ) && ! array_key_exists( $ip, $online_stats_day )
							     || array_key_exists( 3, $buffer ) && intval( $buffer[3] ) !== $online_stats_day[ $ip ][ count( $online_stats_day[ $ip ] ) - 1 ]['val'] ) {
								$online_stats_day[ $ip ][] = [
									'val'       => intval( $buffer[3] ),
									'timestamp' => intval( $buffer[0] )
								];
							}
						}
						gzclose( $fp );
						unset( $fp, $buffer, $ip );

						foreach ( $slot_stats_day as $ip => $data ) {
							if ( ! file_exists( $local_cache_path . '/instance_slot_history' ) ) {
								mkdir( $local_cache_path . '/instance_slot_history', 0777 );
							}

							if ( ! file_exists( $local_cache_path . '/instance_slot_history/' . $year ) ) {
								mkdir( $local_cache_path . '/instance_slot_history/' . $year, 0777 );
							}

							if ( ! file_exists( $local_cache_path . '/instance_slot_history/' . $year . '/' . $month ) ) {
								mkdir( $local_cache_path . '/instance_slot_history/' . $year . '/' . $month, 0777 );
							}

							if ( ! file_exists( $local_cache_path . '/instance_slot_history/' . $year . '/' . $month . '/' . $day ) ) {
								mkdir( $local_cache_path . '/instance_slot_history/' . $year . '/' . $month . '/' . $day, 0777 );
							}

							if ( ! $handle = fopen( $local_cache_path . '/instance_slot_history/' . $year . '/' . $month . '/' . $day . '/' . $ip . '.json', 'w' ) ) {
								echo "Не могу открыть файл ($filename)";
								exit;
							}

							if ( fwrite( $handle, json_encode( $data ) ) === false ) {
								echo "Не могу произвести запись в файл ($filename)";
								exit;
							}

							fclose( $handle );
							unset( $handle );
						}
						unset( $slot_stats_day );

						foreach ( $online_stats_day as $ip => $data ) {
							if ( ! file_exists( $local_cache_path . '/instance_online_history' ) ) {
								mkdir( $local_cache_path . '/instance_online_history', 0777 );
							}

							if ( ! file_exists( $local_cache_path . '/instance_online_history/' . $year ) ) {
								mkdir( $local_cache_path . '/instance_online_history/' . $year, 0777 );
							}

							if ( ! file_exists( $local_cache_path . '/instance_online_history/' . $year . '/' . $month ) ) {
								mkdir( $local_cache_path . '/instance_online_history/' . $year . '/' . $month, 0777 );
							}

							if ( ! file_exists( $local_cache_path . '/instance_online_history/' . $year . '/' . $month . '/' . $day ) ) {
								mkdir( $local_cache_path . '/instance_online_history/' . $year . '/' . $month . '/' . $day, 0777 );
							}

							if ( ! $handle = fopen( $local_cache_path . '/instance_online_history/' . $year . '/' . $month . '/' . $day . '/' . $ip . '.json', 'w' ) ) {
								echo "Не могу открыть файл ($filename)";
								exit;
							}

							if ( fwrite( $handle, json_encode( $data ) ) === false ) {
								echo "Не могу произвести запись в файл ($filename)";
								exit;
							}

							fclose( $handle );
							unset( $handle );
						}
						unset( $online_stats_day );
						break;
				}
			}
		}
	}
}


function ftp_file_map_set_file( &$array, $key, $value ) {
	if ( is_null( $key ) ) {
		return $array = $value;
	}
	$keys = explode( '/', $key );
	while ( count( $keys ) > 1 ) {
		$key = array_shift( $keys );
		// If the key doesn't exist at this depth, we will just create an empty array
		// to hold the next value, allowing us to create the arrays to hold final
		// values at the correct depth. Then we'll keep digging into the array.
		if ( ! isset( $array[ $key ] ) || ! is_array( $array[ $key ] ) ) {
			$array[ $key ] = array();
		}
		$array =& $array[ $key ];
	}
	$array[] = $value;

	return $array;
}

function ftp_list_files_recursive( $ftp_stream, $path ) {
	$lines = ftp_rawlist( $ftp_stream, $path );

	$result = array();
	if ( is_array( $lines ) ) {
		foreach ( $lines as $line ) {
			$tokens   = explode( " ", $line );
			$name     = $tokens[ count( $tokens ) - 1 ];
			$type     = $tokens[0][0];
			$filepath = $path . "/" . $name;

			if ( $type == 'd' ) {
				$result = array_merge( $result, ftp_list_files_recursive( $ftp_stream, $filepath ) );
			} else {
				$result[] = $filepath;
			}
		}
	}

	return $result;
}

function fileread( $filepath ) {
	$f = fopen( $filepath, 'r' );
	if ( ! $f ) {
		return false;
	}
	$data = fread( $f, filesize( $filepath ) );
	fclose( $f );

	return $data;
}

function formatBytes( $bytes, $precision = 2 ) {
	$units = array( "b", "kb", "mb", "gb", "tb" );
	$bytes = max( $bytes, 0 );
	$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
	$pow   = min( $pow, count( $units ) - 1 );
	$bytes /= ( 1 << ( 10 * $pow ) );

	return round( $bytes, $precision ) . " " . $units[ $pow ];
}

print formatBytes( memory_get_peak_usage() ) . PHP_EOL;
