<?php

namespace SocialSyncEngine\Admin\REST;

use SocialSyncEngine\SocialHandlers\FacebookHandler;
use SocialSyncEngine\SocialHandlers\LinkedInHandler;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Returns the connection status for all registered platforms.
 *
 * Route:
 *   GET /socialsync-engine/v1/status
 */
class StatusController extends WP_REST_Controller {

    protected $namespace = 'socialsync-engine/v1';
    protected $rest_base = 'status';

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_status' ],
                'permission_callback' => [ $this, 'admin_check' ],
            ],
        ] );
    }

    public function get_status( WP_REST_Request $request ): WP_REST_Response {
        $facebook = new FacebookHandler();
        $linkedin = new LinkedInHandler();

        return rest_ensure_response( [
            'facebook' => [
                'connected' => $facebook->is_connected(),
                'page_name' => get_option( 'socialsync_facebook_page_name', '' ),
            ],
            'linkedin' => [
                'connected'  => $linkedin->is_connected(),
                'person_urn' => get_option( 'socialsync_linkedin_person_urn', '' ),
            ],
        ] );
    }

    public function admin_check(): bool {
        return current_user_can( 'manage_options' );
    }
}
