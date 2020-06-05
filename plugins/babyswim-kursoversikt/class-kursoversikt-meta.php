<?php
if ( ! class_exists( 'WP' ) ) {
	exit;
}

class Kursoversikt_Meta {
	public static function sanitize_billing_address_1( string $address ): string {
		$address = mb_convert_case( trim( $address ), MB_CASE_TITLE );
		$len = mb_strlen( trim( $address ) );
		$address = mb_str_replace(
			[ ' Vei', ' Gate', ' Allé', ' Terrasse', ' Skolevei', ' Den ', ' Ivs ', ' Viis ' ],
			[ ' vei', ' gate', ' allé', ' terrasse', ' skolevei', ' den ', ' IVs ', ' VIIs ' ],
			$address
		);
		if ( ! is_numeric( mb_substr( $address, $len - 1 ) ) && is_numeric( mb_substr( $address, $len - 2, 1 ) ) ) {
			$address = mb_substr( $address, 0, $len - 1 ) . ' ' . mb_strtoupper( mb_substr( $address, $len - 1 ) );
		}
		return $address;
	}

	public static function sanitize_billing_postcode( string $code, $country = 'NO' ): string {
		$code = substr( trim( $code ), -4 );
		$code = str_pad( intval( $code ), 4, '0', STR_PAD_LEFT );
		return $code;
	}

	public static function sanitize_billing_city( string $city ): string {
		$city = mb_convert_case( trim( $city ), MB_CASE_TITLE );
		$city = mb_str_replace(
			[ ' I ', ' In ', ' On '. ' Upon ', ' At ', ' Am ' ],
			[ ' i ', ' in ', ' on ', ' upon ', ' at ', ' am ' ],
			$city
		);
		return $city;
	}

	public static function sanitize_billing_phone( string $phone, $country = 'NO' ): string {
		$phone = str_replace( ' ', '', trim( $phone ) );
		$phone = strval( intval( $phone ) );
		$len = strlen( $phone );
		$phone = substr( $phone, 0, 2 ) === '00' ? substr( $phone, 2 ) : $phone;
		$phone = substr( $phone, 0, 1 ) === '+'  ? substr( $phone, 1 ) : $phone;
		$phone = $len > 8 &&
			     substr( $phone, 0, 2 ) === '47' ? substr( $phone, 2 ) : $phone;
//		$phone = substr( $phone, 0, 1 ) === '4' || substr( $phone, 0, 1 ) === '9' ?
//			substr( $phone, 0, 3 ) . '&nbsp;' . substr( $phone, 3, 2 ) . '&nbsp;' . substr( $phone, 5, 2 ) :
//			substr( $phone, 0, 2 ) . '&nbsp;' . substr( $phone, 2, 2 ) . '&nbsp;' . substr( $phone, 4, 2 ) . '&nbsp;' . substr( $phone, 6 )
//		;
		return $phone;
	}

	public static function sanitize_billing_email( string $email ): string {
		$email = sanitize_email( strtolower( $email ) );
		return $email;
	}

	public static function sanitize_billing_first_name( string $first_name ): string {
		$first_name = mb_convert_case( trim( $first_name ), MB_CASE_TITLE );
		return $first_name;
	}
	
	public static function sanitize_billing_last_name( string $last_name ): string {
		$last_name = str_replace(
			[ ' Mc', ' Mac' ], [ ' Mc ', ' Mac ' ],
			$last_name
		);
		$last_name = mb_convert_case( trim( $last_name ), MB_CASE_TITLE );
		$last_name  = str_replace(
			[ ' Na ', '_Van_', ' De ', ' Ter ' ],
			[ '_na_', '_van_', '_de_', '_ter_' ],
			$last_name
		);
		$last_name = str_replace(
			[ ' Mc ' ,  ' Mc'  ],
			[ ' Mac ',  ' Mac' ],
			$last_name
		);
		return str_replace( '_', ' ', $last_name );
	}

	public static function sanitize_name( array $names ): array {
		$first_name = mb_convert_case( trim( $names['billing_first_name'] ), MB_CASE_TITLE );
		$last_name  = mb_convert_case( trim( $names['billing_last_name' ] ), MB_CASE_TITLE );
		if ( $first_name && $last_name ) {
			$last_name  = str_replace(
				[ ' Na ', '_Van_', ' De ' ],
				[ '_na_', '_van_', '_de_' ],
				$last_name
			);
			$last_names = explode( ' ', $last_name );
			$last_name  = str_replace( '_', ' ', array_pop( $last_names ) );
			foreach ( $last_names as $name ) {
				$first_name .= ' ' . $name;
			}
			$names = [ 'billing_first_name' => $first_name, 'billing_last_name' => $last_name ];
		}
		return $names;
	}

	public static function plugins_loaded() {
		add_action( 'woocommerce_customer_object_updated_props', function( $customer, $updated_props ) {
			$user_id = $customer->get_id();
			$updated_props = in_array( 'billing_first_name', $updated_props ) || in_array ( 'billing_last_name', $updated_props ) ?
				array_merge( $updated_props, [ 'name' => [ 'billing_first_name', 'billing_last_name' ] ] ) :
//				[ 'name' => [ 'billing_first_name', 'billing_last_name' ], 'billing_address_1', 'billing_postcode', 'billing_city', 'billing_email' ]
				( WP_DEBUG ? array_merge( $updated_props, [ 'name' => [ 'billing_first_name', 'billing_last_name' ], 'billing_address_1', 'billing_postcode', 'billing_city', 'billing_email' ] ) : $updated_props )
			;
//			error_log( print_r( $updated_props, true ) );
			foreach ( $updated_props as $method => $prop ) {
				if ( is_array( $prop ) ) {
					$method = 'sanitize_' . $method;
//					error_log( $method . method_exists( __CLASS__, $method ) );
					$old_values = [];
					foreach ( $prop as $meta_key ) {
						$old_values[ $meta_key ] = get_user_meta( $user_id, $meta_key, true );
					}
					if ( method_exists( __CLASS__, $method ) ) {
						$new_values = self::$method( $old_values );
//						error_log( $method . ' ' . print_r( $old_values, true ) . print_r( $new_values, true ) );
						foreach ( $new_values as $meta_key => $new_value ) {
							if ( $new_value != $old_values[ $meta_key ] ) {
								update_user_meta( $user_id, $meta_key, $new_value );
							}
						}
					}
				} else {
					$method = 'sanitize_' . $prop;
					$value = get_user_meta( $user_id, $prop, true );
//					error_log( $method . ' ' . $value . ' ' . self::$method( $value ) . '|' . ( method_exists( __CLASS__, $method ) ? 'ok'
//					 : 'no') );
					if ( $value && method_exists( __CLASS__, $method ) ) {
						update_user_meta( $user_id, $prop, self::$method( $value ) );	
					}
				}
			}
		}, 10, 2 );
	}
}

add_action( 'plugins_loaded', [ 'Kursoversikt_Meta', 'plugins_loaded' ] );