<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MNEM_Template_Engine {
    public function render_string( string $template, array $context = array() ): string {
        $replacements = array();

        foreach ( $context as $key => $value ) {
            $replacements[ '{{' . $key . '}}' ] = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
        }

        return strtr( $template, $replacements );
    }
}
