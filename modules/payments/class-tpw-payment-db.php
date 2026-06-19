<?php

class TPW_Payment_DB {
    public const VERSION = '1.1.0';

    public static function create_table() {
        global $wpdb;
        $methods_table = $wpdb->prefix . 'tpw_payment_methods';
        $payments_table = $wpdb->prefix . 'tpw_rsvp_payments';
        $charset_collate = $wpdb->get_charset_collate();

        $methods_sql = "CREATE TABLE $methods_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            slug varchar(100) NOT NULL,
            sort_order int(11) NOT NULL DEFAULT 0,
            active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug_unique (slug)
        ) $charset_collate;";

        $payments_sql = "CREATE TABLE $payments_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            submission_id bigint(20) unsigned NOT NULL,
            guest_id bigint(20) unsigned DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            confirmed_amount decimal(10,2) DEFAULT NULL,
            expected_amount decimal(10,2) DEFAULT NULL,
            surcharge_applied decimal(10,2) DEFAULT NULL,
            overpayment_amount decimal(10,2) DEFAULT '0.00',
            payment_method varchar(50) DEFAULT NULL,
            payment_reference text DEFAULT NULL,
            paid_by varchar(100) DEFAULT NULL,
            covers_self tinyint(1) DEFAULT '1',
            covered_guest_ids text DEFAULT NULL,
            paid_at datetime DEFAULT NULL,
            marked_by bigint(20) unsigned DEFAULT NULL,
            checkout_url text DEFAULT NULL,
            notes text DEFAULT NULL,
            amount_breakdown json DEFAULT NULL,
            updated_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY submission_id (submission_id),
            KEY guest_id (guest_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($methods_sql);
        dbDelta($payments_sql);
        if ($wpdb->last_error) {
            error_log('❌ DB Error: ' . $wpdb->last_error);
        }

        // Insert default methods if not already present
        $default_methods = [
            ['name' => 'Bank Transfer', 'slug' => 'bacs'],
            ['name' => 'Cheque', 'slug' => 'cheque'],
            ['name' => 'Cash', 'slug' => 'cash'],
            ['name' => 'Card on the day', 'slug' => 'card-on-the-day'],
            ['name' => 'Pay by Card (via SumUp)', 'slug' => 'sumup'],
            ['name' => 'Pay by Card (via Square)', 'slug' => 'square'],
            ['name' => 'WooCommerce', 'slug' => 'woocommerce'],
        ];

        $i = 0;
        foreach ($default_methods as $method) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $methods_table WHERE slug = %s", $method['slug']));
            if (!$exists) {
                $wpdb->insert($methods_table, [
                    'name' => $method['name'],
                    'slug' => $method['slug'],
                    'sort_order' => $i,
                    'active' => 1,
                    'created_at' => current_time('mysql'),
                ]);
            }
            $i++;
        }
    }
}
