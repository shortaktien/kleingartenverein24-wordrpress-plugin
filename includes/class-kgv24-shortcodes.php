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
        add_shortcode('kgv-arbeitseinsaetze', [$this, 'render_work_assignments_shortcode']);
        add_shortcode('kgv24_arbeitseinsaetze', [$this, 'render_work_assignments_shortcode']);
        add_shortcode('kgv-versammlungen', [$this, 'render_meetings_shortcode']);
        add_shortcode('kgv24_versammlungen', [$this, 'render_meetings_shortcode']);
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
            ],
            $atts,
            'kgv-garten'
        );

        $plots = $this->client->get_vacant_plots();

        if (is_wp_error($plots)) {
            return $this->render_notice($plots->get_error_message(), 'error');
        }

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

    public function render_work_assignments_shortcode($atts = []): string
    {
        $atts = shortcode_atts(
            [
                'limit' => 0,
            ],
            $atts,
            'kgv-arbeitseinsaetze'
        );

        $assignments = $this->client->get_work_assignments();

        if (is_wp_error($assignments)) {
            return $this->render_notice($assignments->get_error_message(), 'error');
        }

        $assignments = $this->limit_items($assignments, $atts['limit']);
        wp_enqueue_style('kgv24-public');

        if (count($assignments) === 0) {
            return $this->render_notice(__('Aktuell sind keine kommenden Arbeitseinsätze geplant.', 'kgv24'), 'empty');
        }

        ob_start();
        ?>
        <div class="kgv24-list" data-kgv24-component="work-assignments">
            <?php foreach ($assignments as $assignment) : ?>
                <article class="kgv24-list-card">
                    <div class="kgv24-list-card__date"><?php echo esc_html($this->format_datetime($this->get_value($assignment, ['starts_at', 'date', 'scheduled_at']))); ?></div>
                    <h3 class="kgv24-list-card__title"><?php echo esc_html($this->get_value($assignment, ['title', 'name']) ?: __('Arbeitseinsatz', 'kgv24')); ?></h3>

                    <?php if ($this->get_value($assignment, ['description', 'task', 'what']) !== '') : ?>
                        <p class="kgv24-list-card__text"><?php echo esc_html($this->get_value($assignment, ['description', 'task', 'what'])); ?></p>
                    <?php endif; ?>

                    <dl class="kgv24-list-card__facts">
                        <div>
                            <dt><?php echo esc_html__('Freie Plätze', 'kgv24'); ?></dt>
                            <dd><?php echo esc_html($this->format_slots($this->get_value($assignment, ['available_slots', 'free_slots', 'places_available']))); ?></dd>
                        </div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public function render_meetings_shortcode($atts = []): string
    {
        $atts = shortcode_atts(
            [
                'limit' => 0,
            ],
            $atts,
            'kgv-versammlungen'
        );

        $meetings = $this->client->get_meetings();

        if (is_wp_error($meetings)) {
            return $this->render_notice($meetings->get_error_message(), 'error');
        }

        $meetings = $this->limit_items($meetings, $atts['limit']);
        wp_enqueue_style('kgv24-public');

        if (count($meetings) === 0) {
            return $this->render_notice(__('Aktuell sind keine kommenden Versammlungen geplant.', 'kgv24'), 'empty');
        }

        ob_start();
        ?>
        <div class="kgv24-list" data-kgv24-component="meetings">
            <?php foreach ($meetings as $meeting) : ?>
                <article class="kgv24-list-card">
                    <div class="kgv24-list-card__date"><?php echo esc_html($this->format_datetime($this->get_value($meeting, ['scheduled_at', 'date']))); ?></div>
                    <h3 class="kgv24-list-card__title"><?php echo esc_html($this->get_value($meeting, ['title', 'name']) ?: __('Versammlung', 'kgv24')); ?></h3>

                    <?php if ($this->get_value($meeting, ['location', 'place', 'venue']) !== '') : ?>
                        <dl class="kgv24-list-card__facts">
                            <div>
                                <dt><?php echo esc_html__('Ort', 'kgv24'); ?></dt>
                                <dd><?php echo esc_html($this->get_value($meeting, ['location', 'place', 'venue'])); ?></dd>
                            </div>
                        </dl>
                    <?php endif; ?>

                    <?php if ($this->get_list($meeting, 'agenda') !== []) : ?>
                        <div class="kgv24-list-card__section">
                            <div class="kgv24-list-card__label"><?php echo esc_html__('Agenda', 'kgv24'); ?></div>
                            <ul class="kgv24-list-card__items">
                                <?php foreach ($this->get_list($meeting, 'agenda') as $agenda_item) : ?>
                                    <li><?php echo esc_html($agenda_item); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php

        return (string) ob_get_clean();
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

    private function format_datetime(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    private function format_slots(string $slots): string
    {
        if ($slots === '') {
            return __('Keine Angabe', 'kgv24');
        }

        return sprintf(
            /* translators: %s is a number of available slots. */
            _n('%s Platz', '%s Plätze', (int) $slots, 'kgv24'),
            number_format_i18n((int) $slots)
        );
    }

    private function get_list(array $item, string $key): array
    {
        if (!isset($item[$key]) || !is_array($item[$key])) {
            return [];
        }

        $values = [];
        foreach ($item[$key] as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function limit_items(array $items, $limit): array
    {
        $limit = max(0, absint($limit));

        if ($limit === 0) {
            return $items;
        }

        return array_slice($items, 0, $limit);
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

}
