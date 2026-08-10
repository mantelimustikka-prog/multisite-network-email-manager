<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MNEM_Mail_Router {
    private $settings;
    private $logger;

    public function __construct( MNEM_Settings $settings, MNEM_Logger $logger ) {
        $this->settings = $settings;
        $this->logger   = $logger;
    }

    public function hooks(): void {
        add_action( 'phpmailer_init', array( $this, 'configure_phpmailer' ) );
        add_filter( 'wp_mail_from', array( $this, 'mail_from' ) );
        add_filter( 'wp_mail_from_name', array( $this, 'mail_from_name' ) );
    }

    public function configure_phpmailer( PHPMailer\PHPMailer\PHPMailer $phpmailer ): void {
        if ( ! $this->settings->get( 'smtp_enabled' ) ) {
            return;
        }

        $host = (string) $this->settings->get( 'smtp_host', '' );
        if ( '' === $host ) {
            $this->logger->warning( 'SMTP is enabled but no SMTP host is configured.' );
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host       = $host;
        $phpmailer->Port       = (int) $this->settings->get( 'smtp_port', 587 );
        $phpmailer->SMTPAuth   = '' !== (string) $this->settings->get( 'smtp_username', '' );
        $phpmailer->Username   = (string) $this->settings->get( 'smtp_username', '' );
        $phpmailer->Password   = (string) $this->settings->get( 'smtp_password', '' );
        $phpmailer->SMTPSecure = 'none' === $this->settings->get( 'smtp_encryption', 'tls' ) ? '' : (string) $this->settings->get( 'smtp_encryption', 'tls' );

        $this->logger->info(
            'PHPMailer configured for SMTP delivery.',
            array( 'provider' => $this->settings->get( 'smtp_provider', 'custom' ) )
        );
    }

    public function mail_from( string $email ): string {
        $configured = (string) $this->settings->get( 'from_email', '' );

        return '' !== $configured ? $configured : $email;
    }

    public function mail_from_name( string $name ): string {
        $configured = (string) $this->settings->get( 'from_name', '' );

        return '' !== $configured ? $configured : $name;
    }
}
