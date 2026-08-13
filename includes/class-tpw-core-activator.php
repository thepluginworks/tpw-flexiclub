<?php
/**
 * Fired during plugin activation
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TPW_Core_Activator {

    /**
     * Code to run on plugin activation.
     */
    public static function activate() {
        // Trigger any setup tasks here (e.g., flushing rewrite rules)
        flush_rewrite_rules();
        tpw_core_ensure_site_society_id();
        // require_once TPW_CORE_PATH . 'modules/guests/class-tpw-guests-table.php';
        // TPW_Guests_Table::create_table();
        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-menus-manager.php';
        TPW_Menus_Manager::create_table();
        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-event-menu-rel.php';
        TPW_Event_Menu_Rel::create_table();
        
        require_once TPW_CORE_PATH . 'modules/payments/class-tpw-payment-db.php';
        TPW_Payment_DB::create_table();

        require_once TPW_CORE_PATH . 'modules/costs/class-tpw-costs-db.php';
        TPW_Costs_DB::create_table();

        require_once TPW_CORE_PATH . 'modules/members/class-tpw-members-db.php';
        TPW_Members_DB::create_table();
        require_once TPW_CORE_PATH . 'modules/members/class-tpw-member-fields-installer.php';
        TPW_Member_Fields_Installer::insert_default_fields();
        require_once TPW_CORE_PATH . 'modules/members/signups/class-tpw-signup-attempts-db.php';
        TPW_Signup_Attempts_DB::create_table();

        require_once TPW_CORE_PATH . 'modules/menus/class-tpw-course-choices-manager.php';

        // Gallery module DB (Phase 2: create schema on activation, silent and safe)
        try {
            require_once TPW_CORE_PATH . 'modules/gallery/gallery-db.php';
            if ( class_exists( 'TPW_Gallery_DB' ) ) {
                $current = get_option( 'tpw_gallery_db_version', '' );
                // Read target schema version from class constant
                $target  = TPW_Gallery_DB::VERSION;
                if ( version_compare( (string) $current, (string) $target, '<' ) ) {
                    TPW_Gallery_DB::create_tables();
                }
            }
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) {
                error_log( 'TPW Core activation: Gallery DB setup failed - ' . $e->getMessage() );
            }
        }

        // Create Email Templates table
        try {
            require_once TPW_CORE_PATH . 'modules/email/class-tpw-email-templates-db.php';
            if ( class_exists( 'TPW_Email_Templates_DB' ) ) {
                TPW_Email_Templates_DB::create_table();
            }
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) {
                error_log( 'TPW Core activation: email templates table creation failed - ' . $e->getMessage() );
            }
        }

        // Create Email Logs table and schedule retention cleanup.
        try {
            require_once TPW_CORE_PATH . 'modules/email/class-tpw-email-logs.php';
            if ( class_exists( 'TPW_Email_Logs' ) ) {
                TPW_Email_Logs::create_table();
                TPW_Email_Logs::schedule_cleanup();
            }
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) {
                error_log( 'TPW Core activation: email logs setup failed - ' . $e->getMessage() );
            }
        }

        // Create Email Queue table and schedule Action Scheduler reconciliation.
		try {
			require_once TPW_CORE_PATH . 'modules/email/class-tpw-email-queue.php';
			if ( class_exists( 'TPW_Email_Queue' ) ) {
				TPW_Email_Queue::create_table();
                TPW_Email_Queue::ensure_reconciliation_scheduled();
			}
		} catch ( \Throwable $e ) {
			if ( function_exists( 'error_log' ) ) {
				error_log( 'TPW Core activation: email queue setup failed - ' . $e->getMessage() );
			}
		}

        // Set default currency settings if not already set
        $settings = get_option( 'flexievent_settings', [] );

        if ( empty( $settings['currency_symbol'] ) ) {
            $settings['currency_symbol'] = '£';
        }

        if ( empty( $settings['currency_code'] ) ) {
            $settings['currency_code'] = 'GBP';
        }

        update_option( 'flexievent_settings', $settings );

        // Seed default surcharge options (percent and fixed) for all supported methods
        try {
            $methods = [ 'woocommerce', 'square', 'sumup', 'bacs', 'cheque', 'cash', 'card-on-the-day' ];
            foreach ( $methods as $m ) {
                $k_percent = 'tpw_surcharge_' . $m . '_percent';
                $k_fixed   = 'tpw_surcharge_' . $m . '_fixed';
                if ( false === get_option( $k_percent, false ) ) {
                    add_option( $k_percent, 0 );
                }
                if ( false === get_option( $k_fixed, false ) ) {
                    add_option( $k_fixed, 0 );
                }
            }
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) {
                error_log( 'TPW Core activation: surcharge defaults setup skipped - ' . $e->getMessage() );
            }
        }

        // Migrate legacy option 'tpw_member_viewable_fields' to new visibility table
        try {
            global $wpdb;
            $legacy_viewable = get_option( 'tpw_member_viewable_fields', null );
            if ( null !== $legacy_viewable ) {
                $vis_table = $wpdb->prefix . 'tpw_member_field_visibility';
                if ( is_array( $legacy_viewable ) && ! empty( $legacy_viewable ) ) {
                    foreach ( $legacy_viewable as $field_key ) {
                        $field_key = sanitize_key( $field_key );
                        if ( '' === $field_key ) continue;
                        // Use raw query so we can safely backtick the reserved column name `group`
                        $sql = $wpdb->prepare(
                            "INSERT INTO $vis_table (field_key, `group`, is_visible) VALUES (%s, %s, %d)",
                            $field_key, 'member', 1
                        );
                        $wpdb->query( $sql );
                    }
                }
                // Note: We keep the legacy option since it now powers the Member Profile view
                // (profile visibility is independent from directory visibility matrix).
                // If you want to reset profile visibility, you can clear it via the Member Settings UI.
            }

            // Seed default visibility rules for enabled fields if not already present
            try {
                $settings_tbl = $wpdb->prefix . 'tpw_field_settings';
                $enabled_fields = (array) $wpdb->get_col( "SELECT field_key FROM {$settings_tbl} WHERE is_enabled = 1" );
                $enabled_fields = array_values( array_filter( array_map( 'sanitize_key', $enabled_fields ) ) );
                if ( ! empty( $enabled_fields ) ) {
                    $vis_table = $wpdb->prefix . 'tpw_member_field_visibility';

                    // Helper: check if a mapping exists
                    $has_mapping = function( $group, $field_key ) use ( $wpdb, $vis_table ) {
                        $sql = $wpdb->prepare( "SELECT 1 FROM {$vis_table} WHERE `group` = %s AND field_key = %s LIMIT 1", $group, $field_key );
                        return (bool) $wpdb->get_var( $sql );
                    };

                    // Admin: sees all enabled fields
                    foreach ( $enabled_fields as $fk ) {
                        if ( ! $has_mapping( 'admin', $fk ) ) {
                            $wpdb->insert( $vis_table, [ 'field_key' => $fk, 'group' => 'admin', 'is_visible' => 1 ], [ '%s','%s','%d' ] );
                        }
                    }

                    // Member: basic fields (name + email + phone)
                    $member_defaults = [ 'first_name', 'surname', 'email', 'mobile', 'landline' ];
                    foreach ( $enabled_fields as $fk ) {
                        if ( in_array( $fk, $member_defaults, true ) ) {
                            if ( ! $has_mapping( 'member', $fk ) ) {
                                $wpdb->insert( $vis_table, [ 'field_key' => $fk, 'group' => 'member', 'is_visible' => 1 ], [ '%s','%s','%d' ] );
                            }
                        }
                    }

                    // Committee: inherits member defaults (add any committee-specific fields here if needed)
                    foreach ( $enabled_fields as $fk ) {
                        if ( in_array( $fk, $member_defaults, true ) ) {
                            if ( ! $has_mapping( 'committee', $fk ) ) {
                                $wpdb->insert( $vis_table, [ 'field_key' => $fk, 'group' => 'committee', 'is_visible' => 1 ], [ '%s','%s','%d' ] );
                            }
                        }
                    }

                    // Guest: no defaults (explicitly do nothing)
                }
            } catch ( \Throwable $e2 ) {
                if ( function_exists( 'error_log' ) ) {
                    error_log( 'TPW Core activation: default visibility seeding skipped - ' . $e2->getMessage() );
                }
            }
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) {
                error_log( 'TPW Core activation: migrating viewable fields failed - ' . $e->getMessage() );
            }
        }

        // Ensure a default Members Menu is available and assigned to the 'tpw_member_menu' location
        // Only if nothing is currently assigned for that location.
        // Note: During activation, not all theme hooks run, but we can still set the theme_mod mapping.
        try {
            if ( ! function_exists( 'get_nav_menu_locations' ) ) {
                // Safety include for nav menu functions during activation
                if ( defined( 'ABSPATH' ) ) {
                    @require_once ABSPATH . 'wp-includes/nav-menu.php';
                }
            }

            if ( ! function_exists( 'wp_create_nav_menu' ) ) {
                // Admin helper functions
                if ( defined( 'ABSPATH' ) ) {
                    @require_once ABSPATH . 'wp-admin/includes/nav-menu.php';
                }
            }

            if ( function_exists( 'get_nav_menu_locations' ) ) {
                $locations = get_nav_menu_locations();
                if ( ! is_array( $locations ) ) {
                    $locations = [];
                }

                $has_assignment = isset( $locations['tpw_member_menu'] ) && ! empty( $locations['tpw_member_menu'] );

                if ( ! $has_assignment ) {
                    // Create or find a "Members Menu"
                    $menu_name = __( 'Members Menu', 'tpw-core' );
                    $menu_obj  = function_exists( 'wp_get_nav_menu_object' ) ? wp_get_nav_menu_object( $menu_name ) : null;
                    $menu_id   = $menu_obj && isset( $menu_obj->term_id ) ? (int) $menu_obj->term_id : 0;

                    if ( ! $menu_id && function_exists( 'wp_create_nav_menu' ) ) {
                        $menu_id = (int) wp_create_nav_menu( $menu_name );
                    }

                    if ( $menu_id > 0 ) {
                        if ( function_exists( 'tpw_core_ensure_member_menu_defaults' ) ) {
                            $menu_id = (int) tpw_core_ensure_member_menu_defaults( $menu_id );
                        }

                        if ( $menu_id > 0 ) {
                            // Assign to the tpw_member_menu location.
                            $locations['tpw_member_menu'] = $menu_id;
                            set_theme_mod( 'nav_menu_locations', $locations );
                        }
                    }
                }
            }
        } catch ( \Throwable $e ) {
            if ( function_exists( 'error_log' ) ) {
                error_log( 'TPW Core activation: menu setup skipped due to error - ' . $e->getMessage() );
            }
        }

        if ( class_exists( 'TPW_Core_System_Pages' ) ) {
            TPW_Core_System_Pages::ensure_tables();
            // Ensure key system pages exist
            try {
                TPW_Core_System_Pages::register_page( 'member-login', [
                    'title'     => 'Member Login',
                    'shortcode' => '[tpw_member_login]',
                    'plugin'    => 'tpw-core',
                    'required'  => 1,
                ] );
                TPW_Core_System_Pages::register_page( 'my-profile', [
                    'title'     => 'My Profile',
                    'shortcode' => '[tpw_member_profile]',
                    'plugin'    => 'tpw-core',
                    'required'  => 1,
                ] );
                TPW_Core_System_Pages::ensure_page( 'member-login' );
                $profile_page_id = (int) TPW_Core_System_Pages::ensure_page( 'my-profile' );
                if ( 0 < $profile_page_id && 0 >= (int) get_option( 'tpw_member_profile_page_id', 0 ) ) {
                    update_option( 'tpw_member_profile_page_id', $profile_page_id );
                }

                $flexiclub_pages = [
                    'club-management' => [
                        'title'     => 'Club Management',
                        'shortcode' => '[flexiclub]',
                        'required'  => 1,
                    ],
                    'logs' => [
                        'title'     => 'Logs',
                        'shortcode' => '[flexiclub workspace="logs"]',
                        'required'  => 0,
                    ],
                    'menu-management' => [
                        'title'     => 'Menu Management',
                        'shortcode' => '[flexiclub_menu_management]',
                        'required'  => 0,
                    ],
                    'archival-system' => [
                        'title'     => 'Archival System',
                        'shortcode' => '[flexiclub_archival_system]',
                        'required'  => 0,
                    ],
                    'tpw-control' => [
                        'title'     => 'iLungu Club Control',
                        'shortcode' => '[tpw-control]',
                        'required'  => 0,
                    ],
                ];

                foreach ( $flexiclub_pages as $slug => $config ) {
                    TPW_Core_System_Pages::register_page( $slug, [
                        'title'     => $config['title'],
                        'shortcode' => $config['shortcode'],
                        'plugin'    => 'tpw-core',
                        'required'  => $config['required'],
                    ] );

					if ( 'logs' === $slug && method_exists( 'TPW_Core_System_Pages', 'unlink' ) ) {
						$logs_page_id      = (int) TPW_Core_System_Pages::get_page_id( 'logs' );
                        $dashboard_page_id = (int) TPW_Core_System_Pages::get_page_id( 'club-management' );

						if ( $logs_page_id > 0 && $logs_page_id === $dashboard_page_id ) {
							TPW_Core_System_Pages::unlink( 'logs' );
						}
					}

                    if ( function_exists( 'tpw_core_maybe_ensure_system_page' ) ) {
                        tpw_core_maybe_ensure_system_page( $slug, $config['shortcode'] );
                    } else {
                        TPW_Core_System_Pages::ensure_page( $slug );
                    }

					if ( 'club-management' === $slug && function_exists( 'tpw_core_maybe_update_club_management_page_title' ) ) {
						tpw_core_maybe_update_club_management_page_title();
					}
                }
            } catch ( \Throwable $e ) {
                if ( function_exists( 'error_log' ) ) {
                    error_log( 'TPW Core activation: ensuring system pages failed - ' . $e->getMessage() );
                }
            }

            // Validate the configured login redirect page option; clear if invalid
            try {
                $redir = (int) get_option( 'tpw_login_redirect_page_id', 0 );
                if ( $redir > 0 && get_post_status( $redir ) !== 'publish' ) {
                    update_option( 'tpw_login_redirect_page_id', 0 );
                }
            } catch ( \Throwable $e ) {
                // no-op
            }
        }
    }
}
