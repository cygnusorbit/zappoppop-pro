<?php
/**
 * CI-only: upload a plugin zip to Freemius and set its release mode.
 * Configuration comes from environment variables. Exits non-zero on any failure.
 */
if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

function fs_fail( $message ) {
	fwrite( STDERR, 'ERROR: ' . $message . PHP_EOL );
	exit( 1 );
}

function fs_env( $name ) {
	$value = getenv( $name );
	if ( false === $value || '' === trim( $value ) ) {
		fs_fail( 'Missing environment variable: ' . $name );
	}
	return trim( $value );
}

function fs_check( $response, $context ) {
	if ( is_object( $response ) && isset( $response->error ) ) {
		$msg = isset( $response->error->message ) ? $response->error->message : json_encode( $response->error );
		fs_fail( $context . ' failed: ' . $msg );
	}
	if ( ! is_object( $response ) ) {
		fs_fail( $context . ' returned an unexpected response.' );
	}
	return $response;
}

$dev_id     = fs_env( 'FS_DEV_ID' );
$public_key = fs_env( 'FS_PUBLIC_KEY' );
$secret_key = fs_env( 'FS_SECRET_KEY' );
$plugin_id  = fs_env( 'FS_PLUGIN_ID' );
$zip        = fs_env( 'FS_ZIP' );
$version    = fs_env( 'FS_VERSION' );
$sdk_dir    = rtrim( fs_env( 'FS_SDK_DIR' ), '/' );
$sandbox    = ( 'true' === strtolower( (string) getenv( 'FS_SANDBOX' ) ) );
$mode       = getenv( 'FS_RELEASE_MODE' );
$mode       = ( false === $mode || '' === trim( $mode ) ) ? 'pending' : trim( $mode );

if ( ! ctype_digit( $plugin_id ) || ! ctype_digit( $dev_id ) ) {
	fs_fail( 'FS_PLUGIN_ID and FS_DEV_ID must be numeric.' );
}
if ( ! in_array( $mode, array( 'pending', 'beta', 'released' ), true ) ) {
	fs_fail( 'FS_RELEASE_MODE must be pending, beta or released.' );
}
if ( ! is_file( $zip ) ) {
	fs_fail( 'Zip not found: ' . $zip );
}
foreach ( array( '/freemius/FreemiusBase.php', '/freemius/Freemius.php' ) as $rel ) {
	if ( ! is_file( $sdk_dir . $rel ) ) {
		fs_fail( 'Freemius SDK file missing: ' . $sdk_dir . $rel );
	}
	require_once $sdk_dir . $rel;
}

define( 'FS__API_SCOPE', 'developer' );
define( 'FS__API_DEV_ID', $dev_id );
define( 'FS__API_PUBLIC_KEY', $public_key );
define( 'FS__API_SECRET_KEY', $secret_key );

try {
	$api = new Freemius_Api( FS__API_SCOPE, FS__API_DEV_ID, FS__API_PUBLIC_KEY, FS__API_SECRET_KEY, $sandbox );

	// 1. Refuse to continue if this version already exists.
	$tags = fs_check( $api->Api( 'plugins/' . $plugin_id . '/tags.json', 'GET' ), 'Listing existing versions' );
	if ( isset( $tags->tags ) && is_array( $tags->tags ) ) {
		foreach ( $tags->tags as $tag ) {
			if ( isset( $tag->version ) && (string) $tag->version === $version ) {
				fs_fail( 'Version ' . $version . ' already exists on Freemius. Bump the version and tag again.' );
			}
		}
	}

	// 2. Upload the zip.
	echo 'Uploading ' . basename( $zip ) . ' as version ' . $version . ( $sandbox ? ' (sandbox)' : '' ) . PHP_EOL;
	$uploaded = $api->Api(
		'plugins/' . $plugin_id . '/tags.json',
		'POST',
		array( 'add_contributor' => false ),
		array( 'file' => $zip )
	);
	fs_check( $uploaded, 'Upload' );
	if ( ! isset( $uploaded->id ) ) {
		fs_fail( 'Upload response has no tag id: ' . json_encode( $uploaded ) );
	}
	$tag_id = (int) $uploaded->id;

	// 3. Set the release mode.
	$updated = $api->Api(
		'plugins/' . $plugin_id . '/tags/' . $tag_id . '.json',
		'PUT',
		array( 'release_mode' => $mode )
	);
	fs_check( $updated, 'Setting release mode' );

	$summary = 'Freemius: version ' . $version . ' uploaded (tag ' . $tag_id . ', mode ' . $mode . ( $sandbox ? ', sandbox' : '' ) . ').';
	echo $summary . PHP_EOL;
	$summary_file = getenv( 'GITHUB_STEP_SUMMARY' );
	if ( $summary_file ) {
		file_put_contents( $summary_file, $summary . PHP_EOL, FILE_APPEND );
	}
} catch ( \Throwable $e ) {
	fs_fail( 'Freemius request threw: ' . $e->getMessage() );
}
