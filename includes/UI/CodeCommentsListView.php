<?php

namespace MediaWiki\Extension\CodeReview\UI;

/**
 * Special:Code/MediaWiki/comments
 */
class CodeCommentsListView extends CodeRevisionListView {
	/** @inheritDoc */
	public function getPager() {
		return new CodeCommentsTablePager( $this );
	}
}
