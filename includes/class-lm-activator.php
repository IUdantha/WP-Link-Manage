<?php
if (!defined('ABSPATH')) exit;

class LM_Activator {
    const VERSION = '1.0.1';

    /** Full table name */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . LM_DB_TABLE;
    }

    /** Activation hook */
    public static function activate() {
        self::install();
    }

    /** Ensure table exists at runtime */
    public static function maybe_install() {
        if (!self::table_exists()) {
            self::install();
        } else {
            // Even if table exists, run tiny migrations (e.g., legacy `type` column)
            self::migrate_legacy_columns();
            self::ensure_indexes();
        }
    }

    /** Install/upgrade via dbDelta */
    public static function install() {
        global $wpdb;
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        // IMPORTANT: no inline comments inside the SQL; dbDelta is picky.
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            link_type VARCHAR(20) NOT NULL,
            url TEXT NOT NULL,
            description LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expire_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY link_type (link_type),
            KEY created_at (created_at),
            KEY expire_at (expire_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Handle legacy -> migration after create/upgrade
        self::migrate_legacy_columns();
        self::ensure_indexes();

        update_option('lm_db_version', self::VERSION);
    }

    /** Table existence */
    public static function table_exists() {
        global $wpdb;
        $table = self::table_name();
        $found = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        return ($found === $table);
    }

    /** Rename legacy `type` -> `link_type` if needed */
    private static function migrate_legacy_columns() {
        global $wpdb;
        $table = self::table_name();

        // If table not present, nothing to do
        if (!self::table_exists()) return;

        $has_type = $wpdb->get_var( $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'type') );
        $has_link_type = $wpdb->get_var( $wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'link_type') );

        // If old `type` exists and new `link_type` does NOT, rename it
        if ($has_type && !$has_link_type) {
            // Use CHANGE COLUMN with both names quoted; no trailing commas.
            $wpdb->query("ALTER TABLE {$table} CHANGE COLUMN `type` `link_type` VARCHAR(20) NOT NULL");
        }
    }

    /** Make sure our expected indexes exist (harmless if they already do) */
    private static function ensure_indexes() {
        global $wpdb;
        $table = self::table_name();
        if (!self::table_exists()) return;

        // Re-run dbDelta to reconcile keys safely
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            link_type VARCHAR(20) NOT NULL,
            url TEXT NOT NULL,
            description LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expire_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY link_type (link_type),
            KEY created_at (created_at),
            KEY expire_at (expire_at)
        ) {$charset_collate};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
