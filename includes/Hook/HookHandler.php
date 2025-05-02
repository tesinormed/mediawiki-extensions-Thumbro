<?php

namespace MediaWiki\Extension\Thumbro\Hook;

use Jcupitt\Vips\Exception;
use Jcupitt\Vips\Image;
use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Extension\Thumbro\MediaHandlers;
use MediaWiki\Hook\BitmapHandlerCheckImageAreaHook;
use MediaWiki\Hook\BitmapHandlerTransformHook;
use MediaWiki\Hook\SoftwareInfoHook;
use ThumbnailImage;

class HookHandler implements
	BitmapHandlerTransformHook,
	BitmapHandlerCheckImageAreaHook,
	SoftwareInfoHook
{
	private Config $config;

	public function __construct( ConfigFactory $configFactory ) {
		$this->config = $configFactory->makeConfig( 'thumbro' );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Extension.json/Schema#ExtensionFunctions
	 */
	public static function postSetup(): void {
		global $wgMediaHandlers;
		foreach ( MediaHandlers::HANDLERS as $mimeType => $class ) {
			$wgMediaHandlers[$mimeType] = $class;
		}
	}

	/**
	 * @inheritDoc
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BitmapHandlerTransform
	 */
	public function onBitmapHandlerTransform( $handler, $image, &$scalerParams, &$mto ) {
		$srcPath = $scalerParams['srcPath'];
		$srcMimeType = $scalerParams['mimeType'];
		$width = $scalerParams['physicalWidth'];
		$height = $scalerParams['physicalHeight'];
		$dstPath = $scalerParams['dstPath'];
		$dstMimeType = $handler->getThumbType( $image->getExtension(), $srcMimeType )[1];
		$dstUrl = $scalerParams['dstUrl'];

		try {
			$vipsImage = Image::thumbnail_buffer(
				file_get_contents( $srcPath ),
				$width,
				[ 'height' => $height ] + $this->getInputOptions( $srcMimeType )
			);
			$vipsImage->writeToFile( $dstPath, $this->getOutputOptions( $dstMimeType ) );
			$mto = new ThumbnailImage(
				$image,
				$dstUrl,
				$dstPath,
				[ 'width' => $width, 'height' => $height ]
			);
		} catch ( Exception $exception ) {
			wfLogWarning( $exception->getMessage() );
			$mto = $handler->getMediaTransformError( $scalerParams, $exception->getMessage() );
		}
		return false;
	}

	/**
	 * @inheritDoc
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BitmapHandlerCheckImageArea
	 */
	public function onBitmapHandlerCheckImageArea( $image, &$params, &$checkImageAreaHookResult ): bool {
		// always override the maximum image area
		$checkImageAreaHookResult = true;
		return false;
	}

	/**
	 * @inheritDoc
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SoftwareInfo
	 */
	public function onSoftwareInfo( &$software ): void {
		$vipsVersion = \Jcupitt\Vips\Config::version();
		$software['[https://www.libvips.org/ libvips]'] = $vipsVersion;
	}

	private function getInputOptions( string $mimeType ): array {
		return $this->config->get( 'ThumbroOptions' )[$mimeType]['input'] ?? [];
	}

	private function getOutputOptions( string $mimeType ): array {
		return $this->config->get( 'ThumbroOptions' )[$mimeType]['output'] ?? [];
	}
}
