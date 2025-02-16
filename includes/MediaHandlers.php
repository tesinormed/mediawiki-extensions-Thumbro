<?php

namespace MediaWiki\Extension\Thumbro;

use MediaWiki\Extension\Thumbro\MediaHandler\ThumbroGIFHandler;
use MediaWiki\Extension\Thumbro\MediaHandler\ThumbroJpegHandler;
use MediaWiki\Extension\Thumbro\MediaHandler\ThumbroPNGHandler;
use MediaWiki\Extension\Thumbro\MediaHandler\ThumbroWebPHandler;

class MediaHandlers {
	public const HANDLERS = [
		'image/gif' => ThumbroGIFHandler::class,
		'image/jpeg' => ThumbroJpegHandler::class,
		'image/png' => ThumbroPNGHandler::class,
		'image/webp' => ThumbroWebPHandler::class
	];
}
