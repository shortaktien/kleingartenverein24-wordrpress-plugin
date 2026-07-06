<?php

if (!defined('ABSPATH')) {
    exit;
}

final class KGV24_Shortcodes
{
    private $client;

    public function __construct(KGV24_API_Client $client)
    {
        $this->client = $client;
    }

    public function init(): void
    {
        add_shortcode('kgv-garten', [$this, 'render_garden_shortcode']);
        add_shortcode('kgv24_garten', [$this, 'render_garden_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets(): void
    {
        wp_register_style(
            'kgv24-public',
            KGV24_PLUGIN_URL . 'assets/css/public.css',
            [],
            KGV24_VERSION
        );
    }

    public function render_garden_shortcode($atts = []): string
    {
        $atts = shortcode_atts(
            [
                'limit' => 0,
                'show_unknown' => '0',
            ],
            $atts,
            'kgv-garten'
        );

        $plots = $this->client->get_plots();

        if (is_wp_error($plots)) {
            return $this->render_notice($plots->get_error_message(), 'error');
        }

        $plots = $this->filter_vacant_plots($plots, $this->to_bool($atts['show_unknown']));
        $limit = max(0, absint($atts['limit']));

        if ($limit > 0) {
            $plots = array_slice($plots, 0, $limit);
        }

        wp_enqueue_style('kgv24-public');

        if (count($plots) === 0) {
            return $this->render_notice(__('Aktuell sind keine freien Gärten verfügbar.', 'kgv24'), 'empty');
        }

        ob_start();
        ?>
        <div class="kgv24-garden-grid" data-kgv24-component="vacant-gardens">
            <?php foreach ($plots as $plot) : ?>
                <?php $title = $this->get_plot_title($plot); ?>
                <article class="kgv24-garden-card">
                    <div class="kgv24-garden-card__header">
                        <span class="kgv24-garden-card__badge"><?php echo esc_html__('Frei', 'kgv24'); ?></span>
                        <h3 class="kgv24-garden-card__title"><?php echo esc_html($title); ?></h3>
                    </div>

                    <dl class="kgv24-garden-card__facts">
                        <?php if ($this->get_value($plot, ['path_name', 'path', 'street', 'location']) !== '') : ?>
                            <div>
                                <dt><?php echo esc_html__('Lage', 'kgv24'); ?></dt>
                                <dd><?php echo esc_html($this->get_value($plot, ['path_name', 'path', 'street', 'location'])); ?></dd>
                            </div>
                        <?php endif; ?>

                        <?php if ($this->get_value($plot, ['area_sqm', 'area', 'size_sqm', 'size']) !== '') : ?>
                            <div>
                                <dt><?php echo esc_html__('Größe', 'kgv24'); ?></dt>
                                <dd><?php echo esc_html($this->format_area($this->get_value($plot, ['area_sqm', 'area', 'size_sqm', 'size']))); ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function filter_vacant_plots(array $plots, bool $show_unknown): array
    {
        $filtered = [];

        foreach ($plots as $plot) {
            $vacancy = $this->detect_vacancy($plot);

            if ($vacancy === true || ($show_unknown && $vacancy === null)) {
                $filtered[] = $plot;
            }
        }

        return $filtered;
    }

    private function detect_vacancy(array $plot): ?bool
    {
        foreach (['is_vacant', 'vacant', 'available', 'is_available', 'unleased', 'is_unleased'] as $key) {
            if (array_key_exists($key, $plot)) {
                return $this->to_bool($plot[$key]);
            }
        }

        foreach (['is_leased', 'leased', 'rented', 'is_rented', 'occupied', 'is_occupied', 'has_contract'] as $key) {
            if (array_key_exists($key, $plot)) {
                return !$this->to_bool($plot[$key]);
            }
        }

        $status = strtolower($this->get_value($plot, ['status', 'lease_status', 'rent_status', 'contract_status']));

        if ($status !== '') {
            if (in_array($status, ['free', 'frei', 'vacant', 'available', 'unleased', 'nicht_gepachtet'], true)) {
                return true;
            }

            if (in_array($status, ['leased', 'rented', 'occupied', 'active', 'verpachtet', 'gepachtet'], true)) {
                return false;
            }
        }

        return null;
    }

    private function get_plot_title(array $plot): string
    {
        $number = $this->get_value($plot, ['number', 'plot_number', 'nr', 'name', 'title']);

        if ($number !== '') {
            return sprintf(
                /* translators: %s is a garden plot number. */
                __('Garten %s', 'kgv24'),
                $number
            );
        }

        return __('Freier Garten', 'kgv24');
    }

    private function get_value(array $item, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $item) || is_array($item[$key]) || is_object($item[$key])) {
                continue;
            }

            $value = trim((string) $item[$key]);

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function format_area(string $area): string
    {
        if ($area === '') {
            return '';
        }

        if (is_numeric($area)) {
            return sprintf(
                /* translators: %s is a square meter value. */
                __('%s qm', 'kgv24'),
                number_format_i18n((float) $area, 0)
            );
        }

        return $area;
    }

    private function render_notice(string $message, string $type): string
    {
        wp_enqueue_style('kgv24-public');

        return sprintf(
            '<div class="kgv24-notice kgv24-notice--%1$s">%2$s</div>',
            esc_attr($type),
            esc_html($message)
        );
    }

    private function to_bool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'ja', 'on'], true);
    }
}
