<?php

// Special:Code/MediaWiki/author/johndoe/link

class CodeRevisionAuthorLink extends CodeRevisionAuthorView {
	function __construct( $repo, $author ) {
		global $wgRequest;
		parent::__construct( $repo, $author );
		$this->mTarget = $wgRequest->getVal( 'linktouser' );
	}

	function getTitle() {
		$repo = $this->mRepo->getName();
		$auth = $this->mAuthor;
		return SpecialPage::getTitleFor( 'Code', "$repo/author/$auth/link" );
	}

	function execute() {
		global $wgRequest, $wgUser;
		if ( !$wgUser->isAllowed( 'codereview-link-user' ) ) {
			throw new PermissionsError( 'codereview-link-user' );
		}

		if ( $wgRequest->wasPosted() ) {
			$this->doSubmit();
		} else {
			$this->doForm();
		}
	}

	function doForm() {
		global $wgOut, $wgUser;
		$form = Xml::openElement( 'form', array( 'method' => 'post',
			'action' => $this->getTitle()->getLocalUrl(),
			'name' => 'uluser', 'id' => 'mw-codeauthor-form1' ) );

		$form .= Html::hidden( 'linktoken', $wgUser->getEditToken( 'link' ) );
		$form .= Xml::openElement( 'fieldset' );

		$additional = '';
		// Is there already a user linked to this author?
		if ( $this->mUser ) {
			$form .= Xml::element( 'legend', array(), wfMessage( 'code-author-alterlink' )->text() );
			$additional = Xml::openElement( 'fieldset' ) .
				Xml::element( 'legend', array(), wfMessage( 'code-author-orunlink' )->text() ) .
				Xml::submitButton( wfMessage( 'code-author-unlink' )->text(), array( 'name' => 'unlink' ) ) .
				Xml::closeElement( 'fieldset' );
		} else {
			$form .= Xml::element( 'legend', array(), wfMessage( 'code-author-dolink' )->text() );
		}

		$form .= Xml::inputLabel( wfMessage( 'code-author-name' )->text(),
			'linktouser', 'username', 30, '' ) . ' ' .
				Xml::submitButton( wfMessage( 'ok' )->text(), array( 'name' => 'newname' ) ) .
				Xml::closeElement( 'fieldset' ) .
				$additional .
				Xml::closeElement( 'form' ) . "\n";

		$wgOut->addHTML( $this->linkStatus() . $form );
	}

	function doSubmit() {
		global $wgOut, $wgRequest, $wgUser;
		// Link an author to a wiki user

		if ( !$wgUser->matchEditToken( $wgRequest->getVal( 'linktoken' ), 'link' ) ) {
			$wgOut->addWikiMsg( 'code-author-badtoken' );
			return;
		}

		if ( strlen( $this->mTarget ) && $wgRequest->getCheck( 'newname' ) ) {
			$user = User::newFromName( $this->mTarget, false );
			if ( !$user || !$user->getId() ) {
				$wgOut->addWikiMsg( 'nosuchusershort', $this->mTarget );
				return;
			}
			$this->mRepo->linkUser( $this->mAuthor, $user );
			$userlink = Linker::userLink( $user->getId(), $user->getName() );
			$wgOut->addHTML(
				'<div class="successbox">' .
					wfMessage( 'code-author-success' )
						->rawParams( $this->authorLink( $this->mAuthor ), $userlink )->escaped() .
				'</div>'
			);
		// Unlink an author to a wiki users
		} elseif ( $wgRequest->getVal( 'unlink' ) ) {
			if ( !$this->mUser ) {
				$wgOut->addHTML( wfMessage( 'code-author-orphan' )
					->rawParams( $this->authorLink( $this->mAuthor ) )->escaped()
				);
				return;
			}
			$this->mRepo->unlinkUser( $this->mAuthor );
			$wgOut->addHTML( '<div class="successbox">' .
					wfMessage( 'code-author-unlinksuccess' )
						->rawParams( $this->authorLink( $this->mAuthor ) )
						->escaped() .
				'</div>'
			);
		}
	}
}
