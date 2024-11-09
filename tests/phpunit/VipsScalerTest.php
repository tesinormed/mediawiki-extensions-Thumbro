<?php

use MediaWiki\Extension\VipsScaler\VipsthumbnailCommand;
use MediaWiki\Extension\VipsScaler\VipsScaler;

/**
 * @covers \MediaWiki\Extension\VipsScaler\VipsScaler
 */
class VipsScalerTest extends MediaWikiMediaTestCase {

	/** @var BitmapHandler */
	private $handler;

	public function setUp(): void {
		parent::setUp();
		$this->handler = new BitmapHandler;
	}

	/**
	 * @dataProvider VipsthumbnailCommandProvider
	 * @param array $params Thumbnailing parameters
	 * @param string $type Mime type
	 * @param array $expectedCommands
	 */
	public function testVipsthumbnailCommand( $params, $type, $expectedCommands ) {
		// This file doesn't necessarily need to actually exist
		$fakeFile = $this->dataFile( "non-existent", $type );
		$actualCommands = VipsScaler::makeCommands( $fakeFile, $params, [] );
		$this->assertEquals( $expectedCommands, $actualCommands );
	}

	public function VipsthumbnailCommandProvider() {
		global $wgVipsCommand;
		$paramBase = [
			'comment' => '',
			'srcWidth' => 2048,
			'srcHeight' => 1536,
			'mimeType' => 'image/tiff',
			'dstPath' => '/tmp/fake/thumb/path.jpg',
			'dstUrl' => 'path.jpg',
			'physicalWidth' => '1024',
			'physicalHeight' => '768',
			'clientWidth' => '1024',
			'clientHeight' => '768',
		];
		return [
			[
				$paramBase,
				'image/tiff',
				[
					new VipsthumbnailCommand( $wgVipsCommand, [ 'size' => $paramBase['physicalWidth'] . 'x' . $paramBase['physicalHeight'] ] )
				]
			],
			[
				$paramBase,
				'image/png',
				[
					new VipsthumbnailCommand( $wgVipsCommand, [ 'size' => $paramBase['physicalWidth'] . 'x' . $paramBase['physicalHeight'] ] )
				]
			],
		];
	}
}
