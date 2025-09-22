<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Admin;

class Settings {
    public const OPTION_LOGGING = 'local2global_logging_enabled';

    public function init(): void {
        \add_action('admin_init', [$this, 'register']);
        \add_action('admin_menu', [$this, 'add_menu']);
    }

    public function add_menu(): void {
        \add_options_page(
            __('Local2Global - Configurações', 'local2global'),
            __('Local2Global', 'local2global'),
            'manage_options',
            'local2global-settings',
            [$this, 'render_page']
        );
    }

    public function register(): void {
        \register_setting('local2global_settings', self::OPTION_LOGGING, [
            'type' => 'string',
            'sanitize_callback' => function( $value ) {
                // Se não vier no POST (checkbox desmarcado), será null -> converte para 'no'
                return $value === 'yes' ? 'yes' : 'no';
            },
            'default' => 'yes',
        ]);

        \add_settings_section(
            'local2global_section_general',
            __('Geral', 'local2global'),
            fn() => print '<p>' . esc_html__('Ajustes gerais do plugin Local2Global.', 'local2global') . '</p>',
            'local2global-settings'
        );

        \add_settings_field(
            self::OPTION_LOGGING,
            __('Ativar logs', 'local2global'),
            [$this, 'render_logging_field'],
            'local2global-settings',
            'local2global_section_general'
        );
    }

    public function render_logging_field(): void {
        $value = \get_option(self::OPTION_LOGGING, 'yes');
        // Hidden para garantir submissão de 'no' quando desmarcado
        echo '<input type="hidden" name="' . esc_attr(self::OPTION_LOGGING) . '" value="no" />';
        echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_LOGGING) . '" value="yes" ' . checked('yes', $value, false) . '> ' . esc_html__('Registrar eventos e diagnósticos no log WooCommerce', 'local2global') . '</label>';
        echo '<p class="description">' . esc_html__('Desmarcar para silenciar logs (exceto erros críticos internos do WooCommerce).', 'local2global') . '</p>';
    }

    public function render_page(): void {
        if (!current_user_can('manage_options')) { return; }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Local2Global - Configurações', 'local2global') . '</h1>';
        echo '<form method="post" action="options.php">';
        \settings_fields('local2global_settings');
        \do_settings_sections('local2global-settings');
        \submit_button();
        echo '</form>';
        echo '</div>';
    }
}
