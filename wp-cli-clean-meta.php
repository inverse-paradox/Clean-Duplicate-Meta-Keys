<?php
/**
 * Plugin Name: IP - Clean Meta Keys
 * Description: A tool to clean duplicate meta keys. Includes WP-CLI command, backend UI, logs, and scheduling.
 * Version: 1.1.0
 * Author: Inverse Paradox
 * Author URI: https://www.inverseparadox.com
 */

class Clean_Meta_Command {

	public function clean( $args, $assoc_args = [], $log_output = true ) {
		global $wpdb;

		$post_id = (int) $args[0];
		$meta_key = sanitize_text_field( $args[1] );

		if ( empty( $post_id ) || empty( $meta_key ) ) {
			return $this->log_output( "Post ID and meta key are required.", 'error', $log_output );
		}

		$this->log_output( "Cleaning meta key: $meta_key for Post ID: $post_id...", 'log', $log_output );

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
			$post_id, $meta_key
		) );

		if ( $count <= 1 ) {
			return $this->log_output( "No duplicates found for Post ID: $post_id, Meta Key: $meta_key.", 'success', $log_output );
		}

		$max_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(meta_id) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
			$post_id, $meta_key
		) );

		if ( ! $max_id ) {
			return $this->log_output( "No matching entries found.", 'warning', $log_output );
		}

		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s AND meta_id < %d",
			$post_id, $meta_key, $max_id
		) );

		return $this->log_output( "Deleted $deleted entries. Kept meta_id: $max_id.", 'success', $log_output );
	}

	public function clean_all( $args = [], $assoc_args = [], $log_output = true ) {
		global $wpdb;

		$post_type = 'tribe_events';
		$meta_keys = [ '_tribe_modified_fields', '_uag_page_assets', '_uag_css_file_name', '_edit_lock' ];
		$post_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", $post_type
		) );

		$output = "Found " . count( $post_ids ) . " $post_type posts.\n";

		foreach ( $post_ids as $post_id ) {
			foreach ( $meta_keys as $meta_key ) {
				$output .= $this->clean( [ $post_id, $meta_key ], [], $log_output ) . "\n";
			}
		}

		$this->log_output( "Finished cleaning meta keys.", 'success', $log_output );

		if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			$existing_logs = get_option( 'ip_clean_meta_logger', [] );
			$existing_logs[ current_time( 'mysql' ) ] = $output;
			update_option( 'ip_clean_meta_logger', array_slice( $existing_logs, -10, 10, true ) ); // Keep last 10
		}

		return $output;
	}

	private function log_output( $msg, $type = 'log', $output = true ) {
		if ( $output ) {
			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::$type( $msg );
			} 
		}
		return $msg;
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'clean-meta', 'Clean_Meta_Command' );
}

add_action( 'admin_menu', function() {
	add_submenu_page(
		'tools.php',
		'Clean Meta Keys',
		'Clean Meta Keys',
		'manage_options',
		'ip-clean-meta',
		'ip_clean_meta_admin_page'
	);
});

