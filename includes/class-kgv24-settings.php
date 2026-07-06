<?php

if (!defined('ABSPATH')) {
    exit;
}

final class KGV24_Settings
{
    private const PAGE_SLUG = 'kgv24';

    private $client;

    public function __construct(KGV24_API_Client $client)
    {
        $this->client = $client;
    }

    public function init(): void
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_kgv24_test_auth', [$this, 'handle_auth_test']);
        add_action('admin_notices', [$this, 'render_auth_notice']);
    }

    public function add_admin_menu(): void
    {
        add_menu_page(
            __('KGV24', 'kgv24'),
            __('KGV24', 'kgv24'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page'],
            'dashicons-palmtree',
            58
        );
    }

    public function register_settings(): void
    {
        register_setting(
            'kgv24_settings_group',
            KGV24_API_Client::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => [],
            ]
        );

        add_settings_section(
            'kgv24_api_section',
            __('API-Verbindung', 'kgv24'),
            static function () {
                echo '<p>' . esc_html__('Trage hier den tenant-gebundenen API-Key aus Kleingartenverein24 ein. Keys beginnen üblicherweise mit kgv_live_ und werden als Bearer-Token gesendet; ein zusätzlicher Tenant-Slug ist nicht nötig.', 'kgv24') . '</p>';
            },
            self::PAGE_SLUG
        );

        add_settings_field(
            'kgv24_api_base_url',
            __('API-URL', 'kgv24'),
            [$this, 'render_api_base_url_field'],
            self::PAGE_SLUG,
            'kgv24_api_section'
        );

        add_settings_field(
            'kgv24_api_token',
            __('API-Key', 'kgv24'),
            [$this, 'render_api_token_field'],
            self::PAGE_SLUG,
            'kgv24_api_section'
        );
    }

    public function sanitize_settings($input): array
    {
        $current = $this->client->get_settings();
        $input = is_array($input) ? $input : [];
        $api_base_url = isset($input['api_base_url']) ? esc_url_raw(trim((string) $input['api_base_url'])) : '';
        $incoming_token = isset($input['api_token']) ? sanitize_text_field(wp_unslash((string) $input['api_token'])) : '';
        $clear_token = !empty($input['clear_api_token']);

        if ($api_base_url === '') {
            $api_base_url = 'https://kleingartenverein24.de';
        }

        $api_base_url = untrailingslashit($api_base_url);
        $api_token = $current['api_token'];

        if ($clear_token) {
            $api_token = '';
        } elseif ($incoming_token !== '') {
            $api_token = $incoming_token;
        }

        $connection_unchanged = $api_base_url === $current['api_base_url'] && $api_token === $current['api_token'];

        return [
            'api_base_url' => $api_base_url,
            'api_token' => $api_token,
            'last_auth_status' => $connection_unchanged ? $current['last_auth_status'] : '',
            'last_auth_message' => $connection_unchanged ? $current['last_auth_message'] : '',
            'last_auth_checked_at' => $connection_unchanged ? $current['last_auth_checked_at'] : '',
        ];
    }

    public function render_api_base_url_field(): void
    {
        $settings = $this->client->get_settings();

        printf(
            '<input type="url" class="regular-text" name="%1$s[api_base_url]" value="%2$s" placeholder="https://kleingartenverein24.de">',
            esc_attr(KGV24_API_Client::OPTION_NAME),
            esc_attr($settings['api_base_url'])
        );
    }

    public function render_api_token_field(): void
    {
        $settings = $this->client->get_settings();
        $has_token = trim((string) $settings['api_token']) !== '';

        printf(
            '<input type="password" class="regular-text" name="%1$s[api_token]" value="" autocomplete="off" placeholder="%2$s">',
            esc_attr(KGV24_API_Client::OPTION_NAME),
            esc_attr($has_token ? __('API-Key gespeichert - leer lassen zum Behalten', 'kgv24') : 'kgv_live_...')
        );
        echo '<p class="description">' . esc_html__('Der vollständige Key wird von KGV24 nur direkt nach Erstellung oder Erneuerung angezeigt. Leer lassen, um den gespeicherten Key beizubehalten.', 'kgv24') . '</p>';

        if ($has_token) {
            printf(
                '<label><input type="checkbox" name="%1$s[clear_api_token]" value="1"> %2$s</label>',
                esc_attr(KGV24_API_Client::OPTION_NAME),
                esc_html__('Gespeicherten API-Key entfernen', 'kgv24')
            );
        }
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = $this->client->get_settings();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('KGV24', 'kgv24'); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('kgv24_settings_group');
                do_settings_sections(self::PAGE_SLUG);
                submit_button(__('Einstellungen speichern', 'kgv24'));
                ?>
            </form>

            <hr>

            <h2><?php echo esc_html__('Authentifizierung prüfen', 'kgv24'); ?></h2>
            <p><?php echo esc_html__('Der Test ruft /api/tenant/session mit Authorization: Bearer auf. Der Tenant wird aus dem API-Key aufgelöst.', 'kgv24'); ?></p>

            <?php if ($settings['last_auth_checked_at'] !== '') : ?>
                <p>
                    <strong><?php echo esc_html__('Letzter Test:', 'kgv24'); ?></strong>
                    <?php echo esc_html($settings['last_auth_checked_at']); ?>
                    <?php if ($settings['last_auth_message'] !== '') : ?>
                        <br>
                        <?php echo esc_html($settings['last_auth_message']); ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('kgv24_test_auth'); ?>
                <input type="hidden" name="action" value="kgv24_test_auth">
                <?php submit_button(__('Authentifizierung testen', 'kgv24'), 'secondary', 'submit', false); ?>
            </form>

            <hr>

            <h2><?php echo esc_html__('Shortcode', 'kgv24'); ?></h2>
            <p>
                <code>[kgv-garten]</code>
            </p>
        </div>
        <?php
    }

    public function handle_auth_test(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Aktion.', 'kgv24'));
        }

        check_admin_referer('kgv24_test_auth');

        $settings = $this->client->get_settings();
        $result = $this->client->test_authentication();
        $settings['last_auth_checked_at'] = current_time('mysql');

        if (is_wp_error($result)) {
            $settings['last_auth_status'] = 'error';
            $settings['last_auth_message'] = $result->get_error_message();
            $notice = 'error';
        } else {
            $settings['last_auth_status'] = 'success';
            $tenant_slug = is_array($result) ? $this->get_tenant_slug($result) : '';
            $settings['last_auth_message'] = $tenant_slug !== ''
                ? sprintf(
                    /* translators: %s is the tenant slug returned by the API. */
                    __('Authentifizierung erfolgreich. Tenant: %s', 'kgv24'),
                    $tenant_slug
                )
                : __('Authentifizierung erfolgreich.', 'kgv24');
            $notice = 'success';
        }

        update_option(KGV24_API_Client::OPTION_NAME, $settings, false);

        wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'kgv24_notice' => $notice], admin_url('admin.php')));
        exit;
    }

    public function render_auth_notice(): void
    {
        if (!isset($_GET['page'], $_GET['kgv24_notice']) || self::PAGE_SLUG !== $_GET['page']) {
            return;
        }

        $notice = sanitize_key((string) wp_unslash($_GET['kgv24_notice']));
        $settings = $this->client->get_settings();
        $class = $notice === 'success' ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible';
        $message = $settings['last_auth_message'] ?: ($notice === 'success'
            ? __('Authentifizierung erfolgreich.', 'kgv24')
            : __('Authentifizierung konnte nicht geprüft werden.', 'kgv24'));

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
    }

    private function get_tenant_slug(array $result): string
    {
        foreach (['tenant_slug', 'tenantSlug', 'slug'] as $key) {
            if (isset($result[$key]) && !is_array($result[$key]) && !is_object($result[$key])) {
                return trim((string) $result[$key]);
            }
        }

        if (isset($result['tenant']) && is_array($result['tenant'])) {
            foreach (['slug', 'tenant_slug', 'tenantSlug'] as $key) {
                if (isset($result['tenant'][$key]) && !is_array($result['tenant'][$key]) && !is_object($result['tenant'][$key])) {
                    return trim((string) $result['tenant'][$key]);
                }
            }
        }

        return '';
    }
}
