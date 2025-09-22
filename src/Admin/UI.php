<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Admin;

class UI {
    public function __construct( private string $plugin_url ) {}

    public function hooks(): void {
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'woocommerce_product_options_attributes', [ $this, 'render_button' ] );
        add_action( 'admin_footer', [ $this, 'render_modal' ] );
    }

    public function enqueue_assets( string $hook ): void {
        if ( ! $this->is_product_screen( $hook ) ) {
            return;
        }

        wp_register_style(
            'local2global-admin',
            $this->plugin_url . 'assets/css/admin.css',
            [],
            \Evolury\Local2Global\VERSION
        );

        wp_register_script(
            'local2global-admin',
            $this->plugin_url . 'assets/js/admin.js',
            [ 'wp-api-fetch', 'wp-i18n' ],
            \Evolury\Local2Global\VERSION,
            true
        );

        $global_attributes = [];
        foreach ( wc_get_attribute_taxonomies() as $taxonomy ) {
            $global_attributes[] = [
                'slug'  => 'pa_' . $taxonomy->attribute_name,
                'label' => $taxonomy->attribute_label,
            ];
        }

        $screen    = get_current_screen();
        $productId = 0;
        if ( isset( $screen->post_type ) && 'product' === $screen->post_type && isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $productId = (int) $_GET['post']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        wp_localize_script(
            'local2global-admin',
            'Local2GlobalSettings',
            [
                'rest'       => [
                    'root'  => esc_url_raw( rest_url( 'local2global/v1' ) ),
                    'nonce' => wp_create_nonce( 'wp_rest' ),
                ],
                'i18n'       => [
                    'title'            => __( 'Mapear atributos locais → globais', 'local2global' ),
                    'discover'         => __( 'Descobrir atributos locais', 'local2global' ),
                    'dryRun'           => __( 'Dry-run', 'local2global' ),
                    'apply'            => __( 'Aplicar', 'local2global' ),
                    'autoMap'          => __( 'Auto-mapear por similaridade', 'local2global' ),
                    'createTerm'       => __( 'Criar termo automaticamente', 'local2global' ),
                    'updateVariations' => __( 'Atualizar variações', 'local2global' ),
                    'backup'           => __( 'Criar backup para reversão', 'local2global' ),
                    'template'         => __( 'Salvar como template', 'local2global' ),
                    'dryRunTitle'      => __( 'Pré-visualização', 'local2global' ),
                    'applyTitle'       => __( 'Aplicando mapeamento…', 'local2global' ),
                    'done'             => __( 'Mapeamento concluído', 'local2global' ),
                ],
                'productId'  => $productId,
                'attributes' => $global_attributes,
            ]
        );

        wp_enqueue_style( 'local2global-admin' );
        wp_enqueue_script( 'local2global-admin' );
    }

    public function render_button(): void {
        global $post;
        if ( empty( $post->ID ) ) {
            return;
        }

        echo '<div class="local2global-button-wrap"><button type="button" class="button local2global-open">' . esc_html__( 'Mapear atributos locais → globais', 'local2global' ) . '</button></div>';
    }

    public function render_modal(): void {
        $screen = get_current_screen();
        if ( ! $screen || 'product' !== $screen->post_type ) {
            return;
        }

        ?>
        <div id="local2global-modal" class="local2global-modal" role="dialog" aria-modal="true" aria-labelledby="local2global-modal-title" hidden>
            <div class="local2global-modal__overlay" data-local2global-close></div>
            <div class="local2global-modal__content" role="document">
                <header class="local2global-modal__header">
                    <h1 id="local2global-modal-title"></h1>
                    <button type="button" class="local2global-modal__close" aria-label="<?php esc_attr_e( 'Fechar', 'local2global' ); ?>" data-local2global-close>&times;</button>
                </header>
                <main class="local2global-modal__body" tabindex="0"></main>
                <footer class="local2global-modal__footer">
                    <button type="button" class="button button-secondary local2global-prev" disabled><?php esc_html_e( 'Voltar', 'local2global' ); ?></button>
                    <button type="button" class="button button-primary local2global-next"><?php esc_html_e( 'Continuar', 'local2global' ); ?></button>
                </footer>
            </div>
        </div>
        <?php
    }

    private function is_product_screen( string $hook ): bool {
        return in_array( $hook, [ 'post.php', 'post-new.php' ], true ) && 'product' === get_post_type();
    }
}
