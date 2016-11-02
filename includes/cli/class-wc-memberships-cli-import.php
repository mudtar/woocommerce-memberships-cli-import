<?php
/**
 * Import a CSV
 */
class WC_Memberships_CLI_Import extends WC_Memberships_CLI_Command {
    /**
     * Import a CSV
     *
     * Importing members will create or update automatically User Memberships in bulk. Importing members does not create any associated billing, subscription or order records.
     *
     * ## OPTIONS
     *
     * <file>
     * : The path to the file to import.
     * Acceptable file types: CSV or tab-delimited text files.
     *
     * [--merge_existing_user_memberships=<bool>]
     * : Update existing records if a matching user membership is found (by User Membership ID)
     * Default: true
     *
     * [--allow_memberships_transfer=<bool>]
     * : (Only available when merge_existing_user_memberships is true)
     * Allow membership transfer between users if the imported user differs from the existing user for the membership (skips conflicting rows when disabled)
     * Default: false
     *
     * [--create_new_user_memberships=<bool>]
     * : Create new user memberships if a matching User Membership ID is not found (skips rows when disabled)
     * Default: true
     *
     * [--create_new_users=<bool>]
     * : (Only available when create_new_user_memberships is true)
     * Create a new user if no matching user is found (skips rows when disabled)
     * Default: false
     *
     * [--default_start_date=<date>]
     * : When creating new memberships, you can specify a default date to set a membership start date if not defined in the import data.
     * Default: today's date
     *
     * [--timezone=<timezone>]
     * : The timezone the dates in the import are from.
     * Example: UTC
     * Default: site timezone
     *
     * [--input_fields_delimiter=<delimiter>]
     * : The delimiter that separates the fields in the file. Change the delimiter based on your input file format.
     * Acceptable values: comma, tab
     * Default: comma
     *
     * ## EXAMPLES
     *
     *     # Use a user with suitable permissions
     *     # Load a file on startup that defines WP_ADMIN as true
     *     $ wp wc memberships import file.csv --user=wp.admin@example.com
     *       --require=define-wp_admin-true.php
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke( $args, $assoc_args ) {

        if ( !is_admin() ) {
            WP_CLI::error(
                "This command must be run within the context of the " .
                "WordPress administration screens. In order to do so, use " .
                "WP-CLI's command line paramter --require to include a file " .
                "that defines the WP_ADMIN constant as true." );
        }

        $filename = $args[0];

        $defaults = array(
            'merge_existing_user_memberships' => true,
            'allow_memberships_transfer'      => false,
            'create_new_user_memberships'     => true,
            'create_new_users'                => false,
            // WC_Memberships_CSV_Import_User_Memberships fills this in
            // automatically as today's date if null.
            'default_start_date'              => null,
            'timezone'                        =>
                get_option( 'timezone_string' ),
            'input_fields_delimiter'          => 'comma',
        );
        $assoc_args = wp_parse_args( $assoc_args, $defaults );

        // For the following conditions, it turns out that even when a
        // checkbox is hidden, its value goes unchanged. i.e. when a box
        // has been checked and then becomes hidden by the unchecking of
        // the box it is dependent upon, a "true" value is still POSTed.
        /*
        // On the WooCommerce Memberships "Import from CSV" admin page,
        // the checkbox corresponding to allow_memberships_transfer is
        // hidden when the checkbox corresponding to
        // merge_existing_user_memberships is unchecked.
        if ( $assoc_args['merge_existing_user_memberships'] ) {
            $assoc_args['allow_memberships_transfer'] = false;
        }

        // On the WooCommerce Memberships "Import from CSV" admin page,
        // the checkbox corresponding to create_new_users is hidden when
        // the checkbox corresponding to create_new_user_memberships is
        // unchecked.
        if ( $assoc_args['create_new_user_memberships'] ) {
            $assoc_args['create_new_users'] = false;
        }
        */

        // The following HTTP POST variables emulate the environment of
        // a submitted WooCommerce Memberships CSV Import form. Their
        // relevance in the woocommerce-memberships control flow begins
        // at WP_Memberships_Admin::process_import_export_form().
        $_POST['MAX_FILE_SIZE']                                                     =
            2097152;
        $_POST['wc_memberships_members_csv_import_merge_existing_user_memberships'] =
            $assoc_args['merge_existing_user_memberships'];
        $_POST['wc_memberships_members_csv_import_allow_memberships_transfer']      =
            $assoc_args['allow_memberships_transfer'];
        $_POST['wc_memberships_members_csv_import_create_new_user_memberships']     =
            $assoc_args['create_new_user_memberships'];
        $_POST['wc_memberships_members_csv_import_create_new_users']                =
            $assoc_args['create_new_users'];
        $_POST['wc_memberships_members_csv_import_default_start_date']              =
            $assoc_args['default_start_date'];
        $_POST['wc_memberships_members_csv_import_timezone']                        =
            $assoc_args['timezone'];
        $_POST['wc_memberships_members_csv_import_fields_delimiter']                =
            $assoc_args['input_fields_delimiter'];
        $_POST['action']                                                            =
            'wc_memberships_csv_import_user_memberships';
        $_POST['_wpnonce']                                                          =
            wp_create_nonce( $_POST['action'] );
        $_POST['_wp_http_referer']                                                  =
            '/wp-admin/admin.php?page=wc_memberships_import_export' .
            '&section=csv_import_user_memberships';

