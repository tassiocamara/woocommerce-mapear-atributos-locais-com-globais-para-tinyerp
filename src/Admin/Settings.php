<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Admin;

class Settings {
    public const OPTION_LOGGING = 'local2global_logging_enabled';
    public const OPTION_AUTO_CREATE = 'local2global_auto_create_terms';
    public const OPTION_UPDATE_VARIATIONS = 'local2global_update_variations';
    public const OPTION_CREATE_BACKUP = 'local2global_create_backup';
    public const OPTION_HYDRATE = 'local2global_hydrate_variations';
    public const OPTION_AGGRESSIVE = 'local2global_aggressive_hydrate_variations';
    public const OPTION_SAVE_TEMPLATE = 'local2global_save_template_default';

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
        // Core feature toggles (yes/no)
        foreach ( [
            self::OPTION_AUTO_CREATE,
            self::OPTION_UPDATE_VARIATIONS,
            self::OPTION_CREATE_BACKUP,
            self::OPTION_HYDRATE,
            self::OPTION_AGGRESSIVE,
            self::OPTION_SAVE_TEMPLATE,
        ] as $opt ) {
            \register_setting('local2global_settings', $opt, [
                'type' => 'string',
                'sanitize_callback' => function( $value ) { return $value === 'yes' ? 'yes' : 'no'; },
                'default' => 'no',
            ]);
        }

        \add_settings_section(
            'local2global_section_general',
            __('Comportamento Padrão', 'local2global'),
            function() {
                echo '<p>' . esc_html__('Defina o comportamento padrão aplicado quando opções não forem enviadas explicitamente via UI modal, REST ou CLI.', 'local2global') . '</p>';
            },
            'local2global-settings'
        );

        $this->add_checkbox_field( self::OPTION_LOGGING, __('Ativar logs', 'local2global'), __('Registrar eventos e diagnósticos no WooCommerce Logs', 'local2global') );
        $this->add_checkbox_field( self::OPTION_AUTO_CREATE, __('Criar termos automaticamente', 'local2global'), __('Cria termos inexistentes ao aplicar mapping.', 'local2global') );
        $this->add_checkbox_field( self::OPTION_UPDATE_VARIATIONS, __('Atualizar variações', 'local2global'), __('Propaga atributo global para as variações.', 'local2global') );
        $this->add_checkbox_field( self::OPTION_CREATE_BACKUP, __('Criar backup para reversão', 'local2global'), __('Gera snapshot antes da aplicação.', 'local2global') );
        $this->add_checkbox_field( self::OPTION_HYDRATE, __('Hidratar variações (recuperar ausentes)', 'local2global'), __('Tenta recuperar variações órfãs (um termo possível).', 'local2global') );
        $this->add_checkbox_field( self::OPTION_AGGRESSIVE, __('Inferência agressiva multi-termos', 'local2global'), __('Heurísticas avançadas (título/SKU) para múltiplos termos.', 'local2global') );
        $this->add_checkbox_field( self::OPTION_SAVE_TEMPLATE, __('Aplicar como template padrão', 'local2global'), __('Salva automaticamente mapping como template reutilizável.', 'local2global') );

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
        echo '<strong>' . esc_html__( 'Quando usar cada opção:', 'local2global' ) . '</strong><br><ul style="margin-top:8px;list-style:disc;padding-left:18px;">';
        $items = [
            __( 'Criar termos automaticamente: migração inicial ou catálogo grande com valores inéditos.', 'local2global' ),
            __( 'Atualizar variações: quando precisa refletir imediatamente o atributo global nas variações.', 'local2global' ),
            __( 'Criar backup: antes de operações volumosas ou primeiras execuções.', 'local2global' ),
            __( 'Aplicar como template padrão: atributos repetitivos em múltiplos produtos (ex.: cores/tamanhos).', 'local2global' ),
            __( 'Hidratar variações: recuperar variações órfãs com único termo possível.', 'local2global' ),
            __( 'Inferência agressiva: títulos/SKU padronizados permitindo distinguir múltiplos termos.', 'local2global' ),
            __( 'Desativar logs: produção estável sem necessidade de diagnosticar.', 'local2global' ),
        ];
        foreach ( $items as $text ) {
            echo '<li>' . esc_html( $text ) . '</li>';
        }
        echo '</ul><p style="margin-top:8px;">' . esc_html__( 'Combine conforme o cenário (ex.: Migração = criar termos + backup + atualizar variações).', 'local2global' ) . '</p>';
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
