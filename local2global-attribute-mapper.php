<?php
/**
 * Plugin Name: Local 2 Global Attribute Mapper
 * Description: Facilita o mapeamento de atributos locais do WooCommerce para atributos globais com variações e templates reutilizáveis.
 * Plugin URI: https://evolury.com.br
 * Author: Tássio Câmara
 * Author URI: https://evolury.com.br
 * Version: 0.1.0
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

const VERSION = '0.1.0';

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

register_activation_hook( __FILE__, static function (): void {
    ( new Setup\Activator( plugin_basename( __FILE__ ) ) )->maybe_declare_hpos_compatibility();
} );

add_action( 'plugins_loaded', static function (): void {
    if ( ! class_exists( '\\WooCommerce' ) ) {
        return;
    }

    load_plugin_textdomain( 'local2global', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

    $plugin = new Plugin( plugin_dir_path( __FILE__ ), plugin_basename( __FILE__ ) );
    $plugin->init();
} );
