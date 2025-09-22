<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Cli;

use Evolury\Local2Global\Services\Mapping_Service;
use WP_CLI_Command;
use WP_Query;

/**
 * Nota: Este comando usa funções globais do WordPress (wp_insert_post, update_post_meta, etc.)
 * que estarão disponíveis somente no runtime WP-CLI. O analisador estático pode sinalizar
 * como indefinidas dentro do namespace.
 */
class CLI_Command extends WP_CLI_Command {
    public function __construct( private Mapping_Service $mapping ) {}

    /**
     * Mapeia atributos locais para globais via linha de comando.
     *
     * ## OPTIONS
     *
     * [--product=<id|all>]
     * : ID do produto. Use "all" para aplicar a todos os produtos.
     *
     * [--attr=<local:pa_slug>]
     * : Par atributo local e taxonomia alvo. Pode ser repetido.
     *
     * [--term=<valor_local:slug_global>]
     * : Par valor local e slug global. Pode ser repetido.
     *
     * [--create-missing=<0|1>]
     * : Criar termos faltantes automaticamente.
     *
     * [--apply-variations=<0|1>]
     * : Atualizar variações.
     *
     * [--dry-run=<0|1>]
     * : Executa em modo de pré-visualização.
    *
    * [--hydrate-variations=<0|1>]
    * : Tenta hidratar variações sem meta local usando heurísticas básicas.
    *
    * [--aggressive-hydrate-variations=<0|1>]
    * : Ativa inferência agressiva multi-termos (título, SKU, padrões numéricos).
     */
    public function map( array $args, array $assoc_args ): void {
        $product_option = $assoc_args['product'] ?? null;
        if ( ! $product_option ) {
            \WP_CLI::error( 'Informe o parâmetro --product.' );
        }

        $products = [];
        if ( 'all' === $product_option ) {
            $query = new WP_Query(
                [
                    'post_type'      => 'product',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                ]
            );
            $products = $query->posts;
        } else {
            $products = [ (int) $product_option ];
        }

        $attr_inputs = (array) ( $assoc_args['attr'] ?? [] );
        if ( empty( $attr_inputs ) ) {
            \WP_CLI::error( 'Informe ao menos um --attr local:pa_slug.' );
        }

        $term_inputs = (array) ( $assoc_args['term'] ?? [] );
        $term_config = [];
        foreach ( $term_inputs as $input ) {
            [ $local_value, $slug ] = array_pad( explode( ':', (string) $input, 2 ), 2, '' );
            if ( '' === $local_value || '' === $slug ) {
                continue;
            }

            $term_config[] = [
                'local_value' => $local_value,
                'term_slug'   => $slug,
                'term_name'   => $local_value,
                'create'      => ! empty( $assoc_args['create-missing'] ),
            ];
        }

        $mapping = [];
        foreach ( $attr_inputs as $attr_pair ) {
            [ $local_attr, $target_tax ] = array_pad( explode( ':', (string) $attr_pair, 2 ), 2, '' );
            if ( '' === $local_attr || '' === $target_tax ) {
                continue;
            }

            $mapping[] = [
                'local_attr'       => $local_attr,
                'local_label'      => $local_attr,
                'target_tax'       => $target_tax,
                'target_label'     => $local_attr,
                'create_attribute' => false,
                'terms'            => $term_config,
            ];
        }

        if ( empty( $mapping ) ) {
            \WP_CLI::error( 'Mapeamento inválido.' );
        }

        $options = [
            'auto_create_terms'              => ! empty( $assoc_args['create-missing'] ),
            'update_variations'              => ! empty( $assoc_args['apply-variations'] ),
            'create_backup'                  => empty( $assoc_args['dry-run'] ),
            'hydrate_variations'             => ! empty( $assoc_args['hydrate-variations'] ),
            'aggressive_hydrate_variations'  => ! empty( $assoc_args['aggressive-hydrate-variations'] ),
        ];

        foreach ( $products as $product_id ) {
            $corr_id = uniqid( 'l2g_cli_', true );

            if ( ! empty( $assoc_args['dry-run'] ) ) {
                $result = $this->mapping->dry_run( (int) $product_id, $mapping, $options, $corr_id );

                if ( is_wp_error( $result ) ) {
                    $data = $result->get_error_data();
                    \WP_CLI::warning( sprintf( 'Dry-run falhou para o produto %d (%s): %s', $product_id, $data['corr_id'] ?? $corr_id, $result->get_error_message() ) );
                    continue;
                }

                \WP_CLI::log( sprintf( 'Dry-run para o produto %d (%s): %s', $product_id, $corr_id, wp_json_encode( $result ) ) );
            } else {
                $result = $this->mapping->apply( (int) $product_id, $mapping, $options, $corr_id );

                if ( is_wp_error( $result ) ) {
                    $data = $result->get_error_data();
                    \WP_CLI::warning( sprintf( 'Erro ao processar o produto %d (%s): %s', $product_id, $data['corr_id'] ?? $corr_id, $result->get_error_message() ) );
                    continue;
                }

                \WP_CLI::success( sprintf( 'Mapeamento aplicado ao produto %d (%s): %s', $product_id, $corr_id, wp_json_encode( $result ) ) );
            }
        }
    }

