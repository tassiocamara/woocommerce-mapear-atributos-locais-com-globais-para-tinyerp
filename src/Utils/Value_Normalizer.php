<?php

declare(strict_types=1);

namespace Evolury\Local2Global\Utils;

class Value_Normalizer {
    public static function normalize( string $value ): string {
        $clean = strtolower( trim( wc_clean( $value ) ) );
        if ( class_exists( '\\Normalizer' ) ) {
            $clean = \Normalizer::normalize( $clean, \Normalizer::FORM_D );
            $clean = preg_replace( '/\p{Mn}+/u', '', $clean );
        }
        $map = [ 'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c' ];
        $clean = strtr( $clean, $map );
        return $clean;
    }
}
