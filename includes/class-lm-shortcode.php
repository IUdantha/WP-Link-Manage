<?php
if (!defined('ABSPATH')) exit;

class LM_Shortcode {
    public static function init() {
        add_shortcode('ielts-resource-links', [__CLASS__, 'render_links']);
    }

    public static function render_links($atts = []) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('Please log in to view your links.', 'link-manage') . '</p>';
        }

        $u = wp_get_current_user();
        $allow_pre = (bool) get_user_meta($u->ID, LM_META_PRE, true);
        $allow_adv = (bool) get_user_meta($u->ID, LM_META_ADV, true);

        if (!$allow_pre && !$allow_adv) {
            return '<p><em>' . esc_html__('No link categories have been assigned to your account yet.', 'link-manage') . '</em></p>';
        }

        // Filters
        $type   = isset($_GET['lm_type']) ? sanitize_text_field($_GET['lm_type']) : '';
        $q      = isset($_GET['lm_q']) ? sanitize_text_field($_GET['lm_q']) : '';
        $c_from = isset($_GET['lm_c_from']) ? sanitize_text_field($_GET['lm_c_from']) : '';
        $c_to   = isset($_GET['lm_c_to']) ? sanitize_text_field($_GET['lm_c_to']) : '';
        $e_from = isset($_GET['lm_e_from']) ? sanitize_text_field($_GET['lm_e_from']) : '';
        $e_to   = isset($_GET['lm_e_to']) ? sanitize_text_field($_GET['lm_e_to']) : '';

        global $wpdb;
        $table = $wpdb->prefix . LM_DB_TABLE;

        // Allowed types for this user
        $allowed = [];
        if ($allow_pre) $allowed[] = 'PRE-IELTS';
        if ($allow_adv) $allowed[] = 'Advanced IELTS';

        $where = [];
        $params = [];

        // user allowed types
        $placeholders = implode(',', array_fill(0, count($allowed), '%s'));
        $where[] = "type IN ($placeholders)";
        $params = array_merge($params, $allowed);

        // type filter
        if ($type && in_array($type, ['PRE-IELTS','Advanced IELTS'], true)) {
            $where[] = "type = %s";
            $params[] = $type;
        }

        // global search
        if ($q) {
            $where[] = "(title LIKE %s OR description LIKE %s OR url LIKE %s)";
            $like = '%' . $wpdb->esc_like($q) . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        // created date range
        if ($c_from) { $where[] = "DATE(created_at) >= %s"; $params[] = $c_from; }
        if ($c_to)   { $where[] = "DATE(created_at) <= %s"; $params[] = $c_to; }

        // expire date range (note: NULL means no expiry; we only filter rows with a date)
        if ($e_from) { $where[] = "(expire_at IS NOT NULL AND DATE(expire_at) >= %s)"; $params[] = $e_from; }
        if ($e_to)   { $where[] = "(expire_at IS NOT NULL AND DATE(expire_at) <= %s)"; $params[] = $e_to; }

        $sql = "SELECT * FROM {$table}";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY created_at DESC";

        $prepared = $wpdb->prepare($sql, $params);
        $rows = $wpdb->get_results($prepared);

        ob_start(); ?>
        <div class="lm-front">
            <form method="get" class="lm-front-filters">
                <input type="text" name="lm_q" value="<?php echo esc_attr($q); ?>" placeholder="<?php esc_attr_e('Search…','link-manage'); ?>" />
                <select name="lm_type">
                    <option value=""><?php _e('All Types','link-manage'); ?></option>
                    <?php foreach (['PRE-IELTS','Advanced IELTS'] as $t): ?>
                        <option value="<?php echo esc_attr($t); ?>" <?php selected($type, $t); ?>><?php echo esc_html($t); ?></option>
                    <?php endforeach; ?>
                </select>

                <span class="lm-group">
                    <label><?php _e('Created From','link-manage'); ?></label>
                    <input type="date" name="lm_c_from" value="<?php echo esc_attr($c_from); ?>" />
                    <label><?php _e('To','link-manage'); ?></label>
                    <input type="date" name="lm_c_to" value="<?php echo esc_attr($c_to); ?>" />
                </span>

                <span class="lm-group">
                    <label><?php _e('Expire From','link-manage'); ?></label>
                    <input type="date" name="lm_e_from" value="<?php echo esc_attr($e_from); ?>" />
                    <label><?php _e('To','link-manage'); ?></label>
                    <input type="date" name="lm_e_to" value="<?php echo esc_attr($e_to); ?>" />
                </span>

                <button class="button"><?php _e('Apply Filters','link-manage'); ?></button>
            </form>

            <table class="lm-front-table">
                <thead>
                    <tr>
                        <th><?php _e('No','link-manage'); ?></th>
                        <th><?php _e('Title','link-manage'); ?></th>
                        <th><?php _e('Type','link-manage'); ?></th>
                        <th><?php _e('Link','link-manage'); ?></th>
                        <th><?php _e('Description','link-manage'); ?></th>
                        <th><?php _e('Created Date','link-manage'); ?></th>
                        <th><?php _e('Expire Date','link-manage'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($rows): $i=1; foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo esc_html($i++); ?></td>
                        <td><?php echo esc_html($r->title); ?></td>
                        <td><?php echo esc_html($r->type); ?></td>
                        <td><a href="<?php echo esc_url($r->url); ?>" target="_blank" rel="noopener"><?php _e('Open','link-manage'); ?></a></td>
                        <td><?php echo wp_kses_post(wpautop($r->description)); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($r->created_at))); ?></td>
                        <td><?php echo $r->expire_at ? esc_html(date_i18n(get_option('date_format'), strtotime($r->expire_at))) : '<em>'.__('No expire','link-manage').'</em>'; ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="7"><?php _e('No links found for your filters.','link-manage'); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }
}
