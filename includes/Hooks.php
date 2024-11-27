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
		list( $major, $minor ) = File::splitMime( $file->getMimeType() );
		if ( $major !== 'image' ) {
			return true;
		}

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
		$vipsVersion = VipsScaler::getSoftwareVersion();
		if ( $vipsVersion ) {
			$software[ '[https://www.libvips.org libvips]' ] = $vipsVersion;
		}

		// TODO: Move this to a class for ImageMagick
		if ( extension_loaded( 'imagick' ) ) {
			$imVersion = \Imagick::getVersion()['versionString'];
			if ( $imVersion ) {
				$parts = explode( ' ', $imVersion );
				if ( isset( $parts[1] ) || preg_match( '/^\d+\.\d+\.\d+$/', $parts[1] ) ) {
					$software[ '[https://imagemagick.org ImageMagick]' ] = $parts[1];
				}
			}
		}

		// TODO: Move this to a class for GD
		if ( extension_loaded( 'gd' ) ) {
			$gdVersion = gd_info()['GD Version'];
			if ( $gdVersion ) {
				$software[ '[https://www.php.net/manual/en/book.image.php GD]' ] = gd_info()['GD Version'];
			}
		}
	}
}
