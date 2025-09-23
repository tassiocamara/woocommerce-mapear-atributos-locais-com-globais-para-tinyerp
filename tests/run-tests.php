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

require __DIR__ . '/stubs/woocommerce.php';
require __DIR__ . '/../src/Services/Mapping_Service.php';
require __DIR__ . '/../src/Services/Variation_Service.php';
require __DIR__ . '/../src/Services/Term_Service.php';
require __DIR__ . '/../src/Utils/Logger.php';
require __DIR__ . '/../src/Utils/Value_Normalizer.php';

use Evolury\Local2Global\Services\Mapping_Service;
use Evolury\Local2Global\Services\Term_Service;
use Evolury\Local2Global\Services\Variation_Service;
use Evolury\Local2Global\Utils\Logger;

class StubTermService extends Term_Service {
    public array $attributes = [];
    public array $terms = [];
    public ?RuntimeException $attributeException = null;
    public ?RuntimeException $termException = null;

    public function __construct( Logger $logger ) {
        parent::__construct( $logger );
    }

    public function ensure_global_attribute( string $target_taxonomy, string $label, bool $create_if_missing = false, array $args = [] ): array {
        if ( $this->attributeException ) {
            throw $this->attributeException;
        }

        $taxonomy = sanitize_key( $target_taxonomy );
        if ( '' === $taxonomy ) {
            $taxonomy = 'pa_default';
        }
        if ( ! str_starts_with( $taxonomy, 'pa_' ) ) {
            $taxonomy = 'pa_' . $taxonomy;
        }

        return $this->attributes[ $taxonomy ] ?? [ 'attribute_id' => 9, 'taxonomy' => $taxonomy ];
    }

    public function ensure_terms( string $taxonomy, array $terms ): array {
        if ( $this->termException ) {
            throw $this->termException;
        }

        if ( isset( $this->terms[ $taxonomy ] ) ) {
            return $this->terms[ $taxonomy ];
        }

        return array_map(
            static fn( array $term ) => [
                'local_value' => $term['local_value'],
                'term_id'     => (int) ( $term['term_id'] ?? random_int( 100, 999 ) ),
                'slug'        => $term['term_slug'] ?? $term['local_value'],
                'created'     => (bool) ( $term['created'] ?? false ),
            ],
            $terms
        );
    }
}

// Templates e Rollback removidos na 0.3.0

class FailingVariationService extends Variation_Service {
    public bool $shouldFail = false;

    public function __construct( Logger $logger ) {
        parent::__construct( $logger );
    }

    public function update_variations( \WC_Product $product, string $taxonomy, string $local_name, array $slug_map, ?string $corr_id = null ): array {
        if ( $this->shouldFail ) {
            throw new RuntimeException( 'variation failure' );
        }

        return parent::update_variations( $product, $taxonomy, $local_name, $slug_map, $corr_id );
    }
}

final class TestRunner {
    private int $assertions = 0;

    public function run(): void {
        $this->testReplaceAttributeWithObject();
        $this->testReplaceAttributeWithLegacyArray();
        $this->testUpdateVariations();
        $this->testApplySuccess();
        $this->testApplyValidationError();
        $this->testApplyInternalError();

        echo sprintf( "All %d assertions passed.\n", $this->assertions );
    }

    private function assertTrue( bool $condition, string $message = 'Assertion failed' ): void {
        if ( ! $condition ) {
            throw new RuntimeException( $message );
        }

        $this->assertions++;
    }

    private function assertSame( $expected, $actual, string $message = '' ): void {
        if ( $expected !== $actual ) {
            $message = $message ?: sprintf( 'Expected %s, got %s', var_export( $expected, true ), var_export( $actual, true ) );
            throw new RuntimeException( $message );
        }

        $this->assertions++;
    }

    private function createMappingService(): Mapping_Service {
        global $test_wc_logger;
        $test_wc_logger = null;

        $logger     = new Logger();
        $terms      = new StubTermService( $logger );
        $variations = new FailingVariationService( $logger );
        
        return new Mapping_Service( $terms, $variations, $logger );
    }

    private function createMappingServiceWithDependencies(): array {
        global $test_wc_logger;
        $test_wc_logger = null;

        $logger     = new Logger();
        $terms      = new StubTermService( $logger );
        $variations = new FailingVariationService( $logger );
        $service    = new Mapping_Service( $terms, $variations, $logger );

        return [ $service, $terms, $variations, $logger ];
    }

