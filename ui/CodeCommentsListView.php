<?php

// Special:Code/MediaWiki/comments
class CodeCommentsListView extends CodeView {
	public $mRepo;

	function __construct( $repo ) {
		parent::__construct( $repo );

		global $wgRequest;
		$this->mAuthor = $wgRequest->getText( 'author' );
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

	function getPager() {
		return new CodeCommentsTablePager( $this );
	}

	function getRepo() {
		return $this->mRepo;
	}
}

// Pager for CodeCommentsListView
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
			'conds' => array( 'cc_repo_id' => $this->mRepo->getId() ),
			'join_conds' => array(
				'code_rev' => array( 'LEFT JOIN', 'cc_repo_id = cr_repo_id AND cc_rev_id = cr_id' )
			)
		);

		if( $this->mView->mAuthor ) {
			$query['conds']['cc_user_text'] = $this->mView->mAuthor;
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
		switch( $name ) {
		case 'cc_rev_id':
			return $this->mView->skin->link(
				SpecialPage::getSafeTitleFor( 'Code', $this->mRepo->getName() . '/' . $value . '#code-comments' ),
				htmlspecialchars( $value ) );
		case 'cr_status':
			return $this->mView->skin->link(
				SpecialPage::getTitleFor( 'Code',
					$this->mRepo->getName() . '/status/' . $value ),
				htmlspecialchars( $this->mView->statusDesc( $value ) ) );
		case 'cc_user_text':
			return $this->mView->skin->userLink( - 1, $value );
		case 'cr_message':
			return $this->mView->messageFragment( $value );
		case 'cc_text':
			return $this->mView->messageFragment( $value );
		case 'cc_timestamp':
			global $wgLang;
			return $wgLang->timeanddate( $value, true );
		}
	}

	function getTitle() {
		return SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() . '/comments' );
	}
}
