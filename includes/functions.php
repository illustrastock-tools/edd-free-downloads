<?php
/**
 * Helper functions
 *
 * @package     EDD\FreeDownloads\Functions
 * @since       1.0.0
 */


// Exit if accessed directly
if( ! defined( 'ABSPATH' ) ) exit;


/**
 * Process downloads
 *
 * @since       1.0.0
 * @return      void
 */
function edd_free_download_process() {

    // No spammers please!
    if( ! empty( $_POST['edd_free_download_check'] ) ) {
        wp_die( __( 'Bad spammer, no download!', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
    }

    if( ! wp_verify_nonce( $_POST['edd_free_download_nonce'], 'edd_free_download_nonce' ) ) {
        wp_die( __( 'Cheatin&#8217; huh?', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
    }

    if ( ! isset( $_POST['edd_free_download_email'] ) || ! is_email( $_POST['edd_free_download_email'] ) ) {
        wp_die( __( 'An internal error has occurred, please try again or contact support.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
    }

    $email       = strip_tags( trim( $_POST['edd_free_download_email'] ) );
    $user        = get_user_by( 'email', $email );

    // No banned emails please!
    if( edd_is_email_banned( $email ) ) {
        wp_die( __( 'An internal error has occurred, please try again or contact support.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
    }

    $download_id = isset( $_POST['edd_free_download_id'] ) ? intval( $_POST['edd_free_download_id'] ) : false;
    if ( empty( $download_id ) ) {
        wp_die( __( 'An internal error has occurred, please try again or contact support.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
    }

    $post_type = get_post_type( $download_id );
    if ( 'download' !== $post_type ) {
        wp_die( __( 'An internal error has occurred, please try again or contact support.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
    }

    if( isset( $_POST['edd_free_download_fname'] ) ) {
        $user_first = sanitize_text_field( $_POST['edd_free_download_fname'] );
    } else {
        $user_first = $user ? $user->first_name : '';
    }

    if( isset( $_POST['edd_free_download_lname'] ) ) {
        $user_last = sanitize_text_field( $_POST['edd_free_download_lname'] );
    } else {
        $user_last = $user ? $user->last_name : '';
    }

    $user_info = array(
        'id'        => 0,
        'email'     => $email,
        'first_name'=> $user_first,
        'last_name' => $user_last,
        'discount'  => 'none'
    );

    $cart_details   = array();
    $download_files = edd_get_download_files( $download_id );
    $item_price     = edd_get_download_price( $download_id );

    if ( ! edd_is_free_download( $download_id ) ) {
        wp_die( __( 'An internal error has occurred, please try again or contact support.', 'edd-free-downloads' ), __( 'Oops!', 'edd-free-downloads' ) );
    }

    $cart_details[0] = array(
        'name'      => get_the_title( $download_id ),
        'id'        => $download_id,
        'price'     => edd_format_amount( 0 ),
        'subtotal'  => edd_format_amount( 0 ),
        'quantity'  => 1,
        'tax'       => edd_format_amount( 0 )
    );

    $date = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) );

    $downloads = array();
    foreach( $download_files as $file ) {
        $downloads[] = array(
            'id'    => $file['attachment_id']
        );
    }

    /**
     * Gateway set to manual because manual + free lists as 'Free Purchase' in order details
     */
    $purchase_data  = array(
        'price'         => edd_format_amount( 0 ),
        'tax'           => edd_format_amount( 0 ),
        'post_date'     => $date,
        'purchase_key'  => strtolower( md5( uniqid() ) ),
        'user_email'    => $email,
        'user_info'     => $user_info,
        'currency'      => edd_get_currency(),
        'downloads'     => $downloads,
        'cart_details'  => $cart_details,
        'gateway'       => 'manual',
        'status'        => 'pending'
    );

    $payment_id = edd_insert_payment( $purchase_data );

    edd_update_payment_status( $payment_id, 'publish' );
    edd_insert_payment_note( $payment_id, __( 'Purchased through EDD Free Downloads', 'edd-free-downloads' ) );
    edd_empty_cart();
    edd_set_purchase_session( $purchase_data );

    $redirect_url = edd_get_option( 'edd_free_downloads_redirect', false );
    $redirect_url = $redirect_url ? esc_url( $redirect_url ) : edd_get_success_page_url();

    wp_redirect( apply_filters( 'edd_free_downloads_redirect', $redirect_url, $payment_id, $purchase_data ) );
    edd_die();
}
add_action( 'edd_free_download_process', 'edd_free_download_process' );


/**
 * Check if a download should use the modal dialog
 *
 * @since       1.0.0
 * @param       int $download_id The ID to check
 * @return      bool $ret True if we should use the modal, false otherwise
 */
function edd_free_downloads_use_modal( $download_id = false ) {
    $ret = false;

    if( $download_id && ! edd_has_variable_prices( $download_id ) && ! edd_is_bundled_product( $download_id ) ) {
        $price = floatval( edd_get_lowest_price_option( $download_id ) );

        if( $price == 0 ) {
            $ret = true;
        }
    }

    return $ret;
}
