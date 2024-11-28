<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\Thumbro\MediaHandlers;

use GIFHandler;

class ThumbroGIFHandler extends GIFHandler {
	/**
	 * @inheritDoc
	 */
	public function getThumbType( $ext, $mime, $params = null ) {
		return [ 'webp', 'image/webp' ];
	}
}
