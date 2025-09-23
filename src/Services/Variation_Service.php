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
        $reasons     = [ 'missing_source_meta' => 0, 'no_slug_match' => 0, 'already_ok' => 0, 'fallback_updated' => 0 ];
        $local_key   = 'attribute_' . sanitize_title( $local_name );
        $target_key  = 'attribute_' . $taxonomy;
        $variation_ids  = $product->get_children();
        $total       = count( $variation_ids );
        $variation_objects = []; // Para armazenar objetos para validação final

        foreach ( $variation_ids as $variation_id ) {
            $variation = wc_get_product( $variation_id );
            if ( ! $variation instanceof WC_Product_Variation ) {
                continue;
            }
            
            // Armazena o objeto para validação final
            $variation_objects[] = $variation;

            $this->logger->info( 'variation.process.start', [ 
                'variation_id' => $variation_id, 
                'local_key' => $local_key, 
                'target_key' => $target_key 
            ] );

            // Estratégia 1: Metadados diretos (método atual)
            $result = $this->try_direct_meta_update( $variation, $local_key, $target_key, $slug_map );
            
            if ( $result['status'] === 'updated' ) {
                $updated++;
                $this->logger->info( 'variation.update.direct_meta', [ 
                    'variation_id' => $variation_id, 
                    'value' => $result['value'] 
                ] );
                continue;
            }
            
            if ( $result['status'] === 'already_ok' ) {
                $skipped++;
                $reasons['already_ok']++;
                $this->logger->info( 'variation.skip.already_ok', [ 'variation_id' => $variation_id ] );
                continue;
            }

            // Estratégia 2: Atributos da variação (fallback)
            $fallback_result = $this->try_variation_attributes_update( $variation, $taxonomy, $local_name, $slug_map );
            
            if ( $fallback_result['status'] === 'updated' ) {
                $updated++;
                $reasons['fallback_updated']++;
                $this->logger->info( 'variation.update.fallback', [ 
                    'variation_id' => $variation_id, 
                    'method' => 'variation_attributes',
                    'value' => $fallback_result['value'] 
                ] );
                continue;
            }

            // Estratégia 3: Inferência por título/SKU (estratégia avançada)
            $inference_result = $this->try_inference_update( $variation, $taxonomy, $local_name, $slug_map, $product );
            
            if ( $inference_result['status'] === 'updated' ) {
                $updated++;
                $reasons['fallback_updated']++;
                $this->logger->info( 'variation.update.inference', [ 
                    'variation_id' => $variation_id, 
                    'method' => 'inference',
                    'value' => $inference_result['value'] 
                ] );
                continue;
            }

            // Estratégia 4: Função nativa do WooCommerce (último recurso)
            $wc_result = $this->try_wc_native_update( $variation, $taxonomy, $local_name, $slug_map );
            
            if ( $wc_result['status'] === 'updated' ) {
                $updated++;
                $reasons['fallback_updated']++;
                $this->logger->info( 'variation.update.wc_native', [ 
                    'variation_id' => $variation_id, 
                    'method' => 'wc_get_product_variation_attributes',
                    'value' => $wc_result['value'] 
                ] );
                continue;
            }

            // Nenhuma estratégia funcionou
            $skipped++;
            if ( $result['status'] === 'missing_source_meta' ) {
                $reasons['missing_source_meta']++;
            } else {
                $reasons['no_slug_match']++;
            }
            
            $this->logger->warning( 'variation.skip.no_strategy', [ 
                'variation_id' => $variation_id, 
                'reason' => $result['status'],
                'tried_strategies' => ['direct_meta', 'variation_attributes', 'inference', 'wc_native'],
                'debug_info' => [
                    'has_local_meta' => '' !== (string) $variation->get_meta( $local_key ),
                    'has_target_meta' => '' !== (string) $variation->get_meta( $target_key ),
                    'variation_attributes' => array_keys( $variation->get_variation_attributes( false ) ),
                    'title' => get_the_title( $variation_id ),
                    'sku' => $variation->get_meta( '_sku' ),
                    'wc_native_attributes' => function_exists( 'wc_get_product_variation_attributes' ) ? 
                        wc_get_product_variation_attributes( $variation_id ) : 'function_not_available'
                ]
            ] );
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

    /**
     * Estratégia 1: Tenta atualizar usando metadados diretos (método original)
     * 
     * @param WC_Product_Variation $variation
     * @param string $local_key
     * @param string $target_key
     * @param array<string, string> $slug_map
     * @return array{status:string, value?:string}
     */
    private function try_direct_meta_update( WC_Product_Variation $variation, string $local_key, string $target_key, array $slug_map ): array {
        $variation_id = $variation->get_id();
        
        $this->logger->info( 'variation.strategy1_start', [
            'variation_id' => $variation_id,
            'local_key' => $local_key,
            'target_key' => $target_key,
            'slug_map_count' => count( $slug_map )
        ] );
        $current_target = (string) $variation->get_meta( $target_key );
        $current_value  = (string) $variation->get_meta( $local_key );

        // Meta_values log removido para reduzir ruído - apenas log se necessário para debug

        // Se já existe meta target e não há mais local, consideramos already_ok.
        if ( '' === $current_value && '' !== $current_target ) {
            return [ 'status' => 'already_ok' ];
        }

        if ( '' === $current_value ) {
            return [ 'status' => 'missing_source_meta' ];
        }

        $normalized = Value_Normalizer::normalize( $current_value );
        if ( ! isset( $slug_map[ $normalized ] ) ) {
            $this->logger->warning( 'variation.slug_map_missing', [ 
                'variation_id' => $variation->get_id(), 
                'raw_value' => $current_value, 
                'normalized' => $normalized,
                'available_slugs' => array_keys( $slug_map )
            ] );
            return [ 'status' => 'no_slug_match' ];
        }

        // Se já possui meta target igual ao slug esperado
        if ( '' !== $current_target && $slug_map[ $normalized ] === $current_target ) {
            return [ 'status' => 'already_ok' ];
        }

        // Atualiza os metadados
        $variation->update_meta_data( $target_key, $slug_map[ $normalized ] );
        
        $this->logger->info( 'variation.meta_updated', [
            'variation_id' => $variation_id,
            'target_key' => $target_key,
            'new_value' => $slug_map[ $normalized ]
        ] );
        
        $variation->delete_meta_data( $local_key );
        
        $this->logger->info( 'variation.old_meta_removed', [
            'variation_id' => $variation_id,
            'removed_key' => $local_key
        ] );
        
        // CRÍTICO: Salvamento individual para evitar sobrescrita
        $this->force_individual_variation_save( $variation, $target_key, $slug_map[ $normalized ] );
        
        $this->clear_variation_cache( $variation->get_id() );
        
        return [ 'status' => 'updated', 'value' => $slug_map[ $normalized ] ];
    }

    /**
     * Estratégia 2: Tenta atualizar usando atributos da variação (fallback para WooCommerce)
     * 
     * @param WC_Product_Variation $variation
     * @param string $taxonomy
     * @param string $local_name
     * @param array<string, string> $slug_map
     * @return array{status:string, value?:string}
     */
    private function try_variation_attributes_update( WC_Product_Variation $variation, string $taxonomy, string $local_name, array $slug_map ): array {
        // Obtém os atributos da variação sem prefixo
        $variation_attributes = $variation->get_variation_attributes( false );
        $local_normalized = sanitize_title( $local_name );
        
        // Procura o valor do atributo local nos atributos da variação
        $current_value = '';
        foreach ( $variation_attributes as $attr_key => $attr_value ) {
            if ( sanitize_title( $attr_key ) === $local_normalized && '' !== $attr_value ) {
                $current_value = $attr_value;
                break;
            }
        }

        if ( '' === $current_value ) {
            $this->logger->info( 'variation.fallback.no_attribute', [
                'variation_id' => $variation->get_id(),
                'looking_for' => $local_normalized,
                'available_attributes' => array_keys( $variation_attributes )
            ] );
            return [ 'status' => 'missing_source_meta' ];
        }

        // Tenta encontrar o slug no mapa
        $normalized = Value_Normalizer::normalize( $current_value );
        if ( ! isset( $slug_map[ $normalized ] ) ) {
            $this->logger->warning( 'variation.fallback.slug_map_missing', [ 
                'variation_id' => $variation->get_id(), 
                'raw_value' => $current_value, 
                'normalized' => $normalized,
                'available_slugs' => array_keys( $slug_map )
            ] );
            return [ 'status' => 'no_slug_match' ];
        }

        // Atualiza os atributos da variação
        $target_key = 'attribute_' . $taxonomy;
        $new_slug = $slug_map[ $normalized ];
        
        // Remove o atributo local e adiciona o global
        $updated_attributes = $variation_attributes;
        unset( $updated_attributes[ $local_normalized ] );
        $updated_attributes[ $taxonomy ] = $new_slug;
        
        // Atualiza através dos metadados também para compatibilidade
        $variation->update_meta_data( $target_key, $new_slug );
        
        // Remove metadados antigos se existirem
        $old_local_key = 'attribute_' . $local_normalized;
        $variation->delete_meta_data( $old_local_key );
        
        // Salva através do método do WooCommerce
        $variation->set_attributes( $updated_attributes );
        
        // CRÍTICO: Garante que cada variação seja salva independentemente
        // Para evitar sobrescrita quando múltiplas variações têm o mesmo valor
        $this->force_individual_variation_save( $variation, $target_key, $new_slug );
        
        $this->clear_variation_cache( $variation->get_id() );
        
        $this->logger->info( 'variation.fallback.success', [
            'variation_id' => $variation->get_id(),
            'old_value' => $current_value,
            'new_slug' => $new_slug,
            'method' => 'variation_attributes'
        ] );
        
        return [ 'status' => 'updated', 'value' => $new_slug ];
    }

    /**
     * Estratégia 3: Tenta inferir valores analisando título, SKU e padrões da variação
     * 
     * @param WC_Product_Variation $variation
     * @param string $taxonomy
     * @param string $local_name
     * @param array<string, string> $slug_map
     * @param WC_Product $parent_product
     * @return array{status:string, value?:string}
     */
    private function try_inference_update( WC_Product_Variation $variation, string $taxonomy, string $local_name, array $slug_map, WC_Product $parent_product ): array {
        $variation_id = $variation->get_id();
        $post = get_post( $variation_id );
        $title = $post ? $post->post_title : '';
        $sku = (string) $variation->get_meta( '_sku' );
        
        $this->logger->info( 'variation.inference.start', [
            'variation_id' => $variation_id,
            'title' => $title,
            'sku' => $sku,
            'searching_for' => $local_name
        ] );

        // Analisa o atributo pai para entender os padrões
        $parent_attributes = $parent_product->get_attributes();
        $target_attribute = null;
        
        foreach ( $parent_attributes as $attr ) {
            if ( $attr->get_name() === $local_name && $attr->get_variation() ) {
                $target_attribute = $attr;
                break;
            }
        }

        if ( ! $target_attribute ) {
            return [ 'status' => 'missing_source_meta' ];
        }

        // Obtém as opções do atributo pai
        $parent_options = $target_attribute->get_options();
        if ( empty( $parent_options ) ) {
            return [ 'status' => 'missing_source_meta' ];
        }

        // Tenta encontrar correspondência no título ou SKU da variação
        $candidates = $this->find_attribute_candidates( $title, $sku, $parent_options, $slug_map );
        
        if ( empty( $candidates ) ) {
            $this->logger->info( 'variation.inference.no_candidates', [
                'variation_id' => $variation_id,
                'parent_options' => $parent_options,
                'title' => $title,
                'sku' => $sku
            ] );
            return [ 'status' => 'no_slug_match' ];
        }

        // Usa o primeiro candidato mais provável
        $best_candidate = $candidates[0];
        $target_key = 'attribute_' . $taxonomy;
        
        // Atualiza os metadados
        $variation->update_meta_data( $target_key, $best_candidate );
        
        // CRÍTICO: Salvamento individual para evitar sobrescrita
        $this->force_individual_variation_save( $variation, $target_key, $best_candidate );
        
        // Força atualização imediata no banco
        if ( function_exists( 'clean_post_cache' ) ) {
            \clean_post_cache( $variation_id );
        }
        
        $this->clear_variation_cache( $variation_id );
        
        $this->logger->info( 'variation.inference.success', [
            'variation_id' => $variation_id,
            'inferred_value' => $best_candidate,
            'candidates' => $candidates,
            'method' => 'title_sku_analysis'
        ] );
        
        return [ 'status' => 'updated', 'value' => $best_candidate ];
    }

    /**
     * Encontra candidatos para atributos analisando título e SKU
     * 
     * @param string $title
     * @param string $sku
     * @param array $parent_options
     * @param array<string, string> $slug_map
     * @return array
     */
    private function find_attribute_candidates( string $title, string $sku, array $parent_options, array $slug_map ): array {
        $candidates = [];
        $search_text = strtolower( $title . ' ' . $sku );
        
        foreach ( $parent_options as $option ) {
            $option_normalized = Value_Normalizer::normalize( $option );
            
            // Verifica se existe no slug_map
            if ( ! isset( $slug_map[ $option_normalized ] ) ) {
                continue;
            }
            
            $target_slug = $slug_map[ $option_normalized ];
            
            // Estratégias de busca (por ordem de prioridade)
            $search_patterns = [
                strtolower( $option ),           // Valor original
                $option_normalized,              // Valor normalizado
                $target_slug,                    // Slug alvo
                preg_quote( strtolower( $option ), '/' ), // Valor escapado para regex
            ];
            
            foreach ( $search_patterns as $pattern ) {
                if ( empty( $pattern ) ) continue;
                
                // Busca exata
                if ( strpos( $search_text, $pattern ) !== false ) {
                    $candidates[] = $target_slug;
                    break;
                }
                
                // Busca por palavra completa
                if ( preg_match( '/\b' . preg_quote( $pattern, '/' ) . '\b/u', $search_text ) ) {
                    $candidates[] = $target_slug;
                    break;
                }
            }
        }
        
        // Remove duplicatas mantendo ordem
        return array_unique( $candidates );
    }

    /**
     * Estratégia 4: Usa função nativa do WooCommerce para obter atributos da variação
     * 
     * @param WC_Product_Variation $variation
     * @param string $taxonomy
     * @param string $local_name
     * @param array<string, string> $slug_map
     * @return array{status:string, value?:string}
     */
    private function try_wc_native_update( WC_Product_Variation $variation, string $taxonomy, string $local_name, array $slug_map ): array {
        $variation_id = $variation->get_id();
        
        // Usa a função nativa do WooCommerce para obter atributos
        if ( ! function_exists( 'wc_get_product_variation_attributes' ) ) {
            return [ 'status' => 'missing_source_meta' ];
        }
        
        $wc_attributes = wc_get_product_variation_attributes( $variation_id );
        $local_key = 'attribute_' . sanitize_title( $local_name );
        
        $this->logger->info( 'variation.wc_native.attributes', [
            'variation_id' => $variation_id,
            'wc_attributes' => $wc_attributes,
            'looking_for' => $local_key
        ] );
        
        if ( ! isset( $wc_attributes[ $local_key ] ) ) {
            return [ 'status' => 'missing_source_meta' ];
        }
        
        $current_value = (string) $wc_attributes[ $local_key ];
        if ( '' === $current_value ) {
            return [ 'status' => 'missing_source_meta' ];
        }
        
        // Tenta encontrar o slug no mapa
        $normalized = Value_Normalizer::normalize( $current_value );
        if ( ! isset( $slug_map[ $normalized ] ) ) {
            $this->logger->warning( 'variation.wc_native.slug_map_missing', [ 
                'variation_id' => $variation_id, 
                'raw_value' => $current_value, 
                'normalized' => $normalized,
                'available_slugs' => array_keys( $slug_map )
            ] );
            return [ 'status' => 'no_slug_match' ];
        }
        
        $target_key = 'attribute_' . $taxonomy;
        $new_slug = $slug_map[ $normalized ];
        
        // Atualiza os metadados
        $variation->update_meta_data( $target_key, $new_slug );
        $variation->delete_meta_data( $local_key );
        
        // CRÍTICO: Salvamento individual para evitar sobrescrita
        $this->force_individual_variation_save( $variation, $target_key, $new_slug );
        
        // Força atualização imediata no banco
        if ( function_exists( 'clean_post_cache' ) ) {
            \clean_post_cache( $variation_id );
        }
        
        $this->clear_variation_cache( $variation_id );
        
        $this->logger->info( 'variation.wc_native.success', [
            'variation_id' => $variation_id,
            'old_value' => $current_value,
            'new_slug' => $new_slug,
            'method' => 'wc_get_product_variation_attributes'
        ] );
        
        return [ 'status' => 'updated', 'value' => $new_slug ];
    }

    /**
     * Limpa caches da variação para garantir atualização imediata
     * 
     * @param int $variation_id
     */
    private function clear_variation_cache( int $variation_id ): void {
        if ( function_exists( 'wp_cache_delete' ) ) {
            \wp_cache_delete( $variation_id, 'posts' );
            \wp_cache_delete( $variation_id, 'post_meta' );
        }
        if ( function_exists( 'clean_post_cache' ) ) {
            \clean_post_cache( $variation_id );
        }
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

    /**
     * CORREÇÃO CRÍTICA: Persistência atômica baseada em boas práticas WooCommerce
     * 
     * Problema identificado: $variation->save() está limpando metadados devido a hooks
     * Solução: Persistência direta no banco + gerenciamento de hooks + cache + validação atômica
     * 
     * @param WC_Product_Variation $variation
     * @param string $target_key
     * @param string $new_slug
     */
    private function force_individual_variation_save( WC_Product_Variation $variation, string $target_key, string $new_slug ): void {
        $variation_id = $variation->get_id();
        
        // Remove hooks interferentes temporariamente
        $this->disable_interfering_hooks();
        
        try {
            // Persistência direta no banco de dados
            global $wpdb;
            
            if ( $wpdb && method_exists( $wpdb, 'replace' ) ) {
                $wpdb->replace(
                    $wpdb->postmeta,
                    [
                        'post_id' => $variation_id,
                        'meta_key' => $target_key,
                        'meta_value' => $new_slug
                    ]
                );
            }
            
            // Limpeza de cache
            $this->clear_comprehensive_cache( $variation_id );
            if ( method_exists( $variation, 'read_meta_data' ) ) {
                $variation->read_meta_data( true );
            }
            
            // Verificação e fallback se necessário
            if ( $wpdb && method_exists( $wpdb, 'get_var' ) ) {
                $verified_value = $wpdb->get_var( $wpdb->prepare(
                    "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
                    $variation_id,
                    $target_key
                ) );
                
                if ( $verified_value !== $new_slug ) {
                    $wpdb->query( $wpdb->prepare(
                        "REPLACE INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES (%d, %s, %s)",
                        $variation_id,
                        $target_key,
                        $new_slug
                    ) );
                }
            }
            
        } finally {
            $this->restore_interfering_hooks();
        }
        
        // Flush cache e verificação final
        if ( function_exists( 'wp_cache_flush' ) ) {
            \wp_cache_flush();
        }
        
        $this->verify_immediate_persistence( $variation_id, $target_key, $new_slug );
    }
    
    /**
     * Remove hooks interferentes temporariamente (boas práticas WooCommerce)
     */
    private function disable_interfering_hooks(): void {
        if ( function_exists( 'remove_action' ) ) {
            \remove_action( 'woocommerce_save_product_variation', 'wc_delete_product_transients' );
            \remove_action( 'save_post', 'wc_delete_product_transients' );
            
            $this->logger->info( 'variation.hooks.disabled', [
                'hooks' => [ 'wc_delete_product_transients' ]
            ] );
        }
    }
    
    /**
     * Restaura hooks removidos
     */
    private function restore_interfering_hooks(): void {
        if ( function_exists( 'add_action' ) ) {
            \add_action( 'woocommerce_save_product_variation', 'wc_delete_product_transients' );
            \add_action( 'save_post', 'wc_delete_product_transients' );
            
            $this->logger->info( 'variation.hooks.restored', [
                'hooks' => [ 'wc_delete_product_transients' ]
            ] );
        }
    }
    
    /**
     * Limpeza abrangente de cache (seguindo padrão WooCommerce)
     */
    private function clear_comprehensive_cache( int $variation_id ): void {
        // WordPress core caches
        if ( function_exists( 'wp_cache_delete' ) ) {
            \wp_cache_delete( $variation_id, 'posts' );
            \wp_cache_delete( $variation_id, 'post_meta' );
        }
        if ( function_exists( 'clean_post_cache' ) ) {
            \clean_post_cache( $variation_id );
        }
        
        // WooCommerce specific caches
        if ( function_exists( 'wp_cache_delete' ) ) {
            \wp_cache_delete( 'wc_product_' . $variation_id, 'posts' );
            \wp_cache_delete( $variation_id, 'products' );
            
            // Variation specific caches
            $cache_key = 'wc_product_variation_attributes_' . $variation_id;
            \wp_cache_delete( $cache_key, 'products' );
        }
        
        if ( function_exists( 'wc_delete_product_transients' ) ) {
            wc_delete_product_transients( $variation_id );
        }
    }
    
    /**
     * Verifica se os dados foram realmente persistidos corretamente
     */
    private function verify_immediate_persistence( int $variation_id, string $target_key, string $expected_value ): void {
        global $wpdb;
        
        if ( !$wpdb || !method_exists( $wpdb, 'get_var' ) ) {
            // Em ambiente de teste sem WordPress
            return;
        }
        
        // Verificação direta no banco de dados (mais confiável)
        $db_value = $wpdb->get_var( $wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s",
            $variation_id,
            $target_key
        ) );
        
        if ( $db_value === $expected_value ) {
            $this->logger->info( 'variation.persistence_verified', [
                'variation_id' => $variation_id,
                'target_key' => $target_key,
                'value' => $expected_value
            ] );
        } else {
            $this->logger->error( 'variation.persistence_failure', [
                'variation_id' => $variation_id,
                'target_key' => $target_key,
                'expected' => $expected_value,
                'actual' => $db_value
            ] );
        }
    }
}
