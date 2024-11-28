<?php
declare( strict_types=1 );

namespace MediaWiki\Extension\Thumbro\MediaHandlers;

use WebPHandler;

class ThumbroWebPHandler extends WebPHandler {
	/**
	 * @inheritDoc
	 */
	public function getThumbType( $ext, $mime, $params = null ) {
		return [ 'webp', 'image/webp' ];
	}

	/**
	 * @inheritDoc
	 */
	public function mustRender( $file ) {
		return false;
	}
}
