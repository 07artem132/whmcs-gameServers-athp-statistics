<?php
/**
 * Created by PhpStorm.
 * User: Artem
 * Date: 10.12.2018
 * Time: 19:29
 */

namespace WHMCS\Module\Addon\GameServersAthpStats;

class VirtualServerStatisticsController {
	private static $cache_dir = ROOTDIR . '/modules/addons/GameServersAthpStats/cache';

	public static function getAllowIpForLastWeekly(): array {
		$ips = [];

		$path = self::$cache_dir . DIRECTORY_SEPARATOR . 'instance_max_slots_by_virtual_server' . DIRECTORY_SEPARATOR . date( 'Y' ) . DIRECTORY_SEPARATOR . date( 'n' );

		foreach ( scandir( $path ) as $item ) {
			if ( is_dir( realpath( $path . DIRECTORY_SEPARATOR . $item ) ) && is_numeric( $item ) && intval( $item ) >= ( intval( date( 'j' ) ) - 7 ) ) {
				foreach ( scandir( $path . DIRECTORY_SEPARATOR . $item ) as $instance ) {
					if ( $instance === '.' || $instance === '..' ) {
						continue;
					}

					$ips[ substr( $instance, 0, - 5 ) ] = '';
				}
			}
		}


		return array_keys( $ips );
	}

	public static function getAllowIpForMonth( int $year, int $moth ): array {
		$ips = [];

		$path = self::$cache_dir . DIRECTORY_SEPARATOR . 'instance_max_slots_by_virtual_server' . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $moth;

		foreach ( scandir( $path ) as $item ) {
			if ( is_dir( realpath( $path . DIRECTORY_SEPARATOR . $item ) ) && is_numeric( $item ) ) {
				foreach ( scandir( $path . DIRECTORY_SEPARATOR . $item ) as $instance ) {
					if ( $instance === '.' || $instance === '..' ) {
						continue;
					}
					$ips[ substr( $instance, 0, - 5 ) ] = '';
				}
			}
		}


		return array_keys( $ips );
	}

	public static function getAllowIpForYear( int $year ): array {
		$ips = [];

		foreach ( scandir( self::$cache_dir . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR ) as $moth ) {
			if ( is_dir( realpath( self::$cache_dir . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $moth ) ) && is_numeric( $moth ) ) {
				$path = self::$cache_dir . DIRECTORY_SEPARATOR . 'instance_max_slots_by_virtual_server' . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $moth;
				foreach ( scandir( $path ) as $item ) {
					if ( is_dir( realpath( $path . DIRECTORY_SEPARATOR . $item ) ) && is_numeric( $item ) ) {
						foreach ( scandir( $path . DIRECTORY_SEPARATOR . $item ) as $instance ) {
							if ( $instance === '.' || $instance === '..' ) {
								continue;
							}
							$ips[ substr( $instance, 0, - 5 ) ] = '';
						}
					}
				}
			}
		}

		return array_keys( $ips );
	}



	public static function getAvailableMonthsForYear( int $year ): array {
		$return = [];

		foreach ( scandir( self::$cache_dir . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR ) as $item ) {
			if ( is_dir( realpath( self::$cache_dir . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $item ) ) && is_numeric( $item ) ) {
				$return [] = $item;
			}
		};

		return array_sort( $return, function ( $value ) {
			return $value;
		} );
	}

	public static function getAvailableYears(): array {
		$return = [];

		foreach ( scandir( self::$cache_dir . DIRECTORY_SEPARATOR ) as $item ) {
			if ( is_dir( realpath( self::$cache_dir . DIRECTORY_SEPARATOR . $item ) ) && is_numeric( $item ) ) {
				$return [] = $item;
			}
		};

		return array_sort( $return, function ( $value ) {
			return $value;
		} );
	}



	public static function getAllowUidForIpForLastWeekly( string $ip ): array {
		$uids = [];

		$path = self::$cache_dir . DIRECTORY_SEPARATOR . 'instance_max_slots_by_virtual_server' . DIRECTORY_SEPARATOR . date( 'Y' ) . DIRECTORY_SEPARATOR . date( 'n' );

		foreach ( scandir( $path ) as $item ) {
			if ( is_dir( realpath( $path . DIRECTORY_SEPARATOR . $item ) ) && is_numeric( $item ) && intval( $item ) >= ( intval( date( 'j' ) ) - 7 ) ) {
				foreach ( scandir( $path . DIRECTORY_SEPARATOR . $item ) as $instance ) {
					if ( $instance === '.' || $instance === '..' ) {
						continue;
					}
					if ( $ip == substr( $instance, 0, - 5 ) ) {
						$data = json_decode( file_get_contents( $path . DIRECTORY_SEPARATOR . $item . DIRECTORY_SEPARATOR . $instance ), true );
						$uids = array_merge( $uids, array_keys( $data ) );
					}
				}
			}
		}

		return array_values( $uids );
	}



	public static function getMaxOnlineVirtualServersLastWeekly( $uid ): int {
		$online = 0;

		$path = self::$cache_dir . DIRECTORY_SEPARATOR . 'virtual_server_online_history' . DIRECTORY_SEPARATOR . date( 'Y' ) . DIRECTORY_SEPARATOR . date( 'n' );
		foreach ( scandir( $path ) as $item ) {
			if ( is_dir( realpath( $path . DIRECTORY_SEPARATOR . $item ) ) && is_numeric( $item ) && intval( $item ) >= ( intval( date( 'j' ) ) - 7 ) ) {
				if ( file_exists( $path . DIRECTORY_SEPARATOR . $item . DIRECTORY_SEPARATOR . base64_encode( $uid ) . '.json' ) ) {
					$data = json_decode( file_get_contents( $path . DIRECTORY_SEPARATOR . $item . DIRECTORY_SEPARATOR . base64_encode( $uid ) . '.json' ) );
					foreach ( $data as $tick ) {
						if ( $tick->val > $online ) {
							$online = $tick->val;
						}
					}
				} else {
					continue;
				}
			}
		}


		return $online;
	}

	public static function getMaxSlotVirtualServersLastWeekly( $uid ): int {
		$slots = 0;

		$path = self::$cache_dir . DIRECTORY_SEPARATOR . 'virtual_server_slot_history' . DIRECTORY_SEPARATOR . date( 'Y' ) . DIRECTORY_SEPARATOR . date( 'n' );
		foreach ( scandir( $path ) as $item ) {
			if ( is_dir( realpath( $path . DIRECTORY_SEPARATOR . $item ) ) && is_numeric( $item ) && intval( $item ) >= ( intval( date( 'j' ) ) - 7 ) ) {
				if ( file_exists( $path . DIRECTORY_SEPARATOR . $item . DIRECTORY_SEPARATOR . base64_encode( $uid ) . '.json' ) ) {
					$data = json_decode( file_get_contents( $path . DIRECTORY_SEPARATOR . $item . DIRECTORY_SEPARATOR . base64_encode( $uid ) . '.json' ) );
					foreach ( $data as $tick ) {
						if ( $tick->val > $slots ) {
							$slots = $tick->val;
						}
					}
				} else {
					continue;
				}
			}
		}


		return $slots;
	}


}