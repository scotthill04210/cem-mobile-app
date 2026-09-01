<?php

defined( 'ABSPATH' ) || exit;

class CMA_Attendees {

	/**
	 * @var array<int, array<int, array{key:string,order_id:int,attendee_id:string,first_name:string,last_name:string,name:string,company:string}>>
	 */
	private static array $schedule_cache = [];

	/**
	 * @var array<int, array<int, array<string, mixed>>>
	 */
	private static array $roster_cache = [];

	/**
	 * Attendees registered to the selected CEM schedule.
	 *
	 * @return array<int, array{key:string,order_id:int,attendee_id:string,first_name:string,last_name:string,name:string,company:string}>
	 */
	public static function get_for_schedule( int $schedule_id ): array {
		$schedule_id = absint( $schedule_id );
		if ( $schedule_id <= 0 || ! class_exists( 'CEM_Reports' ) || ! class_exists( 'CEM_Order_Data' ) ) {
			return [];
		}

		if ( isset( self::$schedule_cache[ $schedule_id ] ) ) {
			return self::$schedule_cache[ $schedule_id ];
		}

		$allowed_sessions = array_values(
			array_filter(
				array_map( 'absint', (array) CEM_Schedule_Meta::get( $schedule_id, 'session_ids' ) )
			)
		);

		if ( empty( $allowed_sessions ) ) {
			self::$schedule_cache[ $schedule_id ] = [];
			return [];
		}

		$rows = [];
		$seen = [];

		foreach ( CEM_Reports::get_filtered_orders( [ 'schedule_id' => $schedule_id ], CEM_Order_Data::get_count_statuses() ) as $order ) {
			$company = CEM_Order_Data::get_company_name( $order );

			foreach ( self::attendees_for_order( $order ) as $attendee ) {
				$session_ids = self::attending_session_ids( $order, $attendee );
				$on_schedule = array_values( array_intersect( $session_ids, $allowed_sessions ) );
				if ( empty( $on_schedule ) ) {
					continue;
				}

				$attendee_id = (string) ( $attendee['attendee_id'] ?? '' );
				if ( '' === $attendee_id ) {
					continue;
				}

				$key = self::make_key( (int) $order->get_id(), $attendee_id );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;

				$display_name = trim( (string) ( $attendee['name'] ?? '' ) );
				if ( '' === $display_name ) {
					$display_name = trim( (string) ( $attendee['badge_name'] ?? '' ) );
				}
				if ( '' === $display_name ) {
					continue;
				}

				[ $first_name, $last_name ] = self::split_name( $display_name );

				$rows[] = [
					'key'         => $key,
					'order_id'    => (int) $order->get_id(),
					'attendee_id' => $attendee_id,
					'first_name'  => $first_name,
					'last_name'   => $last_name,
					'name'        => $display_name,
					'company'     => $company,
				];
			}
		}

		usort(
			$rows,
			static fn( array $a, array $b ): int => strcasecmp( $a['last_name'] . ' ' . $a['first_name'], $b['last_name'] . ' ' . $b['first_name'] )
		);

		self::$schedule_cache[ $schedule_id ] = $rows;

		return $rows;
	}

	public static function make_key( int $order_id, string $attendee_id ): string {
		return $order_id . '__' . $attendee_id;
	}

	public static function parse_key( string $key ): array {
		$parts = explode( '__', $key, 2 );

		return [
			'order_id'    => absint( $parts[0] ?? 0 ),
			'attendee_id' => (string) ( $parts[1] ?? '' ),
		];
	}

	public static function find_by_key( string $key, int $schedule_id = 0 ): ?array {
		$schedule_id = $schedule_id > 0 ? $schedule_id : CMA_Settings::get_schedule_id();
		foreach ( self::get_for_schedule( $schedule_id ) as $attendee ) {
			if ( $attendee['key'] === $key ) {
				return $attendee;
			}
		}

		return null;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function attendees_for_order( WC_Order $order ): array {
		$attendees = CEM_Order_Data::group_order_items_into_attendees( $order );
		if ( ! empty( $attendees ) ) {
			return $attendees;
		}

		$fallback = [];
		$order_id = (int) $order->get_id();
		if ( ! isset( self::$roster_cache[ $order_id ] ) ) {
			self::$roster_cache[ $order_id ] = CEM_Attendance_Roster::get_for_order( $order_id );
		}
		foreach ( self::$roster_cache[ $order_id ] as $entry ) {
			$attendee_id = (string) ( $entry['attendee_id'] ?? '' );
			if ( '' === $attendee_id ) {
				continue;
			}

			$sessions = [];
			foreach ( (array) ( $entry['sessions'] ?? [] ) as $session ) {
				if ( empty( $session['attending'] ) ) {
					continue;
				}
				$session_id = absint( $session['session_id'] ?? 0 );
				if ( $session_id > 0 ) {
					$sessions[] = [ 'session_id' => $session_id ];
				}
			}

			$fallback[] = [
				'attendee_id' => $attendee_id,
				'name'        => (string) ( $entry['name'] ?? '' ),
				'badge_name'  => '',
				'sessions'    => $sessions,
			];
		}

		return $fallback;
	}

	/**
	 * @param array<string, mixed> $attendee
	 * @return int[]
	 */
	private static function attending_session_ids( WC_Order $order, array $attendee ): array {
		$session_ids = [];
		$order_id    = (int) $order->get_id();
		if ( ! isset( self::$roster_cache[ $order_id ] ) ) {
			self::$roster_cache[ $order_id ] = CEM_Attendance_Roster::get_for_order( $order_id );
		}
		$roster = self::$roster_cache[ $order_id ];

		foreach ( $roster as $entry ) {
			if ( (string) ( $entry['attendee_id'] ?? '' ) !== (string) ( $attendee['attendee_id'] ?? '' ) ) {
				continue;
			}

			foreach ( (array) ( $entry['sessions'] ?? [] ) as $session ) {
				if ( empty( $session['attending'] ) ) {
					continue;
				}
				$session_id = absint( $session['session_id'] ?? 0 );
				if ( $session_id > 0 ) {
					$session_ids[] = $session_id;
				}
			}
		}

		if ( empty( $session_ids ) ) {
			foreach ( (array) ( $attendee['sessions'] ?? [] ) as $session ) {
				$session_id = absint( $session['session_id'] ?? 0 );
				if ( $session_id > 0 ) {
					$session_ids[] = $session_id;
				}
			}
		}

		return array_values( array_unique( $session_ids ) );
	}

	/**
	 * @return array{0:string,1:string}
	 */
	private static function split_name( string $name ): array {
		$name  = trim( $name );
		$parts = preg_split( '/\s+/', $name, 2 );
		if ( ! is_array( $parts ) || '' === $parts[0] ) {
			return [ $name, '' ];
		}

		return [
			$parts[0],
			isset( $parts[1] ) ? $parts[1] : '',
		];
	}
}
