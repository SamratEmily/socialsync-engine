<?php

namespace SocialSyncEngine;

/**
 * Handles script and style enqueueing for the admin dashboard.
 */
class Assets {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    /**
     * Enqueue admin scripts and styles on the plugin's own page.
     *
     * @param string $hook_suffix Current admin page hook.
     */
    public function enqueue_admin_scripts( string $hook_suffix ): void {
        if ( ! str_contains( $hook_suffix, 'socialsync-engine' ) ) {
            return;
        }

        $asset_file = SOCIALSYNC_ENGINE_BUILD_DIR . '/admin.asset.php';
        $asset      = file_exists( $asset_file )
            ? require $asset_file
            : [ 'dependencies' => [], 'version' => SOCIALSYNC_ENGINE_VERSION ];

        wp_enqueue_script(
            'socialsync-engine-admin',
            SOCIALSYNC_ENGINE_BUILD_URL . '/admin.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        wp_enqueue_style(
            'socialsync-engine-admin',
            SOCIALSYNC_ENGINE_BUILD_URL . '/admin.css',
            [],
            $asset['version']
        );

        // Pass data to the React app.
        wp_localize_script(
            'socialsync-engine-admin',
            'socialSyncEngine',
            [
                'nonce'       => wp_create_nonce( 'wp_rest' ),
                'apiRoot'     => esc_url_raw( rest_url() ),
                'apiBase'     => esc_url_raw( rest_url( 'socialsync-engine/v1' ) ),
                'oauthBase'   => esc_url_raw( rest_url( 'socialsync-engine/v1/oauth' ) ),
                'adminUrl'    => esc_url_raw( admin_url( 'admin.php?page=socialsync-engine' ) ),
                'pluginUrl'   => esc_url_raw( SOCIALSYNC_ENGINE_PLUGIN_URL ),
                'connections' => [
                    'facebook' => $this->is_platform_connected( 'facebook' ),
                    'linkedin' => $this->is_platform_connected( 'linkedin' ),
                ],
            ]
        );
    }

    /**
     * Check whether a platform token is stored.
     */
    private function is_platform_connected( string $platform ): bool {
        $token = get_option( "socialsync_{$platform}_access_token", '' );
        return ! empty( $token );
    }
}
