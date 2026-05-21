<?php

// Add "Payment" tab to FlexiEvent settings page
add_filter('flexievent_settings_tabs', function($tabs) {
    $tabs['payments'] = 'Payments';
    return $tabs;
});

add_filter('flexievent_settings_allowed_keys', function($allowed_keys) {
    $allowed_keys[] = 'currency_symbol';
    $allowed_keys[] = 'currency_code';

    return array_values(array_unique($allowed_keys));
});

// Add content for the "Payments" tab
add_action('flexievent_settings_tab_content_payments', function($settings) {
    // Define the settings fields inside the callback
    $settings_fields = [
        'enable_payments' => 'Enable Payments (Yes/No)',
        'currency_symbol' => 'Currency Symbol (e.g. £)',
    ];
    ?>
    <table class="form-table">
        <tbody>
            <?php foreach ($settings_fields as $key => $label): ?>
                <?php if ($key === 'enable_payments') continue; ?>
                <tr>
                    <th scope="row">
                        <label for="flexievent_settings[<?php echo esc_attr($key); ?>]">
                            <?php echo esc_html($label); ?>
                        </label>
                    </th>
                    <td>
                        <?php if ($key === 'currency_symbol'): ?>
                            <?php $selected_symbol = get_option( 'flexievent_currency_symbol', '£' ); ?>
                            <select name="flexievent_settings[currency_symbol]" id="flexievent_settings[currency_symbol]">
                                <option value="£" <?php selected($selected_symbol, '£'); ?>>£ – British Pound (GBP)</option>
                                <option value="$" <?php selected($selected_symbol, '$'); ?>>$ – US Dollar (USD)</option>
                                <option value="€" <?php selected($selected_symbol, '€'); ?>>€ – Euro (EUR)</option>
                                <option disabled>──────────</option>
                                <option value="A$" <?php selected($selected_symbol, 'A$'); ?>>A$ – Australian Dollar (AUD)</option>
                                <option value="NZ$" <?php selected($selected_symbol, 'NZ$'); ?>>NZ$ – New Zealand Dollar (NZD)</option>
                                <option value="HK$" <?php selected($selected_symbol, 'HK$'); ?>>HK$ – Hong Kong Dollar (HKD)</option>
                                <option value="SGD" <?php selected($selected_symbol, 'SGD'); ?>>SGD – Singapore Dollar</option>
                                <option value="MX$" <?php selected($selected_symbol, 'MX$'); ?>>MX$ – Mexican Peso (MXN)</option>
                                <option value="TWD" <?php selected($selected_symbol, 'TWD'); ?>>TWD – New Taiwan Dollar</option>
                                <option value="SAR" <?php selected($selected_symbol, 'SAR'); ?>>SAR – Saudi Riyal</option>
                                <option value="EGP" <?php selected($selected_symbol, 'EGP'); ?>>EGP – Egyptian Pound</option>
                                <option value="؋" <?php selected($selected_symbol, '؋'); ?>>؋ – Afghan Afghani (AFN)</option>
                                <option value="R$" <?php selected($selected_symbol, 'R$'); ?>>R$ – Brazilian Real (BRL)</option>
                                <option value="C$" <?php selected($selected_symbol, 'C$'); ?>>C$ – Canadian Dollar (CAD)</option>
                                <option value="¥" <?php selected($selected_symbol, '¥'); ?>>¥ – Japanese Yen (JPY)</option>
                                <option value="CN¥" <?php selected($selected_symbol, 'CN¥'); ?>>CN¥ – Chinese Yuan (CNY)</option>
                                <option value="Kč" <?php selected($selected_symbol, 'Kč'); ?>>Kč – Czech Koruna (CZK)</option>
                                <option value="kr (DKK)" <?php selected($selected_symbol, 'kr (DKK)'); ?>>kr – Danish Krone (DKK)</option>
                                <option value="kr (NOK)" <?php selected($selected_symbol, 'kr (NOK)'); ?>>kr – Norwegian Krone (NOK)</option>
                                <option value="kr (SEK)" <?php selected($selected_symbol, 'kr (SEK)'); ?>>kr – Swedish Krona (SEK)</option>
                                <option value="₹" <?php selected($selected_symbol, '₹'); ?>>₹ – Indian Rupee (INR)</option>
                                <option value="Rp" <?php selected($selected_symbol, 'Rp'); ?>>Rp – Indonesian Rupiah (IDR)</option>
                                <option value="₪" <?php selected($selected_symbol, '₪'); ?>>₪ – Israeli Shekel (ILS)</option>
                                <option value="₩" <?php selected($selected_symbol, '₩'); ?>>₩ – South Korean Won (KRW)</option>
                                <option value="RM" <?php selected($selected_symbol, 'RM'); ?>>RM – Malaysian Ringgit (MYR)</option>
                                <option value="₦" <?php selected($selected_symbol, '₦'); ?>>₦ – Nigerian Naira (NGN)</option>
                                <option value="₱" <?php selected($selected_symbol, '₱'); ?>>₱ – Philippine Peso (PHP)</option>
                                <option value="zł" <?php selected($selected_symbol, 'zł'); ?>>zł – Polish Zloty (PLN)</option>
                                <option value="руб" <?php selected($selected_symbol, 'руб'); ?>>руб – Russian Ruble (RUB)</option>
                                <option value="R" <?php selected($selected_symbol, 'R'); ?>>R – South African Rand (ZAR)</option>
                                <option value="Fr" <?php selected($selected_symbol, 'Fr'); ?>>Fr – Swiss Franc (CHF)</option>
                                <option value="฿" <?php selected($selected_symbol, '฿'); ?>>฿ – Thai Baht (THB)</option>
                                <option value="₺" <?php selected($selected_symbol, '₺'); ?>>₺ – Turkish Lira (TRY)</option>
                                <option value="₴" <?php selected($selected_symbol, '₴'); ?>>₴ – Ukrainian Hryvnia (UAH)</option>
                                <option value="د.إ" <?php selected($selected_symbol, 'د.إ'); ?>>د.إ – UAE Dirham (AED)</option>
                            </select>
                        <?php else: ?>
                            <input
                                type="text"
                                name="flexievent_settings[<?php echo esc_attr($key); ?>]"
                                id="flexievent_settings[<?php echo esc_attr($key); ?>]"
                                value="<?php echo esc_attr($settings[$key] ?? ''); ?>"
                                class="regular-text"
                            />
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th scope="row">
                    <label for="flexievent_settings[currency_code]">Currency Code (ISO 4217)</label>
                </th>
                <td>
                    <input
                        type="text"
                        name="flexievent_settings[currency_code]"
                        id="flexievent_settings[currency_code]"
                        value="<?php echo esc_attr( get_option( 'flexievent_currency_code', 'GBP' ) ); ?>"
                        class="regular-text"
                    />
                </td>
            </tr>
        </tbody>
    </table>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const symbolSelect = document.getElementById('flexievent_settings[currency_symbol]');
        const codeInput = document.getElementById('flexievent_settings[currency_code]');

        const symbolToCode = {
            '£': 'GBP',
            '$': 'USD',
            '€': 'EUR',
            'A$': 'AUD',
            'NZ$': 'NZD',
            'HK$': 'HKD',
            'SGD': 'SGD',
            'MX$': 'MXN',
            'TWD': 'TWD',
            'SAR': 'SAR',
            'EGP': 'EGP',
            '؋': 'AFN',
            'R$': 'BRL',
            'C$': 'CAD',
            '¥': 'JPY',
            'CN¥': 'CNY',
            'Kč': 'CZK',
            'kr (DKK)': 'DKK',
            'kr (NOK)': 'NOK',
            'kr (SEK)': 'SEK',
            '₹': 'INR',
            'Rp': 'IDR',
            '₪': 'ILS',
            '₩': 'KRW',
            'RM': 'MYR',
            '₦': 'NGN',
            '₱': 'PHP',
            'zł': 'PLN',
            'руб': 'RUB',
            'R': 'ZAR',
            'Fr': 'CHF',
            '฿': 'THB',
            '₺': 'TRY',
            '₴': 'UAH',
            'د.إ': 'AED'
        };

        symbolSelect.addEventListener('change', function () {
            const selectedSymbol = symbolSelect.value;
            if (symbolToCode[selectedSymbol]) {
                codeInput.value = symbolToCode[selectedSymbol];
            }
        });
    });
    </script>
    <?php
});

