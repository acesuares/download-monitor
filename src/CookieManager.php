<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Class DLM_Cookie_Manager
 *
 * @since 4.0
 */
class DLM_Cookie_Manager {

	/**
	 * Cookie key
	 *
	 * @var string
	 */
	public static $key = '_dlm_cookie';

	/**
	 * Holds the class object.
	 *
	 * @var object
	 *
	 * @since 4.3.1
	 */
	public static $instance;

	/**
	 * Class constructor
	 *
	 * @since 4.9.5
	 */
	private function __construct() {
	}

	/**
	 * Returns the singleton instance of the class
	 *
	 * @return object The DLM_Cookie_Manager object.
	 *
	 * @since 4.9.5
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof DLM_Cookie_Manager ) ) {
			self::$instance = new DLM_Cookie_Manager();
		}

		return self::$instance;
	}


	/**
	 * Check if the cookie exists for this download & version. If it does exist the requester has requested the exact
	 * same download & version in the past minute.
	 *
	 * Deprecated since 4.9.5
	 *
	 * @param  DLM_Download  $download
	 *
	 * @return bool
	 */
	public static function exists( $download ) {
		$exists = false;

		// get JSON data
		$cdata = self::get_cookie_data();

		// check if no parse errors occurred
		if ( null != $cdata && is_array( $cdata ) && ! empty( $cdata ) ) {
			// check in cookie data for download AND version ID
			if ( $cdata['download'] == $download->get_id() && $cdata['version'] == $download->get_version()->get_version_number() ) {
				$exists = true;
			}
		}

		return $exists;
	}

	/**
	 * Get cookie data
	 *
	 * Deprecated since 4.9.5
	 *
	 * @return array|null
	 */
	public static function get_cookie_data() {
		$cdata = null;
		if ( ! empty( $_COOKIE[ self::$key ] ) ) {
			$cdata = json_decode( base64_decode( sanitize_text_field( wp_unslash( $_COOKIE[ self::$key ] ) ) ), true );
		}

		return $cdata;
	}

	/**
	 * Set cookie
	 *
	 * @param  DLM_Download  $download
	 */
	public function set_cookie( $download, $cookie_data = array() ) {
		$secure      = is_ssl();
		$cookie_data = wp_parse_args(
			$cookie_data,
			array(
				'expires'  => time() + 60,
				'secure'   => $secure,
				'httponly' => true,
				'meta'     => array(),
			)
		);

		/**
		 * Filter cookie data
		 * Old hook used to set cookie data for the wp_dlm_downloading cookie.
		 * Deprecated since 4.9.5
		 *
		 * @hook  wp_dlm_set_downloading_cookie
		 *
		 * @param  array  $cookie_data
		 *
		 * @since 4.0
		 *
		 */
		$cookie_data = apply_filters(
			'wp_dlm_set_downloading_cookie',
			$cookie_data
		);

		/**
		 * Filter cookie data
		 * New hook used to set cookie data for the general _dlm_cookie cookie.
		 * The cookie_data should contain the following:
		 * - expires: The expiration time of the cookie
		 * - secure: Whether the cookie should be secure or not
		 * - httponly: Whether the cookie should be httponly or not
		 * - meta: An array of meta data to be stored in the database in the cookie meta table. Each meta item should be
		 * an array and will be stored as a separate row in the database similar to post meta.
		 *
		 * @hook  _dlm_cookie_data
		 *
		 * @param  array  $cookie_data
		 *
		 * @since 4.9.5
		 *
		 */
		$cookie_data = apply_filters(
			'_dlm_cookie_data',
			$cookie_data
		);

		// Create cookie hash using download ID, site name, 'dlm' string and cookie ID
		$hash = wp_hash( $download->get_id() . 'dlm' . get_bloginfo( 'name' ) . wp_rand() );
		// Insert cookie into database
		$this->insert_cookie( $hash, $download, $cookie_data );

		// Set the cookie
		setcookie(
			self::$key,
			$hash,
			$cookie_data['expires'],
			COOKIEPATH,
			COOKIE_DOMAIN,
			$cookie_data['secure'],
			$cookie_data['httponly']
		);
	}

