<?php

// Special:Code/MediaWiki
class CodeRevisionListView extends CodeView {
	/**
	 * @var CodeRepository
	 */
	public $mRepo;
	public $mPath, $batchForm;

	/**
	 * @var
	 */
	protected $filters = array();

	/**
	 * @param $repo CodeRepository|String
	 */
	function __construct( $repo ) {
		parent::__construct( $repo );
		global $wgRequest;

		$path = $wgRequest->getVal( 'path' );

		if ( $path != '' ) {
			$this->mPath = self::pathsToArray( $path );
		} else {
			$this->mPath = array();
		}

		$this->mAuthor = $wgRequest->getText( 'author' );
		$this->mStatus = $wgRequest->getText( 'status' );

		if ( $this->mAuthor ) {
			$this->filters[] = wfMessage( 'code-revfilter-cr_author', $this->mAuthor )->text();
		}
		if ( $this->mStatus ) {
			$this->filters[] = wfMessage( 'code-revfilter-cr_status', $this->mStatus )->text();
		}

		if ( count( $this->filters ) ) {
			global $wgLang;
			$this->mAppliedFilter = $wgLang->listToText( $this->filters );
		} else {
			$this->mAppliedFilter = null;
		}
	}

	/**
	 * @param $path string
	 * @return array
	 */
	public static function pathsToArray( $path ) {
		return array_map( array( 'self', 'preparePaths' ), explode( '|', $path ) );
	}

	/**
	 * @param $path string
	 * @return string
	 */
	public static function preparePaths( $path ) {
		$path = trim( $path );
		$path = rtrim( $path, '/' );
		$path = htmlspecialchars( $path );
		if ( strlen( $path ) && $path[0] !== '/' ) {
			$path = "/{$path}"; // make sure this is a valid path
		}
		return $path;
	}

	/**
	 * @return string
	 */
	public function getPathsAsString() {
		return implode( '|', $this->mPath );
	}

	function execute() {
		global $wgOut, $wgUser, $wgRequest;
		if ( !$this->mRepo ) {
			$view = new CodeRepoListView();
			$view->execute();
			return;
		}

		// Check for batch change requests.
		$editToken = $wgRequest->getVal( 'wpBatchChangeEditToken' );
		$revisions = $wgRequest->getArray( 'wpRevisionSelected' );
		if ( $wgRequest->wasPosted() && count( $revisions )
			&& $wgUser->matchEditToken( $editToken )
		) {
			$this->doBatchChange();
			return;
		}

		// Get the total count across all pages
		$dbr = wfGetDB( DB_SLAVE );
		$revCount = $this->getRevCount( $dbr );

		$pager = $this->getPager();
		$pathForm = $this->showForm( $pager );

		// Build batch change interface as needed
		$this->batchForm = $wgUser->isAllowed( 'codereview-set-status' ) ||
			$wgUser->isAllowed( 'codereview-add-tag' );

		$navBar = $pager->getNavigationBar();

		$wgOut->addHTML( $pathForm );

		$wgOut->addHTML(
			$navBar .
			'<table><tr><td>' . $pager->getLimitForm() . '</td>'
		);
		if ( $revCount !== -1 ) {
			$wgOut->addHTML(
				'<td>&#160;<strong>' .
					wfMessage( 'code-rev-total' )->numParams( $revCount )->escaped() .
					'</strong></td>'
			);
		}

		$wgOut->addHTML(
			'</tr></table>' .
			Xml::openElement( 'form',
				array( 'action' => $pager->getTitle()->getLocalURL(), 'method' => 'post' )
			) .
			$pager->getBody() .
			// $pager->getLimitDropdown() .
			$navBar
		);
		if ( $this->batchForm ) {
			$wgOut->addHTML(
				$this->buildBatchInterface( $pager )
			);
		}

		$wgOut->addHTML( Xml::closeElement( 'form' ) . $pathForm );
	}

