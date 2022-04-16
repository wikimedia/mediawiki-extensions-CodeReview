<?php

namespace MediaWiki\Extension\CodeReview\UI;

use SpecialPage;

class SvnRevStatusTablePager extends SvnRevTablePager {
	public function __construct( $view, $status ) {
		parent::__construct( $view );
		$this->mStatus = $status;
	}

	public function getQueryInfo() {
		$info = parent::getQueryInfo();
		// FIXME: normalize input?
		$info['conds']['cr_status'] = $this->mStatus;
		return $info;
	}

	public function getTitle() {
		$repo = $this->mRepo->getName();
		return SpecialPage::getTitleFor( 'Code', "$repo/status/$this->mStatus" );
	}
}
