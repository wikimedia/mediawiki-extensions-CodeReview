<?php

namespace MediaWiki\Extension\CodeReview\UI;

use MediaWiki\Extension\CodeReview\Backend\CodeRepository;

class CodeRevisionTagView extends CodeRevisionListView {
	/**
	 * @param CodeRepository|string $repo
	 * @param string $tag
	 */
	public function __construct( $repo, $tag ) {
		$this->mTag = $tag;

		if ( $this->mTag ) {
			$this->filters[] = wfMessage( 'code-revfilter-ct_tag', $this->mTag )->text();
		}
		parent::__construct( $repo );
	}

	/**
	 * @return SvnRevTagTablePager
	 */
	public function getPager() {
		return new SvnRevTagTablePager( $this, $this->mTag );
	}
}
