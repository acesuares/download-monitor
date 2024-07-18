<?php
/**
 * Extensions Page
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

use \WPChill\DownloadMonitor\Util;

/**
 * DLM_Admin_Extensions Class
 */
class DLM_Admin_Extensions {

	/**
	 * Holds the class object.
	 *
	 * @since 4.4.5
	 *
	 * @var object
	 */
	public static $instance;

	/**
	 * Json response
	 *
	 * @var
	 *
	 * @since 4.4.5
	 */
	private $response;

	/**
	 * DLM's extensions
	 *
	 * @var array
	 *
	 * @since 4.4.5
	 */
	private $extensions = array();

	// @todo: Maybe gather extensions from the API?
	public $free_extensions = array();

	public $pro_extensions = array();

	/**
	 * DLM's extensions tabs
	 *
	 * @var array
	 *
	 * @since 4.4.5
	 */
	private $tabs = array();

	/**
	 * DLM's installed extensions
	 *
	 * @var array
	 *
	 * @since 4.4.5
	 */
	public $installed_extensions = array();

	/**
	 * DLM Licensed extensions
	 *
	 * @var array
	 * @since 4.7.4
	 */
	private $licensed_extensions = array();

	/**
	 * Json
	 *
	 * @var mixed|string
	 *
	 * @since 4.4.5
	 */
	private $json;

	/**
	 * Our Products
	 *
	 * @var
	 *
	 * @since 4.4.5
	 */
	private $products;


	public function __construct() {
		// Add the extensions menu links
		add_filter( 'dlm_admin_menu_links', array( $this, 'extensions_pages' ), 30 );

		// Remove not needed menu link from appearing in dashboard
		add_filter( 'submenu_file', array( $this, 'remove_submenu_item' ) );

		// Load our required data
		add_action( 'admin_init', array( $this, 'load_data' ), 15 );
	}

	/**
	 * Loads required data and sets tabs
	 *
	 * @since 4.4.5
	 */
	public function load_data() {
		if ( ! DLM_Admin_Helper::is_dlm_admin_page() ) {
			return;
		}

		$loader     = new Util\ExtensionLoader();
		$this->json = $loader->fetch();

		$this->products = DLM_Product_Manager::get()->get_products();

		// Set the extensions
		$this->set_response();

		$this->set_tabs();

		$this->set_licensed_extensions();
	}

	/**
	 * Returns the singleton instance of the class.
	 *
	 * @return object The DLM_Admin_Extensions object.
	 * @since 4.4.5
	 *
	 */
	public static function get_instance() {
		if ( ! isset( DLM_Admin_Extensions::$instance ) && ! ( DLM_Admin_Extensions::$instance instanceof DLM_Admin_Extensions ) ) {
			DLM_Admin_Extensions::$instance = new DLM_Admin_Extensions();
		}

		return DLM_Admin_Extensions::$instance;
	}

	/**
	 * Add extensions menu links
	 *
	 * @param $links
	 *
	 * @return mixed
	 */
	public function extensions_pages( $links ) {
		$links[] = array(
			'page_title' => __( 'Download Monitor Extensions', 'download-monitor' ),
			'menu_title' => '<span style="color:#419CCB;font-weight:bold;">' . __( 'Extensions', 'download-monitor' ) . '</span>',
			'capability' => 'manage_options',
			'menu_slug'  => 'dlm-extensions',
			'function'   => array( $this, 'available_extensions' ),
			'priority'   => 50,
		);

		return $links;
	}

	/**
	 * Remove the submenus we don't want to show
	 *
	 * @param $submenu_file
	 *
	 * @return mixed
	 *
	 * @since 4.4.5
	 */
	public function remove_submenu_item( $submenu_file ) {
		remove_submenu_page( 'edit.php?post_type=dlm_download', 'dlm-installed-extensions' );

		return $submenu_file;
	}


