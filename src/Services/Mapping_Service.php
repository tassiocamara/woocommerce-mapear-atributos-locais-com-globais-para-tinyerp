<?php
declare(strict_types=1);

namespace Evolury\Local2Global\Services;

use Evolury\Local2Global\Utils\Logger;
use Evolury\Local2Global\Utils\Value_Normalizer;
use RuntimeException;
use Throwable;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variable;
use WP_Error;

// Stub mínimo para análise estática quando WooCommerce/WordPress não carregado.
if ( ! function_exists( '\\term_exists' ) ) {
    function term_exists( $term, $taxonomy = '', $parent = null ) { return 0; }
}

class Mapping_Service {
    public function __construct(
        private Term_Service $terms,
        private Variation_Service $variations,
        private Logger $logger
    ) {}

    public function dry_run( int $product_id, array $mapping, array $options = [], ?string $corr_id = null ): array|WP_Error {
        return $this->logger->scoped(
            $this->build_context( $product_id, $corr_id, 'dry_run' ),
            function () use ( $product_id, $mapping, $options, $corr_id ) {
                // Depreciação: apenas term_name em termos (opções removidas definitivamente >=0.3.0)
                $deprecated_mapping  = [];
                foreach ( $mapping as $m ) {
                    if ( isset( $m['term_name'] ) ) {
                        $deprecated_mapping[] = true;
                    }
                }
                if ( $deprecated_mapping ) {
                    $this->logger->warning( 'dry_run.deprecated_fields', [ 'mapping_term_name' => true ] );
                }
                $product = wc_get_product( $product_id );
                if ( ! $product instanceof WC_Product ) {
                    $this->logger->warning( 'dry_run.invalid_product', [ 'product_id' => $product_id ] );

                    return $this->error( 'l2g_invalid_product', __( 'Produto inválido.', 'local2global' ), 400, $corr_id, [ 'product_id' => $product_id ] );
                }

                $report = [
                    'product_id' => $product_id,
                    'attributes' => [],
                    'errors'     => [],
                ];

                foreach ( $mapping as $attribute_mapping ) {
                    $target_tax    = $this->sanitize_taxonomy( (string) ( $attribute_mapping['target_tax'] ?? '' ) );
                    $local_label   = (string) ( $attribute_mapping['local_label'] ?? $attribute_mapping['local_attr'] ?? '' );
                    $create_attr   = ! empty( $attribute_mapping['create_attribute'] );
                    $term_actions  = [ 'create' => [], 'existing' => [] ];
                    $term_errors   = [];

                    $this->logger->info( 'dry_run.attribute.start', [ 'local_label' => $local_label, 'target_tax' => $target_tax, 'create_attribute' => $create_attr ] );

                    if ( '' === $target_tax ) {
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
                        $term = null;
                        if ( $attribute_exists && function_exists( 'get_term_by' ) ) {
                            $term = get_term_by( 'slug', $slug, $target_tax );
                        }
                        $log_context = [ 'local_value' => $local_value, 'slug' => $slug, 'attribute_exists' => $attribute_exists, 'target_tax' => $target_tax ];
                        if ( $term ) {
                            $term_actions['existing'][] = $local_value;
                            $this->logger->info( 'dry_run.term.existing', $log_context );
                        } elseif ( ! empty( $term_map['create'] ) || ! $attribute_exists ) {
                            $term_actions['create'][] = [ 'value' => $local_value, 'slug' => $slug ];
                            $this->logger->info( 'dry_run.term.create', $log_context + [ 'reason' => ! $attribute_exists ? 'attribute_will_be_created' : 'flag_create' ] );
                        } else {
                            $term_errors[] = sprintf( __( 'Termo %1$s não encontrado na taxonomia %2$s.', 'local2global' ), $local_value, $target_tax );
                            $this->logger->warning( 'dry_run.term.missing', $log_context );
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

                    $this->logger->info( 'dry_run.attribute.end', [
                        'target_tax' => $target_tax,
                        'term_create_count' => count( $term_actions['create'] ),
                        'term_existing_count' => count( $term_actions['existing'] ),
                        'error_count' => count( $term_errors ),
                    ] );

                    $report['errors'] = array_merge( $report['errors'], $term_errors );
                }

                $this->logger->info( 'dry_run.completed', [ 'errors' => count( $report['errors'] ) ] );

                return $report;
            }
        );
    }

    public function apply( int $product_id, array $mapping, array $options = [], ?string $corr_id = null ): array|WP_Error {
        return $this->logger->scoped(
            $this->build_context( $product_id, $corr_id, 'apply' ),
            function () use ( $product_id, $mapping, $options, $corr_id ) {
                $deprecated_mapping  = false;
                foreach ( $mapping as $m ) {
                    if ( isset( $m['term_name'] ) ) { $deprecated_mapping = true; break; }
                }
                if ( $deprecated_mapping ) {
                    $this->logger->warning( 'apply.deprecated_fields', [ 'mapping_term_name' => true ] );
                }
                $product = wc_get_product( $product_id );
                if ( ! $product instanceof WC_Product ) {
                    $this->logger->warning( 'apply.invalid_product', [ 'product_id' => $product_id ] );

                    return $this->error( 'l2g_invalid_product', __( 'Produto inválido.', 'local2global' ), 400, $corr_id, [ 'product_id' => $product_id ] );
                }

                $attributes_before = $product->get_attributes();

                $this->logger->info( 'apply.start', [ 'attributes' => count( $mapping ) ] );
                // Opções removidas na 0.3.0 (comportamento determinístico)
                $this->logger->info( 'attributes.snapshot.before', $this->describe_attributes( $attributes_before ) );

                // Backup descontinuado

                $results = [
                    'created_terms' => [],
                    'existing_terms'=> [],
                    'updated_attrs' => [],
                    'variations'    => [],
                ];

                $term_assignments  = [];
                $variation_jobs    = [];
                $template_jobs     = [];// templates removidos

                foreach ( $mapping as $index => $attribute_mapping ) {
                    $local_name   = trim( (string) ( $attribute_mapping['local_attr'] ?? '' ) );
                    $local_label  = (string) ( $attribute_mapping['local_label'] ?? $local_name );
                    $target_tax   = $this->sanitize_taxonomy( (string) ( $attribute_mapping['target_tax'] ?? '' ) );
                    $create_attr  = ! empty( $attribute_mapping['create_attribute'] );
                    $terms_config = is_array( $attribute_mapping['terms'] ?? null ) ? (array) $attribute_mapping['terms'] : [];

                    $error = $this->logger->scoped(
                        array_filter( [
                            'local_attr' => $local_name ?: null,
                            'target_tax' => $target_tax ?: null,
                        ] ),
                        function () use (
                            $index,
                            $local_name,
                            $local_label,
                            $target_tax,
                            $create_attr,
                            $terms_config,
                            $attribute_mapping,
                            $options,
                            $product,
                            &$results,
                            &$term_assignments,
                            &$variation_jobs,
                            &$template_jobs,
                            $corr_id
                        ) {
                            $this->logger->info( 'attribute.process.start', [ 'index' => $index, 'local_name' => $local_name, 'target_tax' => $target_tax ] );

                            if ( '' === $local_name ) {
                                return $this->error(
                                    'l2g_validation',
                                    sprintf( __( 'Atributo local na posição %d não informado.', 'local2global' ), $index + 1 ),
                                    400,
                                    $corr_id,
                                    [ 'attribute_index' => $index ]
                                );
                            }

                            if ( '' === $target_tax ) {
                                return $this->error(
                                    'l2g_validation',
                                    sprintf( __( 'O atributo global para %s não foi definido.', 'local2global' ), $local_name ),
                                    400,
                                    $corr_id,
                                    [ 'attribute' => $local_name ]
                                );
                            }

                            if ( ! taxonomy_exists( $target_tax ) && ! $create_attr ) {
                                return $this->error(
                                    'l2g_attribute_missing',
                                    sprintf( __( 'O atributo global %s não existe e criação automática está desativada.', 'local2global' ), $target_tax ),
                                    400,
                                    $corr_id,
                                    [ 'target_tax' => $target_tax ]
                                );
                            }

                            try {
                                $attribute_info = $this->terms->ensure_global_attribute(
                                    $target_tax,
                                    $attribute_mapping['target_label'] ?? $local_label,
                                    $create_attr,
                                    $attribute_mapping['attribute_args'] ?? []
                                );
                            } catch ( RuntimeException $exception ) {
                                $this->logger->warning( 'apply.attribute_failure', [ 'exception' => $exception ] );

                                return $this->error( 'l2g_attribute_missing', $exception->getMessage(), 400, $corr_id, [ 'target_tax' => $target_tax ] );
                            }

                            try {
                                $term_results = $this->terms->ensure_terms( $attribute_info['taxonomy'], $terms_config );
                            } catch ( RuntimeException $exception ) {
                                $this->logger->warning( 'apply.term_failure', [ 'exception' => $exception ] );

                                $status = $this->is_conflict_message( $exception->getMessage() ) ? 409 : 400;

                                return $this->error( 'l2g_terms_missing', $exception->getMessage(), $status, $corr_id, [ 'target_tax' => $target_tax ] );
                            }

                            $term_ids = array_values( array_unique( array_map( static fn( array $item ) => (int) $item['term_id'], $term_results ) ) );
                            if ( empty( $term_ids ) ) {
                                return $this->error( 'l2g_validation', __( 'Nenhum termo válido informado para o mapeamento.', 'local2global' ), 400, $corr_id, [ 'target_tax' => $target_tax ] );
                            }

                            $slug_map = [];
                            $created  = [];
                            $existing = [];

                            foreach ( $term_results as $term_result ) {
                                $normalized            = Value_Normalizer::normalize( $term_result['local_value'] );
                                $slug_map[ $normalized ] = $term_result['slug'];
                                if ( $term_result['created'] ) {
                                    $created[] = $term_result['slug'];
                                } else {
                                    $existing[] = $term_result['slug'];
                                }
                            }

                            $updated = $this->replace_attribute( $product, $local_name, $attribute_info['taxonomy'], $attribute_info['attribute_id'], $term_ids );
                            if ( ! $updated ) {
                                $this->logger->warning( 'apply.attribute_not_found', [ 'local_attr' => $local_name ] );

                                return $this->error(
                                    'l2g_attribute_missing',
                                    sprintf( __( 'Não foi possível localizar o atributo local %s no produto.', 'local2global' ), $local_name ),
                                    400,
                                    $corr_id,
                                    [ 'attribute' => $local_name ]
                                );
                            }

                            $term_assignments[] = [
                                'taxonomy' => $attribute_info['taxonomy'],
                                'terms'    => array_map( 'intval', $term_ids ),
                            ];

                            $results['created_terms'][ $attribute_info['taxonomy'] ]  = $created;
                            $results['existing_terms'][ $attribute_info['taxonomy'] ] = $existing;
                            $results['updated_attrs'][]                               = $attribute_info['taxonomy'];

                            // Sempre atualizar variações
                            $variation_jobs[] = [
                                'taxonomy'   => $attribute_info['taxonomy'],
                                'local_name' => $local_name,
                                'slug_map'   => $slug_map,
                            ];
                            $this->logger->info( 'attribute.slug_map', [ 'taxonomy' => $attribute_info['taxonomy'], 'slug_map' => $slug_map ] );

                            $this->logger->info( 'attribute.summary', [
                                'taxonomy' => $attribute_info['taxonomy'],
                                'created_terms' => $created,
                                'existing_terms' => $existing,
                                'term_created_count' => count( $created ),
                                'term_existing_count' => count( $existing ),
                            ] );

                            // save_template removido

                            $this->logger->info( 'attribute.process.end', [ 'taxonomy' => $attribute_info['taxonomy'] ] );
                            return null;
                        }
                    );

                    if ( $error instanceof WP_Error ) {
                        return $error;
                    }
                }

                try {
                    $assignment_errors = [];
                    foreach ( $term_assignments as $assignment ) {
                        $set = wp_set_object_terms( $product_id, $assignment['terms'], $assignment['taxonomy'], false );
                        if ( $set instanceof \WP_Error ) {
                            $error_msg           = $set->get_error_message();
                            $msg                 = sprintf( __( 'Falha ao atribuir termos para %s: %s', 'local2global' ), $assignment['taxonomy'], $error_msg );
                            $assignment_errors[] = $msg;
                            $this->logger->warning( 'apply.term_assignment_failed', [ 'taxonomy' => $assignment['taxonomy'], 'error' => $error_msg ] );
                            continue;
                        }
                        $this->logger->info( 'apply.term_assignment', [ 'taxonomy' => $assignment['taxonomy'], 'terms' => $assignment['terms'] ] );
                    }
                    if ( ! empty( $assignment_errors ) ) {
                        return $this->error( 'l2g_term_assignment', implode( '; ', $assignment_errors ), 500, $corr_id );
                    }

                    // templates removidos

                    foreach ( $variation_jobs as $job ) {
                        $variation_stats = $this->variations->update_variations(
                            $product,
                            $job['taxonomy'],
                            $job['local_name'],
                            $job['slug_map'],
                            $corr_id,
                            false,
                            false
                        );
                        $results['variations'][ $job['taxonomy'] ] = $variation_stats;
                    }

                    // Atualiza atributos padrão (default attributes) caso apontem para o atributo local antigo.
                    // (Removido ajuste de atributos padrão para simplificar e evitar dependência de métodos não tipados no analisador.)

                    $product->save();

                    $this->logger->info( 'attributes.snapshot.after', $this->describe_attributes( $product->get_attributes() ) );

                    if ( $product->is_type( 'variable' ) ) {
                        WC_Product_Variable::sync( $product, true );
                    }

                    wc_delete_product_transients( $product_id );
                } catch ( Throwable $exception ) {
                    $this->logger->error( 'apply.finalization_failed', [ 'exception' => $exception ] );

                    return $this->error( 'l2g_apply_failed', __( 'Falha ao concluir a aplicação do mapeamento.', 'local2global' ), 500, $corr_id, [ 'details' => $exception->getMessage() ] );
                }

                $summary = [];
                foreach ( $results['updated_attrs'] as $tax ) {
                    $summary[$tax] = [
                        'created_terms'  => $results['created_terms'][$tax] ?? [],
                        'existing_terms' => $results['existing_terms'][$tax] ?? [],
                        'variations'     => $results['variations'][$tax] ?? null,
                    ];
                }
                $this->logger->info( 'apply.completed', [ 'updated' => count( $results['updated_attrs'] ), 'summary' => $summary ] );

                return $results;
            }
        );
    }

    private function replace_attribute( WC_Product $product, string $local_name, string $taxonomy, int $attribute_id, array $term_ids ): bool {
        if ( ! taxonomy_exists( $taxonomy ) ) {
            throw new RuntimeException( sprintf( 'Taxonomia %s não registrada.', $taxonomy ) );
        }

        $attributes = $this->normalize_product_attributes( $product->get_attributes() );
        $replaced   = false;
        $local_key  = $this->normalize_attribute_name( $local_name );

        $debug_list = [];
        foreach ( $attributes as $idx => $attr_debug ) {
            if ( $attr_debug instanceof WC_Product_Attribute ) {
                if ( str_starts_with( $attr_debug->get_name(), 'pa_' ) ) { continue; }
                $debug_list[] = [ 'index' => $idx, 'name' => $attr_debug->get_name(), 'normalized' => $this->normalize_attribute_name( $attr_debug->get_name() ) ];
            }
        }
        $this->logger->info( 'replace_attribute.scan', [ 'local_key' => $local_key, 'candidates' => $debug_list ] );

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
            // Em versões recentes o atributo é taxonômico se name começa com 'pa_' e id > 0.

            $attributes[ $taxonomy ] = $new_attribute;

            if ( $index !== $taxonomy ) {
                unset( $attributes[ $index ] );
            }

            $replaced = true;
            break;
        }

        if ( $replaced ) {
            $product->set_attributes( $attributes );
            $this->logger->info( 'replace_attribute.success', [ 'taxonomy' => $taxonomy, 'term_ids' => $term_ids ] );
        } else {
            $this->logger->warning( 'replace_attribute.not_found', [ 'local_key' => $local_key ] );
        }

        return $replaced;
    }

    private function normalize_local_value( string $value ): string { return Value_Normalizer::normalize( $value ); }

    // normalize_options removido

    private function build_context( int $product_id, ?string $corr_id, string $operation ): array {
        $context = [
            'product_id' => $product_id,
            'operation'  => $operation,
        ];

        if ( $corr_id ) {
            $context['corr_id'] = $corr_id;
        }

        return $context;
    }

    private function error( string $code, string $message, int $status, ?string $corr_id, array $details = [] ): WP_Error {
        $data = array_merge( $details, [ 'status' => $status ] );

        if ( $corr_id ) {
            $data['corr_id'] = $corr_id;
        }

        return new WP_Error( $code, $message, $data );
    }

    private function is_conflict_message( string $message ): bool {
        $message = strtolower( $message );

        return str_contains( $message, 'já existe' ) || str_contains( $message, 'already exists' );
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
    // Definimos como taxonômico implicitamente via heurística (nome 'pa_' + opções inteiras) sem chamar set_taxonomy() inexistente.

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
            $taxonomy = 'pa_' . substr( $taxonomy, 0, 25 );
        }
        return $taxonomy;
    }

