<?php

namespace MediaWiki\Extension\Thumbro\MediaHandler;

use GIFHandler;

class ThumbroGIFHandler extends GIFHandler {
	/**
	 * @inheritDoc
	 */
	public function getThumbType( $ext, $mime, $params = null ): array {
		// animated AVIF is not supported by libvips yet
		return [ 'webp', 'image/webp' ];
	}
}
