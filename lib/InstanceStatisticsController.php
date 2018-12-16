<?php
/**
 * Created by PhpStorm.
 * User: Artem
 * Date: 10.12.2018
 * Time: 19:29
 */

namespace WHMCS\Module\Addon\GameServersAthpStats;

class InstanceStatisticsController {
	private static $cache_dir = ROOTDIR . '/modules/addons/GameServersAthpStats/cache';

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



	public static function getAllowIpForMonth( int $year, int $moth ): array {
		$ips = [];

		$path = self::$cache_dir . DIRECTORY_SEPARATOR . 'instance_slot_history' . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $moth;

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
				$path = self::$cache_dir . DIRECTORY_SEPARATOR . 'instance_slot_history' . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $moth;
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



	public static function getAvgSlotsMonth( int $year, int $moth, array $ip_allow = [] ): int {
		$avg = [];

		$path = self::$cache_dir . DIRECTORY_SEPARATOR . 'instance_slot_history' . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $moth;

		foreach ( scandir( $path ) as $item ) {
			if ( is_dir( realpath( $path . DIRECTORY_SEPARATOR . $item ) ) && is_numeric( $item ) ) {
				foreach ( scandir( $path . DIRECTORY_SEPARATOR . $item ) as $instance ) {
					if ( $instance === '.' || $instance === '..' ) {
						continue;
					}

					if ( ! empty( $ip_allow ) ) {
						if ( ! in_array( substr( $instance, 0, - 5 ), $ip_allow ) ) {
							continue;
						}
					}

					$data = json_decode( file_get_contents( $path . DIRECTORY_SEPARATOR . $item . DIRECTORY_SEPARATOR . $instance ) );

					foreach ( $data as $obj ) {
						$avg[ $instance ][] = $obj->val;
					}

				}
			}
		}

		array_walk( $avg, function ( &$stats ) {
			$stats = array_sum( $stats ) / count( $stats );
		} );

		return array_sum( $avg );
	}

	public static function getAvgOnlineMonth( int $year, int $moth, array $ip_allow = [] ): int {
		$avg = [];

		$path = self::$cache_dir . DIRECTORY_SEPARATOR . 'instance_online_history' . DIRECTORY_SEPARATOR . $year . DIRECTORY_SEPARATOR . $moth;

		foreach ( scandir( $path ) as $item ) {
			if ( is_dir( realpath( $path . DIRECTORY_SEPARATOR . $item ) ) && is_numeric( $item ) ) {
				foreach ( scandir( $path . DIRECTORY_SEPARATOR . $item ) as $instance ) {
					if ( $instance === '.' || $instance === '..' ) {
						continue;
					}

					if ( ! empty( $ip_allow ) ) {
						if ( ! in_array( substr( $instance, 0, - 5 ), $ip_allow ) ) {
							continue;
						}
					}

					$data = json_decode( file_get_contents( $path . DIRECTORY_SEPARATOR . $item . DIRECTORY_SEPARATOR . $instance ) );

					foreach ( $data as $obj ) {
						$avg[ $instance ][] = $obj->val;
					}

				}
			}
		}

		array_walk( $avg, function ( &$stats ) {
			$stats = array_sum( $stats ) / count( $stats );
		} );

		return array_sum( $avg );
	}



	public static function getMaxSlotsByVirtualServersLastWeekly( array $ip_allow = [] ): array {
		$maxSlotsByVirtualServers = [];

		$path = self::$cache_dir . DIRECTORY_SEPARATOR . 'instance_max_slots_by_virtual_server' . DIRECTORY_SEPARATOR . date( 'Y' ) . DIRECTORY_SEPARATOR . date( 'n' );

		foreach ( scandir( $path ) as $item ) {
			if ( is_dir( realpath( $path . DIRECTORY_SEPARATOR . $item ) ) && is_numeric( $item ) && intval( $item ) >= ( intval( date( 'j' ) ) - 7 ) ) {
				foreach ( scandir( $path . DIRECTORY_SEPARATOR . $item ) as $instance ) {
					if ( $instance === '.' || $instance === '..' ) {
						continue;
					}

					if ( ! empty( $ip_allow ) ) {
						if ( ! in_array( substr( $instance, 0, - 5 ), $ip_allow ) ) {
							continue;
						}
					}

					$data = json_decode( file_get_contents( $path . DIRECTORY_SEPARATOR . $item . DIRECTORY_SEPARATOR . $instance ) );
					foreach ( $data as $uid => $slots ) {
						$maxSlotsByVirtualServers[ $instance ][ $uid ][] = $slots;
					}
				}
			}
		}

		array_walk( $maxSlotsByVirtualServers, function ( &$virtualServer ) {
			array_walk( $virtualServer, function ( &$stats ) {
				$stats = max( $stats );
			} );
		} );

		return $maxSlotsByVirtualServers;
	}

	public static function getMaxOnlineByVirtualServersLastWeekly( array $ip_allow = [] ): array {
		$maxOnlineByVirtualServers = [];

		$path = self::$cache_dir . DIRECTORY_SEPARATOR . 'instance_max_online_by_virtual_server' . DIRECTORY_SEPARATOR . date( 'Y' ) . DIRECTORY_SEPARATOR . date( 'n' );

		foreach ( scandir( $path ) as $item ) {
			if ( is_dir( realpath( $path . DIRECTORY_SEPARATOR . $item ) ) && is_numeric( $item ) && intval( $item ) >= ( intval( date( 'j' ) ) - 7 ) ) {
				foreach ( scandir( $path . DIRECTORY_SEPARATOR . $item ) as $instance ) {
					if ( $instance === '.' || $instance === '..' ) {
						continue;
					}

					if ( ! empty( $ip_allow ) ) {
						if ( ! in_array( substr( $instance, 0, - 5 ), $ip_allow ) ) {
							continue;
						}
					}

					$data = json_decode( file_get_contents( $path . DIRECTORY_SEPARATOR . $item . DIRECTORY_SEPARATOR . $instance ) );
					foreach ( $data as $uid => $slots ) {
						$maxOnlineByVirtualServers[ $instance ][ $uid ][] = $slots;
					}
				}
			}
		}

		array_walk( $maxOnlineByVirtualServers, function ( &$virtualServer ) {
			array_walk( $virtualServer, function ( &$stats ) {
				$stats = max( $stats );
			} );
		} );

		return $maxOnlineByVirtualServers;
	}

}