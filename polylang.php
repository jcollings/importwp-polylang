<?php

/**
 * Plugin Name: Import WP - Polylang Addon
 * Plugin URI: https://www.importwp.com
 * Description: Allow Import WP to import Polylang translations.
 * Author: James Collings <james@jclabs.co.uk>
 * Version: 0.0.1 
 * Author URI: https://www.importwp.com
 * Network: True
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

define('IWP_POLYLANG_FILE', __FILE__);
define('IWP_POLYLANG_VERSION', '0.0.1');

add_action('admin_init', 'iwp_polylang_check');

function iwp_polylang_requirements_met()
{
    return false === (is_admin() && current_user_can('activate_plugins') &&  (!function_exists('import_wp') || !defined('POLYLANG') || version_compare(IWP_VERSION, '2.6.2', '<')));
}

function iwp_polylang_check()
{
    if (!iwp_polylang_requirements_met()) {

        add_action('admin_notices', 'iwp_polylang_notice');

        deactivate_plugins(plugin_basename(__FILE__));

        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}

function iwp_polylang_setup()
{
    if (!iwp_polylang_requirements_met()) {
        return;
    }

    $base_path = dirname(__FILE__);

    require_once $base_path . '/setup.php';

    // Install updater
    if (file_exists($base_path . '/updater.php') && !class_exists('IWP_Updater')) {
        require_once $base_path . '/updater.php';
    }

    if (class_exists('IWP_Updater')) {
        $updater = new IWP_Updater(__FILE__, 'importwp-polylang');
        $updater->initialize();
    }
}
add_action('plugins_loaded', 'iwp_polylang_setup', 9);

function iwp_polylang_notice()
{
    echo '<div class="error">';
    echo '<p><strong>Import WP - Polylang Addon</strong> requires that you have <strong>Import WP v2.6.2 or newer</strong>, and <strong>Polylang</strong> installed.</p>';
    echo '</div>';
}
