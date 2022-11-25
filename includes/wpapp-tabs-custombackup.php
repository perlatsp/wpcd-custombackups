<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPCD_WordPress_TABS_APP_CUSTOMBACKUP extends WPCD_WORDPRESS_TABS {

	public $options = false;
	public function __construct() {

		parent::__construct();

		$this->options = WPCD_Custom_BackUp_Settings::get_options();

		$this->SSH_HOST = "CHANGEME";
		$this->SSH_USER = "CHANGEME";
		$this->SSH_PORT = "CHANGEME";
		$this->SSH_PASSWORD = "CHANGEME";

		$this->SSH_REMOTE_LOCATION = "/share/homes/perlat";

		if( !empty( $this->options['ssh_host'] ) ){
			$this->SSH_HOST = $this->options['ssh_host'];
		}
		if( !empty( $this->options['ssh_user'] ) ){
			$this->SSH_USER = $this->options['ssh_user'];
		}
		if( !empty( $this->options['ssh_password'] ) ){
			$this->SSH_PASSWORD = $this->options['ssh_password'];
		}

		if( !empty( $this->options['ssh_port'] ) ){
			$this->SSH_PORT = $this->options['ssh_port'];
		}
		if( !empty( $this->options['ssh_remote_location'] ) ){
			$this->SSH_REMOTE_LOCATION = $this->options['ssh_remote_location'];
		}


		$this->set_scripts_folder( dirname( __FILE__ ) . '/scripts/' );
		$this->set_scripts_folder_relative( '/scripts/' );

		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabnames", array( $this, 'get_tab' ), 10, 1 );
		add_filter( "wpcd_app_{$this->get_app_name()}_get_tabs", array( $this, 'get_tab_fields_custombackup' ), 10, 2 );
		add_filter( "wpcd_app_{$this->get_app_name()}_tab_action", array( $this, 'tab_action_custombackup' ), 10, 3 );

		add_action( "wpcd_command_{$this->get_app_name()}_completed", array( $this, 'command_completed_custombackup' ), 10, 2 );

		add_filter( 'wpcd_script_file_name', array( $this, 'wpcd_script_file_name' ), 10, 2 );
		add_filter( 'wpcd_wpapp_replace_script_tokens', array( $this, 'wpcd_wpapp_replace_script_tokens' ), 10, 7 );
	}

	public function command_completed_custombackup( $id, $name ) {

		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_status" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action" );
		delete_post_meta( $id, "wpcd_app_{$this->get_app_name()}_action_args" );
	}

	public function get_tab( $tabs ) {
		$tabs['custombackup'] = array(
			'label' => __( 'Offsite Backup', 'wpcd' ),
		);
		return $tabs;
	}


	public function get_tab_fields_custombackup( array $fields, $id ) {
		return $this->get_fields_for_tab( $fields, $id, 'custombackup' );
	}


	public function handle_compress_toggle($id,$action){
		if( $this->should_compress_backup( $id ) ){
			update_post_meta( $id, 'offsite_backup_compression_enabled', 'no' );
		}else{
			update_post_meta( $id, 'offsite_backup_compression_enabled', 'yes' );
		}
	}
	public function tab_action_custombackup( $result, $action, $id ) {

		switch ( $action ) {
			case 'compress-files':
				$this->handle_compress_toggle($id,$action);
				$result['refresh'] = false;
				break;
			case 'backup-db':
				$result = $this->backup_db( $id, $action );
				break;
			case 'backup-files':
				$result = $this->backup_files( $id, $action );
				$result['refresh']= true;
				break;
			case 'backup-db-files':
				$result = $this->backup_db_files( $id, $action );
				break;
		}

		return $result;
	}

	public function get_actions( $id ) {
		return $this->get_server_fields_custombackup( $id );
	}

	public function should_compress_backup( $id ){
		$value = $this->get_meta_value( $id, 'offsite_backup_compression_enabled', 'no' );
		return $value == 'yes';
	}

	private function get_meta_value( $id, $meta_name, $default_value = 'off' ) {
		$value = get_post_meta( $id, $meta_name, true );
		if ( empty( $value ) ) {
			$value = $default_value;
		}
		return $value;
	}

	private function get_server_fields_custombackup( $id ) {

		$actions = array();

		$last_db_backup = $this->get_meta_value( $id,'site_last_db_backup',false);
		$last_files_backup = $this->get_meta_value( $id,'site_last_files_backup',false);
		$last_db_files_backup = $this->get_meta_value( $id,'site_last_db_files_backup',false);

		$actions['custombackup-add-on-heading'] = array(
			'label'          => __( 'WP Backups', 'wpcd' ),
			'type'           => 'heading',
			'raw_attributes' => array(
				'desc' => 'Backups are done offsite<br>',
			),
		);

		$actions['compress-files']= [
			'name'       => __( 'Compress Backup Files', 'wpcd' ),
			'tab'        => 'custombackup',
			'type'       => 'switch',
			'raw_attributes' => array(
				'on_label'   => __( 'Yes', 'wpcd' ),
				'off_label'  => __( 'No', 'wpcd' ),
				'std'                 =>  $this->should_compress_backup( $id ) ? true : false,
				'desc'                => "Enable/disable backup compression",
				'confirmation_prompt' => "Are you sure you want to enable Back up files Compression?",
				'save_field' => true,
			),
			'save_field' => true,
		];

		$actions['backup-db'] = array(
			'label'          => __( 'Backup DB', 'wpcd' ),
			'raw_attributes' => array(
				'std'                 => __( 'Backup DB', 'wpcd' ),
				'desc'                => __( 'Backup DB on the site', 'wpcd' ),
				'data-wpcd-fields'    => wp_json_encode( array( '#wpcd_app_action_custombackup-action-field-01' ) ),
				'confirmation_prompt' => __( 'This is going to backup DB only? previous backup will be removed', 'wpcd' ),
				'log_console'         => false,
				'console_message'     => __( 'Preparing to start...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'type'           => 'button',
		);

		if( !empty( $last_db_backup ) ){
			$actions['last-db-backup'] = array(
				'name'=>'',
				'tab'  => 'custombackup',
				'type'=> 'custom_html',
				'raw_attributes' => array(
					'std' => 'Last DB Backup:<strong>'.date('d/m/Y H:i:s',$last_db_backup).'</strong>',
				)
			);
		}



		$instance = $this->get_app_instance_details( $id );

		$domain = $this->get_domain_name( $id );


		$actions['backup-files'] = array(
			'label'          => __( 'Backup Files', 'wpcd' ),
			'raw_attributes' => array(
				'std'                 => __( 'Backup Files', 'wpcd' ),
				'desc'                => __( 'Backup Files on the site ', 'wpcd' ),
				'data-wpcd-fields'    => wp_json_encode( array( '#wpcd_app_action_custombackup-action-field-01' ) ),
				'confirmation_prompt' => __( 'This is going to backup files only?', 'wpcd' ),
				'log_console'         => false,
				'console_message'     => __( 'Preparing to start...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'type'           => 'button',
		);

		if( !empty( $last_files_backup ) ){
			$actions['last-files-backup'] = array(
				'name'=>'',
				'tab'  => 'custombackup',
				'type'=> 'custom_html',
				'raw_attributes' => array(
					'std' => 'Last Files Backup: <strong>'.date('d/m/Y H:i:s',$last_files_backup).'</strong>',
				)
			);
		}


		$actions['backup-db-files'] = array(
			'label'          => __( 'Backup DB+Files', 'wpcd' ),
			'raw_attributes' => array(
				'std'                 => __( 'Backup DB+Files', 'wpcd' ),
				'desc'                => __( 'Backup DB+Files to a local file on the server', 'wpcd' ),
				'data-wpcd-fields'    => wp_json_encode( array( '#wpcd_app_action_custombackup-action-field-01' ) ),
				'confirmation_prompt' => __( 'This is going to backup DB+Files?', 'wpcd' ),
				'log_console'         => false,
				'console_message'     => __( 'Preparing to start...<br /> Please DO NOT EXIT this screen until you see a popup message indicating that the operation has completed or has errored.<br />This terminal should refresh every 60-90 seconds with updated progress information from the server. <br /> After the operation is complete the entire log can be viewed in the COMMAND LOG screen.', 'wpcd' ),
			),
			'type'           => 'button',
		);

		if( !empty( $last_db_files_backup ) ){
			$actions['last-db-files-backup'] = array(
				'name'=>'',
				'tab'  => 'custombackup',
				'type'=> 'custom_html',
				'raw_attributes' => array(
					'std' => 'Last DB & Files Backup:<strong>'.date('d/m/Y H:i:s',$last_db_files_backup).'</strong>',
				)
			);
		}

		return $actions;
	}


	private function backup_db( $id, $action ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = wp_parse_args( sanitize_text_field( wp_unslash( $_POST['params'] ) ) );
		$domain = $this->get_domain_name( $id );

		$filename = "db-".date('d_m_Y_H_i_s').".sql";
		$command  = sprintf( '%s---%s---%d', $action, $domain, gmdate( 'B' ) );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		$server_name = $this->get_server_name( $id );

		if( empty( $server_name ) ){
			$server_name = "Other";
		}

		$_remote_location = $this->SSH_REMOTE_LOCATION."/$server_name/$domain/".date('d_m_Y');

		$compressed = $this->should_compress_backup( $id ) ? "true" : "false";

		$run_cmd = $this->turn_script_into_command(
			$instance,
			'backup-db.txt',
			array_merge(
				$args,
				array(
					'command'  => $command,
					'action'   => $action,
					'domain'   => $domain,
					'sshhost'  => $this->SSH_HOST,
					'sshuser'  => $this->SSH_USER,
					'sshpass'  => $this->SSH_PASSWORD,
					'sshport'  => "$this->SSH_PORT",
					'filename' => $filename,
					'compressed' => $compressed,
					'remotelocation'=>$_remote_location,
				)
			)
		);

		update_post_meta( $id, 'site_last_db_backup', time() );

		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );
		$return['cmd'] = $run_cmd;
		return $return;

	}


	private function backup_files( $id, $action ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = wp_parse_args( sanitize_text_field( wp_unslash( $_POST['params'] ) ) );
		$domain = $this->get_domain_name( $id );

		// dont use any file extension here
		$filename = "files-".date('d-m-Y-H-i-s');
		$command             = sprintf( '%s---%s---%d', $action, $domain, gmdate( 'B' ) );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		$server_name = $this->get_server_name( $id );

		if( empty( $server_name ) || is_null( $server_name )){
			$server_name = "Other";
		}

		$_remote_location = $this->SSH_REMOTE_LOCATION."/$server_name/$domain/".date('d_m_Y');
		$compressed = $this->should_compress_backup( $id ) ? "true" : "false";
		$run_cmd = $this->turn_script_into_command(
			$instance,
			'backup-files.txt',
			array_merge(
				$args,
				array(
					'command' 	=> $command,
					'action'  	=> $action,
					'domain'  	=> $domain,
					'sshhost'  	=> $this->SSH_HOST,
					'sshuser'  	=> $this->SSH_USER,
					'sshpass'  	=> $this->SSH_PASSWORD,
					'sshport'  	=> "$this->SSH_PORT",
					'filename'  => $filename,
					'compressed'=>$compressed,
					'remotelocation'=>$_remote_location,
				)
			)
		);
		update_post_meta( $id, 'site_last_files_backup', time() );
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );
		$return['cmd'] = $run_cmd;
		return $return;
	}

	private function backup_db_files( $id, $action ) {

		$instance = $this->get_app_instance_details( $id );

		if ( is_wp_error( $instance ) ) {
			return new \WP_Error( sprintf( __( 'Unable to execute this request because we cannot get the instance details for action %s', 'wpcd' ), $action ) );
		}

		$args = wp_parse_args( sanitize_text_field( wp_unslash( $_POST['params'] ) ) );

		$domain = $this->get_domain_name( $id );

		$files_file_name = "files-".date('d-m-Y-H-i-s');
		$dbfilename = "db-".date('d_m_Y_H_i_s').".sql";

		$command             = sprintf( '%s---%s---%d', $action, $domain, gmdate( 'B' ) );
		$instance['command'] = $command;
		$instance['app_id']  = $id;

		$server_name = $this->get_server_name( $id );

		if( empty( $server_name ) ){
			$server_name = "Other";
		}

		$_remote_location = $this->SSH_REMOTE_LOCATION."/$server_name/$domain/".date('d_m_Y');

		$compressed = $this->should_compress_backup( $id ) ? "true" : "false";

		$run_cmd = $this->turn_script_into_command(
			$instance,
			'backup-db-files.txt',
			array_merge(
				$args,
				array(
					'command' => $command,
					'action'  => $action,
					'domain'  => $domain,
					'sshhost'  => $this->SSH_HOST,
					'sshuser'  => $this->SSH_USER,
					'sshpass'  => $this->SSH_PASSWORD,
					'sshport'  => "$this->SSH_PORT",
					'filename' => $files_file_name,
					'dbfilename'=>$dbfilename,
					'compressed' => $compressed,
					'remotelocation'=>$_remote_location,
				)
			)
		);
		update_post_meta( $id, 'site_last_db_files_backup', time() );
		$return = $this->run_async_command_type_2( $id, $command, $run_cmd, $instance, $action );
		$return['cmd'] = $run_cmd;
		return $return;

		// $success_msg = __( 'Your files will be ready to download in a few minutes, please come back to this page later....', 'wpcd' );
		// $return      = array(
		// 	'msg'     => $success_msg,
		// 	'refresh' => 'yes',
		// );
		// return $return;

	}



	public function wpcd_script_file_name( $script_name ) {

		if ( 'backup-db.txt' !== $script_name ) {
			return $script_name;
		}

		if ( 'backup-files.txt' !== $script_name ) {
			return $script_name;
		}

		if ( 'backup-db-files.txt' !== $script_name ) {
			return $script_name;
		}

		return WPCDCUSTOMBACKU_PATH . 'includes/scripts/' . $script_name;
	}


	public function wpcd_wpapp_replace_script_tokens( $new_array, $array, $script_name, $script_version, $instance, $command, $additional ) {

		if ( 'backup-db.txt' === $script_name ) {
			$command_name = $additional['command'];
			$new_array    = array_merge(
				array(
					'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
					'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
				),
				$additional
			);
		}

		if ( 'backup-files.txt' === $script_name ) {
			$command_name = $additional['command'];
			$new_array    = array_merge(
				array(
					'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
					'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
				),
				$additional
			);
		}

		if ( 'backup-db-files.txt' === $script_name ) {
			$command_name = $additional['command'];
			$new_array    = array_merge(
				array(
					'SCRIPT_LOGS'  => "{$this->get_app_name()}_{$command_name}",
					'CALLBACK_URL' => $this->get_command_url( $instance['app_id'], $command_name, 'completed' ),
				),
				$additional
			);
		}
		// wp_send_json($new_array);
		return $new_array;
	}

}
new WPCD_WordPress_TABS_APP_CUSTOMBACKUP();
