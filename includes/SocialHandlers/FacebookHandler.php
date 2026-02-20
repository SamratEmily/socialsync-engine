<?php

namespace SocialSyncEngine\SocialHandlers;

use GuzzleHttp\Exception\GuzzleException;

/**
 * Facebook Graph API v19.0 handler.
 *
 * Supported scopes: pages_manage_posts, pages_read_engagement,
 *                   publish_video (for video uploads).
 *
 * Token strategy: short-lived user token → long-lived user token (60 days)
 *                 → Page access token (never expires when generated from a
 *                   long-lived user token).
 */
class FacebookHandler extends AbstractSocialHandler {

    private const GRAPH_VERSION = 'v19.0';
    private const GRAPH_BASE    = 'https://graph.facebook.com/' . self::GRAPH_VERSION;
    private const CHUNK_SIZE    = 1_048_576; // 1 MB chunks for resumable video upload.

    /** Option-key prefix stored in wp_options. */
    protected string $option_prefix = 'socialsync_facebook';

    public function get_platform_name(): string {
        return 'Facebook';
    }

    // -------------------------------------------------------------------------
    // OAuth helpers
    // -------------------------------------------------------------------------

    public function get_auth_url( string $state ): string {
        $params = http_build_query( [
            'client_id'     => $this->get_option( 'app_id' ),
            'redirect_uri'  => $this->get_oauth_redirect_uri(),
            'state'         => $state,
            'scope'         => 'pages_manage_posts,pages_read_engagement,publish_video',
            'response_type' => 'code',
        ] );

        return "https://www.facebook.com/dialog/oauth?{$params}";
    }

    public function exchange_code_for_token( string $code ): array {
        // Step 1: Short-lived token.
        try {
            $response = $this->http->get( self::GRAPH_BASE . '/oauth/access_token', [
                'query' => [
                    'client_id'     => $this->get_option( 'app_id' ),
                    'client_secret' => $this->get_option( 'app_secret' ),
                    'redirect_uri'  => $this->get_oauth_redirect_uri(),
                    'code'          => $code,
                ],
            ] );

            $data = json_decode( (string) $response->getBody(), true );

            if ( empty( $data['access_token'] ) ) {
                return $this->error( $data['error']['message'] ?? 'Token exchange failed.' );
            }

            // Step 2: Exchange short-lived token for a long-lived token.
            $ll_response = $this->http->get( self::GRAPH_BASE . '/oauth/access_token', [
                'query' => [
                    'grant_type'        => 'fb_exchange_token',
                    'client_id'         => $this->get_option( 'app_id' ),
                    'client_secret'     => $this->get_option( 'app_secret' ),
                    'fb_exchange_token' => $data['access_token'],
                ],
            ] );

            $ll_data = json_decode( (string) $ll_response->getBody(), true );

            if ( empty( $ll_data['access_token'] ) ) {
                return $this->error( 'Long-lived token exchange failed.' );
            }

            // Step 3: Fetch the managed Page and its never-expiring page token.
            $pages_response = $this->http->get( self::GRAPH_BASE . '/me/accounts', [
                'query' => [ 'access_token' => $ll_data['access_token'] ],
            ] );

            $pages_data = json_decode( (string) $pages_response->getBody(), true );

            if ( empty( $pages_data['data'][0] ) ) {
                // No managed pages – fall back to storing the user token.
                $this->set_option( 'access_token', $ll_data['access_token'] );
                $this->set_option( 'page_id',      '' );
            } else {
                $page = $pages_data['data'][0];
                $this->set_option( 'access_token', $page['access_token'] );
                $this->set_option( 'page_id',      $page['id'] );
                $this->set_option( 'page_name',    $page['name'] );
            }

            return [ 'success' => true ];

        } catch ( GuzzleException $e ) {
            return $this->error( $e->getMessage() );
        }
    }

    public function refresh_token(): bool {
        // Page access tokens generated from a long-lived user token never
        // expire, so no refresh is needed. Return true as a no-op.
        return true;
    }

    public function is_connected(): bool {
        return ! empty( $this->get_access_token() );
    }

    public function disconnect(): void {
        $this->delete_option( 'access_token' );
        $this->delete_option( 'page_id' );
        $this->delete_option( 'page_name' );
    }

    // -------------------------------------------------------------------------
    // Publishing
    // -------------------------------------------------------------------------

    public function post_text( string $caption, array $privacy ): array {
        $page_id = (string) $this->get_option( 'page_id' );

        if ( empty( $page_id ) ) {
            return $this->error( 'No Facebook Page ID configured.' );
        }

        try {
            $response = $this->http->post( self::GRAPH_BASE . "/{$page_id}/feed", [
                'form_params' => [
                    'message'      => $caption,
                    'privacy'      => json_encode( $this->map_privacy( $privacy ) ),
                    'access_token' => $this->get_access_token(),
                ],
            ] );

            $data = json_decode( (string) $response->getBody(), true );

            if ( isset( $data['error'] ) ) {
                return $this->error( $data['error']['message'] );
            }

            return $this->success( $data['id'] ?? '' );

        } catch ( GuzzleException $e ) {
            return $this->error( $e->getMessage() );
        }
    }

