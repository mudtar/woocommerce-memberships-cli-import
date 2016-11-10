<?php
/**
 * Plugin Name: WooCommerce Memberships CLI Import
 * Plugin URI: https://github.com/mudtar/woocommerce-memberships-cli-import
 * Description: Adds a WP-CLI command that functionally piggybacks the WooCommerce Memberships CSV importer.
 * Author: Ian Burton
 * Author URI: https://github.com/mudtar
 */

defined( 'ABSPATH' ) or exit;

// WooCommerce Memberships CLI Import needs to be brought into the fold
// via this framework with the rest of the WooCommerce plugins so that
// it can access the WC_Memberships_CLI_Command class.
if ( !class_exists( 'SV_WC_Framework_Bootstrap' ) ) {
	require_once(
        __DIR__ .
        '/lib/skyverge/woocommerce/class-sv-wc-framework-bootstrap.php'
	);
}

SV_WC_Framework_Bootstrap::instance()->register_plugin(
    '4.4.0',
    __( 'WooCommerce Memberships CLI Import',
        'woocommerce-memberships-cli-import' ),
    __FILE__,
    'init_woocommerce_memberships_cli_import',
    // These compatibility arguments have been carried over from
    // WooCommerce Memberships to ensure a compatibile environment.
    array(
        'minimum_wc_version'   => '2.4.13',
        'minimum_wp_version'   => '4.1',
        'backwards_compatible' => '4.4.0',
    )
);

function init_woocommerce_memberships_cli_import() {
    /**
     * WooCommerce Memberships CLI Import Main Plugin Class
     */
    class WC_Memberships_Extension_CLI_Import extends SV_WC_Plugin {
        /**
         * Plugin ID
         */
        const PLUGIN_ID = 'memberships-cli-import';

        /**
         * Plugin version number
         */
        const VERSION = '1';

        /**
         * @var WC_Memberships_Extension_CLI_Import single instance of
         *                                          this plugin
         */
        protected static $instance;

        /**
         * Initializes the plugin
         */
        public function __construct() {
            parent::__construct(
                self::PLUGIN_ID,
                self::VERSION
            );

            // include required files
            //
            // Set the action priority such that woocommerce-memberships
            // is sure to load before this. This satisfies the
            // dependency on WC_Memberships_CLI_Command.
            add_action(
                'sv_wc_framework_plugins_loaded',
                array( $this, 'includes' ),
                25
            );
        }

        /**
         * Include required files
         */
        public function includes() {
            // WP CLI support
            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                include_once $this->get_plugin_path() .
                    '/includes/cli-add-commands.php';
            }
        }

        /**
         * Load plugin text domain.
         *
         * @see \SV_WC_Plugin::load_translation()
         */
        public function load_translation() {
            load_plugin_textdomain( 'woocommerce-memberships-cli-import' );
        }

        /**
         * Returns the plugin name, localized
         *
         * @see \SV_WC_Plugin::get_plugin_name()
         * @return string the plugin name
         */
        public function get_plugin_name() {
            return __( 'WooCommerce Memberships CLI Import',
                       'woocommerce-memberships-cli-import' );
        }

        /**
         * Returns __FILE__
         *
         * @see \SV_WC_Plugin::get_file()
         * @return string the full path and filename of the plugin file
         */
        protected function get_file() {
            return __FILE__;
        }

        /**
         * Main Memberships CLI Import Instance, ensures only one
         * instance is/can be loaded
         *
         * @see wc_memberships_cli_import()
         * @return \WC_Memberships_Extension_CLI_Import
         */
        public static function instance() {
            if ( is_null( self::$instance ) ) {
                self::$instance = new self();
            }
            return self::$instance;
        }
    }

    // I wanted to replicate the WooCommerce Memberships / SkyVerge
    // WooCommerce Plugin Framework singleton approach with the
    // following:
    //
    //     /**
    //      * Returns the One True Instance of Memberships CLI Import
    //      *
    //      * @return WC_Memberships_Extension_CLI_Import
    //      */
    //     function wc_memberships_cli_import() {
    //         return WC_Memberships_Extension_CLI_Import::instance();
    //     }
    //
    //     // fire it up!
    //     wc_memberships_cli_import();
    //
    // Unfortunately, the PHPDoc for wc_memberships_cli_import was being
    // applied as the annotation for the 'wc memberships import' command
    // rather than the PHPDoc for WC_Memberships_CLI_Import::_invoke(),
    // and I haven't been able to figure out why.
    WC_Memberships_Extension_CLI_Import::instance();
}
