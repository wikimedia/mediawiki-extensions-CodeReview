<?php

namespace MediaWiki\Extension\CodeReview\UI;

use MediaWiki\Extension\CodeReview\Backend\CodeCommentLinkerHtml;
use MediaWiki\Extension\CodeReview\Backend\CodeCommentLinkerWiki;
use MediaWiki\Extension\CodeReview\Backend\CodeRepository;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use RequestContext;
use SpecialPage;

/**
 * Extended by CodeRevisionListView and CodeRevisionView
 */
abstract class CodeView {
	public CodeRepository $mRepo;

	public CodeCommentLinkerHtml $codeCommentLinkerHtml;

	public CodeCommentLinkerWiki $codeCommentLinkerWiki;

	/** @var string|array */
	public $mPath;

	public string $mAuthor;

	public string $mStatus;

	/**
	 * @param CodeRepository|string $repo
	 */
	public function __construct( $repo ) {
		$this->mRepo = ( $repo instanceof CodeRepository )
			? $repo
			: CodeRepository::newFromName( $repo );

		$this->codeCommentLinkerHtml = new CodeCommentLinkerHtml( $this->mRepo );
		$this->codeCommentLinkerWiki = new CodeCommentLinkerWiki( $this->mRepo );
	}

	/**
	 * @param string $permission
	 * @param Authority $performer
	 * @return bool
	 */
	public function validPost( $permission, Authority $performer ) {
		$context = RequestContext::getMain();
		$request = $context->getRequest();
		$userToken = $context->getCsrfTokenSet();
		return $request->wasPosted()
			&& $userToken->matchToken( $request->getVal( 'wpEditToken' ) )
			&& $performer->isAllowed( $permission );
	}

	abstract public function execute();

	/**
	 * @param string $author
	 * @param array $extraParams
	 * @return string
	 */
	public function authorLink( $author, $extraParams = [] ) {
		$repo = $this->mRepo->getName();
		$special = SpecialPage::getTitleFor( 'Code', "$repo/author/$author" );

		return MediaWikiServices::getInstance()->getLinkRenderer()->makeLink( $special, $author, [], $extraParams );
	}

	/**
	 * @param string $status
	 * @return string
	 */
	public function statusDesc( $status ) {
		return wfMessage( "code-status-$status" )->text();
	}

	/**
	 * @param string $text
	 * @return string
	 */
	public function formatMessage( $text ) {
		$escText = nl2br( htmlspecialchars( $text ) );
		return $this->codeCommentLinkerHtml->link( $escText );
	}

	/**
	 * @param string $value
	 * @return string
	 */
	public function messageFragment( $value ) {
		global $wgLang;
		$message = trim( $value );
		$lines = explode( "\n", $message, 2 );
		$first = $lines[0];

		$html = $this->formatMessage( $first );
		$truncated = $wgLang->truncateHtml( $html, 80 );

		if ( count( $lines ) > 1 ) {
			// If multiline, we might want to add an ellipse
			$ellipsis = wfMessage( 'ellipsis' )->text();
			// Hack: don't add if the end is already an ellipse
			if ( substr( $truncated, -strlen( $ellipsis ) ) !== $ellipsis ) {
				$truncated .= $ellipsis;
			}
		}

		return $truncated;
	}

	/**
	 * Formatted HTML array for properties display
	 * @param array $fields 'propname' => HTML data
	 * @return string
	 */
	public function formatMetaData( $fields ) {
		$html = '<table class="mw-codereview-meta">';
		foreach ( $fields as $label => $data ) {
			$html .= "<tr><td>" . wfMessage( $label )->escaped() . "</td><td>$data</td></tr>\n";
		}
		return $html . "</table>\n";
	}

	/**
	 * @return bool|CodeRepository
	 */
	public function getRepo() {
		if ( $this->mRepo ) {
			return $this->mRepo;
		}
		return false;
	}
}
