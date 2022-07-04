<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'DLM_Reports' ) ) {

	/**
	 * DLM_Reports
	 *
	 * @since 4.6.0
	 */
	class DLM_Reports {

		/**
		 * Holds the class object.
		 *
		 * @since 4.6.0
		 *
		 * @var object
		 */
		public static $instance;

		private $headers = array();

		/**
		 * DLM_Reports constructor.
		 *
		 * @since 4.6.0
		 */
		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'create_global_variable' ) );
			add_action( 'wp_ajax_dlm_update_report_setting', array( $this, 'save_reports_settings' ) );
			add_action( 'wp_ajax_dlm_top_downloads_reports', array( $this, 'get_ajax_top_downloads_markup' ) );
			add_action( 'admin_init', array( $this, 'set_table_headers' ) );

		}

		/**
		 * Returns the singleton instance of the class.
		 *
		 * @return object The DLM_Reports object.
		 *
		 * @since 4.6.0
		 */
		public static function get_instance() {

			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof DLM_Reports ) ) {
				self::$instance = new DLM_Reports();
			}

			return self::$instance;

		}

		/**
		 * Set table headers
		 *
		 * @return void
		 */
		public function set_table_headers() {
			$this->headers = apply_filters(
				'dlm_reports_top_downloads',
				array(
					'table_headers' => array(
						'id'                        => 'ID',
						'title'                     => esc_html__( 'Title', 'download-monitor' ),
						'completed_downloads'       => esc_html__( 'Completed', 'download-monitor' ),
						'failed_downloads'          => esc_html__( 'Failed', 'download-monitor' ),
						'redirected_downloads'      => esc_html__( 'Redirected', 'download-monitor' ),
						'total_downloads'           => esc_html__( 'Total', 'download-monitor' ),
						'logged_in_downloads'       => esc_html__( 'Logged In', 'download-monitor' ),
						'non_logged_in_downloads'   => esc_html__( 'Non Logged In', 'download-monitor' ),
						'percent_downloads'         => esc_html__( '% of total', 'download-monitor' ),
						'content_locking_downloads' => esc_html__( 'Content Locking', 'download-monitor' ),
					)
				)
			);
		}

		/**
		 * Set our global variable dlmReportsStats so we can manipulate given data
		 *
		 * @since 4.6.0
		 */
		public function create_global_variable() {

			$rest_route_download_reports = rest_url() . 'download-monitor/v1/download_reports';
			$rest_route_user_reports     = rest_url() . 'download-monitor/v1/user_reports';
			$rest_route_user_data        = rest_url() . 'download-monitor/v1/user_data';
			$rest_route_templates        = rest_url() . 'download-monitor/v1/templates';
			// Let's add the global variable that will hold our reporst class and the routes
			wp_add_inline_script( 'dlm_reports', 'let dlmReportsInstance = {}; dlm_admin_url = "' . admin_url() . '" ; const dlmDownloadReportsAPI ="' . $rest_route_download_reports . '"; const dlmUserReportsAPI ="' . $rest_route_user_reports . '"; const dlmUserDataAPI ="' . $rest_route_user_data . '"; const dlmTemplates = "' . $rest_route_templates . '"; ', 'before' );
		}

		/**
		 * Register DLM Logs Routes
		 *
		 * @since 4.6.0
		 */
		public function register_routes() {

			// The REST route for downloads reports.
			register_rest_route(
				'download-monitor/v1',
				'/download_reports',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'rest_stats' ),
					'permission_callback' => '__return_true',
				)
			);

			// The REST route for user reports.
			register_rest_route(
				'download-monitor/v1',
				'/user_reports',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'user_reports_stats' ),
					'permission_callback' => '__return_true',
				)
			);

			// The REST route for users data.
			register_rest_route(
				'download-monitor/v1',
				'/user_data',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'user_data_stats' ),
					'permission_callback' => '__return_true',
				)
			);

			// The REST route for users data.
			register_rest_route(
				'download-monitor/v1',
				'/templates',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'reports_templates' ),
					'permission_callback' => '__return_true',
				)
			);
		}

		/**
		 * Get our stats for the chart
		 *
		 * @return WP_REST_Response
		 * @throws Exception
		 * @since 4.6.0
		 */
		public function rest_stats() {

			return $this->respond( $this->report_stats() );
		}

		/**
		 * Get our stats for the user reports
		 *
		 * @return WP_REST_Response
		 * @since 4.6.0
		 */
		public function user_reports_stats() {

			return $this->respond( $this->get_user_reports() );
		}


		/**
		 * Get our user data
		 *
		 * @return WP_REST_Response
		 * @since 4.6.0
		 */
		public function user_data_stats() {

			return $this->respond( $this->get_user_data() );
		}

		/**
		 * Get our user data
		 *
		 * @return WP_REST_Response
		 * @since 4.6.0
		 */
		public function reports_templates() {

			return $this->respond( $this->get_reports_templates() );
		}

		/**
		 * Send our data
		 *
		 * @param $data JSON data received from report_stats.
		 *
		 * @return WP_REST_Response
		 * @since 4.6.0
		 */
		public function respond( $data ) {

			$result = new \WP_REST_Response( $data, 200 );

			$result->set_headers(
				array(
					'Cache-Control' => 'max-age=3600, s-max-age=3600',
					'Content-Type' => 'application/json',
				)
			);

			return $result;
		}

		/**
		 * Return stats
		 *
		 * @retun array
		 * @since 4.6.0
		 */
		public function report_stats() {

			global $wpdb;

			if ( ! DLM_Logging::is_logging_enabled() || ! DLM_Utils::table_checker( $wpdb->dlm_reports ) ) {
				return array();
			}

			$cache_key = 'dlm_insights';
			$stats     = wp_cache_get( $cache_key, 'dlm_reports_page' );

			if ( ! $stats ) {
				$stats = $wpdb->get_results( "SELECT  * FROM {$wpdb->dlm_reports};", ARRAY_A );
				wp_cache_set( $cache_key, $stats, 'dlm_reports_page', 12 * HOUR_IN_SECONDS );
			}

			return $stats;
		}

		/**
		 * Return user reports stats
		 *
		 * @retun array
		 * @since 4.6.0
		 */
		public function get_user_reports() {

			global $wpdb;

			if ( ! DLM_Logging::is_logging_enabled() || ! DLM_Utils::table_checker( $wpdb->dlm_reports ) ) {
				return array();
			}

			$cache_key           = 'dlm_insights_users';
			$user_reports        = array();
			$offset              = isset( $_REQUEST['offset'] ) ? absint( sanitize_text_field( wp_unslash( $_REQUEST['offset'] ) ) ) : 0;
			$count               = isset( $_REQUEST['limit'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['limit'] ) ) : 10000;
			$offset_limit        = $offset * 10000;

			wp_cache_delete( $cache_key, 'dlm_user_reports' );
			$stats = wp_cache_get( $cache_key, 'dlm_user_reports' );
			if ( ! $stats ) {
				$downloads    = $wpdb->get_results( 'SELECT user_id, user_ip, download_id, download_date, download_status FROM ' . $wpdb->download_log . " ORDER BY ID desc LIMIT {$offset_limit}, {$count};", ARRAY_A );
				$user_reports = array(
					'logs'   => $downloads,
					'offset' => ( 10000 === count( $downloads ) ) ? $offset + 1 : '',
					'done'   => ( 10000 > count( $downloads ) ) ? true : false
				);
				wp_cache_set( $cache_key, $user_reports, 'dlm_reports_page', 12 * HOUR_IN_SECONDS );
			}

			return $user_reports;
		}

		/**
		 * Return user data
		 *
		 * @retun array
		 * @since 4.6.0
		 */
		public function get_user_data() {

			global $wpdb;

			if ( ! DLM_Logging::is_logging_enabled() || ! DLM_Utils::table_checker( $wpdb->dlm_reports ) ) {
				return array();
			}

			$cache_key = 'dlm_insights_users';
			$user_data = array();

			$stats = wp_cache_get( $cache_key, 'dlm_user_data' );
			if ( ! $stats ) {
				$users = get_users();
				foreach ( $users as $user ) {
					$user_data                    = $user->data;
					$users_data[ $user_data->ID ] = array(
						'id'           => $user_data->ID,
						'nicename'     => $user_data->user_nicename,
						'url'          => $user_data->user_url,
						'registered'   => $user_data->user_registered,
						'display_name' => $user_data->display_name,
						'role'         => ( ( ! in_array( 'administrator', $user->roles, true ) ) ? $user->roles : '' ),
					);
				}
				wp_cache_set( $cache_key, $user_data, 'dlm_user_data', 12 * HOUR_IN_SECONDS );
			}

			return $user_data;
		}

		/**
		 * Save reports settings
		 *
		 * @return void
		 * @since 4.6.0
		 */
		public function save_reports_settings() {

			if ( ! isset( $_POST['_ajax_nonce'] ) ) {
				wp_send_json_error( 'No nonce' );
			}

			check_ajax_referer( 'dlm_reports_nonce' );
			$option = ( isset( $_POST['name'] ) ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';

			if ( 'dlm_clear_api_cache' === $option ) {
				wp_cache_delete( 'dlm_insights', 'dlm_reports_page' );
				die();
			}

			if ( isset( $_POST['checked'] ) && 'true' === $_POST['checked'] ) {
				$value = 'on';
			} else {
				$value = 'off';
			}

			update_option( $option, $value );
			die();
		}

		/**
		 * Get top downloads HTML markup
		 *
		 * @return void
		 */
		public function get_top_downloads_markup( $offset = 0, $limit = 15) {
			global $wpdb;

			$downloads = $wpdb->get_results( 'SELECT COUNT(ID) as downloads, download_id, download_status FROM ' . $wpdb->download_log . " GROUP BY download_id ORDER BY downloads desc LIMIT  " . absint( $offset ) . " , " . absint( $limit ) . ";", ARRAY_A );

			ob_start();
			$dlm_top_downloads = $this->headers;
			include __DIR__ . '/components/top-downloads/top-downloads-table.php';
			$html = ob_get_clean();
			return $html;

		}

		/**
		 * Get top downloads HTML markup
		 *
		 * @return void
		 */
		public function get_ajax_top_downloads_markup( $offset = 0, $limit = 15 ) {

			if ( ! isset( $_POST['nonce'] ) ) {
				wp_send_json_error( 'No nonce' );
			}
			check_ajax_referer( 'dlm_reports_nonce', 'nonce' );

			global $wpdb;
			if ( isset( $_POST['offset'] ) && '' !== $_POST['offset'] ) {
				$offset = absint( $_POST['offset'] );
			}

			if ( isset( $_POST['limit'] ) && '' !== $_POST['limit'] ) {
				$limit = absint( $_POST['limit'] );
			}

			$downloads = $wpdb->get_results( 'SELECT COUNT(ID) as downloads, download_id, download_status FROM ' . $wpdb->download_log . " GROUP BY download_id ORDER BY downloads desc LIMIT  " . absint( $offset ) . " , " . absint( $limit ) . ";", ARRAY_A );

			ob_start();
			foreach ( $downloads as $log ) {
				// Get markup for each download.
				include __DIR__ . '/components/top-downloads/top-downloads-row.php';
			}
			$html    = ob_get_clean();
			$reponse = array(
				'html'   => $html,
				'loaded' => count( $downloads )
			);
			wp_send_json( $reponse );
		}

		/**
		 * Get total downloads, including failed ones
		 *
		 * @return void
		 * @since 4.6.0
		 */
		public function get_total_logs_count() {
			global $wpdb;
			$count = $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->download_log};" );

			return $count;
		}

		/**
		 * The HTML for the reports templates
		 *
		 * @return string
		 */
		public function get_reports_templates() {
			ob_start();
			$dlm_top_downloads = $this->headers;
			include __DIR__ . '/components/js-downloads/top-downloads-header-js.php';
			$header = ob_get_clean();

			ob_start();
		}
	}
}
