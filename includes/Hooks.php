<?php

namespace MediaWiki\Extension\VipsScaler;

use File;
use MediaTransformOutput;
use MediaWiki\Hook\BitmapHandlerCheckImageAreaHook;
use MediaWiki\Hook\BitmapHandlerTransformHook;
use TransformationalImageHandler;

class Hooks implements
	BitmapHandlerTransformHook,
	BitmapHandlerCheckImageAreaHook
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
}