// --- TPW Core Settings integration: Payment Methods tab content ---
if ( ! function_exists( 'tpw_core_payments_get_frontend_detail_method_map' ) ) {
    function tpw_core_payments_get_frontend_detail_method_map() {
        $base = defined( 'TPW_CORE_PATH' )
            ? TPW_CORE_PATH . 'modules/payments/'
            : plugin_dir_path( __FILE__ );

        return [
            'bacs' => [
                'label'    => __( 'Bank Transfer', 'tpw-core' ),
                'file'     => $base . 'class-tpw-bacs-settings.php',
                'callback' => [ 'TPW_BACS_Settings', 'render_settings_form' ],
            ],
            'cheque' => [
                'label'    => __( 'Cheque', 'tpw-core' ),
                'file'     => $base . 'class-tpw-cheque-settings.php',
                'callback' => [ 'TPW_Cheque_Settings', 'render_settings_form' ],
            ],
            'cash' => [
                'label'    => __( 'Cash', 'tpw-core' ),
                'file'     => $base . 'class-tpw-cash-settings.php',
                'callback' => [ 'TPW_Cash_Settings', 'render_settings_form' ],
            ],
            'card-on-the-day' => [
                'label'    => __( 'Card on the day', 'tpw-core' ),
                'file'     => $base . 'class-tpw-card-on-the-day-settings.php',
                'callback' => [ 'TPW_Card_On_The_Day_Settings', 'render_settings_form' ],
            ],
        ];
    }
}

