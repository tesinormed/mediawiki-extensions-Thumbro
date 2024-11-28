<?php
/*
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
 *
 * @file
 */

namespace MediaWiki\Extension\Thumbro;

use Html;
use HTMLForm;
use HTMLIntField;
use HTMLTextField;
use MediaTransformOutput;
use MediaWiki\Extension\Thumbro\Libraries\Libvips;
use MediaWiki\MediaWikiServices;
use OOUI\CheckboxInputWidget;
use OOUI\FieldLayout;
use OOUI\FieldsetLayout;
use OOUI\HtmlSnippet;
use OOUI\LabelWidget;
use OOUI\PanelLayout;
use PermissionsError;
use SpecialPage;
use Status;
use StreamFile;
use MediaWiki\Title\Title;
use User;

/**
 * A Special page intended to test Thumbro.
 * @author Bryan Tong Minh
 */
class SpecialThumbroTest extends SpecialPage {
	public function __construct() {
		parent::__construct( 'ThumbroTest', 'thumbro-test' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName(): string {
		return 'media';
	}

	/**
	 * @inheritDoc
	 */
	public function userCanExecute( User $user ): bool {
		return $this->getConfig()->get( 'ThumbroExposeTestPage' ) && parent::userCanExecute( $user );
	}

	/**
	 * @inheritDoc
	 */
	public function displayRestrictionError(): void {
		if ( !$this->getConfig()->get( 'ThumbroExposeTestPage' ) ) {
			throw new PermissionsError(
				null,
				[ 'querypage-disabled' ]
			);
		}

		parent::displayRestrictionError();
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ): void {
		$request = $this->getRequest();
		$this->setHeaders();

		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
		}

		if ( $request->getText( 'thumb' ) ) {
			$this->streamThumbnail();
		} else {
			$this->showForm();
		}
	}

	/**
	 */
	protected function showThumbnails(): void {
		$request = $this->getRequest();
		$this->getOutput()->enableOOUI();
		// Check if there is any input
		if ( !( $request->getText( 'file' ) ) ) {
			return;
		}

		// Check if valid file was provided
		$title = Title::newFromText( $request->getText( 'file' ), NS_FILE );
		if ( $title === null ) {
			$this->getOutput()->addWikiMsg( 'thumbro-invalid-file' );
			return;
		}
		$services = MediaWikiServices::getInstance();
		$file = $services->getRepoGroup()->findFile( $title );
		if ( !$file || !$file->exists() ) {
			$this->getOutput()->addWikiMsg( 'thumbro-invalid-file' );
			return;
		}

		// Create options
		$width = $request->getInt( 'width' );
		if ( !$width ) {
			$this->getOutput()->addWikiMsg( 'thumbro-invalid-width' );
			return;
		}
		$vipsUrlOptions = [ 'thumb' => $file->getName(), 'width' => $width ];
		/*
		if ( $request->getRawVal( 'sharpen' ) !== null ) {
			$vipsUrlOptions['sharpen'] = $request->getFloat( 'sharpen' );
		}
		if ( $request->getBool( 'bilinear' ) ) {
			$vipsUrlOptions['bilinear'] = 1;
		}
		*/

		// Generate normal thumbnail
		$params = [ 'width' => $width ];
		$thumb = $file->transform( $params );
		if ( !$thumb || $thumb->isError() ) {
			$this->getOutput()->addWikiMsg( 'thumbro-thumb-error' );
			return;
		}

		// Check if we actually scaled the file
		$normalThumbUrl = $thumb->getUrl();
		if ( $services->getUrlUtils()->expand( $normalThumbUrl ) == $file->getFullUrl() ) {
			$this->getOutput()->addWikiMsg( 'thumbro-thumb-notscaled' );
		}

		// Make url to the vips thumbnail
		$vipsThumbUrl = $this->getPageTitle()->getLocalUrl( $vipsUrlOptions );

		// HTML for the thumbnails
		$thumbs = new HtmlSnippet( Html::rawElement( 'div', [ 'id' => 'mw-thumbrotest-thumbnails' ],
			Html::element( 'img', [
				'src' => $normalThumbUrl,
				'alt' => $this->msg( 'thumbro-default-thumb' )->text(),
			] ) . ' ' .
			Html::element( 'img', [
				'src' => $vipsThumbUrl,
				'alt' => $this->msg( 'thumbro-vips-thumb' )->text(),
			] )
		) );

		// Helper messages shown above the thumbnails rendering
		$form = [
			new LabelWidget( [ 'label' => $this->msg( 'thumbro-thumbs-help' )->text() ] )
		];

		// A checkbox to easily alternate between both views:
		$form[] = new FieldLayout(
				new CheckboxInputWidget( [
					'name' => 'mw-thumbrotest-thumbs-switch',
					'inputId' => 'mw-thumbrotest-thumbs-switch',
				] ),
				[
					'label' => $this->msg( 'thumbro-thumbs-switch-label' )->text(),
					'align' => 'inline',
					'infusable' => true,
				]
			);

		$fieldset = new FieldsetLayout( [
			'label' => $this->msg( 'thumbro-thumbs-legend' )->text(),
			'items' => $form,
		] );

		$this->getOutput()->addHTML(
			new PanelLayout( [
				'expanded' => false,
				'padded' => true,
				'framed' => true,
				'content' => [ $fieldset , $thumbs ],
			] )
		);

		// Finally output all of the above
		$this->getOutput()->addModules( [ 'ext.thumbro' ] );
	}

	/**
	 * TODO
	 */
	protected function showForm(): void {
		$form = HTMLForm::factory( 'ooui', $this->getFormFields(), $this->getContext() );
		$form->setWrapperLegend( $this->msg( 'thumbro-form-legend' )->text() );
		$form->setSubmitText( $this->msg( 'thumbro-form-submit' )->text() );
		$form->setSubmitCallback( [ __CLASS__, 'processForm' ] );
		$form->setMethod( 'get' );

		// Looks like HTMLForm does not actually show the form if submission
		// was correct. So we have to show it again.
		// See HTMLForm::show()
		$result = $form->show();
		if ( $result === true || ( $result instanceof Status && $result->isGood() ) ) {
			$form->displayForm( $result );
			$this->showThumbnails();
		}
	}

	/**
	 * [[Special:ThumbroTest]] form structure for HTMLForm
	 */
	protected function getFormFields(): array {
		$fields = [
			'File' => [
				'name'          => 'file',
				'class'         => HTMLTextField::class,
				'required'      => true,
				'size' 			=> '80',
				'label-message' => 'thumbro-form-file',
				'validation-callback' => [ __CLASS__, 'validateFileInput' ],
			],
			'Width' => [
				'name'          => 'width',
				'class'         => HTMLIntField::class,
				'default'       => '640',
				'size'          => '5',
				'required'      => true,
				'label-message' => 'thumbro-form-width',
				'validation-callback' => [ __CLASS__, 'validateWidth' ],
			],
			/*
			'SharpenRadius' => [
				'name'          => 'sharpen',
				'class'         => HTMLFloatField:class,
				'default'		=> '0.0',
				'size'			=> '5',
				'label-message' => 'thumbro-form-sharpen-radius',
				'validation-callback' => [ __CLASS__, 'validateSharpen' ],
			],
			'Bilinear' => [
				'name' 			=> 'bilinear',
				'class' 		=> HTMLCheckField::class,
				'label-message'	=> 'thumbro-form-bilinear',
			],
			*/
		];

		/**
		 * Match ImageMagick by default
		 */
		/*
		if ( preg_match( '/^[0-9.]+x([0-9.]+)$/', $this->getConfig()->get( 'SharpenParameter' ), $m ) ) {
			$fields['SharpenRadius']['default'] = $m[1];
		}
		*/
		return $fields;
	}

	/**
	 * @return bool|string
	 */
	public static function validateFileInput( ?string $input, array $alldata ) {
		if ( !trim( $input ) ) {
			// Don't show an error if the file is not yet specified,
			// because it is annoying
			return true;
		}

		$title = Title::newFromText( $input, NS_FILE );
		if ( $title === null ) {
			return wfMessage( 'thumbro-invalid-file' )->text();
		}
		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $title );
		if ( !$file || !$file->exists() ) {
			return wfMessage( 'thumbro-invalid-file' )->text();
		}

		// Looks sensible enough.
		return true;
	}

