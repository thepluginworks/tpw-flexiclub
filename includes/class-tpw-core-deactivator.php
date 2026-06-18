<?php
/**
 * Fired during plugin deactivation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TPW_Core_Deactivator {
	public static function deactivate() {
		if ( ! class_exists( 'TPW_Core_Scheduler' ) && defined( 'TPW_CORE_PATH' ) ) {
			$scheduler_file = TPW_CORE_PATH . 'includes/scheduler/class-tpw-core-scheduler.php';
			if ( file_exists( $scheduler_file ) ) {
				require_once $scheduler_file;
			}
		}

		if ( ! class_exists( 'TPW_Email_Queue' ) && defined( 'TPW_CORE_PATH' ) ) {
			$queue_file = TPW_CORE_PATH . 'modules/email/class-tpw-email-queue.php';
			if ( file_exists( $queue_file ) ) {
				require_once $queue_file;
			}
		}

		if ( ! class_exists( 'TPW_Email_Logs' ) && defined( 'TPW_CORE_PATH' ) ) {
			$logs_file = TPW_CORE_PATH . 'modules/email/class-tpw-email-logs.php';
			if ( file_exists( $logs_file ) ) {
				require_once $logs_file;
			}
		}

		if ( class_exists( 'TPW_Email_Queue' ) ) {
			TPW_Email_Queue::unschedule_actions();
		}

		if ( class_exists( 'TPW_Email_Logs' ) ) {
			TPW_Email_Logs::unschedule_cleanup();
		}
	}
}
