<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Rest;

use Evolury\Local2Global\Services\Discovery_Service;
use Evolury\Local2Global\Services\Mapping_Service;
use Evolury\Local2Global\Utils\Logger;
use RuntimeException;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Rest_Controller {
    private string $namespace = 'local2global/v1';

    public function __construct( private Discovery_Service $discovery, private Mapping_Service $mapping, private Logger $logger ) {}

    public function register_routes(): void {
        register_rest_route(
            $this->namespace,
            '/discover',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'discover' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => [
                    'product_id' => [
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/map',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'map' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => [
                    'product_id' => [
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'mapping'    => [
                        'type'     => 'array',
                        'required' => true,
                    ],
                    'options'    => [
                        'type'     => 'object',
                        'required' => false,
                    ],
                    'mode'       => [
                        'type'              => 'string',
                        'required'          => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/terms/(?P<taxonomy>[a-z0-9_\-]+)',
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'terms' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => [
                    'search' => [
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'number' => [
                        'type'    => 'integer',
                        'default' => 100,
                    ],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/variations/update',
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'variations_update' ],
                'permission_callback' => [ $this, 'permissions_check' ],
                'args'                => [
                    'product_id' => [
                        'type'              => 'integer',
                        'required'          => true,
                        'sanitize_callback' => 'absint',
                    ],
                    'taxonomies' => [
                        'type'     => 'array',
                        'required' => false,
                    ],
                ],
            ]
        );
    }

    public function discover( WP_REST_Request $request ): WP_REST_Response {
        $product_id = (int) $request->get_param( 'product_id' );
        $data       = $this->discovery->discover( $product_id );

        return new WP_REST_Response( $data );
    }

    public function map( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $corr_id = uniqid( 'l2g_', true );

        return $this->logger->scoped(
            [ 'corr_id' => $corr_id, 'endpoint' => 'map' ],
            function () use ( $request, $corr_id ) {
                $product_id = (int) $request->get_param( 'product_id' );
                $mapping    = $request->get_param( 'mapping' );
                $options    = $request->get_param( 'options' ); // legacy (ignoradas >=0.3.0)
                $mode       = strtolower( (string) $request->get_param( 'mode' ) );

                if ( $product_id <= 0 ) {
                    $this->logger->warning( 'map.validation_failed', [ 'reason' => 'invalid_product', 'product_id' => $product_id ] );

                    return $this->rest_error( 'l2g_invalid_product', __( 'Produto inválido.', 'local2global' ), 400, $corr_id );
                }

                if ( ! is_array( $mapping ) || empty( $mapping ) ) {
                    $this->logger->warning( 'map.validation_failed', [ 'reason' => 'invalid_mapping', 'product_id' => $product_id ] );

                    return $this->rest_error( 'l2g_validation', __( 'Mapeamento não informado.', 'local2global' ), 400, $corr_id );
                }

                if ( null !== $options && ! is_array( $options ) && ! is_object( $options ) ) {
                    $this->logger->warning( 'map.validation_failed', [ 'reason' => 'invalid_options', 'product_id' => $product_id ] );

                    return $this->rest_error( 'l2g_validation', __( 'Formato de opções inválido.', 'local2global' ), 400, $corr_id );
                }

                $options = $options ? (array) $options : [];
                $deprecated_keys = [ 'auto_create_terms', 'update_variations', 'create_backup', 'hydrate_variations', 'aggressive_hydrate_variations', 'save_template' ];
                $sent_deprecated = array_values( array_intersect( $deprecated_keys, array_keys( $options ) ) );
                $mapping_deprecated = [];
                foreach ( (array) $mapping as $m ) {
                    if ( isset( $m['save_template'] ) || isset( $m['term_name'] ) ) {
                        $mapping_deprecated[] = [ 'save_template' => isset( $m['save_template'] ), 'term_name' => isset( $m['term_name'] ) ];
                    }
                }
                if ( ! empty( $sent_deprecated ) || ! empty( $mapping_deprecated ) ) {
                    $this->logger->warning( 'map.deprecated_fields', [
                        'deprecated_options' => $sent_deprecated,
                        'mapping_flags'      => $mapping_deprecated,
                        'message'            => 'Campos legacy ignorados a partir da 0.3.0',
                    ] );
                }

                $this->logger->info(
                    'map.request_received',
                    [
                        'product_id' => $product_id,
                        'mode'       => $mode ?: 'apply',
                        'mapping'    => array_map( static fn( $item ) => array_intersect_key( (array) $item, [ 'local_attr' => true, 'target_tax' => true ] ), $mapping ),
                    ]
                );

                try {
                    // options legacy ignoradas (comportamento único)
                    if ( 'apply' === $mode ) {
                        $result = $this->mapping->apply( $product_id, (array) $mapping, [], $corr_id );
                    } else {
                        $result = $this->mapping->dry_run( $product_id, (array) $mapping, [], $corr_id );
                    }

                    if ( is_wp_error( $result ) ) {
                        $data = $result->get_error_data() ?: [];
                        if ( is_array( $data ) && empty( $data['corr_id'] ) ) {
                            $data['corr_id'] = $corr_id;
                            if ( method_exists( $result, 'add_data' ) ) {
                                $result->add_data( $data );
                            }
                        }

                        return $result;
                    }

                    return rest_ensure_response(
                        [
                            'ok'       => true,
                            'corr_id'  => $corr_id,
                            'result'   => $result,
                        ]
                    );
                } catch ( RuntimeException $exception ) {
                    $this->logger->warning( 'map.runtime_error', [ 'exception' => $exception ] );

                    return $this->rest_error(
                        'l2g_validation',
                        $exception->getMessage(),
                        400,
                        $corr_id,
                        [ 'details' => $exception->getMessage() ]
                    );
                } catch ( Throwable $exception ) {
                    $this->logger->error( 'map.unhandled_exception', [ 'exception' => $exception, 'product_id' => $product_id ] );

                    return $this->rest_error(
                        'l2g_apply_failed',
                        sprintf( __( 'Falha ao processar o mapeamento: %s', 'local2global' ), $exception->getMessage() ),
                        500,
                        $corr_id,
                        [ 'details' => $exception->getMessage() ]
                    );
                }
            }
        );
    }

    private function rest_error( string $code, string $message, int $status, string $corr_id, array $details = [] ): WP_Error {
        $data              = array_merge( $details, [ 'status' => $status, 'corr_id' => $corr_id ] );

        return new WP_Error( $code, $message, $data );
    }

    public function terms( WP_REST_Request $request ): WP_REST_Response {
        $taxonomy = sanitize_key( (string) $request['taxonomy'] );
        $search   = (string) $request->get_param( 'search' );
        $number   = (int) $request->get_param( 'number' );

        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'search'     => $search,
                'number'     => $number,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );

        if ( is_wp_error( $terms ) ) {
            return new WP_REST_Response( [ 'error' => $terms->get_error_message() ], 400 );
        }

        $data = array_map(
            static fn( $term ) => [
                'term_id' => (int) $term->term_id,
                'name'    => (string) $term->name,
                'slug'    => (string) $term->slug,
            ],
            $terms
        );

        return new WP_REST_Response( [ 'terms' => $data ] );
    }

    public function variations_update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $corr_id    = uniqid( 'l2g_var_', true );
        $product_id = (int) $request->get_param( 'product_id' );
        $taxonomies = $request->get_param( 'taxonomies' );
        if ( $product_id <= 0 ) {
            return $this->rest_error( 'l2g_invalid_product', __( 'Produto inválido.', 'local2global' ), 400, $corr_id );
        }
        if ( null !== $taxonomies && ! is_array( $taxonomies ) ) {
            return $this->rest_error( 'l2g_validation', __( 'Formato de taxonomies inválido.', 'local2global' ), 400, $corr_id );
        }

        $this->logger->info( 'variation.resync.request', [ 'corr_id' => $corr_id, 'product_id' => $product_id, 'tax_filter' => $taxonomies ] );
        $hydr_legacy = $request->get_param( 'hydrate_variations' );
        $agg_legacy  = $request->get_param( 'aggressive_hydrate_variations' );
        if ( null !== $hydr_legacy || null !== $agg_legacy ) {
            $this->logger->warning( 'variation.resync.deprecated_flags', [ 'hydrate_variations' => $hydr_legacy, 'aggressive_hydrate_variations' => $agg_legacy ] );
        }
    $result = $this->mapping->update_variations_only( $product_id, $taxonomies ? array_map( 'sanitize_key', $taxonomies ) : null, $corr_id );
        if ( is_wp_error( $result ) ) {
            $data = $result->get_error_data();
            if ( is_array( $data ) && empty( $data['corr_id'] ) ) {
                $data['corr_id'] = $corr_id;
                if ( method_exists( $result, 'add_data' ) ) {
                    $result->add_data( $data );
                }
            }
            return $result;
        }
        return new WP_REST_Response( [ 'ok' => true, 'corr_id' => $corr_id, 'result' => $result ] );
    }

    public function permissions_check(): bool|WP_Error {
        if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_products' ) ) {
            return true;
        }

    $user_id   = \get_current_user_id();
        $caps      = [];
        if ( $user_id ) {
            $user = \get_user_by( 'id', $user_id );
            if ( $user ) {
                $caps = array_keys( array_filter( (array) $user->allcaps ) );
            }
        }

        $this->logger->warning( 'permission.denied', [
            'user_id' => $user_id,
            'caps'    => $caps,
            'needed'  => [ 'manage_woocommerce', 'edit_products' ],
        ] );

        return new WP_Error( 'local2global_forbidden', __( 'Permissão insuficiente.', 'local2global' ), [ 'status' => 403, 'user_id' => $user_id ] );
    }
}