	/**
	 * Set cookie
	 *
	 * @param  DLM_Download  $download
	 */
	public function update_cookie( $hash, $download, $cookie_data = array() ) {
		$secure      = is_ssl();
		$cookie_data = wp_parse_args(
			$cookie_data,
			array(
				'expires'  => time() + 60,
				'secure'   => $secure,
				'httponly' => true,
				'meta'     => array(),
			)
		);

		/**
		 * Filter cookie data
		 * Old hook used to set cookie data for the wp_dlm_downloading cookie.
		 * Deprecated since 4.9.5
		 *
		 * @hook  wp_dlm_set_downloading_cookie
		 *
		 * @param  array  $cookie_data
		 *
		 * @since 4.0
		 *
		 */
		$cookie_data = apply_filters(
			'wp_dlm_set_downloading_cookie',
			$cookie_data
		);

		/**
		 * Filter cookie data
		 * New hook used to set cookie data for the general _dlm_cookie cookie.
		 * The cookie_data should contain the following:
		 * - expires: The expiration time of the cookie
		 * - secure: Whether the cookie should be secure or not
		 * - httponly: Whether the cookie should be httponly or not
		 * - meta: An array of meta data to be stored in the database in the cookie meta table. Each meta item should be
		 * an array and will be stored as a separate row in the database similar to post meta.
		 *
		 * @hook  _dlm_cookie_data
		 *
		 * @param  array  $cookie_data
		 *
		 * @since 4.9.5
		 *
		 */
		$cookie_data = apply_filters(
			'_dlm_cookie_data',
			$cookie_data
		);

		// Insert cookie into database
		$this->update_cookie_db( $hash, $download, $cookie_data );

		// Set the cookie
		setcookie(
			self::$key,
			$hash,
			$cookie_data['expires'],
			COOKIEPATH,
			COOKIE_DOMAIN,
			$cookie_data['secure'],
			$cookie_data['httponly']
		);
	}

	/**
	 * Insert cookie into database
	 * The cookie_data should contain the following:
	 *  - expires: The expiration time of the cookie
	 *  - secure: Whether the cookie should be secure or not
	 *  - httponly: Whether the cookie should be httponly or not
	 *  - meta: An array of meta data to be stored in the database in the cookie meta table. Each meta item should be
	 *  an array and will be stored as a separate row in the database similar to post meta.
	 *
	 * @param $hash        string Cookie hash
	 * @param $download    DLM_Download Download object
	 * @param $cookie_data array Cookie data
	 *
	 * @return int Cookie ID
	 *
	 * @since 4.9.5
	 */
	private function insert_cookie( $hash, $download, $cookie_data ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'dlm_cookies',
			array(
				'hash'            => $hash,
				'creation_date'   => current_time( 'mysql' ),
				'expiration_date' => wp_date( 'Y-m-d H:i:s', $cookie_data['expires'] ),
			),
			array(
				'%s',
				'%s',
				'%s',
			)
		);

		$cookie_id = $wpdb->insert_id;
		// Check if cookie meta is set
		if ( ! empty( $cookie_data['meta'] ) ) {
			// Cycle through meta and insert into database
			foreach ( $cookie_data['meta'] as $meta_key => $meta_value ) {
				$wpdb->insert(
					$wpdb->prefix . 'dlm_cookiemeta',
					array(
						'cookie_id' => $cookie_id,
						'meta_key'  => $meta_key,
						'meta_data' => $meta_value,
					),
					array(
						'%d',
						'%s',
						'%s',
					)
				);
			}
		}

