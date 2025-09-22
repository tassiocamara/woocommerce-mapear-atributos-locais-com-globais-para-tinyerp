<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Utils;

use WC_Logger_Interface;

class Logger {
    private WC_Logger_Interface $logger;

    /** @var array<int, array<string, mixed>> */
    private array $context_stack = [];
    private bool $enabled;

    public function __construct() {
        $this->logger  = wc_get_logger();
        $this->refresh_enabled();
        // Atualiza flag quando a opção for alterada
        \add_action( 'update_option_local2global_logging_enabled', function( $old, $new ) {
            $this->enabled = ( $new !== 'no' );
        }, 10, 2 );
    }

    public function scoped( array $context, callable $callback ) {
        $this->push_context( $context );

        try {
            return $callback( $this );
        } finally {
            $this->pop_context();
        }
    }

    public function push_context( array $context ): void {
        $this->context_stack[] = $context;
    }

    public function pop_context(): void {
        array_pop( $this->context_stack );
    }

    public function info( string $message, array $context = [] ): void {
        if ( ! $this->enabled ) { return; }
        $this->logger->info( $message, $this->prepare_context( $context ) );
    }

    public function warning( string $message, array $context = [] ): void {
        if ( ! $this->enabled ) { return; }
        $this->logger->warning( $message, $this->prepare_context( $context ) );
    }

    public function error( string $message, array $context = [] ): void {
        if ( ! $this->enabled ) { return; }
        $this->logger->error( $message, $this->prepare_context( $context ) );
    }

    public function set_enabled( bool $enabled ): void {
        $this->enabled = $enabled;
    }

    private function refresh_enabled(): void {
        $value         = \get_option( 'local2global_logging_enabled', 'yes' );
        $this->enabled = $value !== 'no';
    }

    private function prepare_context( array $context ): array {
        $stacked = [];
        foreach ( $this->context_stack as $layer ) {
            $stacked = array_merge( $stacked, $layer );
        }

        $context = array_merge( $stacked, $context );
        $context['source'] = 'local2global';

        if ( isset( $context['exception'] ) && $context['exception'] instanceof \Throwable ) {
            $exception             = $context['exception'];
            $context['exception']  = [
                'class'   => get_class( $exception ),
                'message' => $exception->getMessage(),
            ];
            if ( defined( 'L2G_DEBUG' ) && L2G_DEBUG ) {
                $context['trace'] = $exception->getTraceAsString();
            }
        }

        return $context;
    }
}
