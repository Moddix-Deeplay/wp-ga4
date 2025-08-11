<?php
/*
Plugin Name: WP GA4
Description: WordPress plugin for integrating Google Analytics 4
Version: 1.4.1
*/

use WP_GA4\Cache;
use WP_GA4\GoogleAnalytics;

// Инициализация плагина

// Создаем пункт меню в админке
add_action('admin_menu', function () {
    add_options_page(
        'Настройка интеграции с GA4',   // Заголовок страницы
        'Интеграция с GA4',                // Название в меню
        'manage_options',            // Права доступа
        'wp-ga4-settings',        // Slug страницы
        'wpga4_settings_page'    // Функция отображения
    );
});

// Регистрируем настройки
add_action('admin_init', function () {
    // Регистрируем группу настроек
    register_setting(
        'wp-ga4_settings_group', // Группа настроек
        'wp-ga4_settings'       // Название опции в БД
    );

    // Добавляем секцию
    add_settings_section(
        'wp-ga4_main_section',          // ID секции
        'Основные настройки',              // Заголовок
        'wpga4_section_callback',      // Функция вывода
        'wp-ga4-settings'               // Страница, где показывать
    );

    // google_service_account_key
    add_settings_field(
        'wp-ga4_google_service_account_key',            // ID поля
        'Google Service Account Key',                  // Заголовок
        'wpga4_service_account_key_callback',  // Функция вывода
        'wp-ga4-settings',             // Страница
        'wp-ga4_main_section'          // Секция
    );

    // google_analytics_property_id
    add_settings_field(
        'wp-ga4_google_analytics_property_id',            // ID поля
        'Property ID',                  // Заголовок
        'wpga4_google_analytics_property_id_callback',  // Функция вывода
        'wp-ga4-settings',             // Страница
        'wp-ga4_main_section'          // Секция
    );

    add_settings_field(
        'wp-ga4_cache_ttl',            // ID поля
        'Cache TTL',                  // Заголовок
        'wpga4_cache_ttl_callback',  // Функция вывода
        'wp-ga4-settings',             // Страница
        'wp-ga4_main_section'          // Секция
    );
});

// Колбэк для секции
function wpga4_section_callback(): void
{
    echo '<p>Здесь вы можете настроить основные параметры плагина</p>';
}

// Колбэк для текстового поля
function wpga4_service_account_key_callback(): void
{
    $options = get_option('wp-ga4_settings');
    $value = $options['google_service_account_key'] ?? '';
    echo '<textarea name="wp-ga4_settings[google_service_account_key]" rows="5" cols="33">' . esc_attr($value) . '</textarea>';
    echo '<p class="description">Ключ в формате JSON</p>';
}

function wpga4_google_analytics_property_id_callback(): void
{
    $options = get_option('wp-ga4_settings');
    $value = $options['google_analytics_property_id'] ?? '';
    echo '<input type="text" name="wp-ga4_settings[google_analytics_property_id]" value="' . esc_attr($value) . '" class="regular-text">';
    echo '<p class="description">Property ID</p>';
}

function wpga4_cache_ttl_callback(): void
{
    $options = get_option('wp-ga4_settings');
    $value = $options['cache_ttl'] ?? '';
    echo '<input type="text" name="wp-ga4_settings[cache_ttl]" value="' . esc_attr($value) . '" class="regular-text">';
    echo '<p class="description">Cache life in seconds</p>';
}


