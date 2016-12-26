<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'AkismetKlik' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['AkismetKlik'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for AkismetKlik extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the TranslateSvg extension requires MediaWiki 1.28+' );
}