    public function post_image(
        string $caption,
        string $file_path,
        string $mime_type,
        array  $privacy
    ): array {
        $page_id = (string) $this->get_option( 'page_id' );

        if ( empty( $page_id ) ) {
            return $this->error( 'No Facebook Page ID configured.' );
        }

        try {
            $response = $this->http->post( self::GRAPH_BASE . "/{$page_id}/photos", [
                'multipart' => [
                    [
                        'name'     => 'source',
                        'contents' => fopen( $file_path, 'rb' ),
                        'filename' => basename( $file_path ),
                        'headers'  => [ 'Content-Type' => $mime_type ],
                    ],
                    [ 'name' => 'caption',       'contents' => $caption ],
                    [ 'name' => 'privacy',        'contents' => json_encode( $this->map_privacy( $privacy ) ) ],
                    [ 'name' => 'access_token',   'contents' => $this->get_access_token() ],
                ],
            ] );

            $data = json_decode( (string) $response->getBody(), true );

            if ( isset( $data['error'] ) ) {
                return $this->error( $data['error']['message'] );
            }

            return $this->success( $data['post_id'] ?? $data['id'] ?? '' );

        } catch ( GuzzleException $e ) {
            return $this->error( $e->getMessage() );
        }
    }

    /**
     * Chunked (resumable) video upload to the Facebook Graph API.
     *
     * Phase 1 – Initialize the upload session.
     * Phase 2 – Transfer each chunk.
     * Phase 3 – Finish the session, then publish via /{page}/videos.
     */
    public function post_video(
        string $caption,
        string $file_path,
        string $mime_type,
        array  $privacy
    ): array {
        $page_id    = (string) $this->get_option( 'page_id' );
        $file_size  = filesize( $file_path );

        if ( empty( $page_id ) ) {
            return $this->error( 'No Facebook Page ID configured.' );
        }

        try {
            // Phase 1: Initialize.
            $init_response = $this->http->post(
                self::GRAPH_BASE . "/{$page_id}/videos",
                [
                    'form_params' => [
                        'upload_phase'  => 'start',
                        'file_size'     => $file_size,
                        'access_token'  => $this->get_access_token(),
                    ],
                ]
            );

            $init = json_decode( (string) $init_response->getBody(), true );

            if ( isset( $init['error'] ) ) {
                return $this->error( $init['error']['message'] );
            }

            $upload_session_id = $init['upload_session_id'];
            $video_id          = $init['video_id'];
            $start_offset      = (int) $init['start_offset'];
            $end_offset        = (int) $init['end_offset'];

            // Phase 2: Transfer chunks.
            $handle = fopen( $file_path, 'rb' );

            while ( $start_offset < $file_size ) {
                fseek( $handle, $start_offset );
                $chunk = fread( $handle, min( self::CHUNK_SIZE, $end_offset - $start_offset ) );

                $transfer_response = $this->http->post(
                    self::GRAPH_BASE . "/{$page_id}/videos",
                    [
                        'multipart' => [
                            [ 'name' => 'upload_phase',       'contents' => 'transfer' ],
                            [ 'name' => 'upload_session_id',  'contents' => $upload_session_id ],
                            [ 'name' => 'start_offset',       'contents' => (string) $start_offset ],
                            [ 'name' => 'access_token',       'contents' => $this->get_access_token() ],
                            [
                                'name'     => 'video_file_chunk',
                                'contents' => $chunk,
                                'filename' => 'chunk',
                                'headers'  => [ 'Content-Type' => 'application/octet-stream' ],
                            ],
                        ],
                    ]
                );

                $transfer = json_decode( (string) $transfer_response->getBody(), true );

                if ( isset( $transfer['error'] ) ) {
                    fclose( $handle );
                    return $this->error( $transfer['error']['message'] );
                }

                $start_offset = (int) $transfer['start_offset'];
                $end_offset   = (int) $transfer['end_offset'];
            }

            fclose( $handle );

            // Phase 3: Finish and publish.
            $finish_response = $this->http->post(
                self::GRAPH_BASE . "/{$page_id}/videos",
                [
                    'form_params' => [
                        'upload_phase'       => 'finish',
                        'upload_session_id'  => $upload_session_id,
                        'description'        => $caption,
                        'privacy'            => json_encode( $this->map_privacy( $privacy ) ),
                        'access_token'       => $this->get_access_token(),
                    ],
                ]
            );

            $finish = json_decode( (string) $finish_response->getBody(), true );

            if ( isset( $finish['error'] ) ) {
                return $this->error( $finish['error']['message'] );
            }

            return $this->success( $video_id );

        } catch ( GuzzleException $e ) {
            return $this->error( $e->getMessage() );
        }
    }

    // -------------------------------------------------------------------------
    // Privacy mapping
    // -------------------------------------------------------------------------

    /**
     * Map a React privacy string to a Facebook Graph API privacy object.
     *
     * React sends:  'public' | 'friends' | 'only_me'
     * FB Graph API: { "value": "EVERYONE" | "ALL_FRIENDS" | "SELF" }
     */
    private function map_privacy( array $privacy ): array {
        $map = [
            'public'  => 'EVERYONE',
            'friends' => 'ALL_FRIENDS',
            'only_me' => 'SELF',
        ];

        $value = $map[ $privacy['value'] ?? 'public' ] ?? 'EVERYONE';

        return [ 'value' => $value ];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function get_oauth_redirect_uri(): string {
        return esc_url_raw( rest_url( 'socialsync-engine/v1/oauth/facebook/callback' ) );
    }
}
