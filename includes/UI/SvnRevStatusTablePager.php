<?php

namespace MediaWiki\Extension\CodeReview\UI;

use SpecialPage;

class SvnRevStatusTablePager extends SvnRevTablePager {

	private string $mStatus;

	/**
	 * @param CodeView $view
	 * @param string $status
	 */
	public function __construct( $view, $status ) {
		parent::__construct( $view );
		$this->mStatus = $status;
	}

	/** @inheritDoc */
	public function getQueryInfo() {
		$info = parent::getQueryInfo();
		// FIXME: normalize input?
		$info['conds']['cr_status'] = $this->mStatus;
		return $info;
	}

	/** @inheritDoc */
	public function getTitle() {
		$repo = $this->mRepo->getName();
		return SpecialPage::getTitleFor( 'Code', "$repo/status/$this->mStatus" );
	}
}
