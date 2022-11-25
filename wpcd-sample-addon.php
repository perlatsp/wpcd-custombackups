<?php
/**
 * Plugin Name: WPCD-Custom-Backup
 * Plugin URI: https://wpclouddeploy.com
 * Description: #
 * Version: 1.0.0
 * Author: #
 * Author URI: #
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';

/**
 * Bootstrap class for this  plugin.
 */
class WPCD_Custom_BackUp {

	/**
	 *  Constructor function of course.
	 */
	public function __construct() {
		$plugin_data = get_plugin_data( __FILE__ );

		if ( ! defined( 'WPCDCUSTOMBACKUP_URL' ) ) {
			define( 'WPCDCUSTOMBACKUP_URL', plugin_dir_url( __FILE__ ) );
			define( 'WPCDCUSTOMBACKUP_PATH', plugin_dir_path( __FILE__ ) );
			define( 'WPCDCUSTOMBACKUP_PLUGIN', plugin_basename( __FILE__ ) );
			define( 'WPCDCUSTOMBACKUP_EXTENSION', $plugin_data['Name'] );
			define( 'WPCDCUSTOMBACKUP_VERSION', $plugin_data['Version'] );
			define( 'WPCDCUSTOMBACKUP_TEXTDOMAIN', 'wpcd' );
			define( 'WPCDAMPLE_REQUIRES', '2.0.3' );
		}

		/* Run things after WordPress is loaded */
		add_action( 'init', array( $this, 'required_files' ), -20 );

		/* Insert wpapp tabs where they need to go */
		add_action( 'wpcd_wpapp_include_app_tabs', array( $this, 'required_wpapp_tab_files' ) );

	}

	/**
	 * Include additional files as needed
	 *
	 * Action Hook: init
	 */
	public function required_files() {

	}

	/**
	 * Insert tabs on the app detail screen
	 *
	 * Action Hook: wpcd_wpapp_include_app_tabs
	 */
	public function required_wpapp_tab_files() {
		include_once WPCDCUSTOMBACKUP_PATH . '/includes/wpapp-tabs-custombackup.php';
	}

	/**
	 * Placeholder activation function.
	 *
	 * @TODO: You can hook into this function with a WP filter
	 * if you need to do things when the plugin is activated.
	 * Right now nothing in this gets executed.
	 */
	public function activation_hook() {
		// first install.
		$version = get_option( 'wpcdcustombackup_version' );
		if ( ! $version ) {
			update_option( 'wpcdcustombackup_last_version_upgrade', WPCDCUSTOMBACKUP_VERSION );
		}

		if ( WPCDCUSTOMBACKUP_VERSION !== $version ) {
			update_option( 'wpcd_version', WPCDCUSTOMBACKUP_VERSION );
		}

		// Some setup options here?
	}
}

/**
 * Bootstrap the class
 */
if ( class_exists( 'WPCD_Init' ) ) {
	$wpcdcustombackup = new WPCD_Custom_BackUp();
}



class WPCD_Custom_BackUp_Settings {

	public $options = [];
	public static $instance;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct(){

		$this->options = get_option( 'wpcd_custombackup' );

		add_action( 'admin_menu', [ $this, '_custombackup_options_page' ] );
		add_action( 'admin_init', [ $this, '_custombackup_settings' ] );
	}

	public function _custombackup_options_page(){
		add_options_page( 'Backup Options', 'Backup Options', 'manage_options', 'wpcd-custombackup', [$this,'render_wpcdcustombackupoptions'] );
	}

	public static function get_options(){
		$options =  get_option( 'wpcd_custombackup' );
		return self::$instance->prepare_options( $options );
	}

	public function prepare_options( $options ){
		if( empty( $options ) ){
			$options = [
				'ssh_host' => "",
				'ssh_user' => "",
				'ssh_password' =>"",
				'ssh_port' => "",
				'ssh_remote_location' => "",
			];
		}
		$options['ssh_password'] = $this->decrypt_data( $options['ssh_password'] );
		return $options;
	}
	public function _custombackup_settings(){

		register_setting( 'custombackup', 'wpcd_custombackup', [$this,'validate_backup_settings'] );

		add_settings_section(
			'wpcd_custombackup',
			'WPCD Custom Backup options',
			[$this,'wpcd_custombackup_section_callback'],
			'custombackup'
		);

		add_settings_field(
			'ssh_host',
			'SSH Host',
			[ $this,'ssh_host_render'],
			'custombackup',
			'wpcd_custombackup'
		);
		add_settings_field(
			'ssh_user',
			'SSH User',
			[ $this,'ssh_user_render'],
			'custombackup',
			'wpcd_custombackup'
		);
		add_settings_field(
			'ssh_password',
			'SSH Password',
			[ $this,'ssh_password_render'],
			'custombackup',
			'wpcd_custombackup'
		);
		add_settings_field(
			'ssh_port',
			'SSH Port',
			[ $this,'ssh_port_render' ],
			'custombackup',
			'wpcd_custombackup'
		);
		add_settings_field(
			'ssh_remote_location',
			'SSH Remote Location',
			[ $this,'ssh_remote_location_render' ],
			'custombackup',
			'wpcd_custombackup'
		);

	}
	public function encrypt_data( $data ){
		$data = WP_CLOUD_DEPLOY::encrypt( $data );
		return $data;
	}

	public function decrypt_data( $data ){
		$data = WP_CLOUD_DEPLOY::decrypt( $data );
		return $data;
	}

	public function validate_backup_settings( $data ){
		if( $data['ssh_password'] ){
			// if(  )
			$data['ssh_password'] = $this->encrypt_data( $data['ssh_password'] );
		}
		return $data;
	}

	public function ssh_host_render(  ) {
		?>
		<input type='text' name='wpcd_custombackup[ssh_host]' value='<?php echo $this->options['ssh_host']; ?>'>
		<?php
	}
	public function ssh_user_render(  ) {
		?>
		<input type='text' name='wpcd_custombackup[ssh_user]' value='<?php echo $this->options['ssh_user']; ?>'>
		<?php
	}
	public function ssh_password_render(  ) {
		$password = $this->options['ssh_password'];
		if( $password ){
			$password = $this->decrypt_data( $password );
		}
		?>
		<input type='text' name='wpcd_custombackup[ssh_password]' value='<?php echo $password;?>'>
		<?php
	}

	public function ssh_port_render(  ) {
		?>
		<input type='number' min="0" name='wpcd_custombackup[ssh_port]' value='<?php echo $this->options['ssh_port']; ?>'>
		<?php
	}

	public function ssh_remote_location_render(  ) {
		$remote_location = $this->options['ssh_remote_location'] ?? "/home/backups/";
		?>
		<input type='text' name='wpcd_custombackup[ssh_remote_location]' value='<?php echo $remote_location;?>'>
		<?php
	}

	public function render_wpcdcustombackupoptions(  ) {

		?>
		<form action='options.php' method='post'>
			<?php
			settings_fields( 'custombackup' );
			do_settings_sections( 'custombackup' );
			submit_button();
			?>

		</form>
		<?php
	}

	public function wpcd_custombackup_section_callback(){
	}

}

$d = WPCD_Custom_BackUp_Settings::instance();
