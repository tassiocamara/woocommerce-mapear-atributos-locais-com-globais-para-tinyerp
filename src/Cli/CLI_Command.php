<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Cli;

use Evolury\Local2Global\Services\Mapping_Service;
use WP_CLI_Command;
use WP_Query;

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
            'auto_create_terms' => ! empty( $assoc_args['create-missing'] ),
            'update_variations' => ! empty( $assoc_args['apply-variations'] ),
            'create_backup'     => empty( $assoc_args['dry-run'] ),
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
}