	/**
	 * @param int $input
	 * @param array $allData
	 * @return bool|string
	 */
	public static function validateWidth( $input, $allData ) {
		if ( self::validateFileInput( $allData['File'], $allData ) !== true
			|| !trim( $allData['File'] )
		) {
			// Invalid file, error will already be shown at file field
			return true;
		}
		$title = Title::newFromText( $allData['File'], NS_FILE );
		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $title );
		if ( $input <= 0 || $input >= $file->getWidth() ) {
			return wfMessage( 'thumbro-invalid-width' )->text();
		}
		return true;
	}

	/**
	 * @param int $input
	 * @param array $allData
	 * @return bool|string
	 */
	/*
	public static function validateSharpen( $input, $allData ) {
		if ( $input >= 5.0 || $input < 0.0 ) {
			return wfMessage( 'thumbro-invalid-sharpen' )->text();
		}
		return true;
	}
	*/

	/**
	 * Process data submitted by the form.
	 */
	public static function processForm( array $data ): Status {
		return Status::newGood();
	}

	/**
	 *
	 */
	protected function streamThumbnail() {
		$request = $this->getRequest();

		// Validate title and file existance
		$title = Title::newFromText( $request->getText( 'thumb' ), NS_FILE );
		if ( $title === null ) {
			$this->streamError( 404, "Thumbro: invalid title" );
			return;
		}
		$services = MediaWikiServices::getInstance();
		$file = $services->getRepoGroup()->findFile( $title );
		if ( !$file || !$file->exists() ) {
			$this->streamError( 404, "Thumbro: file not found" );
			return;
		}

		// Validate param string
		$handler = $file->getHandler();
		$params = [ 'width' => $request->getInt( 'width' ) ];
		if ( !$handler->normaliseParams( $file, $params ) ) {
			$this->streamError( 500, "Thumbro: invalid parameters" );
			return;
		}

		$config = $this->getConfig();
		$thumbroTestExpiry = $config->get( 'ThumbroTestExpiry' );
		$thumbroOptions = $config->get( 'ThumbroOptions' );
		$thumbroLibraries = $config->get( 'ThumbroLibraries' );

		// Get the thumbnail
		// No remote scaler, need to do it ourselves.
		// Emulate the BitmapHandlerTransform hook
		$tmpFile = ShellCommand::makeTemp( $file->getExtension() );
		$tmpFile->bind( $this );
		$dstPath = $tmpFile->getPath();
		$dstUrl = '';
		wfDebug( __METHOD__ . ": Creating vips thumbnail at $dstPath" );

		$mimeType = $file->getMimeType();
		$scalerParams = [
			// The size to which the image will be resized
			'physicalWidth' => $params['physicalWidth'],
			'physicalHeight' => $params['physicalHeight'],
			'physicalDimensions' => "{$params['physicalWidth']}x{$params['physicalHeight']}",
			// The size of the image on the page
			'clientWidth' => $params['width'],
			'clientHeight' => $params['height'],
			// Comment as will be added to the EXIF of the thumbnail
			'comment' => isset( $params['descriptionUrl'] ) ?
				"File source: {$params['descriptionUrl']}" : '',
			// Properties of the original image
			'srcWidth' => $file->getWidth(),
			'srcHeight' => $file->getHeight(),
			'mimeType' => $mimeType,
			'srcPath' => $file->getLocalRefPath(),
			'dstPath' => $dstPath,
			'dstUrl' => $dstUrl,
			'interlace' => $request->getBool( 'interlace' ),
		];

		$options = [];
		if ( isset( $thumbroOptions[$mimeType] ) && isset( $thumbroOptions[$mimeType]['outputOptions'] ) ) {
			$options['outputOptions'] = $thumbroOptions[$mimeType]['outputOptions'];
		}

		$library = $thumbroOptions[$mimeType]['library'] ?? 'libvips';
		$options['command'] = $thumbroLibraries[$library]['command'];

		/*
		if ( $request->getBool( 'bilinear' ) ) {
			$options['bilinear'] = true;
			wfDebug( __METHOD__ . ": using bilinear scaling" );
		}
		if ( $request->getRawVal( 'sharpen' ) !== null && $request->getFloat( 'sharpen' ) < 5 ) {
			// Limit sharpen sigma to 5, otherwise we have to write huge convolution matrices
			$sharpen = $request->getFloat( 'sharpen' );
			$options['sharpen'] = [ 'sigma' => $sharpen ];
			wfDebug( __METHOD__ . ": sharpening with radius {$sharpen}" );
		}
		*/

		// Call the hook
		/** @var MediaTransformOutput $mto */
		Libvips::doTransform( $handler, $file, $scalerParams, $options, $mto );
		if ( $mto && !$mto->isError() ) {
			wfDebug( __METHOD__ . ": streaming thumbnail..." );
			$this->getOutput()->disable();
			StreamFile::stream( $dstPath, [
				"Cache-Control: public, max-age=$thumbroTestExpiry, s-maxage=$thumbroTestExpiry",
				'Expires: ' . gmdate( 'r ', time() + $thumbroTestExpiry )
			] );
		} else {
			'@phan-var MediaTransformError $mto';
			$this->streamError( 500, $mto->getHtmlMsg() );
		}

		// Cleanup the temporary file
		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		@unlink( $dstPath );
	}

	/**
	 * Generates a blank page with given HTTP error code
	 */
	protected function streamError( int $httpCode, string $error = '' ): void {
		$output = $this->getOutput();
		$output->setStatusCode( $httpCode );
		$output->setArticleBodyOnly( true );
		$output->addHTML( $error );
	}
}
