<?php
if (!defined('ABSPATH')) exit;

class LM_Shortcode {
    public static function init() {
        add_shortcode('ielts-resource-links', [__CLASS__, 'render_links']);
    }

    public static function render_links($atts = []) {
        if (!is_user_logged_in()) {
            return '<div class="container my-3"><div class="alert alert-warning" role="alert">'
                . esc_html__('Please log in to view your links.', 'link-manage')
                . '</div></div>';
        }

        LM_Activator::maybe_install();
        if (!LM_Activator::table_exists()) {
            return '<div class="container my-3"><div class="alert alert-danger" role="alert">'
                . esc_html__('Links are not available yet. Please contact the site administrator.', 'link-manage')
                . '</div></div>';
        }

        $u = wp_get_current_user();
        $allow_pre = (bool) get_user_meta($u->ID, LM_META_PRE, true);
        $allow_adv = (bool) get_user_meta($u->ID, LM_META_ADV, true);

        if (!$allow_pre && !$allow_adv) {
            return '<div class="container my-3"><div class="alert alert-info" role="alert"><em>'
                . esc_html__('No link categories have been assigned to your account yet.', 'link-manage')
                . '</em></div></div>';
        }

        // Filters
        $type   = isset($_GET['lm_type']) ? sanitize_text_field($_GET['lm_type']) : '';
        $q      = isset($_GET['lm_q']) ? sanitize_text_field($_GET['lm_q']) : '';
        $c_from = isset($_GET['lm_c_from']) ? sanitize_text_field($_GET['lm_c_from']) : '';
        $c_to   = isset($_GET['lm_c_to']) ? sanitize_text_field($_GET['lm_c_to']) : '';
        $e_from = isset($_GET['lm_e_from']) ? sanitize_text_field($_GET['lm_e_from']) : '';
        $e_to   = isset($_GET['lm_e_to']) ? sanitize_text_field($_GET['lm_e_to']) : '';

        global $wpdb;
        $table = LM_Activator::table_name();

        // Allowed types
        $allowed = [];
        if ($allow_pre) $allowed[] = 'PRE-IELTS';
        if ($allow_adv) $allowed[] = 'Advanced IELTS';

        $where = [];
        $params = [];

        // Allowed types (required)
        $placeholders = implode(',', array_fill(0, count($allowed), '%s'));
        $where[] = "link_type IN ($placeholders)";
        $params = array_merge($params, $allowed);

        // Hide expired unless NULL
        $where[] = "(expire_at IS NULL OR expire_at >= CURRENT_DATE())";

        // Type filter
        if ($type && in_array($type, ['PRE-IELTS','Advanced IELTS'], true)) {
            $where[] = "link_type = %s";
            $params[] = $type;
        }

        // Global search
        if ($q) {
            $where[] = "(title LIKE %s OR description LIKE %s OR url LIKE %s)";
            $like = '%' . $wpdb->esc_like($q) . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        // Created range
        if ($c_from) { $where[] = "DATE(created_at) >= %s"; $params[] = $c_from; }
        if ($c_to)   { $where[] = "DATE(created_at) <= %s"; $params[] = $c_to; }

        // Expire range (only with a date)
        if ($e_from) { $where[] = "(expire_at IS NOT NULL AND DATE(expire_at) >= %s)"; $params[] = $e_from; }
        if ($e_to)   { $where[] = "(expire_at IS NOT NULL AND DATE(expire_at) <= %s)"; $params[] = $e_to; }

        $sql = "SELECT * FROM {$table}";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY created_at DESC";

        $prepared = $wpdb->prepare($sql, $params);
        $rows = $wpdb->get_results($prepared);

        ob_start(); ?>
        <div class="container my-3 lm-front">
            <form method="get" class="row g-2 align-items-end mb-3 lm-front-filters">
                <div class="col-12 col-md-4">
                    <label class="form-label"><?php _e('Search', 'link-manage'); ?></label>
                    <input type="text" name="lm_q" value="<?php echo esc_attr($q); ?>" class="form-control" placeholder="<?php esc_attr_e('Search…','link-manage'); ?>" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label"><?php _e('Type', 'link-manage'); ?></label>
                    <select name="lm_type" class="form-select">
                        <option value=""><?php _e('All Types','link-manage'); ?></option>
                        <?php foreach (['PRE-IELTS','Advanced IELTS'] as $t): ?>
                            <option value="<?php echo esc_attr($t); ?>" <?php selected($type, $t); ?>><?php echo esc_html($t); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-5">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label"><?php _e('Created From','link-manage'); ?></label>
                            <input type="date" name="lm_c_from" value="<?php echo esc_attr($c_from); ?>" class="form-control" />
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php _e('Created To','link-manage'); ?></label>
                            <input type="date" name="lm_c_to" value="<?php echo esc_attr($c_to); ?>" class="form-control" />
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-5">
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label"><?php _e('Expire From','link-manage'); ?></label>
                            <input type="date" name="lm_e_from" value="<?php echo esc_attr($e_from); ?>" class="form-control" />
                        </div>
                        <div class="col-6">
                            <label class="form-label"><?php _e('Expire To','link-manage'); ?></label>
                            <input type="date" name="lm_e_to" value="<?php echo esc_attr($e_to); ?>" class="form-control" />
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-2">
                    <button class="btn btn-primary w-100"><?php _e('Apply Filters','link-manage'); ?></button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle lm-front-table">
                    <thead class="table-light">
                        <tr>
                            <th scope="col"><?php _e('No','link-manage'); ?></th>
                            <th scope="col"><?php _e('Title','link-manage'); ?></th>
                            <th scope="col"><?php _e('Type','link-manage'); ?></th>
                            <th scope="col"><?php _e('Link','link-manage'); ?></th>
                            <th scope="col"><?php _e('Description','link-manage'); ?></th>
                            <th scope="col"><?php _e('Created Date','link-manage'); ?></th>
                            <th scope="col"><?php _e('Expire Date','link-manage'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($rows)): $i=1; foreach ($rows as $r): ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($i++); ?></th>
                            <td><?php echo esc_html($r->title); ?></td>
                            <td><span class="badge bg-secondary"><?php echo esc_html($r->link_type); ?></span></td>
                            <td><a class="link-primary" href="<?php echo esc_url($r->url); ?>" target="_blank" rel="noopener"><?php _e('Open','link-manage'); ?></a></td>
                            <td><?php echo wp_kses_post(wpautop($r->description)); ?></td>
                            <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($r->created_at))); ?></td>
                            <td><?php echo $r->expire_at ? esc_html(date_i18n(get_option('date_format'), strtotime($r->expire_at))) : '<em>'.__('No expire','link-manage').'</em>'; ?></td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="7"><em><?php _e('No links found for your filters.', 'link-manage'); ?></em></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
