<?php
/**
 * Plugin Name: Link Manage
 * Description: Admin: manage students and links; Frontend: show eligible links via shortcode [ielts-resource-links].
 * Version: 1.0.0
 * Author: You
 * Text Domain: link-manage
 */

if (!defined('ABSPATH')) exit;

define('LM_PLUGIN_FILE', __FILE__);
define('LM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LM_DB_TABLE', 'lm_links'); // final table = $wpdb->prefix . LM_DB_TABLE
define('LM_META_PRE', 'lm_pre_ielts');
define('LM_META_ADV', 'lm_adv_ielts');

require_once LM_PLUGIN_DIR . 'includes/class-lm-activator.php';
require_once LM_PLUGIN_DIR . 'includes/class-lm-admin.php';
require_once LM_PLUGIN_DIR . 'includes/class-lm-shortcode.php';
require_once LM_PLUGIN_DIR . 'includes/class-lm-ajax.php';

/** Activation: create DB table */
register_activation_hook(__FILE__, ['LM_Activator', 'activate']);

/** Boot */
add_action('plugins_loaded', function () {
    // Make absolutely sure the table exists (no runtime DB errors)
    LM_Activator::maybe_install();

    if (is_admin()) {
        LM_Admin::init();
    }
    LM_Shortcode::init();
    LM_Ajax::init();
});
