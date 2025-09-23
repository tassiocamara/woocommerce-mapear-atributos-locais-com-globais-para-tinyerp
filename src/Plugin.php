<?php

declare(strict_types=1);

namespace Evolury\Local2Global;

use Evolury\Local2Global\Admin\UI;
use Evolury\Local2Global\Admin\Settings;
use Evolury\Local2Global\Cli\CLI_Command;
use Evolury\Local2Global\Rest\Rest_Controller;
use Evolury\Local2Global\Services\Discovery_Service;
use Evolury\Local2Global\Services\Mapping_Service;
use Evolury\Local2Global\Services\Term_Service;
use Evolury\Local2Global\Services\Variation_Service;
use Evolury\Local2Global\Utils\Logger;

class Plugin {
    private Logger $logger;
    private Discovery_Service $discovery;
    private Term_Service $terms;
    private Variation_Service $variations;
    private Mapping_Service $mapping;
    private Rest_Controller $rest;

    /** URL base do plugin (termina com /) */
    private string $plugin_url;
    /** Caminho do diretório do plugin (termina com /) */
    private string $plugin_dir;
    /** Basename do plugin (ex.: pasta/plugin.php) */
    private string $plugin_basename;

    /** Mantido para potencial uso futuro/testes */
    private ?UI $admin_ui = null;

    /**
     * @param string $plugin_root     Caminho fornecido pelo bootstrap (arquivo ou diretório).
     * @param string $plugin_basename Basename do plugin (ex.: my-plugin/my-plugin.php).
     */
    public function __construct( private string $plugin_root, string $plugin_basename ) {
        // Deriva path e URL com segurança a partir do basename, independente de $plugin_root.
        $this->plugin_basename = $plugin_basename;
        $file_path             = \WP_PLUGIN_DIR . '/' . $this->plugin_basename;

        $this->plugin_dir = plugin_dir_path( $file_path );
        $this->plugin_url = plugin_dir_url( $file_path );
    }

    public function init(): void {
        $this->logger     = new Logger();
        $this->discovery  = new Discovery_Service();
        $this->terms      = new Term_Service( $this->logger );
        $this->variations = new Variation_Service( $this->logger );
        $this->mapping    = new Mapping_Service(
            $this->terms,
            $this->variations,
            $this->logger
        );

        // Carrega UI apenas no admin.
        if ( is_admin() ) {
            $this->admin_ui = new UI( $this->discovery, $this->plugin_url );
            $this->admin_ui->hooks();
            // Settings page (logging toggle)
            $settings = new Settings();
            $settings->init();
        }

        $this->rest = new Rest_Controller( $this->discovery, $this->mapping, $this->logger );
        add_action( 'rest_api_init', [ $this->rest, 'register_routes' ] );

        if ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) {
            \WP_CLI::add_command( 'local2global', new CLI_Command( $this->mapping ) );
        }
    }
}