    /**
     * Reprocessa apenas as variações (remapeando metas) para taxonomias globais já atribuídas ao produto.
     * Determinístico: não hidrata nem infere valores; apenas normaliza e aplica quando há correspondência.
     *
     * @param int $product_id
     * @param string[]|null $only_taxonomies Lista opcional de taxonomias (pa_*) a limitar.
     * @return array|WP_Error
     */
    public function update_variations_only( int $product_id, ?array $only_taxonomies = null, ?string $corr_id = null ): array|WP_Error {
        return $this->logger->scoped(
            $this->build_context( $product_id, $corr_id, 'variations_resync' ),
            function () use ( $product_id, $only_taxonomies, $corr_id ) {
                $product = wc_get_product( $product_id );
                if ( ! $product instanceof WC_Product ) {
                    return $this->error( 'l2g_invalid_product', __( 'Produto inválido.', 'local2global' ), 400, $corr_id );
                }
                if ( ! $product->is_type( 'variable' ) ) {
                    return $this->error( 'l2g_not_variable', __( 'Produto não é variável.', 'local2global' ), 400, $corr_id );
                }

                $attributes = $this->normalize_product_attributes( $product->get_attributes() );
                $results    = [];
                $this->logger->info( 'variation.resync.start', [ 'attribute_count' => count( $attributes ), 'filter' => $only_taxonomies ] );

                $term_cache = [];
                foreach ( $attributes as $attr ) {
                    if ( ! $attr instanceof WC_Product_Attribute ) { continue; }
                    $tax = $attr->get_name();
                    if ( ! str_starts_with( $tax, 'pa_' ) ) { continue; }
                    if ( $only_taxonomies && ! in_array( $tax, $only_taxonomies, true ) ) { continue; }

                    $options = $attr->get_options(); // term IDs
                    $slug_map = [];
                    foreach ( $options as $term_id ) {
                        $term_id_int = (int) $term_id;
                        if ( isset( $term_cache[ $tax ][ $term_id_int ] ) ) {
                            $term_obj = $term_cache[ $tax ][ $term_id_int ];
                        } else {
                            $term_obj = get_term( $term_id_int, $tax );
                            if ( $term_obj && ! is_wp_error( $term_obj ) ) {
                                $term_cache[ $tax ][ $term_id_int ] = $term_obj;
                            } else {
                                $term_obj = null;
                            }
                        }
                        if ( $term_obj ) {
                            $slug_map[ Value_Normalizer::normalize( $term_obj->name ) ] = $term_obj->slug;
                        }
                    }
                    // Tenta descobrir nome local original para montar a chave de meta. Se não encontrar, usa tax sem prefixo.
                    $local_guess = preg_replace( '/^pa_/', '', $tax );
                    $stats = $this->variations->update_variations( $product, $tax, $local_guess, $slug_map, $corr_id );
                    $results[ $tax ] = $stats;
                }
                // Agrega razões (somente razões suportadas no modo determinístico)
                $aggregate = [ 'updated' => 0, 'skipped' => 0, 'total_variations' => 0, 'updated_pct' => 0.0, 'reasons' => [ 'missing_source_meta' => 0, 'no_slug_match' => 0, 'already_ok' => 0 ] ];
                foreach ( $results as $tax => $stats ) {
                    $aggregate['updated'] += (int) ( $stats['updated'] ?? 0 );
                    $aggregate['skipped'] += (int) ( $stats['skipped'] ?? 0 );
                    $aggregate['total_variations'] += (int) ( $stats['total_variations'] ?? 0 );
                    if ( isset( $stats['reasons'] ) && is_array( $stats['reasons'] ) ) {
                        foreach ( $stats['reasons'] as $reason => $count ) {
                            if ( isset( $aggregate['reasons'][ $reason ] ) ) {
                                $aggregate['reasons'][ $reason ] += (int) $count;
                            } else {
                                $aggregate['reasons'][ $reason ] = (int) $count; // caso futuro
                            }
                        }
                    }
                }
                $aggregate['updated_pct'] = $aggregate['total_variations'] > 0 ? round( ( $aggregate['updated'] / $aggregate['total_variations'] ) * 100, 2 ) : 0.0;
                $this->logger->info( 'variation.resync.summary', [ 'aggregate' => $aggregate, 'taxonomies' => array_keys( $results ) ] );
                $this->logger->info( 'variation.resync.completed', [ 'taxonomies' => $results ] );
                return [ 'product_id' => $product_id, 'taxonomies' => $results, 'aggregate' => $aggregate ];
            }
        );
    }

    /**
     * @param array<int|string, mixed> $attributes
     * @return array<int, array<string, mixed>>
     */
    private function describe_attributes( array $attributes ): array {
        $out = [];
        foreach ( $this->normalize_product_attributes( $attributes ) as $key => $attr ) {
            if ( $attr instanceof WC_Product_Attribute ) {
                $raw_options = $attr->get_options();
                $int_like    = ! empty( $raw_options ) && is_numeric( reset( $raw_options ) );
                $is_tax      = str_starts_with( $attr->get_name(), 'pa_' ) || $int_like;
                $data = [
                    'key'       => (string) $key,
                    'name'      => $attr->get_name(),
                    'is_tax'    => $is_tax,
                    'visible'   => $attr->get_visible(),
                    'variation' => $attr->get_variation(),
                    'position'  => $attr->get_position(),
                ];
                if ( $is_tax ) {
                    $data['options'] = array_map( 'intval', $attr->get_options() );
                } else {
                    $data['options'] = array_map( 'strval', $attr->get_options() );
                }
                $out[] = $data;
            }
        }
        return $out;
    }
}
