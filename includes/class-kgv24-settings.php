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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_kgv24_test_auth', [$this, 'handle_auth_test']);
        add_action('admin_notices', [$this, 'render_auth_notice']);
    }

    public function add_admin_menu(): void
    {
        add_menu_page(
            __('KGV24', 'kleingartenverein24'),
            __('KGV24', 'kleingartenverein24'),
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
            __('API-Verbindung', 'kleingartenverein24'),
            static function () {
                echo '<p>' . esc_html__('Trage hier den tenant-gebundenen API-Key aus Kleingartenverein24 ein. Keys beginnen üblicherweise mit kgv_live_ und werden als Bearer-Token gesendet; ein zusätzlicher Tenant-Slug ist nicht nötig.', 'kleingartenverein24') . '</p>';
            },
            self::PAGE_SLUG
        );

        add_settings_field(
            'kgv24_api_base_url',
            __('API-URL', 'kleingartenverein24'),
            [$this, 'render_api_base_url_field'],
            self::PAGE_SLUG,
            'kgv24_api_section'
        );

        add_settings_field(
            'kgv24_api_token',
            __('API-Key', 'kleingartenverein24'),
            [$this, 'render_api_token_field'],
            self::PAGE_SLUG,
            'kgv24_api_section'
        );
    }

    public function enqueue_admin_assets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'toplevel_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_style(
            'kgv24-admin',
            KGV24_PLUGIN_URL . 'assets/css/admin.css',
            [],
            KGV24_VERSION
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
            esc_attr($has_token ? __('API-Key gespeichert - leer lassen zum Behalten', 'kleingartenverein24') : 'kgv_live_...')
        );
        echo '<p class="description">' . esc_html__('Der vollständige Key wird von KGV24 nur direkt nach Erstellung oder Erneuerung angezeigt. Leer lassen, um den gespeicherten Key beizubehalten.', 'kleingartenverein24') . '</p>';

        if ($has_token) {
            printf(
                '<label><input type="checkbox" name="%1$s[clear_api_token]" value="1"> %2$s</label>',
                esc_attr(KGV24_API_Client::OPTION_NAME),
                esc_html__('Gespeicherten API-Key entfernen', 'kleingartenverein24')
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
            <h1><?php echo esc_html__('KGV24', 'kleingartenverein24'); ?></h1>

            <div class="kgv24-admin-layout">
                <main class="kgv24-admin-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('kgv24_settings_group');
                        do_settings_sections(self::PAGE_SLUG);
                        submit_button(__('Einstellungen speichern', 'kleingartenverein24'));
                        ?>
                    </form>

                    <hr>

                    <h2><?php echo esc_html__('Authentifizierung prüfen', 'kleingartenverein24'); ?></h2>
                    <p><?php echo esc_html__('Der Test ruft /api/tenant/session mit Authorization: Bearer auf. Der Tenant wird aus dem API-Key aufgelöst.', 'kleingartenverein24'); ?></p>

                    <?php if ($settings['last_auth_checked_at'] !== '') : ?>
                        <p>
                            <strong><?php echo esc_html__('Letzter Test:', 'kleingartenverein24'); ?></strong>
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
                        <?php submit_button(__('Authentifizierung testen', 'kleingartenverein24'), 'secondary', 'submit', false); ?>
                    </form>

                    <hr>

                    <h2><?php echo esc_html__('Shortcodes', 'kleingartenverein24'); ?></h2>
                    <p><?php echo esc_html__('Füge einen Shortcode in eine Seite, einen Beitrag oder einen shortcode-kompatiblen Block ein. Mit dem optionalen Attribut limit begrenzt du die Anzahl der Einträge.', 'kleingartenverein24'); ?></p>

                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo esc_html__('Shortcode', 'kleingartenverein24'); ?></th>
                                <th scope="col"><?php echo esc_html__('Was wird angezeigt?', 'kleingartenverein24'); ?></th>
                                <th scope="col"><?php echo esc_html__('Beispiel mit Limit', 'kleingartenverein24'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>[kgv-garten]</code></td>
                                <td><?php echo esc_html__('Zeigt freie Gärten aus Kleingartenverein24 als Karten an.', 'kleingartenverein24'); ?></td>
                                <td><code>[kgv-garten limit="6"]</code></td>
                            </tr>
                            <tr>
                                <td><code>[kgv-arbeitseinsaetze]</code></td>
                                <td><?php echo esc_html__('Zeigt kommende Arbeitseinsätze mit Datum, Beschreibung und freien Plätzen an.', 'kleingartenverein24'); ?></td>
                                <td><code>[kgv-arbeitseinsaetze limit="3"]</code></td>
                            </tr>
                            <tr>
                                <td><code>[kgv-versammlungen]</code></td>
                                <td><?php echo esc_html__('Zeigt kommende Versammlungen mit Datum, Ort und Agenda an.', 'kleingartenverein24'); ?></td>
                                <td><code>[kgv-versammlungen limit="2"]</code></td>
                            </tr>
                        </tbody>
                    </table>
                </main>

                <?php $this->render_info_sidebar($settings); ?>
            </div>
        </div>
        <?php
    }

    private function render_info_sidebar(array $settings): void
    {
        $health_class = 'unknown';
        $health_label = __('Nicht geprüft', 'kleingartenverein24');

        if ($settings['last_auth_status'] === 'success') {
            $health_class = 'success';
            $health_label = __('Verbunden', 'kleingartenverein24');
        } elseif ($settings['last_auth_status'] === 'error') {
            $health_class = 'error';
            $health_label = __('Fehler', 'kleingartenverein24');
        }
        ?>
        <aside class="kgv24-admin-sidebar" aria-label="<?php echo esc_attr__('KGV24 Informationen', 'kleingartenverein24'); ?>">
            <div class="kgv24-admin-sidebar__header">
                <h2><?php echo esc_html__('KGV24 Info', 'kleingartenverein24'); ?></h2>
            </div>
            <div class="kgv24-admin-sidebar__content">
                <div class="kgv24-admin-sidebar__section">
                    <p class="kgv24-admin-sidebar__label"><?php echo esc_html__('API Health', 'kleingartenverein24'); ?></p>
                    <p class="kgv24-admin-sidebar__value kgv24-admin-health kgv24-admin-health--<?php echo esc_attr($health_class); ?>">
                        <span class="kgv24-admin-health__dot" aria-hidden="true"></span>
                        <span><?php echo esc_html($health_label); ?></span>
                    </p>
                    <?php if ($settings['last_auth_checked_at'] !== '') : ?>
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s is the last API auth test timestamp. */
                                esc_html__('Letzter Test: %s', 'kleingartenverein24'),
                                esc_html($settings['last_auth_checked_at'])
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="kgv24-admin-sidebar__section">
                    <p class="kgv24-admin-sidebar__label"><?php echo esc_html__('Support', 'kleingartenverein24'); ?></p>
                    <p class="kgv24-admin-sidebar__value">
                        <a href="<?php echo esc_url('mailto:hallo@shortaktien.de'); ?>">hallo@shortaktien.de</a>
                    </p>
                </div>

                <div class="kgv24-admin-sidebar__section">
                    <p class="kgv24-admin-sidebar__label"><?php echo esc_html__('Links', 'kleingartenverein24'); ?></p>
                    <p class="kgv24-admin-links">
                        <a href="<?php echo esc_url('https://kleingartenverein24.de/'); ?>" target="_blank" rel="noopener noreferrer">kleingartenverein24.de</a>
                        <a href="<?php echo esc_url('https://shortaktien.de/'); ?>" target="_blank" rel="noopener noreferrer">shortaktien.de</a>
                    </p>
                </div>

                <div class="kgv24-admin-sidebar__section">
                    <p class="kgv24-admin-sidebar__label"><?php echo esc_html__('Version', 'kleingartenverein24'); ?></p>
                    <p class="kgv24-admin-sidebar__value"><?php echo esc_html(KGV24_VERSION); ?></p>
                </div>
            </div>
        </aside>
        <?php
    }

    public function handle_auth_test(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Du hast keine Berechtigung für diese Aktion.', 'kleingartenverein24'));
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
                    __('Authentifizierung erfolgreich. Tenant: %s', 'kleingartenverein24'),
                    $tenant_slug
                )
                : __('Authentifizierung erfolgreich.', 'kleingartenverein24');
            $notice = 'success';
        }

        update_option(KGV24_API_Client::OPTION_NAME, $settings, false);

        wp_safe_redirect(add_query_arg(['page' => self::PAGE_SLUG, 'kgv24_notice' => $notice], admin_url('admin.php')));
        exit;
    }

    public function render_auth_notice(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice after wp_safe_redirect().
        if (!isset($_GET['page'], $_GET['kgv24_notice']) || self::PAGE_SLUG !== $_GET['page']) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice after wp_safe_redirect().
        $notice = sanitize_key((string) wp_unslash($_GET['kgv24_notice']));
        $settings = $this->client->get_settings();
        $class = $notice === 'success' ? 'notice notice-success is-dismissible' : 'notice notice-error is-dismissible';
        $message = $settings['last_auth_message'] ?: ($notice === 'success'
            ? __('Authentifizierung erfolgreich.', 'kleingartenverein24')
            : __('Authentifizierung konnte nicht geprüft werden.', 'kleingartenverein24'));

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