	/**
	 * Set DLM's extensions
	 *
	 * @since 4.4.5
	 */
	public function set_response() {
		// Check and see if the connection to the server has failed or not.
		if ( is_array( $this->json ) && isset( $this->json['success'] ) && ! $this->json['success'] ) {
			return;
		}

		$this->response = json_decode( $this->json );

		if ( ! isset( $this->response ) ) {
			return;
		}

		// Get all extensions
		$this->extensions = $this->response->extensions;
		// Get all pro extensions
		$this->pro_extensions = $this->get_extensions_package();

		// Loop through extensions
		foreach ( $this->extensions as $extension_key => $extension ) {
			if ( isset( $extension->free_extension ) && $extension->free_extension ) {
				unset( $this->extensions[ $extension_key ] );
				$this->free_extensions[] = $extension;
				continue;
			}
			// Remove the legacy importer from the extensions list. We don't want to show it.
			if ( 'dlm-legacy-importer' === $extension->product_id ) {
				unset( $this->extensions[ $extension_key ] );
			}
			// Remove the Terms and Conditions extension from the extensions list, as from 5.0.0 it will be included in the core.
			if ( 'dlm-terms-and-conditions' === $extension->product_id ) {
				unset( $this->extensions[ $extension_key ] );
			}
		}
	}


