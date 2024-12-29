<?php

namespace MediaWiki\Extension\Thumbro\Hooks;

use MediaWiki\HookContainer\HookContainer;
use ThumbnailImage;

/**
 * This is a hook runner class, see docs/Hooks.md in core.
 * @internal
 */
class HookRunner implements ThumbroBeforeProduceHtmlHook {
	private HookContainer $hookContainer;

	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/**
	 * @inheritDoc
	 */
	public function onThumbroBeforeProduceHtml( ThumbnailImage $thumbnail, array &$sources ) {
		return $this->hookContainer->run(
			'ThumbroBeforeProduceHtml',
			[ $thumbnail, &$sources ]
		);
	}
}
