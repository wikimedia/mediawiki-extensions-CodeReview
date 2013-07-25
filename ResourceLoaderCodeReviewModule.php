<?php
/**
 * Custom ResourceLoader module that loads a CodeReview.css per-wiki.
 *
 * Based on the SyntaxHighlight GeSHi extension's similar module by Roan Kattouw.
 *
 * @file
 */
class ResourceLoaderCodeReviewModule extends ResourceLoaderWikiModule {
	/**
	 * @param $context ResourceLoaderContext
	 * @return array
	 */
	protected function getPages( ResourceLoaderContext $context ) {
		return array(
			'MediaWiki:CodeReview.css' => array( 'type' => 'style' ),
		);
	}
}