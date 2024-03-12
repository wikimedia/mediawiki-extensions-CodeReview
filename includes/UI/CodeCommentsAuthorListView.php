<?php

namespace MediaWiki\Extension\CodeReview\UI;

use MediaWiki\Extension\CodeReview\Backend\CodeRepository;

class CodeCommentsAuthorListView extends CodeCommentsListView {
	/**
	 * @param CodeRepository|string $repo
	 * @param string $author
	 */
	public function __construct( $repo, $author ) {
		parent::__construct( $repo );
		$this->mAuthor = $author;
	}
}
