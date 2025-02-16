<?php

namespace MediaWiki\Extension\Thumbro\MediaHandler;

use PNGHandler;

class ThumbroPNGHandler extends PNGHandler {
	/**
	 * @inheritDoc
	 */
	public function getThumbType( $ext, $mime, $params = null ): array {
		return [ 'avif', 'image/avif' ];
	}
}
