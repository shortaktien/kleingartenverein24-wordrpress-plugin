<?php
/**
 * Plugin Name: KGV24
 * Plugin URI: https://kleingartenverein24.de/
 * Description: Bindet Kleingartenverein24 in WordPress ein und zeigt freie Gärten per Shortcode an.
 * Version: 0.1.0
 * Author: Kleingartenverein24
 * Author URI: https://kleingartenverein24.de/
 * Text Domain: kgv24
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('KGV24_PLUGIN_FILE', __FILE__);
define('KGV24_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KGV24_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KGV24_VERSION', '0.1.0');

require_once KGV24_PLUGIN_DIR . 'includes/class-kgv24-api-client.php';
require_once KGV24_PLUGIN_DIR . 'includes/class-kgv24-settings.php';
require_once KGV24_PLUGIN_DIR . 'includes/class-kgv24-shortcodes.php';
require_once KGV24_PLUGIN_DIR . 'includes/class-kgv24-plugin.php';

add_action('plugins_loaded', static function () {
    KGV24_Plugin::instance()->init();
});
