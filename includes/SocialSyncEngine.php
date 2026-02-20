<?php

namespace SocialSyncEngine;

use SocialSyncEngine\Admin\AdminMenu;
use SocialSyncEngine\Admin\REST\OAuthController;
use SocialSyncEngine\Admin\REST\PublishController;
use SocialSyncEngine\Admin\REST\StatusController;

/**
 * Main plugin class – Singleton.
 *
 * @class SocialSyncEngine
 */
final class SocialSyncEngine {

    /** @var string */
    public string $version = '1.0.0';

    /** @var SocialSyncEngine|null */
    private static ?SocialSyncEngine $instance = null;

    /** @var array<string, mixed> */
    private array $container = [];

    private function __construct() {
        $this->define_constants();

        register_activation_hook( SOCIALSYNC_ENGINE_FILE, [ $this, 'activate' ] );
        register_deactivation_hook( SOCIALSYNC_ENGINE_FILE, [ $this, 'deactivate' ] );

        add_action( 'plugins_loaded', [ $this, 'init_plugin' ] );
        add_action( 'rest_api_init',  [ $this, 'register_rest_routes' ] );
    }

    public static function init(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Magic getter for container items. */
    public function __get( string $prop ): mixed {
        return $this->container[ $prop ] ?? null;
    }

    public function activate(): void {}

    public function deactivate(): void {}

    public function define_constants(): void {
        defined( 'SOCIALSYNC_ENGINE_VERSION' )      || define( 'SOCIALSYNC_ENGINE_VERSION', $this->version );
        defined( 'SOCIALSYNC_ENGINE_DIR' )          || define( 'SOCIALSYNC_ENGINE_DIR', dirname( SOCIALSYNC_ENGINE_FILE ) );
        defined( 'SOCIALSYNC_ENGINE_INC_DIR' )      || define( 'SOCIALSYNC_ENGINE_INC_DIR', SOCIALSYNC_ENGINE_DIR . '/includes' );
        defined( 'SOCIALSYNC_ENGINE_PLUGIN_URL' )   || define( 'SOCIALSYNC_ENGINE_PLUGIN_URL', plugins_url( '/', SOCIALSYNC_ENGINE_FILE ) );
        defined( 'SOCIALSYNC_ENGINE_BUILD_URL' )    || define( 'SOCIALSYNC_ENGINE_BUILD_URL', SOCIALSYNC_ENGINE_PLUGIN_URL . 'build' );
        defined( 'SOCIALSYNC_ENGINE_BUILD_DIR' )    || define( 'SOCIALSYNC_ENGINE_BUILD_DIR', SOCIALSYNC_ENGINE_DIR . '/build' );
    }

    public function init_plugin(): void {
        $this->init_classes();
        do_action( 'socialsync_engine_loaded' );
    }

    public function init_classes(): void {
        $this->container['assets']            = new Assets();
        $this->container['admin_menu']        = new AdminMenu();
        $this->container['oauth_rest']        = new OAuthController();
        $this->container['publish_rest']      = new PublishController();
        $this->container['status_rest']       = new StatusController();
    }

    public function register_rest_routes(): void {
        $this->container['oauth_rest']?->register_routes();
        $this->container['publish_rest']?->register_routes();
        $this->container['status_rest']?->register_routes();
    }

    public function plugin_url(): string {
        return untrailingslashit( SOCIALSYNC_ENGINE_PLUGIN_URL );
    }
}
