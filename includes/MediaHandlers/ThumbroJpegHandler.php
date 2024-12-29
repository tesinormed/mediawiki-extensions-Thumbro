<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\Thumbro\MediaHandlers;

use JpegHandler;

class ThumbroJpegHandler extends JpegHandler {
	/**
	 * @inheritDoc
	 */
	public function getThumbType( $ext, $mime, $params = null ) {
		return [ 'avif', 'image/avif' ];
	}
}
