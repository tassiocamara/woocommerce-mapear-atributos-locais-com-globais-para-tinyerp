<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Services;

use Evolury\Local2Global\Utils\Logger;
use RuntimeException;
use WC_Product;
use WC_Product_Attribute;

class Mapping_Service {
    public function __construct(
        private Term_Service $terms,
        private Variation_Service $variations,
        private Templates_Service $templates,
        private Rollback_Service $rollback,
        private Logger $logger
    ) {}

    public function dry_run( int $product_id, array $mapping, array $options = [] ): array {
        $product = wc_get_product( $product_id );
        if ( ! $product instanceof WC_Product ) {
            throw new RuntimeException( 'Produto inválido.' );
        }

        $report = [
            'product_id' => $product_id,
            'attributes' => [],
            'errors'     => [],
        ];

        foreach ( $mapping as $attribute_mapping ) {
            $target_tax    = $this->sanitize_taxonomy( $attribute_mapping['target_tax'] ?? '' );
            $local_label   = (string) ( $attribute_mapping['local_label'] ?? $attribute_mapping['local_attr'] ?? '' );
            $create_attr   = ! empty( $attribute_mapping['create_attribute'] );
            $term_actions  = [ 'create' => [], 'existing' => [] ];
            $term_errors   = [];

            if ( empty( $target_tax ) ) {
                $term_errors[] = __( 'Taxonomia alvo não informada.', 'local2global' );
            }

            if ( taxonomy_exists( $target_tax ) ) {
                $attribute_exists = true;
            } else {
                $attribute_exists = false;
                if ( ! $create_attr ) {
                    $term_errors[] = sprintf( __( 'Atributo global %s não existe e criação automática não foi selecionada.', 'local2global' ), $target_tax );
                }
            }

            foreach ( $attribute_mapping['terms'] ?? [] as $term_map ) {
                $local_value = (string) ( $term_map['local_value'] ?? '' );
                $slug        = sanitize_title( $term_map['term_slug'] ?? $term_map['term_name'] ?? $local_value );
                $term        = $attribute_exists ? term_exists( $slug, $target_tax ) : null;

                if ( $term ) {
                    $term_actions['existing'][] = $local_value;
                } elseif ( ! empty( $term_map['create'] ) || ! empty( $options['auto_create_terms'] ) || ! $attribute_exists ) {
                    $term_actions['create'][] = [ 'value' => $local_value, 'slug' => $slug ];
                } else {
                    $term_errors[] = sprintf( __( 'Termo %1$s não encontrado na taxonomia %2$s.', 'local2global' ), $local_value, $target_tax );
                }
            }

            $report['attributes'][] = [
                'local_label'      => $local_label,
                'target_tax'       => $target_tax,
                'attribute_exists' => $attribute_exists,
                'create_attribute' => $create_attr && ! $attribute_exists,
                'terms'            => $term_actions,
                'errors'           => $term_errors,
            ];

            $report['errors'] = array_merge( $report['errors'], $term_errors );
        }

        return $report;
    }

    public function apply( int $product_id, array $mapping, array $options = [] ): array {
        $product = wc_get_product( $product_id );
        if ( ! $product instanceof WC_Product ) {
            throw new RuntimeException( 'Produto inválido.' );
        }

        $attributes_before = $product->get_attributes();

        if ( ! empty( $options['create_backup'] ) ) {
            $this->rollback->create_backup( $product, $attributes_before );
        }

        $results = [
            'created_terms' => [],
            'existing_terms'=> [],
            'updated_attrs' => [],
            'variations'    => [],
        ];

        foreach ( $mapping as $attribute_mapping ) {
            $target_tax    = $this->sanitize_taxonomy( $attribute_mapping['target_tax'] ?? '' );
            $local_name    = (string) ( $attribute_mapping['local_attr'] ?? '' );
            $local_label   = (string) ( $attribute_mapping['local_label'] ?? $local_name );
            $create_attr   = ! empty( $attribute_mapping['create_attribute'] );
            $terms_config  = $attribute_mapping['terms'] ?? [];

            if ( empty( $target_tax ) || empty( $local_name ) ) {
                throw new RuntimeException( 'Mapeamento incompleto.' );
            }

            $attribute_info = $this->terms->ensure_global_attribute( $target_tax, $attribute_mapping['target_label'] ?? $local_label, $create_attr, $attribute_mapping['attribute_args'] ?? [] );
            $term_results   = $this->terms->ensure_terms( $attribute_info['taxonomy'], $terms_config, ! empty( $options['auto_create_terms'] ) );

            $term_ids   = array_values( array_unique( array_map( static fn( array $item ) => (int) $item['term_id'], $term_results ) ) );
            $slug_map   = [];
            $created    = [];
            $existing   = [];

            foreach ( $term_results as $term_result ) {
                $normalized            = $this->normalize_local_value( $term_result['local_value'] );
                $slug_map[ $normalized ] = $term_result['slug'];
                if ( $term_result['created'] ) {
                    $created[] = $term_result['slug'];
                } else {
                    $existing[] = $term_result['slug'];
                }
            }

            $updated = $this->replace_attribute( $product, $local_name, $attribute_info['taxonomy'], $attribute_info['attribute_id'], $term_ids );
            if ( ! $updated ) {
                throw new RuntimeException( sprintf( 'Não foi possível localizar o atributo local %s no produto.', $local_name ) );
            }

            wp_set_object_terms( $product_id, array_map( 'intval', $term_ids ), $attribute_info['taxonomy'], false );

            if ( ! empty( $options['update_variations'] ) ) {
                $variation_stats = $this->variations->update_variations( $product, $attribute_info['taxonomy'], $local_name, $slug_map );
                $results['variations'][ $attribute_info['taxonomy'] ] = $variation_stats;
            }

            if ( ! empty( $attribute_mapping['save_template'] ) ) {
                $this->templates->save_template( $local_label, [
                    'target_tax' => $attribute_info['taxonomy'],
                    'terms'      => array_combine(
                        array_map( static fn( array $item ) => $item['local_value'], $term_results ),
                        array_map( static fn( array $item ) => $item['slug'], $term_results )
                    ),
                ] );
            }

            $results['created_terms'][ $attribute_info['taxonomy'] ]  = $created;
            $results['existing_terms'][ $attribute_info['taxonomy'] ] = $existing;
            $results['updated_attrs'][]                               = $attribute_info['taxonomy'];
        }

        $product->save();

        wc_delete_product_transients( $product_id );

        $this->logger->info( 'Mapeamento aplicado.', [ 'product_id' => $product_id, 'result' => $results ] );

        return $results;
    }

