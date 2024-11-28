<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\Thumbro;

use Config;
use File;
use TransformationalImageHandler;

class Utils {
	/**
	 * Check whether the file should be transformed by Thumbro
	 * If it should, return the options
	 */
	public static function getOptions(
		TransformationalImageHandler $handler,
		File $file,
		Config $config
	): ?array {
		$options = $config->get( 'ThumbroOptions' );
		$libraries = $config->get( 'ThumbroLibraries' );

		foreach ( $options as $mimeType => $option ) {
			if ( $mimeType !== $file->getMimeType() ) {
				continue;
			}

			if ( !isset( $option['enabled'] ) || $option['enabled'] !== true ) {
				continue;
			}

			$library = $option['library'];
			if ( !isset( $library ) || !isset( $libraries[$library] ) || !isset( $libraries[$library]['command'] ) ) {
				continue;
			}
			$option['command'] = $libraries[$library]['command'];

			// Multi-page files are not supported
			if ( $file->isMultipage() ) {
				continue;
			}

			$area = $handler->getImageArea( $file );
			if ( isset( $option['minArea'] ) && $area < $option['minArea'] ) {
				continue;
			}
			if ( isset( $option['maxArea'] ) && $area >= $option['maxArea'] ) {
				continue;
			}

			return $option;
		}
		return null;
	}

	/**
	 * Sets a comment on a file using exiv2.
	 * Requires $wgExiv2Command to be setup properly.
	 *
	 * @todo FIXME need to handle errors such as $wgExiv2Command not available
	 */
	public static function setEXIFComment( string $fileName, string $comment ): void {
		global $wgExiv2Command;

		Shell::command( $wgExiv2Command, 'mo', '-c', $comment, $fileName )
			->execute();
	}
}
