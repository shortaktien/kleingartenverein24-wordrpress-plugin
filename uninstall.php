<?php
/**
 * Uninstall cleanup for KGV24.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('kgv24_settings');
