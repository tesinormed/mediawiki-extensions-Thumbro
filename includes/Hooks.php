<?php

namespace MediaWiki\Extension\VipsScaler;

use File;
use MediaTransformOutput;
use MediaWiki\Hook\BitmapHandlerCheckImageAreaHook;
use MediaWiki\Hook\BitmapHandlerTransformHook;
use MediaWiki\Hook\SoftwareInfoHook;
use MediaWiki\Shell\Shell;
use TransformationalImageHandler;

class Hooks implements
	BitmapHandlerTransformHook,
	BitmapHandlerCheckImageAreaHook,
	SoftwareInfoHook
{
	/**
	 * Hook to BitmapHandlerTransform. Transforms the file using VIPS if it
	 * matches a condition in $wgVipsConditions
	 *
	 * @param TransformationalImageHandler $handler
	 * @param File $file
	 * @param array &$params
	 * @param MediaTransformOutput|null &$mto
	 * @return bool
	 */
	public function onBitmapHandlerTransform( $handler, $file, &$params, &$mto ) {
		// Check $wgVipsConditions
		$options = VipsScaler::getHandlerOptions( $handler, $file, $params );
		if ( !$options ) {
			wfDebug( "...\n" );
			return true;
		}
		return VipsScaler::doTransform( $handler, $file, $params, $options, $mto );
	}

	/**
	 * Hook to BitmapHandlerCheckImageArea. Will set $result to true if the
	 * file will by handled by VipsScaler.
	 *
	 * @param File $file
	 * @param array &$params
	 * @param mixed &$result
	 * @return bool
	 */
	public function onBitmapHandlerCheckImageArea( $file, &$params, &$result ) {
		global $wgMaxImageArea;
		/** @phan-suppress-next-line PhanTypeMismatchArgumentSuperType ImageHandler vs. MediaHandler */
		if ( VipsScaler::getHandlerOptions( $file->getHandler(), $file, $params ) !== false ) {
			wfDebug( __METHOD__ . ": Overriding $wgMaxImageArea\n" );
			$result = true;
			return false;
		}
		return true;
	}

	/**
	 * Hook called to include Vips version info on Special:Version
	 * TODO: We need to drop CLI and use php-vips directly
	 *
	 * @param array &$software Array of wikitext and version numbers
	 */
	public function onSoftwareInfo( &$software ) {
		$result = Shell::command( [ 'vips', '-v' ] )
			->includeStderr()
			->execute();

		if ( $result->getExitCode() != 0 ) {
			// Vips command is not avaliable, exit
			return;
		}
		// Explode the string by '-'
		// stdout returns something like vips-8.7.4-Sat Nov 21 16:50:57 UTC 2020
		$parts = explode( '-', $result->getStdout() );
		// Check if the first part exists and is a valid version number
		if ( !isset( $parts[1] ) || !preg_match( '/^\d+\.\d+\.\d+$/', $parts[1] ) ) {
			return;
		}

		// We've already logged if this isn't ok and there is no need to warn the user on this page.
		$software[ '[https://www.libvips.org libvips]' ] = $parts[1];
	}
}
