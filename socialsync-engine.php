<?php
/**
 * Plugin Name: SocialSync Engine
 * Plugin URI:  https://example.com/socialsync-engine
 * Description: Upload captions and media (Images/Videos) to be posted automatically to Facebook and LinkedIn.
 * Version:     1.0.0
 * Author:      emily50
 * Author URI:  https://example.com
 * Text Domain: socialsync-engine
 * Domain Path: /languages/
 * License:     GPL2
 */

use SocialSyncEngine\SocialSyncEngine;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SOCIALSYNC_ENGINE_FILE' ) ) {
    define( 'SOCIALSYNC_ENGINE_FILE', __FILE__ );
}

if ( ! defined( 'SOCIALSYNC_ENGINE_BASENAME' ) ) {
    define( 'SOCIALSYNC_ENGINE_BASENAME', plugin_basename( __FILE__ ) );
}

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Returns the main plugin instance.
 *
 * @return SocialSyncEngine
 */
function socialsync_engine(): SocialSyncEngine {
    return SocialSyncEngine::init();
}

// Bootstrap the plugin.
socialsync_engine();
