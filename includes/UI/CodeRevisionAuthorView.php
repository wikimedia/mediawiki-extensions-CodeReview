<?php

namespace MediaWiki\Extension\CodeReview\UI;

use Linker;
use MediaWiki\Extension\CodeReview\Backend\CodeRepository;
use MediaWiki\MediaWikiServices;
use RequestContext;
use SpecialPage;

class CodeRevisionAuthorView extends CodeRevisionListView {
	/**
	 * @param CodeRepository|string $repo
	 * @param string $author
	 */
	public function __construct( $repo, $author ) {
		parent::__construct( $repo );
		$this->mAuthor = $author;
		$this->mUser = $this->mRepo->authorWikiUser( $author );
	}

	/**
	 * @return SvnRevAuthorTablePager
	 */
	public function getPager() {
		return new SvnRevAuthorTablePager( $this, $this->mAuthor );
	}

	public function linkStatus() {
		if ( !$this->mUser ) {
			return wfMessage( 'code-author-orphan' )->rawParams( $this->authorLink( $this->mAuthor ) )
				->escaped();
		}

		return wfMessage( 'code-author-haslink' )
			->rawParams( Linker::userLink( $this->mUser->getId(), $this->mUser->getName() ) .
			Linker::userToolLinks(
				$this->mUser->getId(),
				$this->mUser->getName(),
				// default for redContribsWhenNoEdits
				false,
				// Add "send email" link
				Linker::TOOL_LINKS_EMAIL
			) )->escaped();
	}

	public function execute() {
		global $wgOut;

		$linkInfo = $this->linkStatus();

		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		// Give grep a chance to find the usages:
		// code-author-link, code-author-unlink
		if ( RequestContext::getMain()->getUser()->isAllowed( 'codereview-link-user' ) ) {
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