    private function invokeReplaceAttribute( Mapping_Service $service, WC_Product $product, string $local, string $taxonomy, int $attribute_id, array $term_ids ): bool {
        $method = new ReflectionMethod( Mapping_Service::class, 'replace_attribute' );
        $method->setAccessible( true );

        return (bool) $method->invoke( $service, $product, $local, $taxonomy, $attribute_id, $term_ids );
    }

    private function testReplaceAttributeWithObject(): void {
        global $test_object_terms;
        $test_object_terms = [];

        $attribute = new WC_Product_Attribute();
        $attribute->set_name( 'Cor' );
        $attribute->set_position( 1 );
        $attribute->set_visible( true );
        $attribute->set_variation( true );

        $product = new WC_Product( [ 'cor' => $attribute ], 10 );

        $service = $this->createMappingService();
        $result  = $this->invokeReplaceAttribute( $service, $product, 'Cor', 'pa_cor', 9, [ 11, 12 ] );

        $this->assertTrue( $result, 'Attribute should be replaced' );

        $attributes = $product->get_attributes();
        $this->assertTrue( isset( $attributes['pa_cor'] ), 'Attribute key should be taxonomy' );

        /** @var WC_Product_Attribute $replaced */
        $replaced = $attributes['pa_cor'];
        $this->assertSame( 'pa_cor', $replaced->get_name(), 'Attribute name should be taxonomy' );
        $this->assertSame( [ 11, 12 ], $replaced->get_options(), 'Attribute options must be term ids' );
        $this->assertTrue( $replaced->is_taxonomy(), 'Attribute must be taxonomy' );
        $this->assertTrue( $replaced->get_visible(), 'Visibility should be preserved' );
        $this->assertTrue( $replaced->get_variation(), 'Variation flag should be preserved' );
    }

    private function testReplaceAttributeWithLegacyArray(): void {
        $legacy_attribute = [
            'name'         => 'Cor',
            'value'        => 'Azul | Verde',
            'is_visible'   => 1,
            'is_variation' => 0,
            'is_taxonomy'  => 0,
        ];

        $product = new WC_Product( [ 'cor' => $legacy_attribute ], 11 );

        $service = $this->createMappingService();
        $result  = $this->invokeReplaceAttribute( $service, $product, 'Cor', 'pa_cor', 9, [ 14 ] );

        $this->assertTrue( $result, 'Legacy attribute should be replaced' );
        $attributes = $product->get_attributes();
        $this->assertTrue( isset( $attributes['pa_cor'] ), 'Legacy attribute should be converted to taxonomy' );

        /** @var WC_Product_Attribute $replaced */
        $replaced = $attributes['pa_cor'];
        $this->assertSame( [ 14 ], $replaced->get_options(), 'Legacy attribute options should be replaced with term ids' );
        $this->assertSame( 0, $replaced->get_position(), 'Legacy position should default to zero when missing' );
    }

    private function testUpdateVariations(): void {
        global $test_products;
        $test_products = [];
        WC_Product_Variable::$synced_products = [];

        $logger    = new Logger();
        $service   = new Variation_Service( $logger );
        $product   = new WC_Product_Variable( [], 21 );
        $variation = new WC_Product_Variation( 2101 );
        $variation->update_meta_data( 'attribute_cor', 'Azul' );
        register_test_product( $variation );

        $product->set_children( [ $variation->get_id() ] );
        register_test_product( $product );

        $result = $service->update_variations( $product, 'pa_cor', 'Cor', [ 'azul' => 'azul-marinho' ] );

        // Verificar contadores básicos
        $this->assertSame( 1, $result['updated'], 'Should have updated 1 variation' );
        $this->assertSame( 0, $result['skipped'], 'Should have skipped 0 variations' );
        $this->assertSame( 1, $result['total_variations'], 'Should have 1 total variation' );
        $this->assertSame( 100.0, $result['updated_pct'], 'Should have 100% update rate' );

        $meta = $variation->get_all_meta();
        $this->assertSame( 'azul-marinho', $meta['attribute_pa_cor'] ?? null, 'Variation meta should be updated with slug' );
        $this->assertTrue( ! isset( $meta['attribute_cor'] ), 'Legacy variation meta should be removed' );
        // Sync validation removed: we no longer auto-sync to preserve variation mappings
        // $this->assertTrue( in_array( $product->get_id(), WC_Product_Variable::$synced_products, true ), 'Variable product should be synced' );
    }

