<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Services;

use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variation;

class Discovery_Service {
    public function __construct() {}

    /**
     * @return array{
     *   attributes: array<int, array{
     *     name: string,
     *     label: string,
     *     values: array<int, string>,
     *     in_variations: bool,
     *     suggestion: ?array
     *   }>
     * }
     */
    public function discover( int $product_id ): array {
        $product = wc_get_product( $product_id );

        if ( ! $product instanceof WC_Product ) {
            return [ 'attributes' => [] ];
        }

        $attributes = [];
        foreach ( $product->get_attributes() as $attribute ) {
            if ( ! $attribute instanceof WC_Product_Attribute ) {
                continue;
            }

            if ( $attribute->is_taxonomy() ) {
                continue;
            }

            $values = array_map( 'wc_clean', (array) $attribute->get_options() );
            $name   = (string) $attribute->get_name();
            $label  = (string) ( $attribute->get_data()['name'] ?? $name );

            $attributes[] = [
                'name'          => $name,
                'label'         => $label,
                'values'        => $values,
                'in_variations' => $this->attribute_used_in_variations( $product, $name, $values ),
                'suggestion'    => null, // templates removidos na 0.3.0
            ];
        }

        return [ 'attributes' => $attributes ];
    }

    private function attribute_used_in_variations( WC_Product $product, string $attr_name, array $values ): bool {
        if ( ! $product->is_type( 'variable' ) ) {
            return false;
        }

        $children = $product->get_children();
        if ( empty( $children ) ) {
            return false;
        }

        $meta_key = 'attribute_' . sanitize_title( $attr_name );

        foreach ( $children as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( ! $variation instanceof WC_Product_Variation ) {
                continue;
            }

            $value = (string) $variation->get_meta( $meta_key );
            if ( '' !== $value ) {
                return true;
            }
        }

        return false;
    }
}
