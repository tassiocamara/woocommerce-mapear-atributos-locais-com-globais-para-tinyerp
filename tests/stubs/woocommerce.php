<?php

declare(strict_types=1);

class WP_Error {
    private string $code;
    private string $message;
    private array $data;

    public function __construct( string $code, string $message = '', array $data = [] ) {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
    }

    public function get_error_code(): string {
        return $this->code;
    }

    public function get_error_message(): string {
        return $this->message;
    }

    public function get_error_data(): array {
        return $this->data;
    }

    public function add_data( array $data ): void {
        $this->data = $data;
    }
}

function is_wp_error( $value ): bool {
    return $value instanceof WP_Error;
}

interface WC_Logger_Interface {
    public function emergency( $message, array $context = [] );

    public function alert( $message, array $context = [] );

    public function critical( $message, array $context = [] );

    public function error( $message, array $context = [] );

    public function warning( $message, array $context = [] );

    public function notice( $message, array $context = [] );

    public function info( $message, array $context = [] );

    public function debug( $message, array $context = [] );

    public function log( $level, $message, array $context = [] );
}

class WC_Product {
    protected array $attributes = [];
    protected bool $saved = false;
    protected bool $is_variable = false;
    protected int $id;
    protected array $children = [];

    public function __construct( array $attributes = [], int $id = 0 ) {
        $this->attributes = $attributes;
        $this->id         = $id > 0 ? $id : random_int( 1, 1000 );
    }

    public function get_attributes(): array {
        return $this->attributes;
    }

    public function set_attributes( array $attributes ): void {
        $this->attributes = $attributes;
    }

    public function save(): void {
        $this->saved = true;
    }

    public function was_saved(): bool {
        return $this->saved;
    }

    public function is_type( string $type ): bool {
        return 'variable' === $type && $this->is_variable;
    }

    public function set_variable( bool $value ): void {
        $this->is_variable = $value;
    }

    public function set_children( array $children ): void {
        $this->children = $children;
    }

    public function get_children(): array {
        return $this->children;
    }

    public function get_id(): int {
        return $this->id;
    }
}

class WC_Product_Variable extends WC_Product {
    public static array $synced_products = [];

    public function __construct( array $attributes = [], int $id = 0 ) {
        parent::__construct( $attributes, $id );
        $this->set_variable( true );
    }

    public static function sync( WC_Product $product, bool $children = true ): void {
        self::$synced_products[] = $product->get_id();
    }
}

class WC_Product_Variation extends WC_Product {
    private array $meta = [];

    public function __construct( int $id ) {
        parent::__construct( [], $id );
    }

    public function get_meta( string $key ): string {
        return $this->meta[ $key ] ?? '';
    }

    public function update_meta_data( string $key, string $value ): void {
        $this->meta[ $key ] = $value;
    }

    public function delete_meta_data( string $key ): void {
        unset( $this->meta[ $key ] );
    }

    public function get_all_meta(): array {
        return $this->meta;
    }
}

class WC_Product_Attribute {
    private int $id = 0;
    private string $name = '';
    private array $options = [];
    private int $position = 0;
    private bool $visible = false;
    private bool $variation = false;
    private bool $is_taxonomy = false;

    public function set_id( int $id ): void {
        $this->id = $id;
    }

    public function get_id(): int {
        return $this->id;
    }

    public function set_name( string $name ): void {
        $this->name = $name;
    }

    public function get_name(): string {
        return $this->name;
    }

    public function set_options( array $options ): void {
        $this->options = $options;
    }

    public function get_options(): array {
        return $this->options;
    }

    public function set_position( int $position ): void {
        $this->position = $position;
    }

    public function get_position(): int {
        return $this->position;
    }

    public function set_visible( bool $visible ): void {
        $this->visible = $visible;
    }

    public function get_visible(): bool {
        return $this->visible;
    }

    public function set_variation( bool $variation ): void {
        $this->variation = $variation;
    }

    public function get_variation(): bool {
        return $this->variation;
    }

    public function set_taxonomy( bool $value ): void {
        $this->is_taxonomy = $value;
    }

    public function get_taxonomy(): bool {
        return $this->is_taxonomy;
    }
}

function sanitize_title( string $title ): string {
    $normalized = strtolower( $title );
    $normalized = preg_replace( '/[^a-z0-9_\-]+/u', '-', $normalized );
    return trim( (string) $normalized, '-' );
}

function sanitize_key( string $key ): string {
    $key = strtolower( $key );
    return preg_replace( '/[^a-z0-9_]/', '', $key );
}

function sanitize_text_field( string $value ): string {
    return trim( filter_var( $value, FILTER_UNSAFE_RAW, FILTER_FLAG_STRIP_LOW ) );
}

function absint( $value ): int {
    return abs( (int) $value );
}

function wc_clean( $var ) {
    if ( is_string( $var ) ) {
        return trim( $var );
    }

    return $var;
}

function wc_get_text_attributes( string $value ): array {
    if ( '' === $value ) {
        return [];
    }

    $parts = array_map( 'trim', explode( '|', $value ) );
    return array_values( array_filter( $parts, static fn( string $item ): bool => '' !== $item ) );
}

function __ ( string $text ): string {
    return $text;
}

function taxonomy_exists( string $taxonomy ): bool {
    global $test_registered_taxonomies;

    return in_array( $taxonomy, $test_registered_taxonomies, true );
}

function wp_set_object_terms( int $product_id, array $term_ids, string $taxonomy, bool $append ): void {
    global $test_object_terms;

    $test_object_terms[] = [
        'product_id' => $product_id,
        'terms'      => $term_ids,
        'taxonomy'   => $taxonomy,
        'append'     => $append,
    ];
}

function wc_delete_product_transients( int $product_id ): void {
    // noop for tests.
}

function wc_get_logger(): WC_Logger_Interface {
    global $test_wc_logger;

    if ( ! $test_wc_logger ) {
        $test_wc_logger = new class() implements WC_Logger_Interface {
            public array $logs = [];

            public function emergency( $message, array $context = [] ) {}

            public function alert( $message, array $context = [] ) {}

            public function critical( $message, array $context = [] ) {}

            public function error( $message, array $context = [] ): void {
                $this->logs[] = [ 'level' => 'error', 'message' => (string) $message, 'context' => $context ];
            }

            public function warning( $message, array $context = [] ): void {
                $this->logs[] = [ 'level' => 'warning', 'message' => (string) $message, 'context' => $context ];
            }

            public function notice( $message, array $context = [] ) {}

            public function info( $message, array $context = [] ): void {
                $this->logs[] = [ 'level' => 'info', 'message' => $message, 'context' => $context ];
            }

            public function debug( $message, array $context = [] ) {}

            public function log( $level, $message, array $context = [] ) {}
        };
    }

    return $test_wc_logger;
}

function wc_get_product( int $product_id ): ?WC_Product {
    global $test_products;

    return $test_products[ $product_id ] ?? null;
}

function register_test_product( WC_Product $product ): void {
    global $test_products;

    $test_products[ $product->get_id() ] = $product;
}

$test_registered_taxonomies = [ 'pa_cor', 'pa_tamanho' ];
$test_products              = [];
$test_object_terms          = [];
$test_wc_logger             = null;