    private function testApplySuccess(): void {
        global $test_products, $test_object_terms, $test_wc_logger;
        $test_products     = [];
        $test_object_terms = [];
        $test_wc_logger    = null;

        $attribute = new WC_Product_Attribute();
        $attribute->set_name( 'Cor' );
        $attribute->set_options( [ 'Azul', 'Preto' ] );

        $product = new WC_Product( [ 'cor' => $attribute ], 501 );
        register_test_product( $product );

    [ $service, $terms, $variations ] = $this->createMappingServiceWithDependencies();

        $terms->attributes['pa_cor'] = [ 'attribute_id' => 31, 'taxonomy' => 'pa_cor' ];
        $terms->terms['pa_cor']       = [
            [ 'local_value' => 'Azul', 'term_id' => 11, 'slug' => 'azul', 'created' => false ],
            [ 'local_value' => 'Preto', 'term_id' => 12, 'slug' => 'preto', 'created' => true ],
        ];

        $mapping = [
            [
                'local_attr'    => 'Cor',
                'local_label'   => 'Cor',
                'target_tax'    => 'pa_cor',
                    'terms'         => [],
            ],
        ];

    $result = $service->apply( $product->get_id(), $mapping, [], 'l2g_success' );

        $this->assertTrue( ! is_wp_error( $result ), 'Apply should succeed' );
        $this->assertSame( [ 'pa_cor' ], $result['updated_attrs'], 'Updated attributes mismatch' );
        $this->assertTrue( $product->was_saved(), 'Product should be saved' );
    // Templates removidos: não há mais salvamento
        $this->assertSame( 1, count( $test_object_terms ), 'Terms should be assigned once' );
        $this->assertSame( [ 11, 12 ], $test_object_terms[0]['terms'], 'Assigned terms mismatch' );

        global $test_wc_logger;
        $this->assertSame( 'local2global', $test_wc_logger->logs[0]['context']['source'] ?? null, 'Log source mismatch' );
        $has_corr = false;
        if ( $test_wc_logger ) {
        foreach ( $test_wc_logger->logs as $entry ) {
            if ( ( $entry['context']['corr_id'] ?? null ) === 'l2g_success' ) {
                $has_corr = true;
            }
        }
        }
        $this->assertTrue( $has_corr, 'Correlation id should appear in logs' );
    }

    private function testApplyValidationError(): void {
        global $test_products;
        $test_products = [];

        $product = new WC_Product( [ 'cor' => new WC_Product_Attribute() ], 502 );
        register_test_product( $product );

        [ $service ] = $this->createMappingServiceWithDependencies();

        $result = $service->apply( $product->get_id(), [ [ 'local_attr' => 'Cor', 'target_tax' => '' ] ], [], 'l2g_validation' );

        $this->assertTrue( is_wp_error( $result ), 'Result should be WP_Error' );
        $this->assertSame( 'l2g_validation', $result->get_error_code(), 'Error code mismatch' );
        $data = $result->get_error_data();
        $this->assertSame( 400, $data['status'] ?? null, 'Status should be 400' );
        $this->assertSame( 'l2g_validation', $data['corr_id'] ?? null, 'Correlation id mismatch' );
    }

    private function testApplyInternalError(): void {
        global $test_products, $test_registered_taxonomies;
        $test_products = [];
        if ( ! in_array( 'pa_cor', $test_registered_taxonomies, true ) ) {
            $test_registered_taxonomies[] = 'pa_cor';
        }

        $attribute = new WC_Product_Attribute();
        $attribute->set_name( 'Cor' );

        $product = new WC_Product_Variable( [ 'cor' => $attribute ], 503 );
        register_test_product( $product );

    [ $service, $terms, $variations ] = $this->createMappingServiceWithDependencies();
        $variations->shouldFail          = true;

        $terms->attributes['pa_cor'] = [ 'attribute_id' => 40, 'taxonomy' => 'pa_cor' ];
        $terms->terms['pa_cor']       = [
            [ 'local_value' => 'Azul', 'term_id' => 13, 'slug' => 'azul', 'created' => false ],
        ];

        $result = $service->apply(
            $product->get_id(),
            [
                [
                    'local_attr'  => 'Cor',
                    'target_tax'  => 'pa_cor',
                    'terms'       => [ [ 'local_value' => 'Azul' ] ],
                ],
            ],
            [],
            'l2g_fail'
        );

        $this->assertTrue( is_wp_error( $result ), 'Result should be WP_Error' );
        $this->assertSame( 'l2g_apply_failed', $result->get_error_code(), 'Error code mismatch' );
        $data = $result->get_error_data();
        $this->assertSame( 500, $data['status'] ?? null, 'Status should be 500' );
        $this->assertSame( 'l2g_fail', $data['corr_id'] ?? null, 'Correlation id mismatch' );
        $this->assertSame( 'variation failure', $data['details'] ?? null, 'Details should expose root cause' );
    }
}

( new TestRunner() )->run();