    /**
     * Cria um produto variável de teste com atributos locais e executa um mapeamento completo.
     *
     * ## OPTIONS
     *
     * [--attr=<nome_local:pa_slug>]
     * : Define o atributo local e a taxonomia alvo (default: "Cor:pa_cor"). Pode repetir.
     *
     * [--val=<valor_local:slug_global>]
     * : Define um valor local e slug destino (p. ex. "Azul:azul"). Pode repetir.
     *
     * [--variations=<n>]
     * : Quantidade de variações a gerar (default: 2).
     *
     * [--dry-run=<0|1>]
     * : Se definido como 1, apenas simula.
     */
    public function simulate( array $args, array $assoc_args ): void {
        $attrs_input = (array) ( $assoc_args['attr'] ?? [ 'Cor:pa_cor' ] );
        $vals_input  = (array) ( $assoc_args['val'] ?? [ 'Azul:azul', 'Vermelho:vermelho' ] );
        $variations_qtd = (int) ( $assoc_args['variations'] ?? 2 );

        if ( $variations_qtd < 1 ) {
            $variations_qtd = 1;
        }

        $mapping = [];
        $terms_template = [];
        foreach ( $vals_input as $pair ) {
            [ $local_value, $slug ] = array_pad( explode( ':', (string) $pair, 2 ), 2, '' );
            if ( '' === $local_value || '' === $slug ) { continue; }
            $terms_template[] = [
                'local_value' => $local_value,
                'term_slug'   => $slug,
                'term_name'   => $local_value,
                'create'      => true,
            ];
        }

        foreach ( $attrs_input as $a_pair ) {
            [ $local_attr, $target_tax ] = array_pad( explode( ':', (string) $a_pair, 2 ), 2, '' );
            if ( '' === $local_attr || '' === $target_tax ) { continue; }
            $mapping[] = [
                'local_attr'       => $local_attr,
                'local_label'      => $local_attr,
                'target_tax'       => $target_tax,
                'target_label'     => $local_attr,
                'create_attribute' => true,
                'terms'            => $terms_template,
                'save_template'    => true,
            ];
        }

        if ( empty( $mapping ) ) {
            \WP_CLI::error( 'Nenhum atributo válido informado.' );
        }

        // Cria produto variável
        $product_id = wp_insert_post( [
            'post_type'   => 'product',
            'post_status' => 'publish',
            'post_title'  => 'Produto Teste Local2Global ' . wp_generate_password( 6, false ),
        ] );
        if ( ! $product_id || is_wp_error( $product_id ) ) {
            \WP_CLI::error( 'Falha ao criar produto de teste.' );
        }

        // Monta atributos locais no produto (meta `_product_attributes`).
        $attr_meta = [];
        foreach ( $mapping as $index => $map ) {
            $values = array_map( static fn( $t ) => $t['local_value'], $map['terms'] );
            $attr_meta[ sanitize_title( $map['local_attr'] ) ] = [
                'name'         => $map['local_attr'],
                'value'        => implode( ' | ', $values ),
                'position'     => $index,
                'is_visible'   => 1,
                'is_variation' => 1,
                'is_taxonomy'  => 0,
            ];
        }
        update_post_meta( $product_id, '_product_attributes', $attr_meta );

        // Cria variações simples baseadas nos valores (limitado ao primeiro atributo para simplicidade).
        $first_attr = $mapping[0]['local_attr'];
        $values     = array_map( static fn( $t ) => $t['local_value'], $mapping[0]['terms'] );
        $loop       = 0;
        foreach ( $values as $val ) {
            if ( $loop >= $variations_qtd ) { break; }
            $variation_id = wp_insert_post( [
                'post_type'   => 'product_variation',
                'post_status' => 'publish',
                'post_parent' => $product_id,
                'menu_order'  => $loop,
                'post_title'  => 'Variação ' . $val,
            ] );
            if ( $variation_id && ! is_wp_error( $variation_id ) ) {
                update_post_meta( $variation_id, 'attribute_' . sanitize_title( $first_attr ), $val );
                update_post_meta( $variation_id, '_price', 10 + $loop );
                update_post_meta( $variation_id, '_regular_price', 10 + $loop );
            }
            $loop++;
        }

        \WP_CLI::log( sprintf( 'Produto de teste criado: %d', $product_id ) );

        $options = [
            'auto_create_terms' => true,
            'update_variations' => true,
            'create_backup'     => empty( $assoc_args['dry-run'] ),
        ];

        $corr_id = uniqid( 'l2g_sim_', true );
        if ( ! empty( $assoc_args['dry-run'] ) ) {
            $result = $this->mapping->dry_run( (int) $product_id, $mapping, $options, $corr_id );
            if ( is_wp_error( $result ) ) {
                $data = $result->get_error_data();
                \WP_CLI::warning( sprintf( 'Dry-run falhou (%s): %s', $data['corr_id'] ?? $corr_id, $result->get_error_message() ) );
            } else {
                \WP_CLI::success( sprintf( 'Dry-run concluído (%s): %s', $corr_id, wp_json_encode( $result ) ) );
            }
        } else {
            $result = $this->mapping->apply( (int) $product_id, $mapping, $options, $corr_id );
            if ( is_wp_error( $result ) ) {
                $data = $result->get_error_data();
                \WP_CLI::warning( sprintf( 'Aplicação falhou (%s): %s', $data['corr_id'] ?? $corr_id, $result->get_error_message() ) );
            } else {
                \WP_CLI::success( sprintf( 'Mapeamento aplicado (%s): %s', $corr_id, wp_json_encode( $result ) ) );
            }
        }

        \WP_CLI::log( 'Verifique logs WooCommerce (source=local2global) para detalhes.' );
    }

