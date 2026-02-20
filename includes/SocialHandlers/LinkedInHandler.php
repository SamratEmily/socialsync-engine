<?php

namespace SocialSyncEngine\SocialHandlers;

use GuzzleHttp\Exception\GuzzleException;

/**
 * LinkedIn API v2 (UGC Posts) handler.
 *
 * Required OAuth scopes: r_liteprofile, w_member_social, r_emailaddress.
 *
 * Media upload flow:
 *   1. Register an upload (get uploadUrl + asset URN).
 *   2. PUT the binary directly to the uploadUrl.
 *   3. Create a UGC post referencing the asset URN.
 *
 * For videos LinkedIn uses a multi-part upload protocol very similar to
 * the image flow but with VIDEO_UPLOAD as the register request type.
 */
class LinkedInHandler extends AbstractSocialHandler {

    private const API_BASE      = 'https://api.linkedin.com/v2';
    private const CHUNK_SIZE    = 4_000_000; // 4 MB – within LinkedIn's 5 MB per-chunk limit.

    protected string $option_prefix = 'socialsync_linkedin';

    public function get_platform_name(): string {
        return 'LinkedIn';
    }

    // -------------------------------------------------------------------------
    // OAuth
    // -------------------------------------------------------------------------

    public function get_auth_url( string $state ): string {
        $params = http_build_query( [
            'response_type' => 'code',
            'client_id'     => $this->get_option( 'client_id' ),
            'redirect_uri'  => $this->get_oauth_redirect_uri(),
            'state'         => $state,
            'scope'         => 'r_liteprofile r_emailaddress w_member_social',
        ] );

        return "https://www.linkedin.com/oauth/v2/authorization?{$params}";
    }

    public function exchange_code_for_token( string $code ): array {
        try {
            $response = $this->http->post( 'https://www.linkedin.com/oauth/v2/accessToken', [
                'form_params' => [
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'redirect_uri'  => $this->get_oauth_redirect_uri(),
                    'client_id'     => $this->get_option( 'client_id' ),
                    'client_secret' => $this->get_option( 'client_secret' ),
                ],
            ] );

            $data = json_decode( (string) $response->getBody(), true );

            if ( empty( $data['access_token'] ) ) {
                return $this->error( $data['error_description'] ?? 'Token exchange failed.' );
            }

            $this->set_option( 'access_token',  $data['access_token'] );
            $this->set_option( 'refresh_token', $data['refresh_token'] ?? '' );
            $this->set_option( 'expires_at',    time() + (int) ( $data['expires_in'] ?? 5183944 ) );

            // Fetch and store the member URN (needed when creating posts).
            $profile = $this->fetch_profile( $data['access_token'] );
            if ( ! empty( $profile['id'] ) ) {
                $this->set_option( 'person_urn', 'urn:li:person:' . $profile['id'] );
            }

            return [ 'success' => true ];

        } catch ( GuzzleException $e ) {
            return $this->error( $e->getMessage() );
        }
    }

    public function refresh_token(): bool {
        $refresh_token = (string) $this->get_option( 'refresh_token' );

        if ( empty( $refresh_token ) ) {
            return false;
        }

        try {
            $response = $this->http->post( 'https://www.linkedin.com/oauth/v2/accessToken', [
                'form_params' => [
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $refresh_token,
                    'client_id'     => $this->get_option( 'client_id' ),
                    'client_secret' => $this->get_option( 'client_secret' ),
                ],
            ] );

            $data = json_decode( (string) $response->getBody(), true );

            if ( empty( $data['access_token'] ) ) {
                return false;
            }

            $this->set_option( 'access_token',  $data['access_token'] );
            $this->set_option( 'refresh_token', $data['refresh_token'] ?? $refresh_token );
            $this->set_option( 'expires_at',    time() + (int) ( $data['expires_in'] ?? 5183944 ) );

            return true;

        } catch ( GuzzleException $e ) {
            return false;
        }
    }

    public function is_connected(): bool {
        return ! empty( $this->get_access_token() );
    }

    public function disconnect(): void {
        $this->delete_option( 'access_token' );
        $this->delete_option( 'refresh_token' );
        $this->delete_option( 'expires_at' );
        $this->delete_option( 'person_urn' );
    }

    // -------------------------------------------------------------------------
    // Publishing
    // -------------------------------------------------------------------------

    public function post_text( string $caption, array $privacy ): array {
        $person_urn = (string) $this->get_option( 'person_urn' );

        if ( empty( $person_urn ) ) {
            return $this->error( 'LinkedIn person URN not found. Please reconnect.' );
        }

        $body = [
            'author'          => $person_urn,
            'lifecycleState'  => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [ 'text' => $caption ],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $this->map_privacy( $privacy ),
            ],
        ];

