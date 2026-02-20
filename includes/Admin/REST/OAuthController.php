<?php

namespace SocialSyncEngine\Admin\REST;

use SocialSyncEngine\SocialHandlers\FacebookHandler;
use SocialSyncEngine\SocialHandlers\LinkedInHandler;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Handles OAuth 2.0 connect, callback, and disconnect flows.
 *
 * Routes:
 *   GET  /socialsync-engine/v1/oauth/{platform}/connect    – Returns the auth URL.
 *   GET  /socialsync-engine/v1/oauth/{platform}/callback   – Handles the OAuth callback.
 *   POST /socialsync-engine/v1/oauth/{platform}/disconnect – Clears stored tokens.
 *   POST /socialsync-engine/v1/oauth/settings              – Save API credentials (app_id, etc.).
 */
class OAuthController extends WP_REST_Controller {

    protected $namespace = 'socialsync-engine/v1';
    protected $rest_base = 'oauth';

    /** @var array<string, \SocialSyncEngine\SocialHandlers\AbstractSocialHandler> */
    private array $handlers;

    public function __construct() {
        $this->handlers = [
            'facebook' => new FacebookHandler(),
            'linkedin' => new LinkedInHandler(),
        ];
    }

    public function register_routes(): void {
        // Connect – redirects user to the platform's OAuth page.
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<platform>[a-z]+)/connect', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'connect' ],
                'permission_callback' => [ $this, 'admin_check' ],
                'args'                => [ 'platform' => [ 'required' => true, 'type' => 'string' ] ],
            ],
        ] );

        // Callback – receives the authorization code from the platform.
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<platform>[a-z]+)/callback', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'callback' ],
                'permission_callback' => '__return_true', // Public callback URL required by OAuth.
                'args'                => [ 'platform' => [ 'required' => true, 'type' => 'string' ] ],
            ],
        ] );

        // Disconnect – deletes stored tokens.
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<platform>[a-z]+)/disconnect', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'disconnect' ],
                'permission_callback' => [ $this, 'admin_check' ],
                'args'                => [ 'platform' => [ 'required' => true, 'type' => 'string' ] ],
            ],
        ] );

        // Save API credentials (app_id / client_id, app_secret / client_secret).
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/settings', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'save_credentials' ],
                'permission_callback' => [ $this, 'admin_check' ],
            ],
        ] );

        // Retrieve stored API credentials.
        register_rest_route( $this->namespace, '/' . $this->rest_base . '/settings', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_credentials' ],
                'permission_callback' => [ $this, 'admin_check' ],
            ],
        ] );
    }

    // -------------------------------------------------------------------------
    // Handlers
    // -------------------------------------------------------------------------

    /**
     * Return the OAuth authorization URL for a given platform.
     * The React app opens this URL in the same window / a popup.
     */
    public function connect( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $platform = sanitize_key( $request->get_param( 'platform' ) );

        if ( ! isset( $this->handlers[ $platform ] ) ) {
            return new WP_Error( 'invalid_platform', 'Platform not supported.', [ 'status' => 400 ] );
        }

        // Generate and store a CSRF state token.
        $state = wp_generate_password( 32, false );
        set_transient( "socialsync_oauth_state_{$platform}", $state, 10 * MINUTE_IN_SECONDS );

        $auth_url = $this->handlers[ $platform ]->get_auth_url( $state );

        return rest_ensure_response( [ 'auth_url' => $auth_url ] );
    }

    /**
     * Handle the OAuth callback: validate state, exchange code for token,
     * then redirect back to the plugin admin page.
     */
    public function callback( WP_REST_Request $request ): void {
        $platform = sanitize_key( $request->get_param( 'platform' ) );
        $code     = sanitize_text_field( $request->get_param( 'code' ) ?? '' );
        $state    = sanitize_text_field( $request->get_param( 'state' ) ?? '' );
        $admin_url = admin_url( 'admin.php?page=socialsync-engine' );

        if ( ! isset( $this->handlers[ $platform ] ) ) {
            wp_safe_redirect( add_query_arg( 'sse_error', 'invalid_platform', $admin_url ) );
            exit;
        }

        // Validate CSRF state.
        $stored_state = get_transient( "socialsync_oauth_state_{$platform}" );
        delete_transient( "socialsync_oauth_state_{$platform}" );

        if ( empty( $stored_state ) || ! hash_equals( $stored_state, $state ) ) {
            wp_safe_redirect( add_query_arg( 'sse_error', 'state_mismatch', $admin_url ) );
            exit;
        }

        if ( empty( $code ) ) {
            wp_safe_redirect( add_query_arg( 'sse_error', 'no_code', $admin_url ) );
            exit;
        }

        $result = $this->handlers[ $platform ]->exchange_code_for_token( $code );

        if ( $result['success'] ) {
            wp_safe_redirect( add_query_arg( 'sse_connected', $platform, $admin_url ) );
        } else {
            wp_safe_redirect( add_query_arg(
                [ 'sse_error' => 'token_exchange', 'sse_msg' => rawurlencode( $result['error'] ?? '' ) ],
                $admin_url
            ) );
        }

        exit;
    }

    /** Remove stored tokens for a platform. */
    public function disconnect( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $platform = sanitize_key( $request->get_param( 'platform' ) );

        if ( ! isset( $this->handlers[ $platform ] ) ) {
            return new WP_Error( 'invalid_platform', 'Platform not supported.', [ 'status' => 400 ] );
        }

        $this->handlers[ $platform ]->disconnect();

        return rest_ensure_response( [ 'success' => true, 'platform' => $platform ] );
    }

    /** Persist the API credentials for a platform. */
    public function save_credentials( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $platform = sanitize_key( $request->get_param( 'platform' ) ?? '' );

        if ( ! in_array( $platform, [ 'facebook', 'linkedin' ], true ) ) {
            return new WP_Error( 'invalid_platform', 'Platform must be facebook or linkedin.', [ 'status' => 400 ] );
        }

        if ( $platform === 'facebook' ) {
            update_option( 'socialsync_facebook_app_id',     sanitize_text_field( $request->get_param( 'app_id' ) ?? '' ) );
            update_option( 'socialsync_facebook_app_secret', sanitize_text_field( $request->get_param( 'app_secret' ) ?? '' ) );
        } else {
            update_option( 'socialsync_linkedin_client_id',     sanitize_text_field( $request->get_param( 'client_id' ) ?? '' ) );
            update_option( 'socialsync_linkedin_client_secret', sanitize_text_field( $request->get_param( 'client_secret' ) ?? '' ) );
        }

        return rest_ensure_response( [ 'success' => true ] );
    }

    /** Retrieve stored (non-secret) credential labels so the UI can display them. */
    public function get_credentials( WP_REST_Request $request ): WP_REST_Response {
        return rest_ensure_response( [
            'facebook' => [
                'app_id'     => get_option( 'socialsync_facebook_app_id', '' ),
                'app_secret' => ! empty( get_option( 'socialsync_facebook_app_secret', '' ) ) ? '••••••••' : '',
            ],
            'linkedin' => [
                'client_id'     => get_option( 'socialsync_linkedin_client_id', '' ),
                'client_secret' => ! empty( get_option( 'socialsync_linkedin_client_secret', '' ) ) ? '••••••••' : '',
            ],
        ] );
    }

    // -------------------------------------------------------------------------
    // Permission callback
    // -------------------------------------------------------------------------

    public function admin_check(): bool {
        return current_user_can( 'manage_options' );
    }
}
