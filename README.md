<div align="center">
👍🖼️😎
<h1>Thumbro</h1>
<blockquote><p><i>Can we get Thumbor for the wiki?<br>
We have Thumbor at home.<br>
Thumbor at home:
</i></p></blockquote>
</div>

Thumbro is an in-development MediaWiki extension used to improve and expand thumbnailing in MediaWiki. It is unstable for production use. It is forked from [Extension:VipsScaler](https://www.mediawiki.org/wiki/Extension:VipsScaler). Currently, it only supports [libvips](https://www.libvips.org).

## Features
- Use libvips to render thumbnails instead of ImageMagick and GD
- Allow custom output options for libvips
- Render WebP thumbnails by default for jpeg, png, webp

## Installation
1. Install libvips
2. [Download](https://github.com/StarCitizenTools/mediawiki-extensions-Thumbro/archive/main.zip) and place the file(s) in a directory called `Thumbro` in your `extensions/` folder.
3. Add the following code at the bottom of your LocalSettings.php and **after all other extensions**:
```php
wfLoadExtension( 'Thumbro' );
```
3. **✔️Done** - Navigate to Special:Version on your wiki to verify that the extension is successfully installed.

## Configurations
`$wgThumbroLibraries` is used to define the libraries used in Thumbro.

Key | Description 
:--- | :--- 
`command` | Executable used by Thumbro to do image transformation

Default:
```php
$wgThumbroLibraries => [
	"value" => [
		"libvips" => [
			"command": "/usr/bin/vipsthumbnail"
		]
	]
];
```
`$wgThumbroOptions` is used to define the parameters of the thumbnail generation.

Key | Description 
:--- | :--- 
`enabled` | Enable or disable Thumbro for the selected file type
`library` | Corresponds to `$wgThumbroLibraries`, currently only `libvips` is supported
`outputOptions` | Corresponds to the output options in [`VipsForeignSave`](https://www.libvips.org/API/current/VipsForeignSave.html)

Default:
```php
$wgThumbroOptions = [
	"value" => [
		"image/jpeg" => [
			"enabled" => true,
			"library" => "libvips",
			"outputOptions" => [
				"strip": "true",
				"Q": "80"
			]
		],
		"image/png": => [
			"enabled" => true,
			"library" => "libvips",
			"outputOptions" => [
				"strip": "true",
				"filter": "VIPS_FOREIGN_PNG_FILTER_ALL"
			]
		]
	]
];
```
### Special:ThumbroTest
Name | Description | Values | Default
:--- | :--- | :--- | :---
`$wgThumbroExposeTestPage` | Enable Special:ThumbroTest on the wiki | `true` - enable; `false` - disable | `false`
`$wgThumbroTestExpiry` | Control the cache age for the test image streamed to Special:ThumbroTest | integer | `3600`

## Requirements
* [MediaWiki](https://www.mediawiki.org) 1.39.4 or later
* [libvips](https://www.libvips.org)
