<?php

// Special:Code/MediaWiki
class CodeCommentsListView extends CodeView {

	protected $author;

	function __construct( $repo ) {
		parent::__construct();

		$this->repo = ( $repo instanceof CodeRepository )
			? $repo
			: CodeRepository::newFromName( $repo );

		global $wgRequest;
		$this->author = $wgRequest->getText( 'author' );
	}

	function execute() {
		global $wgOut;
		$pager = $this->getPager();
		$limitForm = $pager->getLimitForm();
		$wgOut->addHTML(
			$pager->getNavigationBar() .
			$limitForm .
			$pager->getBody() .
			$limitForm .
			$pager->getNavigationBar()
		);
	}

	function getAuthor() {
		return $this->author;
	}

	/**
	 * @return CodeCommentsTablePager
	 */
	function getPager() {
		return new CodeCommentsTablePager( $this );
	}

	/**
	 * @return CodeRepository|null
	 */
	function getRepo() {
		return $this->repo;
	}
}

// Pager for CodeRevisionListView
class CodeCommentsTablePager extends SvnTablePager {

	function isFieldSortable( $field ) {
		return $field == 'cr_timestamp';
	}

	function getDefaultSort() {
		return 'cc_timestamp';
	}

	function getQueryInfo() {
		$query = array(
			'tables' => array( 'code_comment', 'code_rev' ),
			'fields' => array_keys( $this->getFieldNames() ),
			'conds' => array( 'cc_repo_id' => $this->repo->getId() ),
			'join_conds' => array(
				'code_rev' => array( 'LEFT JOIN', 'cc_repo_id = cr_repo_id AND cc_rev_id = cr_id' )
			)
		);

		if( $this->view instanceof CodeCommentsListView ) {
			$query['conds']['cc_user_text'] = $this->view->getAuthor();
		}

	    return $query;
	}

	function getFieldNames() {
		return array(
			'cc_timestamp' => wfMsg( 'code-field-timestamp' ),
			'cc_user_text' => wfMsg( 'code-field-user' ),
			'cc_rev_id' => wfMsg( 'code-field-id' ),
			'cr_status' => wfMsg( 'code-field-status' ),
			'cr_message' => wfMsg( 'code-field-message' ),
			'cc_text' => wfMsg( 'code-field-text' ),
		);
	}

	function formatValue( $name, $value ) {
		global $wgLang;
		switch( $name ) {
		case 'cc_rev_id':
			return $this->view->skin->link(
				SpecialPage::getTitleFor( 'Code', $this->repo->getName() . '/' . $value . '#code-comments' ),
				htmlspecialchars( $value ) );
		case 'cr_status':
			return $this->view->skin->link(
				SpecialPage::getTitleFor( 'Code',
					$this->repo->getName() . '/status/' . $value ),
				htmlspecialchars( $this->view->statusDesc( $value ) ) );
		case 'cc_user_text':
			return $this->view->skin->userLink( - 1, $value );
		case 'cr_message':
			return $this->view->messageFragment( $value );
		case 'cc_text':
			# Truncate this, blah blah...
			return htmlspecialchars( $wgLang->truncate( $value, 300 ) );
		case 'cc_timestamp':
			global $wgLang;
			return $wgLang->timeanddate( $value, true );
		}
	}

	function getTitle() {
		return SpecialPage::getTitleFor( 'Code', $this->repo->getName() . '/comments' );
	}
}
