<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Services;

use Evolury\Local2Global\Utils\Logger;
use RuntimeException;

// Stubs condicionais apenas para satisfazer análise estática fora do ambiente WordPress.
// Em produção (WordPress carregado) essas funções já existem e os stubs não são registrados.
if ( ! function_exists( 'wc_attribute_taxonomy_id_by_name' ) ) {
    function wc_attribute_taxonomy_id_by_name( $name ) { return 0; }
}
if ( ! function_exists( 'taxonomy_exists' ) ) {
    function taxonomy_exists( $taxonomy ) { return false; }
}
if ( ! function_exists( 'register_taxonomy' ) ) {
    function register_taxonomy( $taxonomy, $object_type, $args = [] ) { return true; }
}
if ( ! function_exists( 'apply_filters' ) ) {
    function apply_filters( $hook, $value ) { return $value; }
}
if ( ! function_exists( 'get_term' ) ) {
    function get_term( $term_id, $taxonomy ) { return null; }
}
if ( ! function_exists( 'get_term_by' ) ) {
    function get_term_by( $field, $value, $taxonomy ) { return null; }
}
if ( ! function_exists( 'wp_insert_term' ) ) {
    function wp_insert_term( $term, $taxonomy, $args = [] ) { return [ 'term_id' => 0 ]; }
}

class Term_Service {
    /** @var array<string, array<string, array{term_id:int, slug:string}>> */
    private array $term_cache = [];

    public function __construct( private Logger $logger ) {}

    /**
     * @return array{attribute_id:int, taxonomy:string}
     */
    public function ensure_global_attribute( string $target_taxonomy, string $label, bool $create_if_missing = false, array $args = [] ): array {
        $target_taxonomy = sanitize_key( $target_taxonomy );
        if ( ! str_starts_with( $target_taxonomy, 'pa_' ) ) {
            $target_taxonomy = 'pa_' . $target_taxonomy;
        }

        $label          = sanitize_text_field( $label );

        $attribute_slug = substr( $target_taxonomy, 3 );
    $attribute_id   = wc_attribute_taxonomy_id_by_name( $attribute_slug ); // função WooCommerce global

        if ( ! $attribute_id ) {
            if ( ! $create_if_missing ) {
                throw new RuntimeException( sprintf( 'Atributo global %s não existe.', $target_taxonomy ) );
            }

            $result = wc_create_attribute(
                [
                    'name'         => $label,
                    'slug'         => $attribute_slug,
                    'type'         => 'select',
                    'order_by'     => $args['order_by'] ?? 'name',
                    'has_archives' => ! empty( $args['enable_archive'] ),
                ]
            );

            if ( is_wp_error( $result ) ) {
                throw new RuntimeException( $result->get_error_message() );
            }

            $attribute_id = (int) $result;
            $this->logger->info( 'Atributo global criado.', [ 'taxonomy' => $target_taxonomy ] );

            // Após criar um novo atributo é necessário recarregar as taxonomias.
            wc_register_attribute_taxonomies();
        } else {
            $this->logger->info( 'Atributo global existente.', [ 'taxonomy' => $target_taxonomy, 'attribute_id' => $attribute_id ] );
        }

        if ( ! \taxonomy_exists( $target_taxonomy ) ) {
            \register_taxonomy( $target_taxonomy, \apply_filters( "woocommerce_taxonomy_objects_{$target_taxonomy}", [ 'product' ] ), \apply_filters( "woocommerce_taxonomy_args_{$target_taxonomy}", [
                'labels'       => [ 'name' => $label ],
                'hierarchical' => false,
                'show_ui'      => false,
                'query_var'    => true,
                'rewrite'      => false,
            ] ) );
        }

        return [
            'attribute_id' => (int) $attribute_id,
            'taxonomy'     => $target_taxonomy,
        ];
    }

    /**
     * @param array<int, array{local_value:string, term_id?:int, term_slug?:string, term_name?:string, create?:bool}> $terms
     * @return array<int, array{local_value:string, term_id:int, slug:string, created:bool}>
     */
    public function ensure_terms( string $taxonomy, array $terms, bool $allow_creation ): array {
        $taxonomy = sanitize_key( $taxonomy );
        if ( ! \taxonomy_exists( $taxonomy ) ) {
            throw new RuntimeException( sprintf( 'Taxonomia %s não registrada.', $taxonomy ) );
        }

        $results = [];

        foreach ( $terms as $term_config ) {
            $local_value = $term_config['local_value'];
            $slug        = sanitize_title( $term_config['term_slug'] ?? $term_config['term_name'] ?? $local_value );
            $existing    = $this->get_cached_term( $taxonomy, $slug );

            $created = false;

            if ( ! $existing && ! empty( $term_config['term_id'] ) ) {
                $term = \get_term( (int) $term_config['term_id'], $taxonomy );
                if ( $term && ! is_wp_error( $term ) ) {
                    $existing = [
                        'term_id' => (int) $term->term_id,
                        'slug'    => (string) $term->slug,
                    ];
                }
            }

            if ( ! $existing ) {
                $term = \get_term_by( 'slug', $slug, $taxonomy );
                if ( $term ) {
                    $existing = [
                        'term_id' => (int) $term->term_id,
                        'slug'    => (string) $term->slug,
                    ];
                    $this->logger->info( 'term.reuse', [ 'taxonomy' => $taxonomy, 'slug' => $slug, 'term_id' => (int) $term->term_id, 'source' => 'lookup' ] );
                }
            }

            if ( ! $existing ) {
                $should_create = ! empty( $term_config['create'] ) || $allow_creation;
                if ( ! $should_create ) {
                    throw new RuntimeException( sprintf( 'Termo "%s" não existe na taxonomia %s.', $local_value, $taxonomy ) );
                }

                $term_name = $term_config['term_name'] ?? $local_value;
                $result    = \wp_insert_term( $term_name, $taxonomy, [ 'slug' => $slug ] );

                if ( is_wp_error( $result ) ) {
                    throw new RuntimeException( $result->get_error_message() );
                }

                $existing = [
                    'term_id' => (int) $result['term_id'],
                    'slug'    => (string) $slug,
                ];
                $created  = true;
                $this->logger->info( 'term.created', [ 'taxonomy' => $taxonomy, 'slug' => $slug, 'term_id' => (int) $result['term_id'] ] );
            }

            $this->set_cached_term( $taxonomy, $existing['slug'], $existing );

            $results[] = [
                'local_value' => $local_value,
                'term_id'     => $existing['term_id'],
                'slug'        => $existing['slug'],
                'created'     => $created,
            ];
            if ( ! $created ) {
                $this->logger->info( 'term.reuse', [ 'taxonomy' => $taxonomy, 'slug' => $existing['slug'], 'term_id' => $existing['term_id'], 'source' => 'cache_or_lookup' ] );
            }
        }

        return $results;
    }

    private function get_cached_term( string $taxonomy, string $slug ): ?array {
        if ( empty( $this->term_cache[ $taxonomy ] ) ) {
            return null;
        }
        return $this->term_cache[ $taxonomy ][ $slug ] ?? null;
    }

    private function set_cached_term( string $taxonomy, string $slug, array $data ): void {
        $this->term_cache[ $taxonomy ][ $slug ] = $data;
    }
}
