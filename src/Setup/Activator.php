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

        $plugin_file = \WP_PLUGIN_DIR . '/' . $this->plugin_basename;

        FeaturesUtil::declare_compatibility( 'custom_order_tables', $plugin_file, true );
    }
}
