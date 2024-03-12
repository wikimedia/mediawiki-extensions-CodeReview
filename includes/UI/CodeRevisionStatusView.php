<?php

namespace MediaWiki\Extension\CodeReview\UI;

use MediaWiki\Extension\CodeReview\Backend\CodeRepository;

class CodeRevisionStatusView extends CodeRevisionListView {
	/**
	 * @param CodeRepository|string $repo
	 * @param string $status
	 */
	public function __construct( $repo, $status ) {
		parent::__construct( $repo );
		$this->mStatus = $status;
	}

	/**
	 * @return SvnRevStatusTablePager
	 */
	public function getPager() {
		return new SvnRevStatusTablePager( $this, $this->mStatus );
	}
}