        $this->superglobal_files_append(
            'wc_memberships_members_csv_import_file',
            $filename,
            'text/csv'
        );

        // The check_admin_referer() call in
        // WC_Memberships_Admin::process_import_export_form() looks for
        // the nonce in $_REQUEST. This superglobal is created upon a
        // true HTTP request, but since we're spoofing, we have to
        // create it ourselves.
        $this->superglobal_request_create();

        // When the CSV import action is triggered and
        // WC_Memberships_Admin::process_import_export_form() reaches
        // its end, a redirect is initiated and there is a call to exit.
        // This shutdown callback is the only way to handle what happens
        // after that exit call.
        register_shutdown_function( function() {
            $message_handler =
                wc_memberships()->get_admin_instance()->get_message_handler();

            foreach ( $message_handler->get_errors() as $error ) {
                WP_CLI::error( $error, false );
            }

            foreach ( $message_handler->get_messages() as $message ) {
                WP_CLI::log( $message );
            }
        } );

        do_action( 'admin_post_wc_memberships_csv_import_user_memberships' );
    }

    /**
     * Add a new file to the $_FILES superglobal as if it had been
     * uploaded via an HTTP request.
     *
     * This is built to properly indicate file upload error messages,
     * but it doesn't handle filesize restrictions, or transfer or write
     * errors.
     *
     * @param string $file_id
     * @param string $filename
     * @param string $type     MIME Content-Type of the file
     * @see http://php.net/manual/en/reserved.variables.files.php
     */
    protected function superglobal_files_append( $file_id, $filename, $type ) {
        /**
         * Append a properly formed element to $_FILES.
         *
         * @param string  $file_id  the array key to use for the file
         * @param string  $filename the path to the "uploaded" file
         * @param string  $type     the MIME type of the file
         * @param string  $tmp_name the path to the tmp file created to
         *                          store the "uploaded" file
         * @param integer $error    the file upload error reflecting the
         *                          status of the upload. Defaults to
         *                          UPLOAD_ERR_OK.
         * @see http://php.net/manual/en/features.file-upload.errors.php
         */
        $do_files_append = function( $file_id, $filename, $type, $tmp_name,
                                     $error = UPLOAD_ERR_OK ) {
            $_FILES[ $file_id ] = array(
                'name'     => basename( $filename ),
                'type'     => $type,
                'tmp_name' => $tmp_name,
                'error'    => $error,
                'size'     => filesize( $tmp_name ),
            );
        };

        // Create a uniquely named file in the INI-designated temporary
        // directory for file uploads. If that isn't designated, put it
        // in the system temporary directory.
        $tmp_name = tempnam(
            ini_get( 'upload_tmp_dir' ) ? ini_get( 'upload_tmp_dir' ) :
                sys_get_temp_dir(),
            'php'
        );

        if ( false === $tmp_name ) {
            // Failed to create a temporary file.
            $do_files_append( $file_id, $filename, $type, null,
                              UPLOAD_ERR_NO_TMP_DIR );
            return;
        }

        // Get the contents of the "uploaded" file.
        $uploaded_file = file_get_contents( $filename );

        if ( false === $uploaded_file ) {
            // Failed to read the "uploaded" file.
            $do_files_append( $file_id, $filename, $type, null,
                              UPLOAD_ERR_NO_FILE );
            return;
        }

        // Copy the contents of the "uploaded" file into the temporary
        // file.
        $bytes_written = file_put_contents( $tmp_name, $uploaded_file );

        if ( false === $bytes_written ) {
            // Failed to write the "uploaded" file to the temporary
            // file.
            $do_files_append( $file_id, $filename, $type, null,
                              UPLOAD_ERR_CANT_WRITE );
            return;
        }

        // If no error condition was reached, this will append the file
        // to the $_FILES superglobal array with an error code of
        // UPLOAD_ERR_OK.
        $do_files_append( $file_id, $filename, $type, $tmp_name );
    }

    /**
     * Create a new $_REQUEST superglobal in the same way PHP does.
     *
     * @see http://php.net/manual/en/reserved.variables.request.php
     */
    protected function superglobal_request_create() {
        $_REQUEST = array();

        if ( !empty( ini_get( 'request_order' ) ) ) {
            $order = ini_get( 'request_order' );
        }
        elseif ( !empty( ini_get( 'variables_order' ) ) ) {
            $order = ini_get( 'variables_order' );
        }
        else {
            $order = 'EGPCS';
        }

        $merge_into_request = function( $mergee ) {
            $_REQUEST = array_merge( $_REQUEST, $mergee );
        };

        foreach ( str_split( $order ) as $superglobal ) {
            switch ( $superglobal ) {
            case 'E':
                $merge_into_request( $_ENV );
                break;
            case 'G':
                $merge_into_request( $_GET );
                break;
            case 'P':
                $merge_into_request( $_POST );
                break;
            case 'C':
                $merge_into_request( $_COOKIE );
                break;
            case 'S':
                $merge_into_request( $_SERVER );
                break;
            }
        }
    }
}
