<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Services;

use Evolury\Local2Global\Utils\Logger;
use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;

class Variation_Service {
    public function __construct( private Logger $logger ) {}

    /**
     * @param array<string, string> $slug_map
     * @return array{updated:int, skipped:int}
     */
    public function update_variations( WC_Product $product, string $taxonomy, string $local_name, array $slug_map ): array {
        if ( ! $product->is_type( 'variable' ) ) {
            return [ 'updated' => 0, 'skipped' => 0 ];
        }

        $updated     = 0;
        $skipped     = 0;
        $local_key   = 'attribute_' . sanitize_title( $local_name );
        $target_key  = 'attribute_' . $taxonomy;
        $variations  = $product->get_children();

        foreach ( $variations as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( ! $variation instanceof WC_Product_Variation ) {
                continue;
            }

            $current_value = (string) $variation->get_meta( $local_key );
            if ( '' === $current_value ) {
                $skipped++;
                continue;
            }

            $normalized = $this->normalize_local_value( $current_value );
            if ( ! isset( $slug_map[ $normalized ] ) ) {
                $skipped++;
                continue;
            }

            $variation->update_meta_data( $target_key, $slug_map[ $normalized ] );
            $variation->delete_meta_data( $local_key );
            $variation->save();
            $updated++;
        }

        if ( $product instanceof WC_Product_Variable ) {
            WC_Product_Variable::sync( $product, true );
        }

        $this->logger->info( 'Variações atualizadas.', [
            'product_id' => $product->get_id(),
            'taxonomy'   => $taxonomy,
            'updated'    => $updated,
            'skipped'    => $skipped,
        ] );

        return [ 'updated' => $updated, 'skipped' => $skipped ];
    }

    private function normalize_local_value( string $value ): string {
        return strtolower( trim( wc_clean( $value ) ) );
    }
}
