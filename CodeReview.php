<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'CodeReview' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['CodeReview'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['CodeReviewAliases'] = __DIR__ . '/CodeReview.alias.php';
	wfWarn(
		'Deprecated PHP entry point used for CodeReview extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the CodeReview extension requires MediaWiki 1.25+' );
}
