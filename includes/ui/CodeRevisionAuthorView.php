<?php

class CodeRevisionAuthorView extends CodeRevisionListView {
	function __construct( $repo, $author ) {
		parent::__construct( $repo );
		$this->mAuthor = $author;
		$this->mUser = $this->mRepo->authorWikiUser( $author );
	}

	function getPager() {
		return new SvnRevAuthorTablePager( $this, $this->mAuthor );
	}

	function linkStatus() {
		if ( !$this->mUser ) {
			return wfMessage( 'code-author-orphan' )->rawParams( $this->authorLink( $this->mAuthor ) )
				->escaped();
		}

		return wfMessage( 'code-author-haslink' )
			->rawParams( Linker::userLink( $this->mUser->getId(), $this->mUser->getName() ) .
			Linker::userToolLinks(
				$this->mUser->getId(),
				$this->mUser->getName(),
				false, /* default for redContribsWhenNoEdits */
				Linker::TOOL_LINKS_EMAIL /* Add "send email" link */
			) )->escaped();
	}

	function execute() {
		global $wgOut, $wgUser;

		$linkInfo = $this->linkStatus();

		$linkRenderer = \MediaWiki\MediaWikiServices::getInstance()->getLinkRenderer();
		// Give grep a chance to find the usages:
		// code-author-link, code-author-unlink
		if ( $wgUser->isAllowed( 'codereview-link-user' ) ) {
			$repo = $this->mRepo->getName();
			$page = SpecialPage::getTitleFor( 'Code', "$repo/author/$this->mAuthor/link" );
			$linkInfo .= ' (' . $linkRenderer->makeLink( $page,
				wfMessage( 'code-author-' . ( $this->mUser ? 'un' : '' ) . 'link' )->text() ) . ')';
		}

		$repoLink = $linkRenderer->makeLink(
			SpecialPage::getTitleFor( 'Code', $this->mRepo->getName() ),
			$this->mRepo->getName()
		);
		$fields = [
			'code-rev-repo' => $repoLink,
			'code-rev-author' => $this->mAuthor,
		];

		$wgOut->addHTML( $this->formatMetaData( $fields ) . $linkInfo );

		parent::execute();
	}
}

class SvnRevAuthorTablePager extends SvnRevTablePager {
	function __construct( $view, $author ) {
		parent::__construct( $view );
		$this->mAuthor = $author;
	}

	function getQueryInfo() {
		$info = parent::getQueryInfo();
		$info['conds']['cr_author'] = $this->mAuthor; // fixme: normalize input?
		return $info;
	}

	function getTitle() {
		$repo = $this->mRepo->getName();
		return SpecialPage::getTitleFor( 'Code', "$repo/author/$this->mAuthor" );
	}
}
