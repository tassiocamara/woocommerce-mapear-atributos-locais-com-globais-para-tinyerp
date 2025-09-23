<?php

declare(strict_types=1);

// Add random_int function if not exists (for PHP < 7.0 compatibility in tests)
if (!function_exists('random_int')) {
    /**
     * Generate a random integer
     * @param int $min
     * @param int $max
     * @return int
     */
    function random_int(int $min, int $max): int {
        $range = $max - $min;
        return $min + (int) (microtime(true) * 1000000) % ($range + 1);
    }
}

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

    public function set_id( int $id ): void {
        $this->id = $id;
    }

    public function set_name( string $name ): void {
        // stub implementation
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

    public function get_meta($key = '', $single = true, $context = 'view') {
        if (empty($key)) {
            return $this->meta;
        }
        return $this->meta[$key] ?? '';
    }

    public function update_meta_data($key, $value, $meta_id = 0): void {
        $this->meta[$key] = $value;
    }

    public function delete_meta_data($key): void {
        unset($this->meta[$key]);
    }

    public function get_all_meta(): array {
        return $this->meta;
    }

    /**
     * Simula get_variation_attributes do WooCommerce
     * @param bool $with_prefix
     * @return array
     */
    public function get_variation_attributes( bool $with_prefix = true ): array {
        $prefix = $with_prefix ? 'attribute_' : '';
        $variation_attributes = [];
        
        foreach ( $this->attributes as $key => $value ) {
            $variation_attributes[ $prefix . $key ] = $value;
        }
        
        return $variation_attributes;
    }
}

class WC_Product_Attribute {
    private int $id = 0;
    private string $name = '';
    private array $options = [];
    private int $position = 0;
    private bool $visible = false;
    private bool $variation = false;

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

    // WooCommerce determina se é taxonomy baseado no nome (prefixo 'pa_') e ID > 0
    public function is_taxonomy(): bool {
        return strpos($this->name, 'pa_') === 0 && $this->id > 0;
    }

    public function get_taxonomy(): string {
        return $this->is_taxonomy() ? $this->name : '';
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

function get_option( string $option, $default = false ) {
    // Para testes, retorna valor padrão ou configurações simuladas
    switch ( $option ) {
        case 'local2global_settings':
            return [ 'logging_enabled' => true ];
        default:
            return $default;
    }
}

function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
    // Stub para testes - não faz nada
}

function register_test_product( WC_Product $product ): void {
    global $test_products;

    $test_products[ $product->get_id() ] = $product;
}

function wc_get_attribute_taxonomies(): array {
    return [
        (object) ['attribute_name' => 'cor', 'attribute_label' => 'Cor'],
        (object) ['attribute_name' => 'tamanho', 'attribute_label' => 'Tamanho'],
    ];
}

function get_terms( array $args ): array {
    $taxonomy = $args['taxonomy'] ?? '';
    
    if ( $taxonomy === 'pa_cor' ) {
        return [
            (object) ['term_id' => 52, 'name' => 'Multicolorido', 'slug' => 'multicolorido'],
        ];
    }
    
    if ( $taxonomy === 'pa_tamanho' ) {
        return [
            (object) ['term_id' => 53, 'name' => '8', 'slug' => '8'],
            (object) ['term_id' => 54, 'name' => '2', 'slug' => '2'],
            (object) ['term_id' => 55, 'name' => '4', 'slug' => '4'],
            (object) ['term_id' => 56, 'name' => '6', 'slug' => '6'],
        ];
    }
    
    return [];
}

$test_registered_taxonomies = [ 'pa_cor', 'pa_tamanho' ];
$test_products              = [];
$test_object_terms          = [];
$test_wc_logger             = null;
$test_posts                 = [];

/**
 * Função stub para simular wc_get_product_variation_attributes
 * 
 * @param int $variation_id
 * @return array
 */
function wc_get_product_variation_attributes( int $variation_id ): array {
    global $test_products;
    
    if ( ! isset( $test_products[ $variation_id ] ) ) {
        return [];
    }
    
    $variation = $test_products[ $variation_id ];
    if ( ! $variation instanceof WC_Product_Variation ) {
        return [];
    }
    
    // Retorna todos os meta dados que começam com 'attribute_'
    $all_meta = $variation->get_all_meta();
    $attributes = [];
    
    foreach ( $all_meta as $key => $value ) {
        if ( strpos( $key, 'attribute_' ) === 0 ) {
            $attributes[ $key ] = $value;
        }
    }
    
    return $attributes;
}

/**
 * Função stub para get_the_title
 * 
 * @param int $post_id
 * @return string
 */
function get_the_title( int $post_id ): string {
    $post = get_post( $post_id );
    return $post ? $post->post_title : '';
}

/**
 * Função stub para get_post
 * 
 * @param int $post_id
 * @return object|null
 */
function get_post( int $post_id ) {
    global $test_posts;
    
    return $test_posts[ $post_id ] ?? null;
}
