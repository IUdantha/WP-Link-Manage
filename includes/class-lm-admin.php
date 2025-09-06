<?php
if (!defined('ABSPATH')) exit;

class LM_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);

        // Admin-post handlers (links CRUD)
        add_action('admin_post_lm_add_link',   [__CLASS__, 'handle_add_link']);
        add_action('admin_post_lm_update_link',[__CLASS__, 'handle_update_link']);
        add_action('admin_post_lm_delete_link',[__CLASS__, 'handle_delete_link']);
    }

    public static function menu() {
        add_menu_page(
            __('Link Manage','link-manage'),
            __('Link Manage','link-manage'),
            'manage_options',
            'lm-links',
            [__CLASS__, 'page_links'],
            'dashicons-admin-links',
            56
        );

        add_submenu_page('lm-links', __('Links','link-manage'), __('Links','link-manage'), 'manage_options', 'lm-links', [__CLASS__, 'page_links']);
        add_submenu_page('lm-links', __('Students','link-manage'), __('Students','link-manage'), 'manage_options', 'lm-students', [__CLASS__, 'page_students']);
        add_submenu_page('lm-links', __('Settings','link-manage'), __('Settings','link-manage'), 'manage_options', 'lm-settings', [__CLASS__, 'page_settings']);
    }

    public static function assets($hook) {
        if (strpos($hook, 'lm-') === false) return;
        wp_enqueue_style('lm-admin', LM_PLUGIN_URL . 'assets/admin.css', [], '1.0.0');
        wp_enqueue_script('lm-admin', LM_PLUGIN_URL . 'assets/admin.js', ['jquery'], '1.0.0', true);
        wp_localize_script('lm-admin', 'LM_Ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('lm_ajax_nonce'),
        ]);
    }

    /* ------------------------------ STUDENTS ------------------------------ */

    public static function page_students() {
        if (!current_user_can('manage_options')) return;

        $s   = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $role= isset($_GET['role']) ? sanitize_text_field($_GET['role']) : '';

        $args = [
            'number' => 50,
            'paged'  => max(1, (int)($_GET['paged'] ?? 1)),
        ];
        if ($s)   $args['search'] = '*' . $s . '*';
        if ($role) $args['role'] = $role;

        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();
        $total = $user_query->get_total();

        $roles = wp_roles()->get_names();
        ?>
        <div class="wrap">
            <h1><?php _e('Students','link-manage'); ?></h1>

            <form method="get" class="lm-filters">
                <input type="hidden" name="page" value="lm-students" />
                <input type="search" name="s" value="<?php echo esc_attr($s); ?>" placeholder="<?php esc_attr_e('Search users…','link-manage'); ?>" />
                <select name="role">
                    <option value=""><?php _e('All Roles','link-manage'); ?></option>
                    <?php foreach ($roles as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($role, $key); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button"><?php _e('Filter','link-manage'); ?></button>
            </form>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Username','link-manage'); ?></th>
                        <th><?php _e('PRE-IELTS','link-manage'); ?></th>
                        <th><?php _e('Advanced IELTS','link-manage'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($users): foreach ($users as $u):
                    $pre = (bool) get_user_meta($u->ID, LM_META_PRE, true);
                    $adv = (bool) get_user_meta($u->ID, LM_META_ADV, true);
                ?>
                    <tr data-user="<?php echo esc_attr($u->ID); ?>">
                        <td>
                            <strong><?php echo esc_html($u->user_login); ?></strong><br>
                            <small><?php echo esc_html($u->user_email); ?></small>
                        </td>
                        <td>
                            <label class="lm-switch">
                                <input type="checkbox" class="lm-toggle" data-key="<?php echo esc_attr(LM_META_PRE); ?>" <?php checked($pre); ?> />
                                <span class="lm-slider"></span>
                            </label>
                        </td>
                        <td>
                            <label class="lm-switch">
                                <input type="checkbox" class="lm-toggle" data-key="<?php echo esc_attr(LM_META_ADV); ?>" <?php checked($adv); ?> />
                                <span class="lm-slider"></span>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="3"><?php _e('No users found.','link-manage'); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <?php
            $per_page = 50;
            $pages = ceil($total / $per_page);
            if ($pages > 1) {
                $base_url = remove_query_arg('paged');
                echo '<p class="tablenav-pages">';
                for ($p=1; $p<=$pages; $p++) {
                    $url = esc_url(add_query_arg('paged', $p, $base_url));
                    echo ($p == $args['paged']) ? "<span class='page-numbers current'>{$p}</span> " : "<a class='page-numbers' href='{$url}'>{$p}</a> ";
                }
                echo '</p>';
            }
            ?>
        </div>
        <?php
    }

    /* ------------------------------- LINKS -------------------------------- */

    public static function page_links() {
        if (!current_user_can('manage_options')) return;

        // Ensure table exists (self-heal; soft notice if still missing)
        LM_Activator::maybe_install();
        if (!LM_Activator::table_exists()) {
            echo '<div class="wrap"><div class="notice notice-error"><p>'
                . esc_html__('Link Manage: database table could not be created. Please check DB permissions and try reactivating the plugin.', 'link-manage')
                . '</p></div></div>';
            return;
        }

        global $wpdb;
        $table = LM_Activator::table_name();

        // Editing?
        $editing = false;
        $edit_row = null;
        if (isset($_GET['edit'])) {
            $editing = true;
            $id = absint($_GET['edit']);
            $edit_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $id));
        }

        /* -------------------------- NEW: FILTERS -------------------------- */
        $type   = isset($_GET['lm_type']) ? sanitize_text_field($_GET['lm_type']) : '';
        $q      = isset($_GET['lm_q']) ? sanitize_text_field($_GET['lm_q']) : '';
        $c_from = isset($_GET['lm_c_from']) ? sanitize_text_field($_GET['lm_c_from']) : '';
        $c_to   = isset($_GET['lm_c_to']) ? sanitize_text_field($_GET['lm_c_to']) : '';
        $e_from = isset($_GET['lm_e_from']) ? sanitize_text_field($_GET['lm_e_from']) : '';
        $e_to   = isset($_GET['lm_e_to']) ? sanitize_text_field($_GET['lm_e_to']) : '';

        $where = [];
        $params = [];

        if ($type && in_array($type, ['PRE-IELTS','Advanced IELTS'], true)) {
            $where[] = "link_type = %s";
            $params[] = $type;
        }
        if ($q) {
            $like = '%' . $wpdb->esc_like($q) . '%';
            $where[] = "(title LIKE %s OR description LIKE %s OR url LIKE %s)";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if ($c_from) { $where[] = "DATE(created_at) >= %s"; $params[] = $c_from; }
        if ($c_to)   { $where[] = "DATE(created_at) <= %s"; $params[] = $c_to; }
        // only consider rows with an expiry date for expire-range filters
        if ($e_from) { $where[] = "(expire_at IS NOT NULL AND DATE(expire_at) >= %s)"; $params[] = $e_from; }
        if ($e_to)   { $where[] = "(expire_at IS NOT NULL AND DATE(expire_at) <= %s)"; $params[] = $e_to; }

        $sql = "SELECT * FROM {$table}";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY created_at DESC";

        $rows = !empty($params) ? $wpdb->get_results($wpdb->prepare($sql, $params)) : $wpdb->get_results($sql);
        /* ------------------------------------------------------------------ */

        ?>
        <div class="wrap">
            <h1><?php _e('Links','link-manage'); ?></h1>

            <button id="lm-add-link-btn" class="button button-primary" <?php echo $editing ? 'style="display:none"' : '';?>>
                + <?php _e('Add Link','link-manage'); ?>
            </button>

            <div id="lm-add-link-panel" class="<?php echo $editing ? 'open' : ''; ?>">
                <h2><?php echo $editing ? __('Edit Link','link-manage') : __('Add Link','link-manage'); ?></h2>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php if ($editing): ?>
                        <input type="hidden" name="action" value="lm_update_link">
                        <input type="hidden" name="id" value="<?php echo esc_attr($edit_row->id); ?>">
                        <?php wp_nonce_field('lm_update_link_'.$edit_row->id); ?>
                    <?php else: ?>
                        <input type="hidden" name="action" value="lm_add_link">
                        <?php wp_nonce_field('lm_add_link'); ?>
                    <?php endif; ?>

                    <table class="form-table">
                        <tr>
                            <th><label for="lm_title"><?php _e('Title','link-manage'); ?></label></th>
                            <td><input type="text" required name="title" id="lm_title" class="regular-text" value="<?php echo esc_attr($edit_row->title ?? ''); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="lm_type"><?php _e('Type','link-manage'); ?></label></th>
                            <td>
                                <select name="type" id="lm_type" required>
                                    <?php
                                    $types = ['PRE-IELTS','Advanced IELTS'];
                                    $val = $edit_row->link_type ?? '';
                                    foreach ($types as $t) {
                                        echo '<option value="'.esc_attr($t).'" '.selected($val, $t, false).'>'.esc_html($t).'</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="lm_url"><?php _e('Link (URL)','link-manage'); ?></label></th>
                            <td><input type="url" required name="url" id="lm_url" class="regular-text" value="<?php echo esc_attr($edit_row->url ?? ''); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="lm_desc"><?php _e('Description','link-manage'); ?></label></th>
                            <td>
                                <?php
                                $content = $edit_row->description ?? '';
                                wp_editor($content, 'lm_desc', [
                                    'textarea_name' => 'description',
                                    'media_buttons' => false,
                                    'teeny' => true,
                                    'textarea_rows' => 6,
                                ]);
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Expiry','link-manage'); ?></th>
                            <td>
                                <?php
                                $has_exp = !empty($edit_row->expire_at);
                                $exp_date = $has_exp ? substr($edit_row->expire_at, 0, 10) : '';
                                ?>
                                <label class="lm-switch">
                                    <input type="checkbox" id="lm_exp_toggle" <?php checked($has_exp); ?>>
                                    <span class="lm-slider"></span>
                                </label>
                                <input type="hidden" name="expire_enabled" id="lm_exp_enabled" value="<?php echo $has_exp ? '1':'0'; ?>">
                                <input type="date" name="expire_date" id="lm_exp_date" value="<?php echo esc_attr($exp_date); ?>" style="<?php echo $has_exp ? '' : 'display:none'; ?>">
                                <p class="description"><?php _e('If off, the link never expires.','link-manage'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button($editing ? __('Update Link','link-manage') : __('Save Link','link-manage')); ?>
                </form>
            </div>

            <!-- NEW: Filters for admin list -->
            <h2 style="margin-top:24px;"><?php _e('All Links','link-manage'); ?></h2>

            <form method="get" class="lm-filters" style="margin-bottom:10px;">
                <input type="hidden" name="page" value="lm-links" />
                <input type="search" name="lm_q" value="<?php echo esc_attr($q); ?>" placeholder="<?php esc_attr_e('Search title, URL, description…','link-manage'); ?>" />

                <select name="lm_type">
                    <option value=""><?php _e('All Types','link-manage'); ?></option>
                    <?php foreach (['PRE-IELTS','Advanced IELTS'] as $t): ?>
                        <option value="<?php echo esc_attr($t); ?>" <?php selected($type, $t); ?>><?php echo esc_html($t); ?></option>
                    <?php endforeach; ?>
                </select>

                <span>
                    <label style="margin-right:4px;"><?php _e('Created From','link-manage'); ?></label>
                    <input type="date" name="lm_c_from" value="<?php echo esc_attr($c_from); ?>" />
                    <label style="margin:0 4px;"><?php _e('To','link-manage'); ?></label>
                    <input type="date" name="lm_c_to" value="<?php echo esc_attr($c_to); ?>" />
                </span>

                <span>
                    <label style="margin-right:4px;"><?php _e('Expire From','link-manage'); ?></label>
                    <input type="date" name="lm_e_from" value="<?php echo esc_attr($e_from); ?>" />
                    <label style="margin:0 4px;"><?php _e('To','link-manage'); ?></label>
                    <input type="date" name="lm_e_to" value="<?php echo esc_attr($e_to); ?>" />
                </span>

                <button class="button button-primary"><?php _e('Apply Filters','link-manage'); ?></button>
                <?php
                  $clear_url = remove_query_arg(['lm_q','lm_type','lm_c_from','lm_c_to','lm_e_from','lm_e_to','edit','paged']);
                ?>
                <a class="button button-secondary" href="<?php echo esc_url($clear_url); ?>"><?php _e('Reset','link-manage'); ?></a>
            </form>

            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Title','link-manage'); ?></th>
                        <th><?php _e('Type','link-manage'); ?></th>
                        <th><?php _e('Link','link-manage'); ?></th>
                        <th><?php _e('Created','link-manage'); ?></th>
                        <th><?php _e('Expiry','link-manage'); ?></th>
                        <th><?php _e('Actions','link-manage'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($rows)): foreach ($rows as $r): ?>
                    <tr>
                        <td><strong><?php echo esc_html($r->title); ?></strong></td>
                        <td><?php echo esc_html($r->link_type); ?></td>
                        <td><a href="<?php echo esc_url($r->url); ?>" target="_blank" rel="noopener"><?php echo esc_html($r->url); ?></a></td>
                        <td><?php echo esc_html($r->created_at); ?></td>
                        <td><?php echo $r->expire_at ? esc_html($r->expire_at) : '<em>'.__('No expire','link-manage').'</em>'; ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url(add_query_arg(['page'=>'lm-links','edit'=>$r->id], admin_url('admin.php'))); ?>">
                                <?php _e('Edit','link-manage'); ?>
                            </a>
                            <a class="button button-small button-link-delete" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=lm_delete_link&id='.$r->id), 'lm_delete_link_'.$r->id)); ?>" onclick="return confirm('<?php esc_attr_e('Delete this link?','link-manage'); ?>');">
                                <?php _e('Delete','link-manage'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="6"><?php _e('No links match your filters.','link-manage'); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public static function handle_add_link() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('lm_add_link');

        $title = sanitize_text_field($_POST['title'] ?? '');
        $type  = sanitize_text_field($_POST['type'] ?? '');
        $url   = esc_url_raw($_POST['url'] ?? '');
        $desc  = wp_kses_post($_POST['description'] ?? '');
        $exp_on= isset($_POST['expire_enabled']) && $_POST['expire_enabled'] === '1';
        $exp_d = $exp_on ? sanitize_text_field($_POST['expire_date'] ?? '') : '';

        if (!$title || !$type || !$url) {
            wp_redirect(add_query_arg(['page'=>'lm-links','lm_msg'=>'missing'], admin_url('admin.php'))); exit;
        }

        $expire_at = $exp_on && $exp_d ? $exp_d . ' 23:59:59' : null;

        global $wpdb;
        $table = LM_Activator::table_name();
        $wpdb->insert($table, [
            'title'       => $title,
            'link_type'   => $type,
            'url'         => $url,
            'description' => $desc,
            'expire_at'   => $expire_at,
        ], ['%s','%s','%s','%s','%s']);

        wp_redirect(admin_url('admin.php?page=lm-links&lm_msg=added'));
        exit;
    }

    public static function handle_update_link() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        $id = absint($_POST['id'] ?? 0);
        check_admin_referer('lm_update_link_'.$id);

        $title = sanitize_text_field($_POST['title'] ?? '');
        $type  = sanitize_text_field($_POST['type'] ?? '');
        $url   = esc_url_raw($_POST['url'] ?? '');
        $desc  = wp_kses_post($_POST['description'] ?? '');
        $exp_on= isset($_POST['expire_enabled']) && $_POST['expire_enabled'] === '1';
        $exp_d = $exp_on ? sanitize_text_field($_POST['expire_date'] ?? '') : '';

        if (!$id || !$title || !$type || !$url) {
            wp_redirect(add_query_arg(['page'=>'lm-links','lm_msg'=>'missing'], admin_url('admin.php'))); exit;
        }

        $expire_at = $exp_on && $exp_d ? $exp_d . ' 23:59:59' : null;

        global $wpdb;
        $table = LM_Activator::table_name();
        $wpdb->update($table, [
            'title'       => $title,
            'link_type'   => $type,
            'url'         => $url,
            'description' => $desc,
            'expire_at'   => $expire_at,
        ], ['id'=>$id], ['%s','%s','%s','%s','%s'], ['%d']);

        wp_redirect(admin_url('admin.php?page=lm-links&lm_msg=updated'));
        exit;
    }

    public static function handle_delete_link() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        $id = absint($_GET['id'] ?? 0);
        check_admin_referer('lm_delete_link_'.$id);

        global $wpdb;
        $table = LM_Activator::table_name();
        if ($id) {
            $wpdb->delete($table, ['id' => $id], ['%d']);
        }
        wp_redirect(admin_url('admin.php?page=lm-links&lm_msg=deleted'));
        exit;
    }

    public static function page_settings() {
        if (!current_user_can('manage_options')) return;
        echo '<div class="wrap"><h1>Settings</h1><p><em>Coming Soon</em></p></div>';
    }
}
