<?php

namespace SocialSyncEngine\Admin;

/**
 * Registers the WordPress admin menu pages.
 */
class AdminMenu {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menu' ] );
    }

    public function register_menu(): void {
        add_menu_page(
            __( 'SocialSync Engine', 'socialsync-engine' ),
            __( 'SocialSync', 'socialsync-engine' ),
            'manage_options',
            'socialsync-engine',
            [ $this, 'render_page' ],
            'dashicons-share',
            56
        );
    }

    /**
     * Render the single-page-app container.
     */
    public function render_page(): void {
        echo '<div id="socialsync-engine-app" class="wrap"></div>';
    }
}
