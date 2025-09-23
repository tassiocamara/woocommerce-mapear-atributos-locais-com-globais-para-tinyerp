<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Admin;

class Settings {
    public const OPTION_LOGGING = 'local2global_logging_enabled'; // único restante na 0.3.0

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
        // Logging
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
            __('Configurações', 'local2global'),
            function() {
                echo '<p>' . esc_html__('A partir da versão 0.3.0 o plugin opera de forma determinística: sempre cria termos marcados explicitamente no mapeamento e sempre atualiza variações. Apenas o logging pode ser controlado aqui.', 'local2global') . '</p>';
            },
            'local2global-settings'
        );

        $this->add_checkbox_field( self::OPTION_LOGGING, __('Ativar logs', 'local2global'), __('Registrar eventos e diagnósticos no WooCommerce Logs', 'local2global') );

        \add_settings_section(
            'local2global_section_help',
            __('Guia de Uso das Opções', 'local2global'),
            [ $this, 'render_help_block' ],
            'local2global-settings'
        );
    }

    private function add_checkbox_field( string $option, string $label, string $description ): void {
        \add_settings_field(
            $option,
            $label,
            function() use ( $option, $description ) {
                $value = \get_option( $option, $option === self::OPTION_LOGGING ? 'yes' : 'no' );
                echo '<input type="hidden" name="' . esc_attr( $option ) . '" value="no" />';
                echo '<label><input type="checkbox" name="' . esc_attr( $option ) . '" value="yes" ' . checked( 'yes', $value, false ) . '> ' . esc_html( $description ) . '</label>';
            },
            'local2global-settings',
            'local2global_section_general'
        );
    }

    public function render_help_block(): void {
        echo '<div class="notice-info l2g-help-block" style="background:#fff;border:1px solid #ccd0d4;padding:12px;line-height:1.4;">';
        echo '<strong>' . esc_html__( 'Modo Simplificado (>= 0.3.0):', 'local2global' ) . '</strong><br>';
        echo '<p>' . esc_html__( 'Todas as antigas opções foram removidas. O mapeamento sempre aplicará as seleções feitas na interface e atualizará variações. Use esta página apenas para habilitar ou desabilitar logs.', 'local2global' ) . '</p>';
        echo '</div>';
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