// Функция отображения страницы настроек
function wpga4_settings_page() {
    // Проверяем права пользователя
    if (!current_user_can('manage_options')) {
        return;
    }

    // Показываем сообщения об обновлении
    if (isset($_GET['settings-updated'])) {
        add_settings_error(
            'wp-ga4_messages',
            'wp-ga4_message',
            'Настройки сохранены',
            'updated'
        );
    }

    // Выводим сообщения
    settings_errors('wp-ga4_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            // Выводим скрытые поля
            settings_fields('wp-ga4_settings_group');
            // Выводим секции
            do_settings_sections('wp-ga4-settings');
            // Кнопка сохранения
            submit_button('Сохранить настройки');
            ?>
        </form>
    </div>
    <?php
}



function report($url, $post_id = null) {
    try {
        $options = get_option('wp-ga4_settings');
        $config = [
            'cache_ttl' => isset($options['cache_ttl']) ? (int) $options['cache_ttl'] : 3600, // 1 hour default
            'cache_dir' => __DIR__ . '/cache',
            'google_service_account_key' => json_decode($options['google_service_account_key'], true) ?? null,
            'google_analytics_property_id' => $options['google_analytics_property_id'] ?? ''
        ];

        $cache = new Cache($config['cache_ttl'], $config['cache_dir']);

        $report = $cache->get(function () use ($config) {
            $ga = new GoogleAnalytics($config['google_service_account_key'], $config['google_analytics_property_id']);
            return $ga->getReport();
        });

        foreach ($report as $row) {
            if ($row['path'] === $url) {
                // Сохраняем данные в метаполя, если передан post_id
                if ($post_id) {
                    update_post_meta($post_id, '_ga4_views', $row['views']);
                    update_post_meta($post_id, '_ga4_avg_time', $row['avg_time']);
                }
                return $row;
            }
        }

        // Сохраняем нулевые значения, если данных нет
        if ($post_id) {
            update_post_meta($post_id, '_ga4_views', 0);
            update_post_meta($post_id, '_ga4_avg_time', 0);
        }

        return ['views' => 0, 'avg_time' => 0];
    } catch (Exception $e) {
        error_log('GA4 Report Error: ' . $e->getMessage());
        return ['views' => 0, 'avg_time' => 0];
    }
}

function add_ga4_columns($columns) {
    $columns['ga4_views'] = __('GA4 Views');
    $columns['ga4_avg_time'] = __('GA4 Avg Time');
    return $columns;
}
add_filter('manage_posts_columns', 'add_ga4_columns');
add_filter('manage_pages_columns', 'add_ga4_columns');

function display_ga4_columns($column, $post_id) {
    if (in_array($column, ['ga4_views', 'ga4_avg_time'])) {
        if ($column === 'ga4_views') {
            $value = get_post_meta($post_id, '_ga4_views', true);
            echo $value ?: 0;
        } else {
            $value = get_post_meta($post_id, '_ga4_avg_time', true);
            echo $value ? round((float) $value, 2) : 0;
        }
    }
}
add_action('manage_posts_custom_column', 'display_ga4_columns', 10, 2);
add_action('manage_pages_custom_column', 'display_ga4_columns', 10, 2);

// === Делаем колонки сортируемыми ===
function make_ga4_columns_sortable($columns) {
    $columns['ga4_views'] = 'ga4_views';
    $columns['ga4_avg_time'] = 'ga4_avg_time';
    return $columns;
}
add_filter('manage_edit-post_sortable_columns', 'make_ga4_columns_sortable');
add_filter('manage_edit-page_sortable_columns', 'make_ga4_columns_sortable');

// === Логика сортировки ===
function sort_ga4_columns($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $orderby = $query->get('orderby');

    if (in_array($orderby, ['ga4_views', 'ga4_avg_time'])) {
        $order = strtoupper($query->get('order')) === 'ASC' ? 'ASC' : 'DESC';

        $meta_key = $orderby === 'ga4_views' ? '_ga4_views' : '_ga4_avg_time';
        $query->set('meta_key', $meta_key);
        $query->set('orderby', 'meta_value_num');
        $query->set('order', $order);
    }
}
add_action('pre_get_posts', 'sort_ga4_columns');

// === Обновление метаданных для всех постов ===
function update_ga4_meta_for_all_posts() {
    $posts = get_posts([
        'post_type' => ['post', 'page'],
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ]);

    foreach ($posts as $post) {
        $post_url = parse_url(get_permalink($post->ID), PHP_URL_PATH);
        report($post_url, $post->ID); // Вызов report с post_id для сохранения метаданных
    }
}

function schedule_ga4_update() {
    if (!wp_next_scheduled('wp_ga4_daily_update')) {
        wp_schedule_event(time(), 'daily', 'wp_ga4_daily_update');
    }
}

function validateConfig(): bool
{
    $options = get_option('wp-ga4_settings');

    $propertyId = $options['google_analytics_property_id'] ?? '';
    if ($propertyId === '' OR !is_numeric($propertyId)) {
        return false;
    }

    $json = $options['google_service_account_key'] ?? '';
    $config = json_decode($json, true);
    if (!$config) {
        return false;
    }

    return true;
}

if (validateConfig()) {
    // Регистрация задачи в WP Cron
    add_action('wp_ga4_daily_update', 'update_ga4_meta_for_all_posts');
    add_action('wp', 'schedule_ga4_update');
}

function wp_ga4_deactivate()
{
    wp_clear_scheduled_hook('wp_ga4_daily_update');
}

register_deactivation_hook(__FILE__, 'wp_ga4_deactivate');
