<?php
/**
 * Plugin Name: Local 2 Global Attribute Mapper
 * Description: Facilita o mapeamento de atributos locais do WooCommerce para atributos globais com variações e templates reutilizáveis.
 * Plugin URI: https://evolury.com.br
 * Author: Tássio Câmara
 * Author URI: https://evolury.com.br
 * Version: 0.2.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Text Domain: local2global
 * Domain Path: /languages
 * WC requires at least: 8.6
 * WC tested up to: 8.7
 */

declare(strict_types=1);

namespace Evolury\Local2Global;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const VERSION = '0.2.0';

if ( ! defined( 'L2G_DEBUG' ) ) {
    define( 'L2G_DEBUG', defined( 'WP_DEBUG' ) && WP_DEBUG );
}

/**
 * Autoloader simplificado baseado em PSR-4.
 *
 * @param string $class Nome da classe.
 */
function autoload( string $class ): void {
    if ( ! str_starts_with( $class, __NAMESPACE__ . '\\' ) ) {
        return;
    }

    $relative = substr( $class, strlen( __NAMESPACE__ ) + 1 );
    $relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
    $path     = plugin_dir_path( __FILE__ ) . 'src/' . $relative . '.php';

    if ( file_exists( $path ) ) {
        require_once $path;
    }
}

spl_autoload_register( __NAMESPACE__ . '\\autoload' );

/**
 * Executada na ativação do plugin para declarar compatibilidade com HPOS.
 */
function on_activation(): void {
    ( new Setup\Activator( plugin_basename( __FILE__ ) ) )->maybe_declare_hpos_compatibility();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\on_activation' );

add_action( 'before_woocommerce_init', static function (): void {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
}, 5 );

/**
 * Bootstrap principal do plugin. Executa apenas após o WooCommerce inicializar.
 */
function bootstrap(): void {
    static $bootstrapped = false;

    if ( $bootstrapped ) {
        return;
    }

    // Garante que o WooCommerce está carregado antes de inicializar o plugin.
    if ( ! class_exists( '\WooCommerce' ) ) {
        return; // WooCommerce não carregado.
    }
    if ( ! did_action( 'woocommerce_init' ) ) {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->info( 'bootstrap.defer_waiting_woocommerce', [ 'source' => 'local2global' ] );
        }
        return; // Aguardando evento woocommerce_init.
    }

    $bootstrapped = true;

    load_plugin_textdomain( 'local2global', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    // Inicializa o núcleo do plugin.
    $plugin = new Plugin( plugin_dir_path( __FILE__ ), plugin_basename( __FILE__ ) );
    $plugin->init();
}
add_action( 'init', __NAMESPACE__ . '\\bootstrap', 20 );

// Fallback: caso o WooCommerce inicialize após o hook 'init' onde tentamos bootstrap,
// garantimos nova tentativa assim que 'woocommerce_init' disparar.
\add_action( 'woocommerce_init', __NAMESPACE__ . '\\bootstrap', 5 );
