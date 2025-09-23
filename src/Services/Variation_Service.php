<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Services;

use Evolury\Local2Global\Utils\Logger;
use Evolury\Local2Global\Utils\Value_Normalizer;
use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;

// Stub condicional para análise estática fora do ambiente WordPress
if ( ! function_exists( '\\get_post' ) ) {
    function get_post( $post_id ) { return null; }
}

class Variation_Service {
    public function __construct( private Logger $logger ) {}

    /**
     * @param array<string, string> $slug_map slug normalizado => slug final
     * @return array{updated:int, skipped:int, reasons:array<string,int>}
     */
    public function update_variations( WC_Product $product, string $taxonomy, string $local_name, array $slug_map, ?string $corr_id = null ): array {
        if ( ! $product->is_type( 'variable' ) ) {
            return [ 'updated' => 0, 'skipped' => 0, 'reasons' => [] ];
        }

    $updated     = 0;
    $skipped     = 0;
    $reasons     = [ 'missing_source_meta' => 0, 'no_slug_match' => 0, 'already_ok' => 0 ];
        $local_key   = 'attribute_' . sanitize_title( $local_name );
        $target_key  = 'attribute_' . $taxonomy;
    $variations  = $product->get_children();
    $total       = count( $variations );

        foreach ( $variations as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( ! $variation instanceof WC_Product_Variation ) {
                continue;
            }

            $current_target = (string) $variation->get_meta( $target_key );
            $current_value  = (string) $variation->get_meta( $local_key );

            // Se já existe meta target e não há mais local, consideramos already_ok.
            if ( '' === $current_value && '' !== $current_target ) {
                $skipped++;
                $reasons['already_ok']++;
                continue;
            }

            if ( '' === $current_value ) { $skipped++; $reasons['missing_source_meta']++; continue; }

            $normalized = Value_Normalizer::normalize( $current_value );
            if ( ! isset( $slug_map[ $normalized ] ) ) {
                $this->logger->warning( 'variation.slug_map_missing', [ 'variation_id' => $variation_id, 'raw_value' => $current_value, 'normalized' => $normalized ] );
                $skipped++;
                $reasons['no_slug_match']++;
                continue;
            }

            // Se já possui meta target igual ao slug esperado e meta local será removida.
            if ( '' !== $current_target && $slug_map[ $normalized ] === $current_target ) {
                $skipped++;
                $reasons['already_ok']++;
                continue;
            }

            $variation->update_meta_data( $target_key, $slug_map[ $normalized ] );
            $variation->delete_meta_data( $local_key );
            $variation->save();
            $updated++;
        }

        // Nota: WC_Product_Variable::sync() removido para evitar sobrescrever as variações
        // O sync será feito pelo Mapping_Service após todas as operações

        $updated_pct = $total > 0 ? round( ( $updated / $total ) * 100, 2 ) : 0.0;
        $context = [
            'product_id' => $product->get_id(),
            'taxonomy'   => $taxonomy,
            'local_attr' => $local_name,
            'updated'    => $updated,
            'skipped'    => $skipped,
            'total_variations' => $total,
            'updated_pct' => $updated_pct,
            'reasons'    => $reasons,
        ];

        if ( $corr_id ) {
            $context['corr_id'] = $corr_id;
        }

        $this->logger->info( 'variation.update.summary', $context );

        return [ 'updated' => $updated, 'skipped' => $skipped, 'total_variations' => $total, 'updated_pct' => $updated_pct, 'reasons' => $reasons ];
    }

    private function normalize_local_value( string $value ): string { return Value_Normalizer::normalize( $value ); }

    /**
     * Inferir candidatos analisando título, slug target atual e SKU.
     * Retorna lista de slugs possíveis (valores do slug_map).
     * Estratégia:
     *  - Se post_title contém exatamente um termo (nome) normalizado => match único
     *  - Se SKU contém slug
     *  - Se padrões numéricos (ex: "180/90") correspondem a termos numéricos.
     *  - Remove duplicados preservando ordem de descoberta.
     *  - Só considera candidatos cujo slug esteja em slug_map.
     *
     * @param array<string,string> $slug_map
     * @return string[] slugs inferidos
     */
    private function infer_candidates( WC_Product_Variation $variation, array $slug_map, string $local_key, string $target_key ): array {
        $post  = get_post( $variation->get_id() );
        $title = $post ? (string) $post->post_title : '';
        $sku   = (string) $variation->get_meta( '_sku' );
        $candidates = [];

        if ( function_exists( 'apply_filters' ) ) {
            $max_title_length = (int) apply_filters( 'local2global_aggressive_max_title_length', 160 );
            $max_candidates   = (int) apply_filters( 'local2global_aggressive_max_candidates', 3 );
        } else {
            $max_title_length = 160;
            $max_candidates   = 3;
        }

        $title_for_matching = mb_strlen( $title ) > $max_title_length ? '' : $title;

        $normalized_to_slug = $slug_map;

        $lower_title = mb_strtolower( $title_for_matching );
        if ( '' !== $lower_title ) {
            foreach ( $normalized_to_slug as $norm => $slug ) {
                if ( preg_match( '/\b' . preg_quote( $norm, '/' ) . '\b/u', $lower_title ) ) {
                    $candidates[] = $slug;
                    if ( count( $candidates ) >= $max_candidates ) { break; }
                }
            }
        }

        if ( count( $candidates ) < $max_candidates ) {
            $lower_sku = mb_strtolower( $sku );
            if ( '' !== $lower_sku ) {
                foreach ( $normalized_to_slug as $norm => $slug ) {
                    if ( str_contains( $lower_sku, $norm ) ) {
                        $candidates[] = $slug;
                        if ( count( $candidates ) >= $max_candidates ) { break; }
                    }
                }
            }
        }

        if ( count( $candidates ) < $max_candidates ) {
            if ( preg_match_all( '/\b\d{1,4}(?:\/\d{1,4})?\b/u', $title . ' ' . $sku, $matches ) ) {
                foreach ( $matches[0] as $raw ) {
                    $norm = Value_Normalizer::normalize( $raw );
                    if ( isset( $normalized_to_slug[ $norm ] ) ) {
                        $candidates[] = $normalized_to_slug[ $norm ];
                        if ( count( $candidates ) >= $max_candidates ) { break; }
                    }
                }
            }
        }

        $unique = [];
        foreach ( $candidates as $slug ) {
            if ( ! in_array( $slug, $unique, true ) ) { $unique[] = $slug; }
        }
        return $unique;
    }
}