function ip_clean_meta_admin_page() {
	$cleaner = new Clean_Meta_Command();

	if ( isset( $_POST['ip_clean_meta_nonce'] ) && wp_verify_nonce( $_POST['ip_clean_meta_nonce'], 'ip_clean_meta' ) ) {
		if ( isset( $_POST['clean_now'] ) ) {
			echo '<div class="notice notice-success"><p>Manual cleanup complete.</p></div>';
			$cleaner->clean_all([], [], true);
		}
		if ( isset( $_POST['schedule_days'] ) ) {
			$days = absint( $_POST['schedule_days'] );
			if ( $days > 0 ) {
				update_option( 'ip_clean_meta_schedule_days', $days );
				
				remove_filter( 'cron_schedules', 'ip_custom_schedule_filter' );
				add_filter( 'cron_schedules', 'ip_custom_schedule_filter' );

				wp_clear_scheduled_hook( 'ip_clean_meta_cron' );
				$interval = DAY_IN_SECONDS * $days;
				$start_time = current_time( 'timestamp' ) + $interval;
				wp_schedule_event( $start_time, 'ip_custom_interval', 'ip_clean_meta_cron' );
			}
		}
	}

	$schedule_days = get_option( 'ip_clean_meta_schedule_days', 0 );
	$logs = get_option( 'ip_clean_meta_logger', [] );

	?>
	<div class="wrap">
		<h1>Clean Meta Keys</h1>

		<form method="post">
			<?php wp_nonce_field( 'ip_clean_meta', 'ip_clean_meta_nonce' ); ?>
			<?php submit_button( 'Run Cleanup Now', 'primary', 'clean_now' ); ?>
		</form>

		<hr>

		<form method="post">
			<?php wp_nonce_field( 'ip_clean_meta', 'ip_clean_meta_nonce' ); ?>
			<h2>Schedule Cleanup</h2>
			<p><label>Run cleanup every <input type="number" name="schedule_days" value="<?php echo esc_attr( $schedule_days ); ?>" min="1" style="width:80px;"> days</label></p>
			<?php
				if ( isset( $_POST['schedule_days'] ) ) {
					$next_run = current_time( 'timestamp' ) + ( $schedule_days * DAY_IN_SECONDS );
				} else {
					$next_run = wp_next_scheduled( 'ip_clean_meta_cron' );
				}
				if ( $next_run ) {
					echo '<p><strong>Next Scheduled Run:</strong> ' . date_i18n( 'Y-m-d H:i:s', $next_run ) . '</p>';
				} else {
					echo '<p><strong>No cleanup scheduled.</strong></p>';
				}
			?>
			<?php submit_button( 'Update Schedule' ); ?>
		</form>

		<hr>

		<h2>Recent Logs</h2>
		<?php if ( $logs ) : ?>
			<pre style="background:#f5f5f5; padding:10px; border:1px solid #ccc; max-height:400px; overflow:auto;"><?php
				foreach ( array_reverse( $logs ) as $date => $log ) {
					echo "=== [$date] ===\n$log\n\n";
				}
			?></pre>
		<?php else : ?>
			<p>No logs recorded yet.</p>
		<?php endif; ?>
		<?php 
			if ( isset( $_POST['clear_log'] ) && check_admin_referer( 'ip_clean_meta', 'ip_clean_meta_nonce' ) ) {
				update_option( 'ip_clean_meta_logger', [] );
				wp_safe_redirect( admin_url( 'tools.php?page=ip-clean-meta&cleared=1' ) );
				exit;
			}

			if ( isset( $_GET['cleared'] ) ) {
				echo '<div class="notice notice-success"><p>Logs cleared.</p></div>';
			}
		?>
		<form method="post" style="margin-top: 1em;">
			<?php wp_nonce_field( 'ip_clean_meta', 'ip_clean_meta_nonce' ); ?>
			<?php submit_button( 'Clear Log', 'delete', 'clear_log', false ); ?>
		</form>
	</div>
	<?php
}

// Custom interval for scheduler
add_filter( 'cron_schedules', function( $schedules ) {
	$days = get_option( 'ip_clean_meta_schedule_days', 0 );
	if ( $days > 0 ) {
		$schedules['ip_custom_interval'] = [
			'interval' => DAY_IN_SECONDS * $days,
			'display'  => "Every {$days} days"
		];
#		$schedules['ip_custom_interval'] = [
#			'interval' => 60 * $days,
#			'display'  => "Every minute"
#		];

	}
	return $schedules;
});

// Cron task
add_action( 'ip_clean_meta_cron', function() {
	$cleaner = new Clean_Meta_Command();
	$cleaner->clean_all([], [], true); 	
});

function ip_custom_schedule_filter( $schedules ) {
	$days = get_option( 'ip_clean_meta_schedule_days', 0 );
	if ( $days > 0 ) {
		$schedules['ip_custom_interval'] = [
			'interval' => DAY_IN_SECONDS * $days,
			'display'  => "Every {$days} days"
		];
	#	$schedules['ip_custom_interval'] = [
	#		'interval' => 60 * $days,
	#		'display'  => "Every minute"
	#	];
	}
	return $schedules;
}
add_filter( 'cron_schedules', 'ip_custom_schedule_filter' );
