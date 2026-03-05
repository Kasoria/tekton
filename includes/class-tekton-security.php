<?php
declare(strict_types=1);
/**
 * Security utilities — encryption, code validation, CSS sanitization.
 *
 * @package Tekton
 * @since   1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Tekton_Security {

	private const CIPHER = 'aes-256-cbc';

	private const BANNED_PATTERNS = [
		'eval(',
		'exec(',
		'system(',
		'shell_exec(',
		'passthru(',
		'proc_open(',
		'popen(',
		'base64_decode',
		'file_put_contents',
		'unserialize(',
		'preg_replace_callback_array',
		'assert(',
		'create_function(',
		'call_user_func_array',
		'$_GET',
		'$_POST',
		'$_REQUEST',
		'$_SERVER',
	];

	public function encrypt_api_key( string $key ): string {
		if ( '' === $key ) {
			return '';
		}

		$enc_key = $this->get_encryption_key();
		$iv_len  = openssl_cipher_iv_length( self::CIPHER );
		$iv      = openssl_random_pseudo_bytes( $iv_len );
		$encrypted = openssl_encrypt( $key, self::CIPHER, $enc_key, 0, $iv );

		if ( false === $encrypted ) {
			return '';
		}

		return base64_encode( $iv . '::' . $encrypted );
	}

	public function decrypt_api_key( string $encrypted ): string {
		if ( '' === $encrypted ) {
			return '';
		}

		$data = base64_decode( $encrypted, true );
		if ( false === $data ) {
			return '';
		}

		$parts = explode( '::', $data, 2 );
		if ( 2 !== count( $parts ) ) {
			return '';
		}

		[ $iv, $ciphertext ] = $parts;
		$enc_key   = $this->get_encryption_key();
		$decrypted = openssl_decrypt( $ciphertext, self::CIPHER, $enc_key, 0, $iv );

		return false === $decrypted ? '' : $decrypted;
	}

	public function mask_api_key( string $key ): string {
		if ( '' === $key || strlen( $key ) < 8 ) {
			return '';
		}
		return substr( $key, 0, 3 ) . '...' . substr( $key, -4 );
	}

	/**
	 * Validate generated code for dangerous patterns.
	 *
	 * @return array{valid: bool, issues: string[]}
	 */
	public function validate_generated_code( string $code ): array {
		$issues = [];

		foreach ( self::BANNED_PATTERNS as $pattern ) {
			if ( false !== stripos( $code, $pattern ) ) {
				$issues[] = sprintf( 'Banned pattern detected: %s', $pattern );
			}
		}

		return [
			'valid'  => empty( $issues ),
			'issues' => $issues,
		];
	}

	public function sanitize_css( string $css ): string {
		$css = preg_replace( '/@import\b[^;]*;/', '', $css );
		$css = preg_replace( '/expression\s*\(/', '', $css );
		$css = preg_replace( '/javascript\s*:/', '', $css );
		$css = preg_replace( '/behavior\s*:/', '', $css );
		$css = preg_replace( '/-moz-binding\s*:/', '', $css );
		$css = preg_replace( '/url\s*\(\s*["\']?\s*data\s*:/i', 'url(blocked:', $css );

		return $css;
	}

	private function get_encryption_key(): string {
		return hash( 'sha256', wp_salt( 'auth' ) );
	}
}
