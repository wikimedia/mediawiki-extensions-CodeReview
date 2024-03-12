<?php

namespace MediaWiki\Extension\CodeReview\UI;

use Wikimedia\Rdbms\IDatabase;

/**
 * Special:Code/MediaWiki
 */
class CodeStatusChangeListView extends CodeRevisionListView {
	/** @inheritDoc */
	public function getPager() {
		return new CodeStatusChangeTablePager( $this );
	}

	/**
	 * @param IDatabase $dbr
	 * @return int
	 */
	protected function getRevCount( $dbr ) {
		return -1;
	}
}