    private function replace_attribute( WC_Product $product, string $local_name, string $taxonomy, int $attribute_id, array $term_ids ): bool {
        if ( ! taxonomy_exists( $taxonomy ) ) {
            throw new RuntimeException( sprintf( 'Taxonomia %s não registrada.', $taxonomy ) );
        }

        $attributes = $this->normalize_product_attributes( $product->get_attributes() );
        $replaced   = false;
        $local_key  = $this->normalize_attribute_name( $local_name );

        foreach ( $attributes as $index => $attribute ) {
            if ( ! $attribute instanceof WC_Product_Attribute ) {
                continue;
            }

            $attr_name = $this->normalize_attribute_name( $attribute->get_name() );
            if ( $attr_name !== $local_key ) {
                continue;
            }

            $new_attribute = new WC_Product_Attribute();
            $new_attribute->set_id( $attribute_id );
            $new_attribute->set_name( $taxonomy );
            $new_attribute->set_options( array_map( 'intval', $term_ids ) );
            $new_attribute->set_visible( $attribute->get_visible() );
            $new_attribute->set_variation( $attribute->get_variation() );
            $new_attribute->set_position( $attribute->get_position() );
            $new_attribute->set_taxonomy( true );

            $attributes[ $taxonomy ] = $new_attribute;

            if ( $index !== $taxonomy ) {
                unset( $attributes[ $index ] );
            }

            $replaced = true;
            break;
        }

        if ( $replaced ) {
            $product->set_attributes( $attributes );
        }

        return $replaced;
    }

    private function normalize_local_value( string $value ): string {
        return strtolower( trim( wc_clean( $value ) ) );
    }

    /**
     * @param array<int|string, mixed> $attributes
     * @return array<int|string, WC_Product_Attribute>
     */
    private function normalize_product_attributes( array $attributes ): array {
        $normalized = [];

        foreach ( $attributes as $index => $attribute ) {
            if ( $attribute instanceof WC_Product_Attribute ) {
                $normalized[ $index ] = $attribute;
                continue;
            }

            if ( is_array( $attribute ) ) {
                $normalized[ $index ] = $this->create_attribute_from_legacy( $index, $attribute );
            }
        }

        return $normalized;
    }

    /**
     * @param int|string $index
     * @param array<string, mixed> $attribute
     */
    private function create_attribute_from_legacy( $index, array $attribute ): WC_Product_Attribute {
        $legacy     = new WC_Product_Attribute();
        $name       = (string) ( $attribute['name'] ?? ( is_string( $index ) ? $index : '' ) );
        $is_tax     = ! empty( $attribute['is_taxonomy'] );
        $options    = [];

        if ( $is_tax ) {
            $options = array_map( 'intval', (array) ( $attribute['options'] ?? [] ) );
        } else {
            if ( isset( $attribute['options'] ) ) {
                $options = array_map( 'strval', (array) $attribute['options'] );
            } elseif ( isset( $attribute['value'] ) ) {
                $value   = (string) $attribute['value'];
                $options = '' === $value ? [] : ( function_exists( 'wc_get_text_attributes' ) ? wc_get_text_attributes( $value ) : array_map( 'trim', explode( '|', $value ) ) );
            }
        }

        $legacy->set_id( (int) ( $attribute['id'] ?? 0 ) );
        $legacy->set_name( $name );
        $legacy->set_options( $options );
        $legacy->set_position( (int) ( $attribute['position'] ?? 0 ) );
        $legacy->set_visible( ! empty( $attribute['is_visible'] ) );
        $legacy->set_variation( ! empty( $attribute['is_variation'] ) );
        $legacy->set_taxonomy( $is_tax );

        return $legacy;
    }

    private function normalize_attribute_name( string $name ): string {
        $normalized = sanitize_title( $name );

        if ( '' !== $normalized ) {
            return $normalized;
        }

        return mb_strtolower( $name );
    }

    private function sanitize_taxonomy( string $taxonomy ): string {
        $taxonomy = sanitize_key( $taxonomy );
        if ( '' === $taxonomy ) {
            return $taxonomy;
        }

        if ( ! str_starts_with( $taxonomy, 'pa_' ) ) {
            $taxonomy = 'pa_' . $taxonomy;
        }

        return $taxonomy;
    }
}
