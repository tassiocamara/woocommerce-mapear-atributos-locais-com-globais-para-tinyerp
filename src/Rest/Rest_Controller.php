<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Rest;

use Evolury\Local2Global\Services\Discovery_Service;
use Evolury\Local2Global\Services\Mapping_Service;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Rest_Controller {
    private string $namespace = 'local2global/v1';

    public function __construct( private Discovery_Service $discovery, private Mapping_Service $mapping ) {}

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
    }

    public function discover( WP_REST_Request $request ): WP_REST_Response {
        $product_id = (int) $request->get_param( 'product_id' );
        $data       = $this->discovery->discover( $product_id );

        return new WP_REST_Response( $data );
    }

    public function map( WP_REST_Request $request ) {
        $product_id = (int) $request->get_param( 'product_id' );
        $mapping    = (array) $request->get_param( 'mapping' );
        $options    = (array) $request->get_param( 'options' );
        $mode       = (string) $request->get_param( 'mode' );

        if ( 'apply' === $mode ) {
            $result = $this->mapping->apply( $product_id, $mapping, $options );
        } else {
            $result = $this->mapping->dry_run( $product_id, $mapping, $options );
        }

        return new WP_REST_Response( $result );
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

    public function permissions_check(): bool|WP_Error {
        if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'edit_products' ) ) {
            return true;
        }

        return new WP_Error( 'local2global_forbidden', __( 'Permissão insuficiente.', 'local2global' ), [ 'status' => 403 ] );
    }
}
