<?php

// Special:Code/MediaWiki
class CodeRevisionListView extends CodeView {
	/**
	 * @var CodeRepository
	 */
	public $mRepo;
	public $mPath, $batchForm;

	/**
	 * @param $repo CodeRepository|String
	 */
	function __construct( $repo ) {
		parent::__construct( $repo );
		global $wgRequest;

		$path = $wgRequest->getVal( 'path' );

		if ( $path != '' ) {
			$this->mPath = array_map( array( $this, 'preparePaths' ), explode( '|', $path ) );
		} else {
			$this->mPath = array();
		}

		$this->mAuthor = $wgRequest->getText( 'author' );
		$this->mStatus = $wgRequest->getText( 'status' );
		$this->mAppliedFilter = null;
	}

	/**
	 * @param string $path
	 * @return string
	 */
	function preparePaths( $path ) {
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
		global $wgOut, $wgUser, $wgRequest, $wgLang;
		if ( !$this->mRepo ) {
			$view = new CodeRepoListView();
			$view->execute();
			return;
		}

		// Check for batch change requests.
		$editToken = $wgRequest->getVal( 'wpBatchChangeEditToken' );
		$revisions = $wgRequest->getArray( 'wpRevisionSelected' );
		if ( $wgRequest->wasPosted() && count( $revisions ) && $wgUser->matchEditToken( $editToken ) ) {
			$this->doBatchChange();
			return;
		}

		// Get the total count across all pages
		$dbr = wfGetDB( DB_SLAVE );
		$revCount = $this->getRevCount( $dbr );

		$pager = $this->getPager();
		$pathForm = $this->showForm( $pager->getHiddenFields( array( 'path' ) ) );

		// Build batch change interface as needed
		$this->batchForm = $wgUser->isAllowed( 'codereview-set-status' ) ||
			$wgUser->isAllowed( 'codereview-add-tag' );

		$navBar = $pager->getNavigationBar();

		$wgOut->addHTML( $pathForm );

		$wgOut->addHTML(
			$navBar .
			'<table><tr><td>' . $pager->getLimitForm() . '</td>' .
			'<td>&#160;<strong>' . wfMsgHtml( 'code-rev-total', $wgLang->formatNum( $revCount ) ) . '</strong></td>' .
			'</tr></table>' .
			Xml::openElement( 'form',
				array( 'action' => $pager->getTitle()->getLocalURL(), 'method' => 'post' )
			) .
			$pager->getBody() .
			//$pager->getLimitDropdown() .
			$navBar .
			( $this->batchForm ?
					$this->buildBatchInterface( $pager )
					: "" ) .
			Xml::closeElement( 'form' )
		);

		$wgOut->addHTML( $pathForm );
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
		$res = $dbr->select( 'code_rev', '*', array( 'cr_id' => $revisions, 'cr_repo_id' => $this->mRepo->getId() ), __METHOD__ );
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

		$changeInterface = Xml::fieldset( wfMsg( 'codereview-batch-title' ),
				Xml::buildForm( $changeFields, 'codereview-batch-submit' ) );

		$changeInterface .= $pager->getHiddenFields();
		$changeInterface .= Html::hidden( 'wpBatchChangeEditToken', $wgUser->editToken() );

		return $changeInterface;
	}