    /**
     * Reprocessa apenas variações de um produto já mapeado.
     *
     * ## OPTIONS
     *
     * --product=<id>
     * : ID do produto variável.
     *
     * [--tax=<pa_tax>]
     * : Limitar a uma ou mais taxonomias (pode repetir).
     *
    * [--hydrate=<0|1>]
    * : Ativa modo de hidratação básica de variações sem meta local.
    *
    * [--aggressive=<0|1>]
    * : Ativa inferência agressiva multi-termos.
     */
    public function variations_update( array $args, array $assoc_args ): void {
        $product_id = (int) ( $assoc_args['product'] ?? 0 );
        if ( $product_id <= 0 ) {
            \WP_CLI::error( 'Informe --product=<id>.' );
        }
        $tax_filters = (array) ( $assoc_args['tax'] ?? [] );
        $corr_id = uniqid( 'l2g_cli_var_', true );
    $hydrate    = ! empty( $assoc_args['hydrate'] );
    $aggressive = ! empty( $assoc_args['aggressive'] );
    $result  = $this->mapping->update_variations_only( $product_id, $tax_filters ?: null, $corr_id, $hydrate, $aggressive );
        if ( is_wp_error( $result ) ) {
            \WP_CLI::warning( sprintf( 'Falha (%s): %s', $corr_id, $result->get_error_message() ) );
            return;
        }
        \WP_CLI::success( sprintf( 'Variações reprocessadas (%s): %s', $corr_id, wp_json_encode( $result ) ) );
    }
}
