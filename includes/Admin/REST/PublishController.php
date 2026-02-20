<?php

namespace SocialSyncEngine\Admin\REST;

use SocialSyncEngine\SocialHandlers\AbstractSocialHandler;
use SocialSyncEngine\SocialHandlers\FacebookHandler;
use SocialSyncEngine\SocialHandlers\LinkedInHandler;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Handles the publish action: receives caption + media file + platform config
 * from the React dashboard and delegates to the correct SocialHandler.
 *
 * Route:
 *   POST /socialsync-engine/v1/publish
 *
 * Expected multipart/form-data body:
 *   caption         string  – The post text.
 *   platforms       JSON    – e.g. ["facebook","linkedin"]
 *   facebook_privacy JSON   – e.g. {"value":"public"}
 *   linkedin_privacy JSON   – e.g. {"value":"connections"}
 *   media           file    – Optional image or video file.
 */
class PublishController extends WP_REST_Controller {

    protected $namespace = 'socialsync-engine/v1';
    protected $rest_base = 'publish';

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/quicktime',
        'video/x-msvideo',
    ];

    private const MAX_IMAGE_BYTES = 10_485_760;  // 10 MB.
    private const MAX_VIDEO_BYTES = 1_073_741_824; // 1 GB.

    /** @var array<string, AbstractSocialHandler> */
    private array $handlers;

    public function __construct() {
        $this->handlers = [
            'facebook' => new FacebookHandler(),
            'linkedin' => new LinkedInHandler(),
        ];
    }

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/' . $this->rest_base, [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'publish' ],
                'permission_callback' => [ $this, 'admin_check' ],
            ],
        ] );
    }

    // -------------------------------------------------------------------------
    // Main handler
    // -------------------------------------------------------------------------

    public function publish( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $caption   = sanitize_textarea_field( $request->get_param( 'caption' ) ?? '' );
        $platforms = json_decode( $request->get_param( 'platforms' ) ?? '[]', true );

        if ( empty( $caption ) ) {
            return new WP_Error( 'missing_caption', 'Caption is required.', [ 'status' => 422 ] );
        }

        if ( empty( $platforms ) || ! is_array( $platforms ) ) {
            return new WP_Error( 'missing_platforms', 'At least one platform must be selected.', [ 'status' => 422 ] );
        }

        $privacy_facebook = json_decode( $request->get_param( 'facebook_privacy' ) ?? '{"value":"public"}', true );
        $privacy_linkedin = json_decode( $request->get_param( 'linkedin_privacy' ) ?? '{"value":"public"}', true );

        // Handle optional media upload.
        $file      = $request->get_file_params()['media'] ?? null;
        $file_path = null;
        $mime_type = null;
        $is_video  = false;

        if ( $file && $file['error'] === UPLOAD_ERR_OK ) {
            $validation = $this->validate_file( $file );

            if ( is_wp_error( $validation ) ) {
                return $validation;
            }

            $file_path = $file['tmp_name'];
            $mime_type = $file['type'];
            $is_video  = str_starts_with( $mime_type, 'video/' );
        }

        // Dispatch to each requested platform.
        $results = [];

        foreach ( $platforms as $platform ) {
            $platform = sanitize_key( $platform );

            if ( ! isset( $this->handlers[ $platform ] ) ) {
                $results[ $platform ] = [ 'success' => false, 'error' => 'Unsupported platform.' ];
                continue;
            }

            $handler = $this->handlers[ $platform ];

            if ( ! $handler->is_connected() ) {
                $results[ $platform ] = [ 'success' => false, 'error' => 'Platform not connected.' ];
                continue;
            }

            $privacy = $platform === 'linkedin' ? $privacy_linkedin : $privacy_facebook;

            if ( $file_path && $mime_type ) {
                $results[ $platform ] = $is_video
                    ? $handler->post_video( $caption, $file_path, $mime_type, $privacy )
                    : $handler->post_image( $caption, $file_path, $mime_type, $privacy );
            } else {
                $results[ $platform ] = $handler->post_text( $caption, $privacy );
            }
        }

        $all_success = array_reduce(
            $results,
            fn( $carry, $item ) => $carry && ( $item['success'] ?? false ),
            true
        );

        return rest_ensure_response( [
            'success' => $all_success,
            'results' => $results,
        ] );
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    private function validate_file( array $file ): true|WP_Error {
        $mime_type = mime_content_type( $file['tmp_name'] );

        if ( ! in_array( $mime_type, self::ALLOWED_MIME_TYPES, true ) ) {
            return new WP_Error(
                'invalid_file_type',
                sprintf( 'File type %s is not allowed.', esc_html( $mime_type ) ),
                [ 'status' => 422 ]
            );
        }

        $is_video = str_starts_with( $mime_type, 'video/' );
        $max_size = $is_video ? self::MAX_VIDEO_BYTES : self::MAX_IMAGE_BYTES;

        if ( $file['size'] > $max_size ) {
            return new WP_Error(
                'file_too_large',
                sprintf( 'File exceeds the %d MB limit.', $max_size / 1_048_576 ),
                [ 'status' => 422 ]
            );
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Permission
    // -------------------------------------------------------------------------

    public function admin_check(): bool {
        return current_user_can( 'manage_options' );
    }
}
