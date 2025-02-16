<?php

namespace MediaWiki\Extension\Thumbro\MediaHandler;

use WebPHandler;

class ThumbroWebPHandler extends WebPHandler {
	/**
	 * @inheritDoc
	 */
	public function getThumbType( $ext, $mime, $params = null ): array {
		return [ 'avif', 'image/avif' ];
	}
}
