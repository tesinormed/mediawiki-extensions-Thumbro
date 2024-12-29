<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\Thumbro\MediaHandlers;

use PNGHandler;

class ThumbroPNGHandler extends PNGHandler {
	/**
	 * @inheritDoc
	 */
	public function getThumbType( $ext, $mime, $params = null ) {
		return [ 'avif', 'image/avif' ];
	}
}
