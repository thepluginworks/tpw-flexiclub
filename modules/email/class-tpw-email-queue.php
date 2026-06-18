<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Email_Queue {
	const TABLE = 'tpw_email_queue';
	const DB_VERSION = '1.1.0';
	const DB_VERSION_OPTION = 'tpw_email_queue_db_version';
	const ACTION_GROUP = 'tpw-email';
	const PROCESS_ACTION_HOOK = 'tpw_email_queue_process_item';
	const RECONCILE_ACTION_HOOK = 'tpw_email_queue_reconcile';

	const STATUS_PENDING = 'pending';
	const STATUS_PROCESSING = 'processing';
	const STATUS_SENT = 'sent';
	const STATUS_FAILED = 'failed';
	const STATUS_CANCELLED = 'cancelled';

	const DEFAULT_BATCH_SIZE = 10;
	const DEFAULT_MAX_ATTEMPTS = 3;
	const DEFAULT_LOCK_TTL_SECONDS = 900;
	const RECONCILE_INTERVAL_SECONDS = 300;

	public static function init() {
		add_action( 'init', [ __CLASS__, 'maybe_install' ], 20 );
		add_action( 'init', [ __CLASS__, 'ensure_reconciliation_scheduled' ], 20 );
		add_action( self::PROCESS_ACTION_HOOK, [ __CLASS__, 'process_scheduled_item' ], 10, 1 );
		add_action( self::RECONCILE_ACTION_HOOK, [ __CLASS__, 'reconcile_pending_items' ] );
		add_filter( 'tpw_core_settings_tabs', [ __CLASS__, 'register_settings_tab' ] );
		add_action( 'tpw_core_settings_tab_content_email-queue', [ __CLASS__, 'render_settings_tab' ] );
		add_action( 'admin_post_tpw_core_process_email_queue', [ __CLASS__, 'handle_process_queue' ] );
		add_action( 'admin_post_tpw_core_retry_email_queue_item', [ __CLASS__, 'handle_retry_item' ] );
		add_action( 'admin_post_tpw_core_cancel_email_queue_item', [ __CLASS__, 'handle_cancel_item' ] );
		add_action( 'admin_post_tpw_core_clear_sent_queue_items', [ __CLASS__, 'handle_clear_sent_items' ] );
	}

	public static function register_settings_tab( $tabs ) {
		if ( ! is_array( $tabs ) ) {
			$tabs = [];
		}

		$tabs['email-queue'] = __( 'Email Queue', 'tpw-core' );

		return $tabs;
	}

	public static function maybe_install() {
		$current = (string) get_option( self::DB_VERSION_OPTION, '' );

		if ( version_compare( $current, self::DB_VERSION, '>=' ) ) {
			return;
		}

		self::create_table();
	}

	public static function create_table() {
		global $wpdb;

		$table_name = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			scheduler_action_id BIGINT(20) UNSIGNED NULL,
			recipient_email VARCHAR(255) NOT NULL DEFAULT '',
			recipient_name VARCHAR(191) NOT NULL DEFAULT '',
			to_payload LONGTEXT NULL,
			subject TEXT NOT NULL,
			body LONGTEXT NOT NULL,
			headers_json LONGTEXT NULL,
			attachments_json LONGTEXT NULL,
			context VARCHAR(191) NOT NULL DEFAULT '',
			source VARCHAR(191) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
			attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
			last_error TEXT NULL,
			scheduled_at DATETIME NOT NULL,
			locked_at DATETIME NULL,
			sent_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY scheduler_action_id (scheduler_action_id),
			KEY status_scheduled (status, scheduled_at),
			KEY locked_at (locked_at),
			KEY source (source(64))
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public static function ensure_reconciliation_scheduled() {
		if ( ! class_exists( 'TPW_Core_Scheduler' ) ) {
			return false;
		}

		if ( TPW_Core_Scheduler::has_scheduled( self::RECONCILE_ACTION_HOOK, [], self::ACTION_GROUP ) ) {
			return true;
		}

		$action_id = TPW_Core_Scheduler::schedule_recurring(
			time() + self::RECONCILE_INTERVAL_SECONDS,
			self::RECONCILE_INTERVAL_SECONDS,
			self::RECONCILE_ACTION_HOOK,
			[],
			self::ACTION_GROUP,
			true
		);

		return false !== $action_id;
	}

	public static function unschedule_actions() {
		global $wpdb;

		if ( ! class_exists( 'TPW_Core_Scheduler' ) ) {
			return;
		}

		TPW_Core_Scheduler::unschedule( self::RECONCILE_ACTION_HOOK, [], self::ACTION_GROUP );

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM " . self::table_name() . " WHERE status IN (%s, %s, %s)",
				self::STATUS_PENDING,
				self::STATUS_PROCESSING,
				self::STATUS_FAILED
			)
		);

		if ( is_array( $ids ) ) {
			foreach ( $ids as $raw_id ) {
				$queue_id = (int) $raw_id;
				if ( $queue_id <= 0 ) {
					continue;
				}

				TPW_Core_Scheduler::unschedule( self::PROCESS_ACTION_HOOK, [ $queue_id ], self::ACTION_GROUP );
			}
		}
	}

	/**
	 * @param string|array $to
	 * @param string       $subject
	 * @param string       $body
	 * @param string|array $headers
	 * @param array        $attachments
	 * @param array        $context
	 * @return bool
	 */
	public static function enqueue_and_schedule( $to, $subject, $body, $headers = [], $attachments = [], $context = [] ) {
		global $wpdb;

		$context = is_array( $context ) ? $context : [];

		$max_attempts = isset( $context['max_attempts'] ) && is_numeric( $context['max_attempts'] )
			? (int) $context['max_attempts']
			: self::DEFAULT_MAX_ATTEMPTS;
		$max_attempts = max( 1, (int) apply_filters( 'tpw_email_queue/max_attempts', $max_attempts, $context ) );

		$priority = isset( $context['priority'] ) && is_numeric( $context['priority'] ) ? (int) $context['priority'] : 100;
		$priority = max( 1, min( 1000, (int) apply_filters( 'tpw_email_queue/priority', $priority, $context ) ) );

		$scheduled_ts = isset( $context['scheduled_at'] ) && is_numeric( $context['scheduled_at'] ) ? (int) $context['scheduled_at'] : time();
		if ( $scheduled_ts < time() ) {
			$scheduled_ts = time();
		}

		$recipient_email = self::extract_recipient_email( $to );
		$recipient_name  = self::extract_recipient_name( $to );

		$row = [
			'scheduler_action_id' => null,
			'recipient_email'  => self::truncate( sanitize_text_field( (string) $recipient_email ), 255 ),
			'recipient_name'   => self::truncate( sanitize_text_field( (string) $recipient_name ), 191 ),
			'to_payload'       => wp_json_encode( $to ),
			'subject'          => (string) $subject,
			'body'             => (string) $body,
			'headers_json'     => wp_json_encode( is_array( $headers ) ? array_values( $headers ) : [ (string) $headers ] ),
			'attachments_json' => wp_json_encode( is_array( $attachments ) ? array_values( array_filter( $attachments ) ) : [] ),
			'context'          => self::truncate( sanitize_text_field( self::extract_context_label( $context ) ), 191 ),
			'source'           => self::truncate( sanitize_text_field( isset( $context['source'] ) ? (string) $context['source'] : '' ), 191 ),
			'status'           => self::STATUS_PENDING,
			'priority'         => $priority,
			'attempts'         => 0,
			'max_attempts'     => $max_attempts,
			'last_error'       => null,
			'scheduled_at'     => gmdate( 'Y-m-d H:i:s', $scheduled_ts ),
			'locked_at'        => null,
			'sent_at'          => null,
			'created_at'       => gmdate( 'Y-m-d H:i:s' ),
			'updated_at'       => gmdate( 'Y-m-d H:i:s' ),
		];

		$formats = [
			'%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s',
		];

		$inserted = $wpdb->insert( self::table_name(), $row, $formats );

		if ( false === $inserted ) {
			return [
				'success'   => false,
				'queue_id'  => 0,
				'action_id' => 0,
				'error'     => (string) $wpdb->last_error,
				'message'   => __( 'Failed to insert email queue row.', 'tpw-core' ),
			];
		}

		$queue_id = (int) $wpdb->insert_id;
		$action_id = self::schedule_queue_action( $queue_id, $scheduled_ts );
		if ( false === $action_id ) {
			self::update_schedule_failure( $queue_id, __( 'Failed to schedule queued email processing.', 'tpw-core' ) );

			return [
				'success'   => false,
				'queue_id'  => $queue_id,
				'action_id' => 0,
				'error'     => __( 'Failed to schedule queued email processing.', 'tpw-core' ),
				'message'   => __( 'Failed to schedule queued email processing.', 'tpw-core' ),
			];
		}

		do_action( 'tpw_email/queued', [
			'queue_id'   => $queue_id,
			'action_id'  => $action_id,
			'recipient'  => $row['recipient_email'],
			'subject'    => $row['subject'],
			'context'    => $row['context'],
			'source'     => $row['source'],
			'scheduled_at' => $row['scheduled_at'],
		] );

		return [
			'success'   => true,
			'queue_id'  => $queue_id,
			'action_id' => $action_id,
			'error'     => '',
			'message'   => __( 'Email queued for sending.', 'tpw-core' ),
		];
	}

	public static function process_scheduled_item( $queue_id = 0 ) {
		$queue_id = (int) $queue_id;
		if ( $queue_id <= 0 ) {
			return;
		}

		$row = self::claim_queue_row( $queue_id );
		if ( ! is_array( $row ) ) {
			return;
		}

		self::process_queue_row( $row );
	}

	public static function reconcile_pending_items() {
		global $wpdb;

		$now = gmdate( 'Y-m-d H:i:s' );
		$stale_lock_threshold = gmdate( 'Y-m-d H:i:s', time() - (int) apply_filters( 'tpw_email_queue/lock_ttl_seconds', self::DEFAULT_LOCK_TTL_SECONDS ) );
		$table = self::table_name();

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET status = %s, locked_at = NULL, updated_at = %s
				 WHERE status = %s
				   AND locked_at IS NOT NULL
				   AND locked_at < %s",
				self::STATUS_PENDING,
				$now,
				self::STATUS_PROCESSING,
				$stale_lock_threshold
			)
		);

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id
				 FROM {$table}
				 WHERE status = %s
				   AND scheduled_at <= %s
				   AND (locked_at IS NULL OR locked_at < %s)
				 ORDER BY priority ASC, scheduled_at ASC, id ASC
				 LIMIT %d",
				self::STATUS_PENDING,
				$now,
				$stale_lock_threshold,
				(int) apply_filters( 'tpw_email_queue/batch_size', self::DEFAULT_BATCH_SIZE )
			)
		);

		if ( empty( $ids ) ) {
			return 0;
		}

		$scheduled = 0;
		foreach ( $ids as $raw_id ) {
			$queue_id = (int) $raw_id;
			if ( $queue_id <= 0 ) {
				continue;
			}

			if ( self::has_pending_action( $queue_id ) ) {
				continue;
			}

			if ( false !== self::schedule_queue_action( $queue_id ) ) {
				$scheduled++;
			}
		}

		return $scheduled;
	}

	protected static function process_queue_row( array $row ) {
		global $wpdb;

		$queue_id = isset( $row['id'] ) ? (int) $row['id'] : 0;
		if ( $queue_id <= 0 ) {
			return;
		}

		$to = self::decode_to_payload( isset( $row['to_payload'] ) ? $row['to_payload'] : '' );
		$subject = isset( $row['subject'] ) ? (string) $row['subject'] : '';
		$body = isset( $row['body'] ) ? (string) $row['body'] : '';
		$headers = self::decode_json_array( isset( $row['headers_json'] ) ? $row['headers_json'] : '' );
		$attachments = self::decode_json_array( isset( $row['attachments_json'] ) ? $row['attachments_json'] : '' );

		$context = [];
		if ( ! empty( $row['context'] ) ) {
			$context['context'] = (string) $row['context'];
		}
		if ( ! empty( $row['source'] ) ) {
			$context['source'] = (string) $row['source'];
		}

		$sent = false;
		$error_message = '';

		if ( class_exists( 'TPW_Email' ) && method_exists( 'TPW_Email', 'dispatch_mail' ) ) {
			$result = TPW_Email::dispatch_mail( $to, $subject, $body, $headers, $attachments, $context );
			$sent = (bool) $result;
			if ( ! $sent ) {
				$error_message = __( 'Immediate dispatch returned false.', 'tpw-core' );
			}
		} else {
			$error_message = __( 'TPW_Email dispatcher unavailable.', 'tpw-core' );
		}

		$attempts = isset( $row['attempts'] ) ? (int) $row['attempts'] + 1 : 1;
		$max_attempts = isset( $row['max_attempts'] ) ? max( 1, (int) $row['max_attempts'] ) : self::DEFAULT_MAX_ATTEMPTS;
		$now = gmdate( 'Y-m-d H:i:s' );

		if ( $sent ) {
			$wpdb->update(
				self::table_name(),
				[
					'status'     => self::STATUS_SENT,
					'attempts'   => $attempts,
					'last_error' => null,
					'sent_at'    => $now,
					'locked_at'  => null,
					'updated_at' => $now,
				],
				[ 'id' => $queue_id ],
				[ '%s', '%d', '%s', '%s', '%s', '%s' ],
				[ '%d' ]
			);

			return;
		}

		$next_status = self::STATUS_FAILED;
		$next_scheduled_at = $now;
		$next_action_id = null;
		if ( $attempts < $max_attempts ) {
			$next_status = self::STATUS_PENDING;
			$retry_delay = self::get_retry_delay_seconds( $attempts );
			$next_scheduled_at = gmdate( 'Y-m-d H:i:s', time() + $retry_delay );
		}

		$wpdb->update(
			self::table_name(),
			[
				'status'       => $next_status,
				'attempts'     => $attempts,
				'last_error'   => $error_message,
				'scheduled_at' => $next_scheduled_at,
				'locked_at'    => null,
				'updated_at'   => $now,
			],
			[ 'id' => $queue_id ],
			[ '%s', '%d', '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);

		if ( self::STATUS_PENDING === $next_status ) {
			$scheduled_action_id = self::schedule_queue_action( $queue_id, strtotime( $next_scheduled_at . ' UTC' ) );
			if ( false === $scheduled_action_id ) {
				self::update_schedule_failure( $queue_id, __( 'Retry scheduling failed after email send failure.', 'tpw-core' ) );
			}
		}
	}

	protected static function get_retry_delay_seconds( $attempt_number ) {
		$attempt_number = max( 1, (int) $attempt_number );
		$base_delay = (int) apply_filters( 'tpw_email_queue/retry_base_delay_seconds', 60 );
		$max_delay = (int) apply_filters( 'tpw_email_queue/retry_max_delay_seconds', 3600 );
		$delay = min( $max_delay, $base_delay * (int) pow( 2, max( 0, $attempt_number - 1 ) ) );
		return max( 30, $delay );
	}

	public static function get_status_counts() {
		global $wpdb;

		$table = self::table_name();
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A );
		$counts = [
			self::STATUS_PENDING    => 0,
			self::STATUS_PROCESSING => 0,
			self::STATUS_SENT       => 0,
			self::STATUS_FAILED     => 0,
			self::STATUS_CANCELLED  => 0,
		];

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$status = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : '';
				if ( isset( $counts[ $status ] ) ) {
					$counts[ $status ] = isset( $row['total'] ) ? (int) $row['total'] : 0;
				}
			}
		}

		return $counts;
	}

	public static function get_page( $status = 'all', $page = 1, $per_page = 20 ) {
		global $wpdb;

		$page = max( 1, (int) $page );
		$per_page = max( 1, min( 100, (int) $per_page ) );
		$offset = ( $page - 1 ) * $per_page;
		$table = self::table_name();

		if ( 'all' === $status ) {
			$query = $wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
				sanitize_key( (string) $status ),
				$per_page,
				$offset
			);
		}

		return $wpdb->get_results( $query );
	}

	public static function count_items( $status = 'all' ) {
		global $wpdb;

		$table = self::table_name();
		if ( 'all' === $status ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", sanitize_key( (string) $status ) )
		);
	}

	public static function retry_failed_item( $queue_id ) {
		global $wpdb;

		$queue_id = (int) $queue_id;
		if ( $queue_id <= 0 ) {
			return false;
		}

		$updated = $wpdb->update(
			self::table_name(),
			[
				'status'       => self::STATUS_PENDING,
				'locked_at'    => null,
				'last_error'   => null,
				'scheduled_at' => gmdate( 'Y-m-d H:i:s' ),
				'updated_at'   => gmdate( 'Y-m-d H:i:s' ),
			],
			[
				'id'     => $queue_id,
				'status' => self::STATUS_FAILED,
			],
			[ '%s', '%s', '%s', '%s', '%s' ],
			[ '%d', '%s' ]
		);

		if ( 1 !== (int) $updated ) {
			return false;
		}

		return false !== self::schedule_queue_action( $queue_id, time() );
	}

	public static function cancel_pending_item( $queue_id ) {
		global $wpdb;

		$queue_id = (int) $queue_id;
		if ( $queue_id <= 0 ) {
			return false;
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE " . self::table_name() . "
				 SET status = %s, locked_at = NULL, updated_at = %s
				 WHERE id = %d
				   AND status IN (%s, %s)",
				self::STATUS_CANCELLED,
				gmdate( 'Y-m-d H:i:s' ),
				$queue_id,
				self::STATUS_PENDING,
				self::STATUS_FAILED
			)
		);

		if ( 1 === (int) $updated && class_exists( 'TPW_Core_Scheduler' ) ) {
			TPW_Core_Scheduler::unschedule( self::PROCESS_ACTION_HOOK, [ $queue_id ], self::ACTION_GROUP );
		}

		return 1 === (int) $updated;
	}

	public static function clear_sent_items( $older_than_days = 30 ) {
		global $wpdb;

		$older_than_days = max( 1, (int) $older_than_days );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $older_than_days * DAY_IN_SECONDS ) );
		$table = self::table_name();

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status = %s AND sent_at IS NOT NULL AND sent_at < %s",
				self::STATUS_SENT,
				$cutoff
			)
		);
	}

	public static function render_settings_tab() {
		if ( ! function_exists( 'tpw_core_current_user_can_manage_settings' ) || ! tpw_core_current_user_can_manage_settings() ) {
			return;
		}

		$status = isset( $_GET['queue_status'] ) ? sanitize_key( wp_unslash( $_GET['queue_status'] ) ) : 'all';
		$page = isset( $_GET['queue_page'] ) ? max( 1, absint( wp_unslash( $_GET['queue_page'] ) ) ) : 1;
		$per_page = 20;
		$items = self::get_page( $status, $page, $per_page );
		$counts = self::get_status_counts();
		$total = self::count_items( $status );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$base_url = function_exists( 'tpw_core_build_settings_tab_url' ) ? tpw_core_build_settings_tab_url( 'email-queue' ) : '';
		$action = esc_url( admin_url( 'admin-post.php' ) );
		$statuses = [
			'all'        => __( 'All', 'tpw-core' ),
			'pending'    => __( 'Pending', 'tpw-core' ),
			'processing' => __( 'Processing', 'tpw-core' ),
			'sent'       => __( 'Sent', 'tpw-core' ),
			'failed'     => __( 'Failed', 'tpw-core' ),
			'cancelled'  => __( 'Cancelled', 'tpw-core' ),
		];

		if ( isset( $_GET['tpw_queue_notice'] ) ) {
			$notice = sanitize_key( wp_unslash( $_GET['tpw_queue_notice'] ) );
			if ( '' !== $notice ) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				if ( 'processed' === $notice ) {
					esc_html_e( 'Email queue reconciliation triggered.', 'tpw-core' );
				} elseif ( 'retried' === $notice ) {
					esc_html_e( 'Queued email moved back to pending and rescheduled.', 'tpw-core' );
				} elseif ( 'cancelled' === $notice ) {
					esc_html_e( 'Queued email cancelled.', 'tpw-core' );
				} elseif ( 'cleared' === $notice ) {
					esc_html_e( 'Old sent queue items cleared.', 'tpw-core' );
				} else {
					esc_html_e( 'Email queue action completed.', 'tpw-core' );
				}
				echo '</p></div>';
			}
		}
		?>
		<div class="tpw-email-queue-tab">
			<p><?php esc_html_e( 'Queued outbound emails are stored durably before delivery. This screen is the business-level email payload and status view. Scheduled Actions remains the infrastructure view for job execution.', 'tpw-core' ); ?></p>
			<p><a class="button button-secondary" href="<?php echo esc_url( admin_url( 'tools.php?page=action-scheduler' ) ); ?>"><?php esc_html_e( 'View Scheduled Actions', 'tpw-core' ); ?></a></p>

			<p>
				<?php foreach ( $statuses as $status_key => $label ) : ?>
					<?php $count = 'all' === $status_key ? self::count_items( 'all' ) : ( isset( $counts[ $status_key ] ) ? (int) $counts[ $status_key ] : 0 ); ?>
					<a class="button<?php echo $status === $status_key ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( [ 'queue_status' => $status_key ], $base_url ) ); ?>"><?php echo esc_html( $label . ' (' . $count . ')' ); ?></a>
				<?php endforeach; ?>
			</p>

			<form method="post" action="<?php echo $action; ?>" style="display:inline-block;margin:0 1rem 1rem 0;">
				<?php wp_nonce_field( 'tpw_core_process_email_queue', 'tpw_core_email_queue_nonce' ); ?>
				<input type="hidden" name="action" value="tpw_core_process_email_queue" />
				<?php if ( function_exists( 'tpw_core_render_settings_context_fields' ) ) { tpw_core_render_settings_context_fields( 'email-queue' ); } ?>
				<?php submit_button( __( 'Reconcile Queue Now', 'tpw-core' ), 'secondary', 'tpw_process_email_queue', false ); ?>
			</form>

			<form method="post" action="<?php echo $action; ?>" style="display:inline-block;margin:0 0 1rem;">
				<?php wp_nonce_field( 'tpw_core_clear_sent_queue_items', 'tpw_core_clear_sent_queue_nonce' ); ?>
				<input type="hidden" name="action" value="tpw_core_clear_sent_queue_items" />
				<?php if ( function_exists( 'tpw_core_render_settings_context_fields' ) ) { tpw_core_render_settings_context_fields( 'email-queue' ); } ?>
				<?php submit_button( __( 'Clear Sent Queue Items', 'tpw-core' ), 'delete', 'tpw_clear_sent_queue_items', false, [ 'onclick' => "return confirm('Are you sure you want to clear old sent queue items?');" ] ); ?>
			</form>

			<table class="widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Created', 'tpw-core' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Recipient', 'tpw-core' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Subject', 'tpw-core' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Context', 'tpw-core' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'tpw-core' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Attempts', 'tpw-core' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Scheduled Action', 'tpw-core' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last Error', 'tpw-core' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'tpw-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $items ) ) : ?>
						<tr><td colspan="9"><?php esc_html_e( 'No queue items found.', 'tpw-core' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $items as $item ) : ?>
							<tr>
								<td><?php echo esc_html( (string) $item->created_at ); ?></td>
								<td><?php echo esc_html( (string) $item->recipient_email ); ?></td>
								<td><?php echo esc_html( (string) $item->subject ); ?></td>
								<td><?php echo esc_html( (string) $item->context ); ?></td>
								<td><?php echo esc_html( ucfirst( (string) $item->status ) ); ?></td>
								<td><?php echo esc_html( (string) $item->attempts . ' / ' . (string) $item->max_attempts ); ?></td>
								<td><?php echo esc_html( isset( $item->scheduler_action_id ) ? (string) $item->scheduler_action_id : '' ); ?></td>
								<td><?php echo esc_html( (string) $item->last_error ); ?></td>
								<td>
									<?php if ( 'failed' === $item->status ) : ?>
										<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'tpw_core_retry_email_queue_item', 'queue_id' => (int) $item->id, 'tpw_settings_context' => isset( tpw_core_get_settings_view_context()['mode'] ) ? tpw_core_get_settings_view_context()['mode'] : 'admin', 'tpw_settings_return_url' => add_query_arg( [ 'queue_status' => $status, 'queue_page' => $page ], $base_url ) ], admin_url( 'admin-post.php' ) ), 'tpw_core_retry_email_queue_item', 'tpw_core_email_queue_nonce' ) ); ?>"><?php esc_html_e( 'Retry', 'tpw-core' ); ?></a>
									<?php endif; ?>
									<?php if ( in_array( $item->status, [ 'pending', 'failed' ], true ) ) : ?>
										<a class="button button-link-delete" href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'tpw_core_cancel_email_queue_item', 'queue_id' => (int) $item->id, 'tpw_settings_context' => isset( tpw_core_get_settings_view_context()['mode'] ) ? tpw_core_get_settings_view_context()['mode'] : 'admin', 'tpw_settings_return_url' => add_query_arg( [ 'queue_status' => $status, 'queue_page' => $page ], $base_url ) ], admin_url( 'admin-post.php' ) ), 'tpw_core_cancel_email_queue_item', 'tpw_core_email_queue_nonce' ) ); ?>"><?php esc_html_e( 'Cancel', 'tpw-core' ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<p>
					<?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
						<a class="button<?php echo $page === $i ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( [ 'queue_status' => $status, 'queue_page' => $i ], $base_url ) ); ?>"><?php echo esc_html( (string) $i ); ?></a>
					<?php endfor; ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle_process_queue() {
		if ( ! function_exists( 'tpw_core_current_user_can_manage_settings' ) || ! tpw_core_current_user_can_manage_settings() ) {
			wp_die( __( 'You do not have permission to manage settings.', 'tpw-core' ) );
		}

		check_admin_referer( 'tpw_core_process_email_queue', 'tpw_core_email_queue_nonce' );
		self::reconcile_pending_items();
		self::redirect_with_notice( 'processed' );
	}

	public static function handle_retry_item() {
		if ( ! function_exists( 'tpw_core_current_user_can_manage_settings' ) || ! tpw_core_current_user_can_manage_settings() ) {
			wp_die( __( 'You do not have permission to manage settings.', 'tpw-core' ) );
		}

		check_admin_referer( 'tpw_core_retry_email_queue_item', 'tpw_core_email_queue_nonce' );
		$queue_id = isset( $_GET['queue_id'] ) ? absint( wp_unslash( $_GET['queue_id'] ) ) : 0;
		if ( $queue_id > 0 ) {
			self::retry_failed_item( $queue_id );
		}
		self::redirect_with_notice( 'retried' );
	}

	public static function handle_cancel_item() {
		if ( ! function_exists( 'tpw_core_current_user_can_manage_settings' ) || ! tpw_core_current_user_can_manage_settings() ) {
			wp_die( __( 'You do not have permission to manage settings.', 'tpw-core' ) );
		}

		check_admin_referer( 'tpw_core_cancel_email_queue_item', 'tpw_core_email_queue_nonce' );
		$queue_id = isset( $_GET['queue_id'] ) ? absint( wp_unslash( $_GET['queue_id'] ) ) : 0;
		if ( $queue_id > 0 ) {
			self::cancel_pending_item( $queue_id );
		}
		self::redirect_with_notice( 'cancelled' );
	}

	public static function handle_clear_sent_items() {
		if ( ! function_exists( 'tpw_core_current_user_can_manage_settings' ) || ! tpw_core_current_user_can_manage_settings() ) {
			wp_die( __( 'You do not have permission to manage settings.', 'tpw-core' ) );
		}

		check_admin_referer( 'tpw_core_clear_sent_queue_items', 'tpw_core_clear_sent_queue_nonce' );
		self::clear_sent_items();
		self::redirect_with_notice( 'cleared' );
	}

	protected static function redirect_with_notice( $notice ) {
		$notice = sanitize_key( (string) $notice );
		if ( function_exists( 'tpw_core_get_settings_redirect_url' ) ) {
			wp_safe_redirect( tpw_core_get_settings_redirect_url( 'email-queue', [ 'tpw_queue_notice' => $notice ] ) );
			exit;
		}

		wp_safe_redirect( admin_url( 'options-general.php?page=tpw-core-settings&tab=email-queue&tpw_queue_notice=' . rawurlencode( $notice ) ) );
		exit;
	}

	protected static function schedule_queue_action( $queue_id, $timestamp = null ) {
		global $wpdb;

		$queue_id = (int) $queue_id;
		if ( $queue_id <= 0 || ! class_exists( 'TPW_Core_Scheduler' ) ) {
			return false;
		}

		$when = is_numeric( $timestamp ) ? (int) $timestamp : time();
		$when = max( time(), $when );
		$action_id = TPW_Core_Scheduler::schedule_single( $when, self::PROCESS_ACTION_HOOK, [ $queue_id ], self::ACTION_GROUP, true );
		if ( false === $action_id ) {
			return false;
		}

		$wpdb->update(
			self::table_name(),
			[
				'scheduler_action_id' => (int) $action_id,
				'updated_at'          => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => $queue_id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		return (int) $action_id;
	}

	protected static function has_pending_action( $queue_id ) {
		if ( ! class_exists( 'TPW_Core_Scheduler' ) ) {
			return false;
		}

		return TPW_Core_Scheduler::has_scheduled( self::PROCESS_ACTION_HOOK, [ (int) $queue_id ], self::ACTION_GROUP );
	}

	protected static function claim_queue_row( $queue_id ) {
		global $wpdb;

		$queue_id = (int) $queue_id;
		if ( $queue_id <= 0 ) {
			return null;
		}

		$now = gmdate( 'Y-m-d H:i:s' );
		$stale_lock_threshold = gmdate( 'Y-m-d H:i:s', time() - (int) apply_filters( 'tpw_email_queue/lock_ttl_seconds', self::DEFAULT_LOCK_TTL_SECONDS ) );
		$table = self::table_name();

		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET status = %s, locked_at = %s, updated_at = %s
				 WHERE id = %d
				   AND status IN (%s, %s)
				   AND scheduled_at <= %s
				   AND (locked_at IS NULL OR locked_at < %s)",
				self::STATUS_PROCESSING,
				$now,
				$now,
				$queue_id,
				self::STATUS_PENDING,
				self::STATUS_PROCESSING,
				$now,
				$stale_lock_threshold
			)
		);

		if ( 1 !== (int) $claimed ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $queue_id ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	protected static function update_schedule_failure( $queue_id, $message ) {
		global $wpdb;

		$wpdb->update(
			self::table_name(),
			[
				'last_error' => self::truncate( sanitize_text_field( (string) $message ), 65535 ),
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			],
			[ 'id' => (int) $queue_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
	}

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	protected static function decode_to_payload( $value ) {
		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) || is_string( $decoded ) ) {
				return $decoded;
			}
		}

		return '';
	}

	protected static function decode_json_array( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return [];
		}

		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? $decoded : [];
	}

	protected static function extract_context_label( array $context ) {
		if ( isset( $context['context'] ) && is_string( $context['context'] ) ) {
			return $context['context'];
		}

		if ( isset( $context['source'] ) && is_string( $context['source'] ) ) {
			return $context['source'];
		}

		return '';
	}

	protected static function extract_recipient_email( $to ) {
		if ( is_string( $to ) ) {
			$email = sanitize_email( $to );
			return is_email( $email ) ? $email : '';
		}

		if ( is_array( $to ) ) {
			foreach ( $to as $entry ) {
				$email = sanitize_email( is_scalar( $entry ) ? (string) $entry : '' );
				if ( is_email( $email ) ) {
					return $email;
				}
			}
		}

		return '';
	}

	protected static function extract_recipient_name( $to ) {
		if ( is_array( $to ) ) {
			foreach ( $to as $entry ) {
				if ( is_string( $entry ) && ! is_email( $entry ) ) {
					return $entry;
				}
			}
		}

		return '';
	}

	protected static function truncate( $value, $max_length ) {
		$value = (string) $value;
		if ( strlen( $value ) <= $max_length ) {
			return $value;
		}

		return substr( $value, 0, $max_length );
	}
}
