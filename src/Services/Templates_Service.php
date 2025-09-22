<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Services;

use Evolury\Local2Global\Utils\Logger;

class Templates_Service {
    private const OPTION_KEY = 'local2global_attribute_templates';

    public function __construct( private Logger $logger ) {}

    public function get_templates(): array {
        $templates = get_option( self::OPTION_KEY, [] );

        if ( ! is_array( $templates ) ) {
            return [];
        }

        return $templates;
    }

    public function get_template_for_label( string $label ): ?array {
        $templates = $this->get_templates();
        $label_key = $this->normalise_label( $label );

        foreach ( $templates as $stored_label => $config ) {
            if ( $this->normalise_label( $stored_label ) === $label_key ) {
                return $config;
            }
        }

        return null;
    }

    public function save_template( string $label, array $config ): void {
        $templates             = $this->get_templates();
        $templates[ $label ]   = $config;
        $updated               = update_option( self::OPTION_KEY, $templates, false );

        if ( $updated ) {
            $this->logger->info(
                sprintf( 'Template atualizado para o atributo local "%s"', $label ),
                [ 'template' => $config ]
            );
        }
    }

    private function normalise_label( string $label ): string {
        return strtolower( trim( $label ) );
    }
}
