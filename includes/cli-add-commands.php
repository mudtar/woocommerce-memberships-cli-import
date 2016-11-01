<?php
/**
 * Register this plugin's WP-CLI commands.
 */

include_once __DIR__ . '/cli/class-wc-memberships-cli-import.php';
WP_CLI::add_command( 'wc memberships import', 'WC_Memberships_CLI_Import' );
