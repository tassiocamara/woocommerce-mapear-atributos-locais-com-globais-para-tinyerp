<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Setup;

use Automattic\WooCommerce\Utilities\FeaturesUtil;

class Activator {
    public function __construct( private string $plugin_basename ) {}

    public function maybe_declare_hpos_compatibility(): void {
        if ( ! class_exists( FeaturesUtil::class ) ) {
            return;
        }

        $basename = $this->plugin_basename;

        add_action( 'before_woocommerce_init', static function () use ( $basename ): void {
            FeaturesUtil::declare_compatibility( 'custom_order_tables', $basename, true );
        } );
    }
}
