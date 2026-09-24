/**
 * @license Copyright (c) 2003-2015, CKSource - Frederico Knabben. All rights reserved.
 * For licensing, see LICENSE.md or http://ckeditor.com/license
 */

CKEDITOR.editorConfig = function( config ) {
	// Define changes to default configuration here. For example:
	// config.language = 'fr';
	// config.uiColor = '#AADC6E';

	config.language = 'cs';
	config.uiColor = '#ffffff';
	config.forcePasteAsPlainText = true;
	config.toolbar = 'Full';

	config.entities  = true;
	config.basicEntities = false;
	config.entities_greek = false;
	config.entities_latin = false;
	config.allowedContent = true;

	config.toolbar_Full = [
		[ 'Format' ],
		[ 'Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript' ],
		[ 'NumberedList', 'BulletedList', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock' ],
		[ 'Link', 'Unlink' ],
		[ 'Image', 'Table', 'Iframe' ],
		[ 'Undo', 'Redo' ],
		[ 'Source' ]
	];

	config.toolbar_Basic = [
		[ 'Bold', 'Italic', 'NumberedList', 'BulletedList', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', '-', 'Link', 'Unlink', 'Anchor' ],
	];

	config.toolbar_Small = [
		[ 'Bold' ],
	];

	config.filebrowserBrowseUrl = '/plugins/kcfinder/browse.php?opener=ckeditor&type=files';
   config.filebrowserImageBrowseUrl = '/plugins/kcfinder/browse.php?opener=ckeditor&type=images';
   config.filebrowserFlashBrowseUrl = '/plugins/kcfinder/browse.php?opener=ckeditor&type=flash';
   config.filebrowserUploadUrl = '/plugins/kcfinder/upload.php?opener=ckeditor&type=files';
   config.filebrowserImageUploadUrl = '/plugins/kcfinder/upload.php?opener=ckeditor&type=images';
   config.filebrowserFlashUploadUrl = '/plugins/kcfinder/upload.php?opener=ckeditor&type=flash';
};
