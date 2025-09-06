<?php
if (!defined('ABSPATH')) exit;

class LM_Activator {
    public static function activate() {
        global $wpdb;
        $table = $wpdb->prefix . LM_DB_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            type VARCHAR(20) NOT NULL,          -- 'PRE-IELTS' or 'Advanced IELTS'
            url TEXT NOT NULL,
            description LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expire_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY type (type),
            KEY created_at (created_at),
            KEY expire_at (expire_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