	/**
	 * @param $hidden string
	 *
	 * @return string
	 */
	function showForm( $hidden = '' ) {
		global $wgScript;

		$ret = Xml::openElement( 'form', array( 'action' => $wgScript, 'method' => 'get' ) ) .
			"<fieldset><legend>" . wfMsgHtml( 'code-pathsearch-legend' ) . "</legend>" .
				'<table width="100%"><tr><td>' .
				Xml::inputlabel( wfMsg( "code-pathsearch-path" ), 'path', 'path', 55, $this->getPathsAsString() ) .
				'&#160;' . Xml::submitButton( wfMsg( 'allpagessubmit' ) ) .
				'</td>';

		if ( strlen( $this->mAppliedFilter ) ) {
			$ret .= '<td>' .
				Xml::label( wfMsg( 'code-pathsearch-filter' ), 'revFilter' ) . '&#160;<strong>' .
				Xml::span( $this->mAppliedFilter, '' ) . '</strong>&#160;' .
				Xml::submitButton( wfMsg( 'code-revfilter-clear' ) ) .
				'</td>';
		}

		$ret .= $hidden;

		$ret .= "</tr></table></fieldset></form>" ;

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
	 * @var $dbr DatabaseBase
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
				'options' => array( 'GROUP BY' => $defaultSort, 'USE INDEX' => array( 'code_path' => 'cp_repo_id' ) ),
				'join_conds' => array(
					'code_rev' => array( 'INNER JOIN', 'cr_repo_id = cp_repo_id AND cr_id = cp_rev_id' ),
					'code_comment' => array( 'LEFT JOIN', 'cc_repo_id = cp_repo_id AND cc_rev_id = cp_rev_id' ),
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
					'code_comment' => array( 'LEFT JOIN', 'cc_repo_id = cr_repo_id AND cc_rev_id = cr_id' ),
				)
			);
		}
		if( $this->mView->mAuthor ) {
			$query['conds']['cr_author'] = $this->mView->mAuthor;
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
			'cr_id' => wfMsg( 'code-field-id' ),
			'cr_status' => wfMsg( 'code-field-status' ),
			'comments' => wfMsg( 'code-field-comments' ),
			'cr_path' => wfMsg( 'code-field-path' ),
			'cr_message' => wfMsg( 'code-field-message' ),
			'cr_author' => wfMsg( 'code-field-author' ),
			'cr_timestamp' => wfMsg( 'code-field-timestamp' ),
		);
		# Only show checkboxen as needed
		if ( $this->mView->batchForm ) {
			$fields = array( 'selectforchange' => wfMsg( 'code-field-select' ) ) + $fields;
		}
		return $fields;
	}

	function formatValue( $name, $value ) { } // unused

	function formatRevValue( $name, $value, $row ) {
		global $wgLang;
		$pathQuery = count( $this->mView->mPath ) ? array( 'path' => $this->mView->getPathsAsString() ) : array();

		switch( $name ) {
		case 'selectforchange':
			$sort = $this->getDefaultSort();
			return Xml::check( "wpRevisionSelected[]", false, array( 'value' => $row->$sort ) );
		case 'cr_id':
			return $this->mView->skin->link(
				SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() . '/' . $value ),
				htmlspecialchars( $value ),
				array(),
				$pathQuery
			);
		case 'cr_status':
			$options = $pathQuery;
			if ( $this->mView->mAuthor ) {
				$options['author'] = $this->mView->mAuthor;
			}
			return $this->mView->skin->link(
				SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() . '/status/' . $value ),
				htmlspecialchars( $this->mView->statusDesc( $value ) ),
				array(),
				$options
			);
		case 'cr_author':
			$options = $pathQuery;
			if ( $this->mView->mStatus ) {
				$options['status'] = $this->mView->mStatus;
			}
			return $this->mView->authorLink( $value, $options );
		case 'cr_message':
			return $this->mView->messageFragment( $value );
		case 'cr_timestamp':
			return $wgLang->timeanddate( $value, true );
		case 'comments':
			if ( $value ) {
				$special = SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() . '/' . $row-> { $this->getDefaultSort() } );
				$special->setFragment( '#code-comments' );
				return $this->mView->skin->link( $special, htmlspecialchars( $value ) );
			} else {
				return '-';
			}
		case 'cr_path':
			$title = $this->mRepo->getName();
			if( $this->mView->mAuthor ) {
				$title .= '/author/' . $this->mView->mAuthor;
			}

			return Xml::openElement( 'div', array( 'title' => (string)$value ) ) .
					$this->mView->skin->link(
						SpecialPage::getTitleFor( 'Code', $title ),
						$wgLang->truncate( (string)$value, 50 ),
						array( 'title' => (string)$value ),
						array( 'path' => (string)$value )
					) . "</div>";
		}
	}

	function getTitle() {
		return SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() );
	}
}
