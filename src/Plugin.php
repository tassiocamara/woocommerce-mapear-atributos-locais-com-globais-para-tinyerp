<?php

declare(strict_types=1);

namespace Evolury\Local2Global;

use Evolury\Local2Global\Admin\UI;
use Evolury\Local2Global\Cli\CLI_Command;
use Evolury\Local2Global\Rest\Rest_Controller;
use Evolury\Local2Global\Services\Discovery_Service;
use Evolury\Local2Global\Services\Mapping_Service;
use Evolury\Local2Global\Services\Rollback_Service;
use Evolury\Local2Global\Services\Templates_Service;
use Evolury\Local2Global\Services\Term_Service;
use Evolury\Local2Global\Services\Variation_Service;
use Evolury\Local2Global\Utils\Logger;

class Plugin {
    private Logger $logger;
    private Templates_Service $templates;
    private Discovery_Service $discovery;
    private Term_Service $terms;
    private Variation_Service $variations;
    private Rollback_Service $rollback;
    private Mapping_Service $mapping;
    private UI $admin_ui;
    private Rest_Controller $rest;

    public function __construct( private string $plugin_dir, private string $plugin_basename ) {}

    public function init(): void {
        $this->logger     = new Logger();
        $this->templates  = new Templates_Service( $this->logger );
        $this->discovery  = new Discovery_Service( $this->templates );
        $this->terms      = new Term_Service( $this->logger );
        $this->variations = new Variation_Service( $this->logger );
        $this->rollback   = new Rollback_Service( $this->logger );
        $this->mapping    = new Mapping_Service( $this->terms, $this->variations, $this->templates, $this->rollback, $this->logger );

        $this->admin_ui = new UI( plugin_dir_url( $this->plugin_dir . 'local2global-attribute-mapper.php' ) );
        $this->admin_ui->hooks();

        $this->rest = new Rest_Controller( $this->discovery, $this->mapping );
        add_action( 'rest_api_init', [ $this->rest, 'register_routes' ] );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            \WP_CLI::add_command( 'local2global', new CLI_Command( $this->mapping ) );
        }
    }
}