	function doBatchChange() {
		global $wgRequest, $wgUser, $wgOut;

		$revisions = $wgRequest->getArray( 'wpRevisionSelected' );
		$removeTags = $wgRequest->getVal( 'wpRemoveTag' );
		$addTags = $wgRequest->getVal( 'wpTag' );
		$status = $wgRequest->getVal( 'wpStatus' );

		// Grab data from the DB
		$dbr = wfGetDB( DB_SLAVE );
		$revObjects = array();
		$res = $dbr->select(
			'code_rev', '*',
			array( 'cr_id' => $revisions, 'cr_repo_id' => $this->mRepo->getId() ),
			__METHOD__
		);
		foreach ( $res as $row ) {
			$revObjects[] = CodeRevision::newFromRow( $this->mRepo, $row );
		}

		if ( $wgUser->isAllowed( 'codereview-add-tag' ) &&
				$addTags || $removeTags ) {
			$addTags = array_map( 'trim', explode( ",", $addTags ) );
			$removeTags = array_map( 'trim', explode( ",", $removeTags ) );

			foreach ( $revObjects as $rev ) {
				$rev->changeTags( $addTags, $removeTags, $wgUser );
			}
		}

		if ( $wgUser->isAllowed( 'codereview-set-status' ) &&
				$revObjects && CodeRevision::isValidStatus( $status ) ) {
			foreach ( $revObjects as $rev ) {
				$rev->setStatus( $status, $wgUser );
			}
		}

		// Automatically refresh
		// This way of getting GET parameters is horrible, but effective.
		$fields = $wgRequest->getValues();
		foreach ( array_keys( $fields ) as $key ) {
			if ( substr( $key, 0, 2 ) == 'wp' || $key == 'title' ) {
				unset( $fields[$key] );
			}
		}

		$wgOut->redirect( $this->getPager()->getTitle()->getFullURL( $fields ) );
	}

	/**
	 * @param SvnRevTablePager $pager
	 * @return string
	 */
	protected function buildBatchInterface( $pager ) {
		global $wgUser;

		$changeFields = array();

		if ( $wgUser->isAllowed( 'codereview-set-status' ) ) {
			$changeFields['code-batch-status'] =
				Xml::tags( 'select', array( 'name' => 'wpStatus' ),
					Xml::tags( 'option',
						array( 'value' => '', 'selected' => 'selected' ), ' '
					) .
					CodeRevisionView::buildStatusList( null, $this )
				);
		}

		if ( $wgUser->isAllowed( 'codereview-add-tag' ) ) {
			$changeFields['code-batch-tags'] = CodeRevisionView::addTagForm( '', '' );
		}

		if ( !count( $changeFields ) ) {
			return ''; // nothing to do here
		}

		$changeInterface = Xml::fieldset( $pager->msg( 'codereview-batch-title' )->text(),
				Xml::buildForm( $changeFields, 'codereview-batch-submit' ) );

		$changeInterface .= $pager->getHiddenFields();
		$changeInterface .= Html::hidden( 'wpBatchChangeEditToken', $wgUser->getEditToken() );

		return $changeInterface;
	}

