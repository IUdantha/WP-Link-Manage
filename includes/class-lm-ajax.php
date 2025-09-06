<?php
if (!defined('ABSPATH')) exit;

class LM_Ajax {
    public static function init() {
        add_action('wp_ajax_lm_toggle_student', [__CLASS__, 'toggle_student']);
    }

    public static function toggle_student() {
        check_ajax_referer('lm_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message'=>'forbidden'], 403);

        $user_id = absint($_POST['user_id'] ?? 0);
        $meta_key= sanitize_key($_POST['meta_key'] ?? '');
        $value   = ($_POST['value'] ?? '') === '1' ? '1' : '0';

        if (!$user_id || !in_array($meta_key, [LM_META_PRE, LM_META_ADV], true)) {
            wp_send_json_error(['message'=>'bad-request'], 400);
        }

        update_user_meta($user_id, $meta_key, $value);
        wp_send_json_success(['ok'=>true, 'meta_key'=>$meta_key, 'value'=>$value]);
    }
}
