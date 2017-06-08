<?php

/**
 * Created on Nov 18, 2010
 *
 * Copyright © 2010 Sam Reed
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

class ApiRevisionUpdate extends ApiBase {

	public function execute() {
		$user = $this->getUser();
		// Before doing anything at all, let's check permissions
		if ( is_callable( [ $this, 'checkUserRightsAny' ] ) ) {
			$this->checkUserRightsAny( 'codereview-use' );
		} else {
			if ( !$user->isAllowed( 'codereview-use' ) ) {
				 $this->dieUsage( 'You don\'t have permission to update code', 'permissiondenied' );
			}
		}

		$params = $this->extractRequestParams();

		if ( $params['comment'] ) {
			if ( is_callable( [ $this, 'checkUserRightsAny' ] ) ) {
				$this->checkUserRightsAny( 'codereview-post-comment' );
			} else {
				if ( !$user->isAllowed( 'codereview-post-comment' ) ) {
					$this->dieUsage(
						'You do not have permission to post comment', 'permissiondenied' );
				}
			}
		}

		global $wgCodeReviewInlineComments;
		if (
			!$wgCodeReviewInlineComments
			&& isset( $params['patchline'] )
		)
		{
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError(
					'apierror-codereview-inlinecommentingdisabled', 'inlinecommentingdisabled' );
			} else {
				$this->dieUsage(
					'Can not attach a comment to a diff when inline commenting is disabled '
						. '($wgCodeReviewInlineComments is false).',
					'inlinecommentingdisabled'
				);
			}
		}

		$repo = CodeRepository::newFromName( $params['repo'] );
		if ( !$repo ) {
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError(
					[ 'apierror-invalidrepo', wfEscapeWikiText( $params['repo'] ) ] );
			} else {
				$this->dieUsage( "Invalid repo ``{$params['repo']}''", 'invalidrepo' );
			}
		}

		$rev = $repo->getRevision( $params['rev'] );

		if ( !$rev ) {
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError( [ 'apierror-nosuchrevid', $params['rev'] ] );
			} else {
				$this->dieUsage( "There is no revision with ID {$params['rev']}", 'nosuchrev' );
			}
		}

		$revisionCommitter = new CodeRevisionCommitterApi( $repo, $rev );

		$commentID = $revisionCommitter->revisionUpdate(
			$params['status'],
			$params['addtags'],
			$params['removetags'],
			$params['addflags'],
			$params['removeflags'],
			$params['addreferences'],
			$params['removereferences'],
			$params['comment'],
			null,
			$params['addreferenced'],
			$params['removereferenced']
		);

		// Forge a response object
		$r = [ 'result' => 'Success' ];

		if ( $commentID !== 0 ) {
			// id inserted
			$r['commentid'] = intval( $commentID );
			// HTML Formatted comment
			$view = new CodeRevisionView( $repo, $rev );
			$comment = CodeComment::newFromID( $commentID, $rev );
			$r['HTML'] = $view->formatComment( $comment );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $r );
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function needsToken() {
		return 'csrf';
	}

	public function getAllowedParams() {
		$flags = CodeRevision::getPossibleFlags();
		return [
			'repo' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'rev' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_REQUIRED => true,
			],
			'comment' => null,
			'status' => [
				ApiBase::PARAM_TYPE => CodeRevision::getPossibleStates()
			],
			'addtags' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			],
			'removetags' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			],
			'addflags' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => $flags
			],
			'removeflags' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => $flags
			],
			'addreferences' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_ISMULTI => true,
			],
			'removereferences' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_ISMULTI => true,
			],
			'addreferenced' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_ISMULTI => true,
			],
			'removereferenced' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_ISMULTI => true,
			],
			'token' => null,
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return [
			'action=coderevisionupdate&repo=MediaWiki&rev=1&status=fixme'
				=> 'apihelp-coderevisionupdate-example-1',
		];
	}
}