		return $cookie_id;
	}

	/**
	 * Update cookie from database
	 * The cookie_data should contain the following:
	 *  - expires: The expiration time of the cookie
	 *  - secure: Whether the cookie should be secure or not
	 *  - httponly: Whether the cookie should be httponly or not
	 *  - meta: An array of meta data to be stored in the database in the cookie meta table. Each meta item should be
	 *  an array and will be stored as a separate row in the database similar to post meta.
	 *
	 * @param $hash        string Cookie hash
	 * @param $download    DLM_Download Download object
	 * @param $cookie_data array Cookie data
	 *
	 *
	 * @since 4.9.5
	 */
	private function update_cookie_db( $hash, $download, $cookie_data ) {
		global $wpdb;

		$cookie_id = $this->get_cookie_id( $hash );
		// Check if cookie meta is set
		if ( ! empty( $cookie_data['meta'] ) ) {
			// Cycle through meta and insert into database
			foreach ( $cookie_data['meta'] as $meta_key => $meta_value ) {
				$wpdb->insert(
					$wpdb->prefix . 'dlm_cookiemeta',
					array(
						'cookie_id' => $cookie_id,
						'meta_key'  => $meta_key,
						'meta_data' => $meta_value,
					),
					array(
						'%d',
						'%s',
						'%s',
					)
				);
			}
		}
	}

	/**
	 * Get cookie hash
	 *
	 * @return bool|string
	 *
	 * @since 4.9.5
	 */
	public function get_cookie_hash() {
		if ( ! empty( $_COOKIE[ self::$key ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ self::$key ] ) );
		}

		return false;
	}

	/**
	 * Get cookie ID from the database
	 *
	 * @param $hash string Cookie hash
	 *
	 * @return mixed
	 *
	 * @since 4.9.5
	 */
	public function get_cookie_id( $hash ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT `id` FROM {$wpdb->prefix}dlm_cookies WHERE `hash` = %s", $hash ) );
	}

	/**
	 * Get cookie creation date from the database
	 *
	 * @param $id int Cookie ID
	 *
	 * @return string|null
	 *
	 * @since 4.9.5
	 */
	public function get_cookie_set_date( $id ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT `creation_date` FROM {$wpdb->prefix}dlm_cookies WHERE `id` = %s", $id ) );
	}

	/**
	 * Get cookie expiration date from the database
	 *
	 * @param $id int Cookie ID
	 *
	 * @return mixed
	 *
	 * @since 4.9.5
	 */
	public function get_cookie_expiration_date( $id ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare( "SELECT `expiration_date` FROM {$wpdb->prefix}dlm_cookies WHERE `id` = %s", $id ) );
	}

	/**
	 * Get cookie meta data from the database
	 *
	 * @param $id          int Cookie ID
	 * @param $meta_key    string Meta key
	 *
	 * @return array|object|stdClass[]|null
	 *
	 * @since 4.9.5
	 */
	public function get_cookie_meta_data( $id, $meta_key ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( "SELECT `meta_data` FROM {$wpdb->prefix}dlm_cookiemeta WHERE `cookie_id` = %d AND `meta_key` = %s", $id, $meta_key ) );
	}


	/**
	 * Get the cookie from the database
	 *
	 * @param $hash string Cookie hash
	 *
	 * @return array|object|stdClass|null
	 *
	 * @since 4.9.5
	 */
	private function get_cookie( $hash ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}dlm_cookies WHERE `hash` = %s;", $hash ) );
	}

	/**
	 * Check the cookie meta data
	 *
	 * @param $meta_key  string Meta key
	 * @param $value     string Meta value to check
	 *
	 * @return bool
	 *
	 * @since 4.9.5
	 */
	public function check_cookie_meta( $meta_key, $value ) {
		// Get cookie hash
		$hash = $this->get_cookie_hash();

		// No hash found. Return false
		if ( ! $hash ) {
			return false;
		}
		// Get cookie ID from database
		$cookie_id = $this->get_cookie_id( $hash );
		//var_dump($cookie_id);
		// No cookie ID found. Return false
		if ( ! $cookie_id ) {
			return false;
		}
		// Check if cookie has expired
		if ( $this->check_expired_cookie( $cookie_id ) ) {
			return false;
		}
		// Get cookie meta data from database
		$meta = $this->get_cookie_meta_data( $cookie_id, $meta_key );

		// Check if meta data is set
		if ( ! empty( $meta ) ) {
			// Cycle through meta data and check if value exists
			foreach ( $meta as $meta_item ) {
				// Check if value exists. Do not use strict comparison here as the meta data is stored as
				// a string in the database and the value might be an integer.
				if ( $meta_item->meta_data == $value ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Delete cookie from database by ID
	 *
	 * @param $cookie_id int Cookie hash
	 *
	 * @since 4.9.5
	 */
	public function delete_cookie( $cookie_id ) {
		global $wpdb;
		// Delete entry from dlm_cookies table
		$wpdb->delete( $wpdb->prefix . 'dlm_cookies', array( 'id' => absint( $cookie_id ) ) );
		// Delete entry from dlm_cookiemeta table
		$wpdb->delete( $wpdb->prefix . 'dlm_cookiemeta', array( 'cookie_id' => absint( $cookie_id ) ) );
	}

	/**
	 * Check expired cookie by ID
	 *
	 * @param $cookie_id int Cookie ID
	 *
	 * @return bool
	 *
	 * @since 4.9.5
	 */
	public function check_expired_cookie( $cookie_id ) {
		$expiration_date = $this->get_cookie_expiration_date( $cookie_id );
		// Check if cookie has expired
		if ( $expiration_date < current_time( 'mysql' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Delete expired cookies
	 *
	 * @since 4.9.5
	 */
	public function delete_expired_cookies() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}dlm_cookies WHERE `expiration_date` < NOW()" );
	}
}