if ( ! function_exists( 'tpw_core_payments_get_method_label' ) ) {
    function tpw_core_payments_get_method_label( $method_slug ) {
        $method_slug = sanitize_key( (string) $method_slug );
        $method_map  = tpw_core_payments_get_frontend_detail_method_map();

        if ( isset( $method_map[ $method_slug ]['label'] ) ) {
            return (string) $method_map[ $method_slug ]['label'];
        }

        $fixed_names = [
            'square'      => __( 'Square', 'tpw-core' ),
            'sumup'       => __( 'SumUp', 'tpw-core' ),
            'woocommerce' => __( 'WooCommerce', 'tpw-core' ),
        ];

        if ( isset( $fixed_names[ $method_slug ] ) ) {
            return (string) $fixed_names[ $method_slug ];
        }

        global $wpdb;
        if ( isset( $wpdb->prefix ) ) {
            $table_name = $wpdb->prefix . 'tpw_payment_methods';
            $label      = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$table_name} WHERE slug = %s LIMIT 1", $method_slug ) );

            if ( is_string( $label ) && '' !== $label ) {
                return $label;
            }
        }

        return ucwords( str_replace( [ '-', '_' ], ' ', $method_slug ) );
    }
}

if ( ! function_exists( 'tpw_core_payments_get_frontend_fallback_message' ) ) {
    function tpw_core_payments_get_frontend_fallback_message( $method_slug ) {
        switch ( sanitize_key( (string) $method_slug ) ) {
            case 'sumup':
                return __( 'SumUp currently keeps its existing admin-only credential and OAuth flow. A front-end-safe wrapper is not available yet, so this method temporarily falls back to the existing admin page.', 'tpw-core' );

            case 'square':
                if ( function_exists( 'tpw_core_get_square_settings_route_owner' ) && 'addon' === tpw_core_get_square_settings_route_owner() ) {
                    return __( 'Square settings are currently owned by the TPW Square Gateway add-on. Use the existing admin route until a front-end-safe compatibility wrapper is available.', 'tpw-core' );
                }

                return __( 'Square configuration includes credential fields and still relies on the existing admin settings surface. Use the admin route for now.', 'tpw-core' );

            case 'woocommerce':
                return class_exists( 'WooCommerce' )
                    ? __( 'WooCommerce payment configuration remains owned by WooCommerce. Use the existing admin route for now.', 'tpw-core' )
                    : __( 'WooCommerce is not active on this site, so there is no front-end configuration screen to render here.', 'tpw-core' );

            default:
                return __( 'This payment method is registered, but a front-end-safe configuration screen is not available yet. Use the existing admin route for now.', 'tpw-core' );
        }
    }
}

