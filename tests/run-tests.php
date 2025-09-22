<?php

declare(strict_types=1);

require __DIR__ . '/stubs/woocommerce.php';
require __DIR__ . '/../src/Services/Mapping_Service.php';
require __DIR__ . '/../src/Services/Variation_Service.php';
require __DIR__ . '/../src/Utils/Logger.php';

use Evolury\Local2Global\Services\Mapping_Service;
use Evolury\Local2Global\Services\Variation_Service;
use Evolury\Local2Global\Utils\Logger;

final class TestRunner {
    private int $assertions = 0;

    public function run(): void {
        $this->testReplaceAttributeWithObject();
        $this->testReplaceAttributeWithLegacyArray();
        $this->testUpdateVariations();

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
        $reflection = new ReflectionClass( Mapping_Service::class );
        /** @var Mapping_Service $service */
        $service    = $reflection->newInstanceWithoutConstructor();

        return $service;
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
        $this->assertTrue( $replaced->get_taxonomy(), 'Attribute must be taxonomy' );
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

        $this->assertSame( [ 'updated' => 1, 'skipped' => 0 ], $result, 'Variation update counters mismatch' );

        $meta = $variation->get_all_meta();
        $this->assertSame( 'azul-marinho', $meta['attribute_pa_cor'] ?? null, 'Variation meta should be updated with slug' );
        $this->assertTrue( ! isset( $meta['attribute_cor'] ), 'Legacy variation meta should be removed' );
        $this->assertTrue( in_array( $product->get_id(), WC_Product_Variable::$synced_products, true ), 'Variable product should be synced' );
    }
}

( new TestRunner() )->run();
