<?php

// Special:Code/MediaWiki/author
class CodeAuthorListView extends CodeView {
	function __construct( $repoName ) {
		parent::__construct();
		$this->mRepo = CodeRepository::newFromName( $repoName );
	}

	function execute() {
		global $wgOut, $wgLang, $wgRequest;
		$authors = $this->mRepo->getAuthorList();
		$repo = $this->mRepo->getName();
		$hideLinked = $wgRequest->getBool( 'unlinkedonly' );
		$text = wfMsg( 'code-authors-text' ) . "\n\n";
		$text .= '<strong>' . wfMsg( 'code-author-total', $wgLang->formatNum( $this->mRepo->getAuthorCount() ) )  . "</strong>\n";

		$wgOut->addWikiText( $text );

		$wgOut->addHTML( '<table class="TablePager">'
				. '<tr><th>' . wfMsgHtml( 'code-field-author' )
				. '</th><th>' . wfMsgHtml( 'code-author-lastcommit' ) . '</th></tr>' );

		foreach ( $authors as $committer ) {
			if ( $committer ) {
				$author = $committer["author"];
				$text = "[[Special:Code/$repo/author/$author|$author]]";
				$user = $this->mRepo->authorWikiUser( $author );
				if ( $user ) {
					if( $hideLinked ) {
						continue;
					}
					$title = htmlspecialchars( $user->getUserPage()->getPrefixedText() );
					$name = htmlspecialchars( $user->getName() );
					$text .= " ([[$title|$name]])";
				}
				$wgOut->addHTML( "<tr><td>" );
				$wgOut->addWikiText( $text );
				$wgOut->addHTML( "</td><td>{$wgLang->timeanddate( $committer['lastcommit'], true )}</td></tr>" );
			}
		}

		$wgOut->addHTML( '</table>' );
	}
}