if ( ! function_exists( 'tpw_core_payments_render_frontend_method_detail' ) ) {
    function tpw_core_payments_render_frontend_method_detail( $method_slug ) {
        $method_slug     = sanitize_key( (string) $method_slug );
        $method_map      = tpw_core_payments_get_frontend_detail_method_map();
        $method_label    = tpw_core_payments_get_method_label( $method_slug );
        $back_url        = function_exists( 'tpw_core_get_payment_methods_settings_url' ) ? tpw_core_get_payment_methods_settings_url() : '';
        $admin_url       = function_exists( 'tpw_core_build_payment_method_admin_url' ) ? tpw_core_build_payment_method_admin_url( $method_slug ) : admin_url( 'options-general.php?page=tpw-core-settings&tab=payment-methods' );
        $can_manage_page = current_user_can( 'manage_options' );

        static $did_styles = false;
        if ( ! $did_styles ) {
            $did_styles = true;
            echo '<style>
            .tpw-payment-method-detail__header{margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid #d9e2ec;}
            .tpw-payment-method-detail__eyebrow{margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#64748b;}
            .tpw-payment-method-detail__header-row{display:flex;gap:16px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;}
            .tpw-payment-method-detail__title{margin:0;font-size:1.6rem;line-height:1.2;}
            .tpw-payment-method-detail__intro{margin:8px 0 0;color:#475569;max-width:56ch;}
            </style>';
        }

        if ( ! $can_manage_page && function_exists( 'tpw_core_user_can' ) ) {
            $can_manage_page = tpw_core_user_can( 'tpw_payments_methods_manage' );
        }

        echo '<div class="tpw-payment-method-detail">';
        echo '<div class="tpw-payment-method-detail__header">';
        echo '<p class="tpw-payment-method-detail__eyebrow">' . esc_html__( 'Payment Methods', 'tpw-core' ) . '</p>';
        echo '<div class="tpw-payment-method-detail__header-row">';
        echo '<div>';
        echo '<h4 class="tpw-payment-method-detail__title">' . esc_html( $method_label ) . '</h4>';
        echo '<p class="tpw-payment-method-detail__intro">' . esc_html__( 'Configure this payment method without leaving the FlexiClub Settings workspace.', 'tpw-core' ) . '</p>';
        echo '</div>';
        if ( '' !== $back_url ) {
            echo '<a class="button button-secondary" href="' . esc_url( $back_url ) . '">' . esc_html__( 'Back to Payment Methods', 'tpw-core' ) . '</a>';
        }
        echo '</div>';
        echo '</div>';

        if ( ! $can_manage_page ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to configure payment methods.', 'tpw-core' ) . '</p></div>';
            echo '</div>';
            return true;
        }

        if ( isset( $method_map[ $method_slug ] ) ) {
            $config = $method_map[ $method_slug ];

            if ( ! empty( $config['file'] ) && ! is_callable( $config['callback'] ) && file_exists( $config['file'] ) ) {
                require_once $config['file'];
            }

            if ( is_callable( $config['callback'] ) ) {
                call_user_func( $config['callback'] );
                echo '</div>';
                return true;
            }
        }

        echo '<div class="notice notice-warning"><p>' . esc_html( tpw_core_payments_get_frontend_fallback_message( $method_slug ) ) . '</p></div>';

        if ( '' !== $admin_url ) {
            echo '<p><a class="button button-primary" href="' . esc_url( $admin_url ) . '">' . esc_html__( 'Open Admin Settings', 'tpw-core' ) . '</a></p>';
        }

        echo '</div>';

        return true;
    }
}

add_action( 'tpw_core_settings_tab_content_payment-methods', function( $active_tab ) {
    $can_manage = function_exists( 'tpw_core_current_user_can_manage_settings' )
        ? tpw_core_current_user_can_manage_settings()
        : current_user_can( 'manage_options' );

    if ( ! $can_manage ) {
        echo '<p>' . esc_html__( 'You do not have permission to view this page.', 'tpw-core' ) . '</p>';
        return;
    }

    // Load the existing Payments admin screen renderer (used by the standalone page too).
    $admin_class_file = defined( 'TPW_CORE_PATH' )
        ? TPW_CORE_PATH . 'modules/payments/class-tpw-payments-admin.php'
        : plugin_dir_path( __FILE__ ) . 'class-tpw-payments-admin.php';

    if ( file_exists( $admin_class_file ) ) {
        require_once $admin_class_file;
    }

    $settings_context = function_exists( 'tpw_core_get_settings_view_context' )
        ? tpw_core_get_settings_view_context()
        : [];
    $settings_mode    = isset( $settings_context['mode'] ) ? sanitize_key( (string) $settings_context['mode'] ) : 'admin';
    $method_slug      = isset( $_GET['payment-method'] ) ? sanitize_key( wp_unslash( $_GET['payment-method'] ) ) : '';

    if ( 'frontend' === $settings_mode && '' !== $method_slug ) {
        tpw_core_payments_render_frontend_method_detail( $method_slug );
        return;
    }

    if ( class_exists( 'TPW_Payments_Admin' ) && method_exists( 'TPW_Payments_Admin', 'render_manage_methods_content' ) ) {
        TPW_Payments_Admin::render_manage_methods_content();
        return;
    }

    echo '<p>' . esc_html__( 'Payment Methods UI is unavailable (missing admin renderer).', 'tpw-core' ) . '</p>';
}, 10, 1 );