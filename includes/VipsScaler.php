<?php
/**
 * PHP wrapper class for VIPS under MediaWiki
 *
 * Copyright © Bryan Tong Minh, 2011
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 * @file
 */

declare( strict_types=1 );

namespace MediaWiki\Extension\VipsScaler;

use File;
use MediaTransformOutput;
use MediaWiki\Extension\VipsScaler\VipsthumbnailCommand;
use MediaWiki\Shell\Shell;
use ThumbnailImage;
use TransformationalImageHandler;

/**
 * Wrapper class for VIPS, a free image processing system good at handling
 * large pictures.
 *
 * http://www.vips.ecs.soton.ac.uk/
 *
 * @author Bryan Tong Minh
 */
class VipsScaler {
	/**
	 * Performs a transform with VIPS
	 *
	 * @see VipsScaler::onTransform
	 */
	public static function doTransform(
		TransformationalImageHandler $handler,
		File $file,
		array $params,
		array $options,
		?MediaTransformOutput &$mto
	): bool {
		wfDebug( __METHOD__ . ': scaling ' . $file->getName() . " using vips\n" );

		$vipsthumbnailCommands = self::makeCommands( $file, $params, $options );
		if ( count( $vipsthumbnailCommands ) == 0 ) {
			return true;
		}	

		// Execute the commands
		/** @var VipsthumbnailCommand $command */
		foreach ( $vipsthumbnailCommands as $i => $command ) {
			$retval = $command->execute();
			if ( $retval != 0 ) {
				wfDebug( __METHOD__ . ": vipsthumbnail command failed!\n" );
				$error = $command->getErrorString() . "\nError code: $retval";
				$mto = $handler->getMediaTransformError( $params, $error );
				return false;
			}
		}

		// Set comment
		if ( !empty( $options['setcomment'] ) && !empty( $params['comment'] ) ) {
			self::setEXIFComment( $params['dstPath'], $params['comment'] );
		}

		// Set the output variable
		$mto = new ThumbnailImage( $file, $params['dstUrl'],
			$params['clientWidth'], $params['clientHeight'], $params['dstPath'] );

		// Stop processing
		return false;
	}

	/**
	 * Converts the given array of arguments into a string in the format
	 * [key=value,key=value,...]. If the array is empty, returns an empty string.
	 * 
	 * @see https://www.libvips.org/API/current/Using-vipsthumbnail.html#output-format-and-options
	 */
	private static function makeOutputOptions( array $args ): string {
		$outputArg = '';
		if ( count( $args ) > 0  ) {
			// Format output options into [key=value,key=value] format
			$outputArg = '[';
			foreach ( $args as $key => $value ) {
				$outputArg .= "$key=$value,";
			}
			$outputArg = rtrim( $outputArg, "," );
			$outputArg .= "]";
		}
		return $outputArg;
	}

	public static function makeCommands(
		File $file,
		array $params,
		array $options
	): array {
		global $wgVipsthumbnailCommand;

		list( $major, $minor ) = File::splitMime( $file->getMimeType() );
		if ( $major !== 'image' ) {
			return [];
		}

		$outputOptions = [];
		switch( $minor ) {
			case 'png':
				// pngsave
				$outputOptions += [
					'strip' => 'true',
					'filter' => 'VIPS_FOREIGN_PNG_FILTER_ALL',
				];
				break;
		}

		$commands = [];

		// Create thumbnail into the same file type
		$baseCommand = new VipsthumbnailCommand( $wgVipsthumbnailCommand, [
			'size' => $params['physicalWidth'] . 'x' . $params['physicalHeight']
		] );
		$baseCommand->setIO( $params['srcPath'], $params['dstPath'] . self::makeOutputOptions( $outputOptions ) );

		$commands[] = $baseCommand;

		return $commands;
	}

	/**
	 * Check the file and params against $wgVipsOptions
	 */
	public static function getHandlerOptions(
		TransformationalImageHandler $handler,
		File $file,
		array $params
	): ?array {
		global $wgVipsOptions;

		wfDebug( __METHOD__ . ": Checking Vips options\n" );

		if ( !isset( $params['page'] ) ) {
			$page = 1;
		} else {
			$page = $params['page'];
		}

		# Iterate over conditions
		foreach ( $wgVipsOptions as $option ) {
			if ( isset( $option['conditions'] ) ) {
				$condition = $option['conditions'];
			} else {
				# Unconditionally pass
				return $option;
			}

			if ( 
				isset( $condition['mimeType'] ) &&
				$file->getMimeType() != $condition['mimeType']
			) {
				continue;
			}

			if ( $file->isMultipage() ) {
				$area = $file->getWidth( $page ) * $file->getHeight( $page );
			} else {
				$area = $handler->getImageArea( $file );
			}
			if ( isset( $condition['minArea'] ) && $area < $condition['minArea'] ) {
				continue;
			}
			if ( isset( $condition['maxArea'] ) && $area >= $condition['maxArea'] ) {
				continue;
			}

			$shrinkFactor = $file->getWidth( $page ) / (
				( ( $handler->getRotation( $file ) % 180 ) == 90 ) ?
				$params['physicalHeight'] : $params['physicalWidth'] );
			if ( isset( $condition['minShrinkFactor'] ) &&
					$shrinkFactor < $condition['minShrinkFactor'] ) {
				continue;
			}
			if ( isset( $condition['maxShrinkFactor'] ) &&
					$shrinkFactor >= $condition['maxShrinkFactor'] ) {
				continue;
			}

			# This condition passed
			return $option;
		}
		# All conditions failed
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