	/**
	 * @param $pager SvnTablePager
	 *
	 * @return string
	 */
	function showForm( $pager ) {
		global $wgScript;

		$states = CodeRevision::getPossibleStates();
		$name = $this->mRepo->getName();

		$title = SpecialPage::getTitleFor( 'Code', $name );
		$options = array( Xml::option( '', $title->getPrefixedText(), $this->mStatus == '' ) );

		// Give grep a chance to find the usages:
		// code-status-new, code-status-fixme, code-status-reverted, code-status-resolved,
		// code-status-ok, code-status-deferred, code-status-old
		foreach ( $states as $state ) {
			$title = SpecialPage::getTitleFor( 'Code', $name . "/status/$state" );
			$options[] = Xml::option(
				$pager->msg( "code-status-$state" )->text(),
				$title->getPrefixedText(),
				$this->mStatus == $state
			);
		}

		$ret = '<fieldset><legend>' .
				wfMessage( 'code-pathsearch-legend' )->escaped() . '</legend>' .
				'<table width="100%"><tr><td>' .
				Xml::openElement( 'form', array( 'action' => $wgScript, 'method' => 'get' ) ) .
				Xml::inputLabel( wfMessage( "code-pathsearch-path" )->text(), 'path', 'path', 55,
					$this->getPathsAsString(), array( 'dir' => 'ltr' ) ) . '&#160;' .
				Xml::label( wfMessage( 'code-pathsearch-filter' )->text(), 'code-status-filter' ) .
			'&#160;' .
				Xml::openElement( 'select', array( 'id' => 'code-status-filter', 'name' => 'title' ) ) .
				"\n" .
				implode( "\n", $options ) .
				"\n" .
				Xml::closeElement( 'select' ) .
				'&#160;' . Xml::submitButton( wfMessage( 'allpagessubmit' )->text() ) .
				$pager->getHiddenFields( array( 'path', 'title' ) ) .
				Xml::closeElement( 'form' ) .
				'</td></tr></table></fieldset>';

		return $ret;
	}

	/**
	 * @return SvnRevTablePager
	 */
	function getPager() {
		return new SvnRevTablePager( $this );
	}

	/**
	 * Get total number of revisions for this revision view
	 *
	 * @param DatabaseBase $dbr
	 * @return int Number of revisions
	 */
	function getRevCount( $dbr ) {
		$query = $this->getPager()->getCountQuery();

		$result = $dbr->selectRow( $query['tables'],
			$query['fields'],
			$query['conds'],
			__METHOD__,
			$query['options'],
			$query['join_conds']
		);
		if ( $result ) {
			return intval( $result->rev_count );
		} else {
			return 0;
		}
	}

	function getRepo() {
		return $this->mRepo;
	}
}

// Pager for CodeRevisionListView
class SvnRevTablePager extends SvnTablePager {
	function getSVNPath() {
		return $this->mView->mPath;
	}

	function getDefaultSort() {
		return count( $this->mView->mPath ) ? 'cp_rev_id' : 'cr_id';
	}

	function getQueryInfo() {
		$defaultSort = $this->getDefaultSort();
		// Path-based query...
		if ( $defaultSort === 'cp_rev_id' ) {
			$query = array(
				'tables' => array( 'code_paths', 'code_rev', 'code_comment' ),
				'fields' => $this->getSelectFields(),
				'conds' => array(
					'cp_repo_id' => $this->mRepo->getId(),
					'cp_path' => $this->getSVNPath(),
				),
				'options' => array(
					'GROUP BY' => $defaultSort,
					'USE INDEX' => array( 'code_path' => 'cp_repo_id' )
				),
				'join_conds' => array(
					'code_rev' => array( 'INNER JOIN',
						'cr_repo_id = cp_repo_id AND cr_id = cp_rev_id' ),
					'code_comment' => array( 'LEFT JOIN',
						'cc_repo_id = cp_repo_id AND cc_rev_id = cp_rev_id' ),
				)
			);
		// No path; entire repo...
		} else {
			$query = array(
				'tables' => array( 'code_rev', 'code_comment' ),
				'fields' => $this->getSelectFields(),
				'conds' => array( 'cr_repo_id' => $this->mRepo->getId() ),
				'options' => array( 'GROUP BY' => $defaultSort ),
				'join_conds' => array(
					'code_comment' => array( 'LEFT JOIN',
						'cc_repo_id = cr_repo_id AND cc_rev_id = cr_id' ),
				)
			);
		}

		if ( $this->mView->mAuthor ) {
			$query['conds']['cr_author'] = $this->mView->mAuthor;
		}

		if ( $this->mView->mStatus ) {
			$query['conds']['cr_status'] = $this->mView->mStatus;
		}
		return $query;
	}

