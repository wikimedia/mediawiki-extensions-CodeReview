<?php

namespace MediaWiki\Extension\CodeReview\UI;

class CodeStatusChangeAuthorListView extends CodeStatusChangeListView {

	public function __construct( $repo, $author ) {
		parent::__construct( $repo );

		$this->mAuthor = $author;
	}
}
