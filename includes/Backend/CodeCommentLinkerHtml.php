<?php

namespace MediaWiki\Extension\CodeReview\Backend;

use Linker;
use MediaWiki\MediaWikiServices;
use Title;

class CodeCommentLinkerHtml extends CodeCommentLinker {

	/**
	 * @param string $url
	 * @param string $text
	 * @return string
	 */
	public function makeExternalLink( $url, $text ) {
		return Linker::makeExternalLink( $url, $text );
	}

	/**
	 * @param Title $title
	 * @param string $text
	 * @return string
	 */
	public function makeInternalLink( $title, $text ) {
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		return $linkRenderer->makeLink( $title, $text );
	}
}