	/**
	 * Output DLM's extensions page
	 *
	 * @since 4.4.5
	 */
	public function available_extensions() {
		// Allow user to reload extensions
		if ( isset( $_GET['dlm-force-recheck'] ) ) {
			delete_transient( 'dlm_extension_json' );
			delete_transient( 'dlm_extension_json_error' );
			delete_transient( 'dlm_pro_extensions' );
		}

		// WPChill Welcome Class
		require_once plugin_dir_path( DLM_PLUGIN_FILE ) . '/includes/submodules/banner/class-wpchill-welcome.php';

		if ( ! class_exists( 'WPChill_Welcome' ) ) {
			return;
		}

		$welcome = WPChill_Welcome::get_instance();

		?>
		<div class="wrap dlm_extensions_wrap">
			<div class="icon32 icon32-posts-dlm_download" id="icon-edit">
				<br/>
			</div>
			<h1>
				<?php
				echo esc_html__( 'Download Monitor Extensions', 'download-monitor' ); ?>
			</h1>
			<?php

			if ( false !== $this->json ) {
				// Display message if it's there
				if ( isset( $this->response->message ) && '' !== $this->response->message ) {
					echo '<div id="message" class="updated">' . esc_html( $this->response->message ) . '</div>';
				}

				// Extensions
				$active_tab = 'dlm-extensions';

				if ( isset( $_GET['page'] ) && isset( $tabs[ $_GET['page'] ] ) ) {
					$active_tab = sanitize_text_field( wp_unslash( $_GET['page'] ) );
				}

				?>
				<h2 class="nav-tab-wrapper">
					<?php
					DLM_Admin_Helper::dlm_tab_navigation( $this->tabs, $active_tab ); ?>
				</h2>
				<a href="<?php
				echo esc_url( add_query_arg( 'dlm-force-recheck', '1', admin_url( 'edit.php?post_type=dlm_download&page=dlm-extensions' ) ) ); ?>"
				   class="button dlm-reload-button">
					<?php
					esc_html_e( 'Reload Extensions', 'download-monitor' ); ?>
				</a>
				<?php
				// Check and see if the connection to the server has failed or not.
				if ( is_array( $this->json ) && isset( $this->json['success'] ) && ! $this->json['success'] ) {
					echo $this->json['message'];
				}
				// Available Extensions
				if ( count( $this->extensions ) > 0 ) {
					echo '<div id="available-extensions" class="settings_panel">';
					echo '<div class="dlm_extensions">';
					?>
					<div id="wpchill-welcome">
						<div class="features">
							<div class="block">
								<div class='wp-clearfix'>
									<ul class='subsubsub dlm-settings-sub-nav dlm-extension-filtering'>
										<li class='active-section'>
											<a id='all-extensions'><?php
												esc_html_e( 'All', 'download-monitor' ); ?></a>
										</li>
										<li>
											<a id='pro-extensions'><?php
												esc_html_e( 'Premium', 'download-monitor' ); ?></a>
										</li>
										<li>
											<a id='free-extensions'><?php
												esc_html_e( 'Free', 'download-monitor' ); ?></a>
										</li>
									</ul>
								</div>
								<?php
								$welcome->layout_start( 4, 'feature-list' ); ?>
								<!-- Let's display the extensions.  -->
								<?php
								// Cycle through the PRO extensions
								foreach ( $this->extensions as $extension ) {
									$welcome->display_extension( $extension->name, wp_kses_post( $extension->desc ), $extension->image, true, '#F08232', $extension->name );
								}

								foreach ( $this->free_extensions as $key => $extension ) {
									$action       = 'install';
									$activate_url = '#';
									$disabled     = false;
									$text         = esc_html__( 'Install', 'download-monitor' );
									$plugin_path  = $extension->dir . '/' . $extension->slug;
									// We use the extension dir for WP repository plugins because of the way the plugin
									// is named in the repository and the way the main file is named.
									$wp_org_path = $extension->dir;
									$label_text  = esc_html__( 'free', 'download-monitor' );
									$badge       = 'free';
									if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_path ) ) {
										$action       = 'activate';
										$text         = esc_html__( 'Activate', 'download-monitor' );
										$label_text   = esc_html__( 'installed', 'download-monitor' );
										$badge        = 'installed';
										$activate_url = add_query_arg(
											array(
												'action'        => 'activate',
												'plugin'        => rawurlencode( $plugin_path ),
												'plugin_status' => 'all',
												'paged'         => '1',
												'_wpnonce'      => wp_create_nonce( 'activate-plugin_' . $plugin_path ),
											),
											admin_url( 'plugins.php' )
										);
									}

									if ( is_plugin_active( $plugin_path ) ) {
										$action     = 'installed';
										$badge      = 'active';
										$label_text = esc_html__( 'active', 'download-monitor' );
										$disabled   = true;
										$text       = esc_html__( 'Installed & Activated', 'download-monitor' );
									}

									echo '<div class="feature-block free-extension">';
									echo '<div class="feature-block__header">';
									echo '<img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTA1IiBoZWlnaHQ9IjEwNSIgdmlld0JveD0iMCAwIDEwNSAxMDUiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxwYXRoIGQ9Ik01Mi41IDAuMDAwNTk5Njc0QzM4LjU3NTYgMC4wMDA1OTk2NzQgMjUuMjIxOSA1LjUzMjAzIDE1LjM3NzYgMTUuMzc4MUM1LjUzMTQ2IDI1LjIyMjkgMCAzOC41NzY2IDAgNTIuNTAwM0MwIDY2LjQyNCA1LjUzMTQ2IDc5Ljc3ODMgMTUuMzc3NiA4OS42MjI1QzI1LjIyMjUgOTkuNDY4NiAzOC41NzYyIDEwNSA1Mi41IDEwNUM2Ni40MjM4IDEwNSA3OS43NzgxIDk5LjQ2ODYgODkuNjIyNCA4OS42MjI1Qzk5LjQ2ODUgNzkuNzc3NyAxMDUgNjYuNDI0IDEwNSA1Mi41MDAzQzEwNSA0My4yODQ1IDEwMi41NzQgMzQuMjMwOCA5Ny45NjY0IDI2LjI1MDJDOTMuMzU4NyAxOC4yNjk1IDg2LjczMDQgMTEuNjQxNiA3OC43NDk3IDcuMDMzNTRDNzAuNzY5IDIuNDI1ODEgNjEuNzE1MiAwIDUyLjQ5OTQgMEw1Mi41IDAuMDAwNTk5Njc0Wk00MC40Nzc3IDM4LjI3MThMNDcuMjQ5OSA0NS4wOTY5VjI2LjI0OTZINTcuNzUwMVY0NS4wOTY5TDY0LjUyMjMgMzguMzI0Nkw3MS45MjUyIDQ1LjcyNzVMNTIuNSA2NS4xNTI2TDMzLjAyMiA0NS42NzQ3TDQwLjQ3NzcgMzguMjcxOFpNNzguNzQ5MSA3OC43NTExSDI2LjI0ODVWNjguMjUxSDc4Ljc0OTFWNzguNzUxMVoiIGZpbGw9InVybCgjcGFpbnQwX2xpbmVhcl8zN184NSkiLz4KPGRlZnM+CjxsaW5lYXJHcmFkaWVudCBpZD0icGFpbnQwX2xpbmVhcl8zN184NSIgeDE9Ii0zNy41MjkzIiB5MT0iMS4wOTMzNGUtMDYiIHgyPSI5NS45NzY2IiB5Mj0iMTA3Ljg3MSIgZ3JhZGllbnRVbml0cz0idXNlclNwYWNlT25Vc2UiPgo8c3RvcCBvZmZzZXQ9IjAuMTEwMTEzIiBzdG9wLWNvbG9yPSIjNURERUZCIi8+CjxzdG9wIG9mZnNldD0iMC40NDM1NjgiIHN0b3AtY29sb3I9IiM0MTlCQ0EiLz4KPHN0b3Agb2Zmc2V0PSIwLjYzNjEyMiIgc3RvcC1jb2xvcj0iIzAwOENENSIvPgo8c3RvcCBvZmZzZXQ9IjAuODU1OTk3IiBzdG9wLWNvbG9yPSIjMDI1RUEwIi8+CjxzdG9wIG9mZnNldD0iMSIgc3RvcC1jb2xvcj0iIzAyNTM4RCIvPgo8L2xpbmVhckdyYWRpZW50Pgo8L2RlZnM+Cjwvc3ZnPgo=" style="max-height: 30px;">';
									echo '<h5>' . esc_html( $extension->name ) . '<div class="pro-label ' . esc_attr( $badge ) . '">' . esc_html( $label_text ) . '</div></h5>';
									echo '</div>';
									echo '<p>' . wp_kses_post( $extension->desc ) . '</p>';
									echo '<div class="dlm-install-plugin-actions"><a class="dlm-install-plugin-link button button-primary" data-activation_url="' . esc_url( $activate_url ) . '" data-action="' . esc_attr( $action ) . '" data-slug="' . esc_attr( $wp_org_path ) . '" href="#" ' . ( $disabled ? 'disabled="disabled"' : '' ) . '>' . esc_html( $text ) . '</a></div>';
									echo '</div>';
								}
								?><!-- end extensions display -->
								<?php
								$welcome->layout_end(); ?>
							</div><!-- .block -->
						</div><!-- .features -->
					</div><!-- #wpchill-welcome -->
					<?php
					echo '</div>';
					echo '</div>';
				} elseif ( count( $this->installed_extensions ) > 0 ) {
					echo '<p>' . esc_html__( 'Wow, looks like you installed all our extensions. Thanks, you rock!', 'download-monitor' ) . '</p>';
				}
			} else {
				echo '<p>' . esc_html__( 'Couldn\'t load extensions, please try again later.', 'download-monitor' ) . '</p>';
			}
			?>
		</div>
		<?php
	}

	/**
	 * Set DLM's extensions tabs
	 *
	 * @since 4.4.5
	 */
	public function set_tabs() {
		$tabs = array(
			'dlm_downloads'   => array(
				'name'     => esc_html__( 'Downloads', 'download-monitor' ),
				'url'      => admin_url( 'edit.php?post_type=dlm_download' ),
				'target'   => '',
				'priority' => '1',
			),
			'dlm-extensions'  => array(
				'name'     => esc_html__( 'Extensions', 'download-monitor' ),
				'url'      => admin_url( 'edit.php?post_type=dlm_download&page=dlm-extensions' ),
				'target'   => '',
				'priority' => '10',
			),
			'suggest_feature' => array(
				'name'     => esc_html__( 'Suggest a feature', 'download-monitor' ),
				'url'      => 'https://forms.gle/3igARBBzrbp6M8Fc7',
				'icon'     => 'dashicons dashicons-external',
				'target'   => '_blank',
				'priority' => '90',
			),
		);

		/**
		 * Hook for Extension tabs
		 */
		$this->tabs = apply_filters( 'dlm_settings_tabs', $tabs );

		// Sort tabs based on priority.
		uasort( $this->tabs, array( 'DLM_Admin_Helper', 'sort_data_by_priority' ) );
	}

	/**
	 * Get DLM's extensions tabs
	 *
	 * @return array
	 *
	 * @since 4.4.5
	 */
	public function get_tabs() {
		return $this->tabs;
	}

	/**
	 * Get extensions
	 *
	 * @return array
	 *
	 * @since 4.4.5
	 */
	public function get_extensions() {
		return $this->installed_extensions;
	}

	/**
	 * Get the available extensions
	 *
	 * @since 4.5.8
	 */
	public function get_available_extensions() {
		return $this->extensions;
	}

	/**
	 * Get extensions
	 *
	 * @return array
	 *
	 * @since 4.4.5
	 */
	public function get_products() {
		return $this->products;
	}

	/**
	 * Get the installed extensions
	 *
	 * @return array
	 */
	public function get_installed_extensions() {
		return $this->installed_extensions;
	}

	/**
	 * Set the licensed extensions
	 *
	 * @return void
	 * @since 4.7.4
	 */
	public function set_licensed_extensions() {
		global $wpdb;

		if ( ! DLM_Admin_Helper::is_dlm_admin_page() ) {
			return;
		}
		// Let's retrieve extensions that have a license key.
		$extensions = $wpdb->get_results( $wpdb->prepare( "SELECT `option_name`, `option_value` FROM {$wpdb->prefix}options WHERE `option_name` LIKE %s AND `option_name` LIKE %s;", $wpdb->esc_like( 'dlm-' ) . '%', '%' . $wpdb->esc_like( '-license' ) ), ARRAY_A );

		foreach ( $extensions as $extension ) {
			$extension_name = str_replace( '-license', '', $extension['option_name'] );
			$value          = unserialize( $extension['option_value'] );
			// Extension must have an active status in order to be regitered.
			if ( isset( $value['status'] ) && 'active' === $value['status'] ) {
				$this->licensed_extensions[] = $extension_name;
			}
		}
	}

	public function get_extensions_package() {
		if ( false !== $extensions = get_transient( 'dlm_pro_extensions' ) ) {
			return $extensions;
		}

		$license_data = get_option( 'dlm_master_license', false );

		if ( ! $license_data ) {
			return array();
		}

		$license_data = json_decode( $license_data, true );

		if ( ! isset( $license_data['license_key'] ) ) {
			return array();
		}

		$store_url   = DLM_Product::STORE_URL . '?wc-api=';
		$api_request = wp_remote_get(
			$store_url . DLM_Product::ENDPOINT_GET_PACKAGES . '&' . http_build_query(
				array(
					'license_key' => $license_data['license_key'],
				),
				'',
				'&'
			),
			array( 'timeout' => 120 )
		);

		// Check request.
		if ( is_wp_error( $api_request ) || wp_remote_retrieve_response_code( $api_request ) != 200 ) {
			return array();
		}

		$pro_extensions = json_decode( $api_request['body'] );

		set_transient( 'dlm_pro_extensions', $pro_extensions, 14 * DAY_IN_SECONDS );

		return $pro_extensions;
	}

	/**
	 * Check if the extension is active.
	 *
	 * @retun bool
	 *
	 * @since 5.0.0
	 */
	public function is_active( $plugin_path ) {
		$active  = false;
		$plugins = get_option( 'active_plugins' );
		foreach ( $plugins as $plugin ) {
			if ( $plugin === $plugin_path ) {
				$active = true;
				break;
			}
		}

		return $active;
	}

	/**
	 * Check if the extension is installed.
	 *
	 * @retun bool
	 *
	 * @since 5.0.0
	 */
	public function is_installed( $plugin_path ) {
		$installed   = false;
		$plugin_path = WP_PLUGIN_DIR . '/' . $plugin_path;
		if ( file_exists( $plugin_path ) ) {
			$installed = true;
		}

		return $installed;
	}

	/**
	 * Get the licensed extensions
	 *
	 * @return array
	 * @since 4.7.4
	 */
	public function get_licensed_extensions() {
		return $this->licensed_extensions;
	}

	/**
	 * Get DLM's json
	 *
	 * @return array
	 *
	 * @since 5.0.0
	 */
	public function get_json() {
		return $this->json;
	}

	/**
	 * Get DLM's response
	 *
	 * @return array
	 *
	 * @since 5.0.0
	 */
	public function get_response() {
		return $this->response;
	}

	/**
	 * Get DLM's free extensions
	 *
	 * @return array
	 *
	 * @since 5.0.0
	 */
	public function get_free_extensions() {
		return $this->free_extensions;
	}
}
