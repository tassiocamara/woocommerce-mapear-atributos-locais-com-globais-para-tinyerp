<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Services;

use Evolury\Local2Global\Utils\Logger;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variation;

class Rollback_Service {
    private const META_KEY = '_local2global_backups';

    public function __construct( private Logger $logger ) {}

    /**
     * @param array<int, WC_Product_Attribute> $original_attributes
     */
    public function create_backup( WC_Product $product, array $original_attributes ): string {
        $backup_id = wp_generate_uuid4();
        $data      = [
            'created_at' => time(),
            'attributes' => array_map( static fn( WC_Product_Attribute $attribute ) => $attribute->get_data(), $original_attributes ),
            'variations' => $this->export_variations( $product ),
        ];

        $backups             = get_post_meta( $product->get_id(), self::META_KEY, true );
        $backups             = is_array( $backups ) ? $backups : [];
        $backups[ $backup_id ] = $data;

        update_post_meta( $product->get_id(), self::META_KEY, $backups );
        $this->logger->info( 'Backup criado para o produto.', [ 'product_id' => $product->get_id(), 'backup_id' => $backup_id ] );

        return $backup_id;
    }

    public function restore_backup( int $product_id, string $backup_id ): bool {
        $backups = get_post_meta( $product_id, self::META_KEY, true );
        if ( empty( $backups[ $backup_id ] ) ) {
            return false;
        }

        $data    = $backups[ $backup_id ];
        $product = wc_get_product( $product_id );
        if ( ! $product instanceof WC_Product ) {
            return false;
        }

        $restored_attributes = [];
        foreach ( $data['attributes'] as $attribute_data ) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_props( $attribute_data );
            $restored_attributes[] = $attribute;
        }

        $product->set_attributes( $restored_attributes );
        $product->save();

        $this->restore_variations( $product, $data['variations'] );

        unset( $backups[ $backup_id ] );
        update_post_meta( $product_id, self::META_KEY, $backups );

        $this->logger->info( 'Backup restaurado.', [ 'product_id' => $product_id, 'backup_id' => $backup_id ] );

        return true;
    }

    private function export_variations( WC_Product $product ): array {
        if ( ! $product->is_type( 'variable' ) ) {
            return [];
        }

        $variations = [];
        foreach ( $product->get_children() as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( ! $variation instanceof WC_Product_Variation ) {
                continue;
            }

            $variations[ $variation_id ] = [
                'attributes' => $variation->get_attributes(),
            ];
        }

        return $variations;
    }

    private function restore_variations( WC_Product $product, array $variations ): void {
        foreach ( $variations as $variation_id => $data ) {
            $variation = wc_get_product( (int) $variation_id );
            if ( ! $variation instanceof WC_Product_Variation ) {
                continue;
            }

            $variation->set_attributes( $data['attributes'] ?? [] );
            $variation->save();
        }

        if ( $product->is_type( 'variable' ) ) {
            \WC_Product_Variable::sync( $product, true );
        }
    }
}