	function getCountQuery() {
		$query = $this->getQueryInfo();

		$query['fields'] = array( 'COUNT( DISTINCT cr_id ) AS rev_count' );
		unset( $query['options']['GROUP BY'] );
		return $query;
	}

	function getSelectFields() {
		return array_unique(
			array( $this->getDefaultSort(),
				'cr_id',
				'cr_repo_id',
				'cr_status',
				'COUNT(DISTINCT cc_id) AS comments',
				'cr_path',
				'cr_message',
				'cr_author',
				'cr_timestamp'
			) );
	}

	function getFieldNames() {
		$fields = array(
			'cr_id' => $this->msg( 'code-field-id' )->text(),
			'cr_status' => $this->msg( 'code-field-status' )->text(),
			'comments' => $this->msg( 'code-field-comments' )->text(),
			'cr_path' => $this->msg( 'code-field-path' )->text(),
			'cr_message' => $this->msg( 'code-field-message' )->text(),
			'cr_author' => $this->msg( 'code-field-author' )->text(),
			'cr_timestamp' => $this->msg( 'code-field-timestamp' )->text()
		);
		# Only show checkboxen as needed
		if ( $this->mView->batchForm ) {
			$fields = array( 'selectforchange' => $this->msg( 'code-field-select' )->text() ) + $fields;
		}
		return $fields;
	}

	function formatValue( $name, $value ) {
		// unused
	}

	function formatRevValue( $name, $value, $row ) {
		$pathQuery = count( $this->mView->mPath )
			? array( 'path' => $this->mView->getPathsAsString() ) : array();

		$linkRenderer = \MediaWiki\MediaWikiServices::getInstance()->getLinkRenderer();
		switch ( $name ) {
		case 'selectforchange':
			$sort = $this->getDefaultSort();
			return Xml::check( "wpRevisionSelected[]", false, array( 'value' => $row->$sort ) );
		case 'cr_id':
			return $linkRenderer->makeLink(
				SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() . '/' . $value ),
				$value,
				array(),
				array()
			);
		case 'cr_status':
			$options = $pathQuery;
			if ( $this->mView->mAuthor ) {
				$options['author'] = $this->mView->mAuthor;
			}
			$options['status'] = $value;
			return $linkRenderer->makeLink(
				SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() ),
				$this->mView->statusDesc( $value ),
				array(),
				$options
			);
		case 'cr_author':
			$options = $pathQuery;
			if ( $this->mView->mStatus ) {
				$options['status'] = $this->mView->mStatus;
			}
			$options['author'] = $value;
			return $linkRenderer->makeLink(
				SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() ),
				$value,
				array(),
				$options
			);
		case 'cr_message':
			return $this->mView->messageFragment( $value );
		case 'cr_timestamp':
			return $this->getLanguage()->timeanddate( $value, true );
		case 'comments':
			if ( $value ) {
				$special = SpecialPage::getTitleFor(
					'Code',
					$this->mRepo->getName() . '/' . $row->{$this->getDefaultSort()},
					'code-comments'
				);
				return $linkRenderer->makeLink(
					$special, $this->getLanguage()->formatNum( $value ) );
			} else {
				return '-';
			}
		case 'cr_path':
			$title = $this->mRepo->getName();

			$options = array( 'path' => (string)$value );
			if ( $this->mView->mAuthor ) {
				$options['author'] = $this->mView->mAuthor;
			}
			if ( $this->mView->mStatus ) {
				$options['status'] = $this->mView->mStatus;
			}

			return Xml::openElement( 'div', array( 'title' => (string)$value, 'dir' => 'ltr' ) ) .
					$linkRenderer->makeLink(
						SpecialPage::getTitleFor( 'Code', $title ),
						$this->getLanguage()->truncate( (string)$value, 50 ),
						array( 'title' => (string)$value ),
						$options
					) . '</div>';
		}

		return '';
	}

	/**
	 * @return Title
	 */
	function getTitle() {
		return SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() );
	}
}
