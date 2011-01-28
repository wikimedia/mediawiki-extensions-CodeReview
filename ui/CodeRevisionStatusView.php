<?php

class CodeRevisionStatusView extends CodeRevisionListView {
	function __construct( $repoName, $status ) {
		parent::__construct( $repoName );
		$this->status = $status;
		$this->appliedFilter = wfMsg( 'code-revfilter-cr_status', $status );
	}

	function getPager() {
		return new SvnRevStatusTablePager( $this, $this->status );
	}
}

class SvnRevStatusTablePager extends SvnRevTablePager {
	function __construct( $view, $status ) {
		parent::__construct( $view );
		$this->status = $status;
	}

	function getQueryInfo() {
		$info = parent::getQueryInfo();
		$info['conds']['cr_status'] = $this->status; // FIXME: normalize input?
		return $info;
	}

	function getTitle() {
		$repo = $this->repo->getName();
		return SpecialPage::getTitleFor( 'Code', "$repo/status/$this->status" );
	}
}
