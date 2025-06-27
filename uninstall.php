<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Удаляем все записи из таблицы wp_options, которые начинаются с wp-ga4_
global $wpdb;
$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = '_ga4_views'");
$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = '_ga4_avg_time'");

// Удаляем опции плагина
delete_option('wp-ga4_settings');
