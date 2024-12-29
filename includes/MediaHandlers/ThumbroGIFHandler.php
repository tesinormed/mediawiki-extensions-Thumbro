<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\Thumbro\MediaHandlers;

use GIFHandler;

class ThumbroGIFHandler extends GIFHandler {
	/**
	 * @inheritDoc
	 */
	public function getThumbType( $ext, $mime, $params = null ) {
		// animated AVIF is not supported by libvips yet
		return [ 'webp', 'image/webp' ];
	}
}
