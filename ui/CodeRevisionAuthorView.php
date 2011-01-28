<?php

class CodeRevisionAuthorView extends CodeRevisionListView {
	function __construct( $repo, $author ) {
		parent::__construct( $repo );
		$this->author = $author;
		$this->user = $this->repo->authorWikiUser( $author );
		$this->appliedFilter = wfMsg( 'code-revfilter-cr_author', $author );
	}

	function getPager() {
		return new SvnRevAuthorTablePager( $this, $this->author );
	}

	function linkStatus() {
		if ( !$this->user )
			return wfMsg( 'code-author-orphan' );

		return wfMsgHtml( 'code-author-haslink',
			$this->skin->userLink( $this->user->getId(), $this->user->getName() ) .
			$this->skin->userToolLinks( $this->user->getId(), $this->user->getName() ) );
	}

	function execute() {
		global $wgOut, $wgUser;

		$linkInfo = $this->linkStatus();

		if ( $wgUser->isAllowed( 'codereview-link-user' ) ) {
			$repo = $this->repo->getName();
			$page = SpecialPage::getTitleFor( 'Code', "$repo/author/$this->author/link" );
			$linkInfo .= ' (' . $this->skin->link( $page,
				wfMsg( 'code-author-' . ( $this->user ? 'un':'' ) . 'link' ) ) . ')' ;
		}

		$repoLink = $wgUser->getSkin()->link( SpecialPage::getTitleFor( 'Code', $this->repo->getName() ),
			htmlspecialchars( $this->repo->getName() ) );
		$fields = array(
			'code-rev-repo' => $repoLink,
			'code-rev-author' => $this->authorLink( $this->author ),
		);

		$wgOut->addHTML( $this->formatMetaData( $fields ) . $linkInfo );

		parent::execute();
	}
}

class SvnRevAuthorTablePager extends SvnRevTablePager {
	function __construct( $view, $author ) {
		parent::__construct( $view );
		$this->author = $author;
	}

	function getQueryInfo() {
		$info = parent::getQueryInfo();
		$info['conds']['cr_author'] = $this->author; // fixme: normalize input?
		return $info;
	}

	function getTitle() {
		$repo = $this->repo->getName();
		return SpecialPage::getTitleFor( 'Code', "$repo/author/$this->author" );
	}
}