        return $this->create_ugc_post( $body );
    }

    public function post_image(
        string $caption,
        string $file_path,
        string $mime_type,
        array  $privacy
    ): array {
        $person_urn = (string) $this->get_option( 'person_urn' );

        if ( empty( $person_urn ) ) {
            return $this->error( 'LinkedIn person URN not found. Please reconnect.' );
        }

        // Step 1: Register the upload.
        $register_result = $this->register_upload( 'IMAGE', $person_urn, filesize( $file_path ) );

        if ( ! $register_result['success'] ) {
            return $register_result;
        }

        // Step 2: Upload the binary.
        $upload_result = $this->upload_binary( $register_result['upload_url'], $file_path );

        if ( ! $upload_result['success'] ) {
            return $upload_result;
        }

        // Step 3: Create the post with the uploaded asset.
        $body = [
            'author'          => $person_urn,
            'lifecycleState'  => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary'    => [ 'text' => $caption ],
                    'shareMediaCategory' => 'IMAGE',
                    'media'              => [
                        [
                            'status'      => 'READY',
                            'media'       => $register_result['asset'],
                            'title'       => [ 'text' => basename( $file_path ) ],
                        ],
                    ],
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $this->map_privacy( $privacy ),
            ],
        ];

        return $this->create_ugc_post( $body );
    }

    /**
     * LinkedIn video upload:
     *   1. Register with VIDEO_UPLOAD type to get multipart upload URLs.
     *   2. Upload each part (PUT to individual signed URLs).
     *   3. Complete the upload session.
     *   4. Create the UGC post.
     */
    public function post_video(
        string $caption,
        string $file_path,
        string $mime_type,
        array  $privacy
    ): array {
        $person_urn = (string) $this->get_option( 'person_urn' );
        $file_size  = filesize( $file_path );

        if ( empty( $person_urn ) ) {
            return $this->error( 'LinkedIn person URN not found. Please reconnect.' );
        }

        // Step 1: Register upload – LinkedIn returns per-part signed upload URLs.
        $register_result = $this->register_upload( 'VIDEO', $person_urn, $file_size );

        if ( ! $register_result['success'] ) {
            return $register_result;
        }

        // Step 2: Upload each chunk using the provided signed URLs.
        $part_etags = [];
        $handle     = fopen( $file_path, 'rb' );

        foreach ( $register_result['upload_urls'] as $index => $signed_url ) {
            $chunk = fread( $handle, self::CHUNK_SIZE );

            try {
                $part_response = $this->http->put( $signed_url, [
                    'body'    => $chunk,
                    'headers' => [
                        'Content-Type' => 'application/octet-stream',
                    ],
                ] );

                $etag = $part_response->getHeaderLine( 'ETag' );
                if ( $etag ) {
                    $part_etags[] = [ 'partNumber' => $index + 1, 'etag' => $etag ];
                }

            } catch ( GuzzleException $e ) {
                fclose( $handle );
                return $this->error( 'Video part upload failed: ' . $e->getMessage() );
            }
        }

        fclose( $handle );

        // Step 3: Complete the upload.
        $complete_result = $this->complete_video_upload(
            $register_result['upload_token'],
            $part_etags
        );

        if ( ! $complete_result['success'] ) {
            return $complete_result;
        }

        // Step 4: Create the UGC post.
        $body = [
            'author'          => $person_urn,
            'lifecycleState'  => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary'    => [ 'text' => $caption ],
                    'shareMediaCategory' => 'VIDEO',
                    'media'              => [
                        [
                            'status'  => 'READY',
                            'media'   => $register_result['asset'],
                            'title'   => [ 'text' => basename( $file_path ) ],
                        ],
                    ],
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $this->map_privacy( $privacy ),
            ],
        ];

        return $this->create_ugc_post( $body );
    }

    // -------------------------------------------------------------------------
    // LinkedIn API internals
    // -------------------------------------------------------------------------

    /**
     * Register a media upload with the LinkedIn API.
     *
     * @param string $media_type  'IMAGE' or 'VIDEO'.
     * @param string $person_urn  Author URN.
     * @param int    $file_size   Size of the file in bytes.
     * @return array
     */
    private function register_upload( string $media_type, string $person_urn, int $file_size ): array {
        $register_type = $media_type === 'VIDEO'
            ? 'com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest'
            : 'com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest';

        try {
            $response = $this->http->post( self::API_BASE . '/assets?action=registerUpload', [
                'headers' => $this->auth_headers(),
                'json'    => [
                    'registerUploadRequest' => [
                        'owner'                  => $person_urn,
                        'recipes'                => [ "urn:li:digitalmediaRecipe:feedshare-{$media_type}" ],
                        'serviceRelationships'   => [
                            [
                                'identifier'       => 'urn:li:userGeneratedContent',
                                'relationshipType' => 'OWNER',
                            ],
                        ],
                        'supportedUploadMechanism' => [ 'MULTIPART_UPLOAD' ],
                    ],
                ],
            ] );

            $data = json_decode( (string) $response->getBody(), true );

            if ( $response->getStatusCode() !== 200 ) {
                return $this->error( $data['message'] ?? 'Register upload failed.' );
            }

            $upload_mechanism = $data['value']['uploadMechanism'] ?? [];
            $multipart        = $upload_mechanism['com.linkedin.digitalmedia.uploading.MultipartUpload'] ?? null;

            if ( $media_type === 'VIDEO' && $multipart ) {
                // Build per-part signed URL list.
                $parts       = $multipart['partUploadRequests'] ?? [];
                $upload_urls = array_map( fn( $p ) => $p['transferUrl'], $parts );

                return [
                    'success'      => true,
                    'asset'        => $data['value']['asset'],
                    'upload_token' => $multipart['metadata']['multipartUploadToken'] ?? '',
                    'upload_urls'  => $upload_urls,
                ];
            }

            // Image – single upload URL.
            $single_url = $data['value']['uploadMechanism']
                ['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']
                ['uploadUrl'] ?? '';

            return [
                'success'    => true,
                'asset'      => $data['value']['asset'],
                'upload_url' => $single_url,
            ];

        } catch ( GuzzleException $e ) {
            return $this->error( $e->getMessage() );
        }
    }

    /** Upload an image binary via HTTP PUT to the signed URL. */
    private function upload_binary( string $upload_url, string $file_path ): array {
        try {
            $this->http->put( $upload_url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->get_access_token(),
                    'Content-Type'  => 'application/octet-stream',
                ],
                'body' => fopen( $file_path, 'rb' ),
            ] );

            return [ 'success' => true ];

        } catch ( GuzzleException $e ) {
            return $this->error( $e->getMessage() );
        }
    }

    /** Finalise a LinkedIn multipart video upload. */
    private function complete_video_upload( string $upload_token, array $part_etags ): array {
        try {
            $this->http->post( self::API_BASE . '/assets?action=completeMultiPartUpload', [
                'headers' => $this->auth_headers(),
                'json'    => [
                    'completeMultipartUploadRequest' => [
                        'metadata'   => [ 'multipartUploadToken' => $upload_token ],
                        'partUploadResponses' => $part_etags,
                    ],
                ],
            ] );

            return [ 'success' => true ];

        } catch ( GuzzleException $e ) {
            return $this->error( $e->getMessage() );
        }
    }

    /** Create a UGC post and return the post ID on success. */
    private function create_ugc_post( array $body ): array {
        try {
            $response = $this->http->post( self::API_BASE . '/ugcPosts', [
                'headers' => $this->auth_headers(),
                'json'    => $body,
            ] );

            $data = json_decode( (string) $response->getBody(), true );

            if ( $response->getStatusCode() !== 201 ) {
                return $this->error( $data['message'] ?? 'Post creation failed.' );
            }

            return $this->success( $data['id'] ?? '' );

        } catch ( GuzzleException $e ) {
            return $this->error( $e->getMessage() );
        }
    }

    /** Fetch the authenticated member's profile. */
    private function fetch_profile( string $token ): array {
        try {
            $response = $this->http->get( self::API_BASE . '/me', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'X-Restli-Protocol-Version' => '2.0.0',
                ],
            ] );

            return json_decode( (string) $response->getBody(), true ) ?? [];

        } catch ( GuzzleException $e ) {
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // Privacy mapping
    // -------------------------------------------------------------------------

    /**
     * Map a React privacy string to a LinkedIn UGC visibility value.
     *
     * React sends:  'public' | 'connections'
     * LinkedIn API: 'PUBLIC'  | 'CONNECTIONS'
     */
    private function map_privacy( array $privacy ): string {
        $map = [
            'public'      => 'PUBLIC',
            'connections' => 'CONNECTIONS',
        ];

        return $map[ $privacy['value'] ?? 'public' ] ?? 'PUBLIC';
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function auth_headers(): array {
        return [
            'Authorization'              => 'Bearer ' . $this->get_access_token(),
            'Content-Type'               => 'application/json',
            'X-Restli-Protocol-Version'  => '2.0.0',
        ];
    }

    private function get_oauth_redirect_uri(): string {
        return esc_url_raw( rest_url( 'socialsync-engine/v1/oauth/linkedin/callback' ) );
    }
}
