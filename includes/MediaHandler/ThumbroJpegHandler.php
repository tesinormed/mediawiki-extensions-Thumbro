<?php

namespace MediaWiki\Extension\Thumbro\MediaHandler;

use JpegHandler;

class ThumbroJpegHandler extends JpegHandler {
	/**
	 * @inheritDoc
	 */
	public function getThumbType( $ext, $mime, $params = null ): array {
		return [ 'avif', 'image/avif' ];
	}
}
