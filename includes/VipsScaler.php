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

		$vipsthumbnailCommands = self::makeCommands( $params, $options );
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

	public static function makeCommands( array $params, array $options ): array {
		global $wgVipsthumbnailCommand;

		$commands = [];

		// Create thumbnail into the same file type
		$baseCommand = new VipsthumbnailCommand( $wgVipsthumbnailCommand, [
			'size' => $params['physicalWidth'] . 'x' . $params['physicalHeight']
		] );
		$baseCommand->setIO( $params['srcPath'], $params['dstPath'] . self::makeOutputOptions( $options['outputOptions'] ?? [] ) );

		$commands[] = $baseCommand;

		return $commands;
	}

	/**
	 * Check the file and params against $wgVipsConfig
	 */
	public static function getHandlerOptions(
		TransformationalImageHandler $handler,
		File $file,
		array $params
	): ?array {
		global $wgVipsConfig;

		wfDebug( __METHOD__ . ": Checking Vips options\n" );

		# Iterate over conditions
		foreach ( $wgVipsConfig as $mimeType => $option ) {
			if ( $mimeType !== $file->getMimeType() ) {
				continue;
			}

			if ( !isset( $option['enabled'] ) || $option['enabled'] !== true ) {
				continue;
			}

			if ( $file->isMultipage() ) {
				// Multi-page files are not supported
				continue;
			}

			$area = $handler->getImageArea( $file );

			if ( isset( $condition['minArea'] ) && $area < $condition['minArea'] ) {
				continue;
			}
			if ( isset( $condition['maxArea'] ) && $area >= $condition['maxArea'] ) {
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

	/**
	 * Return a software version for use in Special:Version
	 */
	public static function getSoftwareVersion(): ?string {
		$result = Shell::command( [ 'vips', '-v' ] )
			->includeStderr()
			->execute();

		if ( $result->getExitCode() != 0 ) {
			// Vips command is not avaliable, exit
			return null;
		}
		// Explode the string by '-'
		// stdout returns something like vips-8.7.4-Sat Nov 21 16:50:57 UTC 2020
		$parts = explode( '-', $result->getStdout() );
		// Check if the first part exists and is a valid version number
		if ( !isset( $parts[1] ) || !preg_match( '/^\d+\.\d+\.\d+$/', $parts[1] ) ) {
			return null;
		}

		return $parts[1];
	}
}
