<?php

namespace MediaWiki\Extension\Thumbro\Hooks;

use ThumbnailImage;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "ThumbroBeforeProduceHtml" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface ThumbroBeforeProduceHtmlHook {
	/**
	 * @param ThumbnailImage $thumbnail
	 * @param array &$sources
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onThumbroBeforeProduceHtml( ThumbnailImage $thumbnail, array &$sources );
}
