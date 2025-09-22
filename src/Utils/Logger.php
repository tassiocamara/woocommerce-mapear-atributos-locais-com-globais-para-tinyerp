<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Utils;

use WC_Logger_Interface;

class Logger {
    private WC_Logger_Interface $logger;

    public function __construct() {
        $this->logger = wc_get_logger();
    }

    public function info( string $message, array $context = [] ): void {
        $this->logger->info( $message, $this->prepare_context( $context ) );
    }

    public function warning( string $message, array $context = [] ): void {
        $this->logger->warning( $message, $this->prepare_context( $context ) );
    }

    public function error( string $message, array $context = [] ): void {
        $this->logger->error( $message, $this->prepare_context( $context ) );
    }

    private function prepare_context( array $context ): array {
        $context['source'] = 'local2global';

        return $context;
    }
}
