<?php

/**
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

class ApiCodeDiff extends ApiBase {

	public function execute() {
		global $wgCodeReviewMaxDiffSize;
		// Before doing anything at all, let's check permissions
		if ( is_callable( array( $this, 'checkUserRightsAny' ) ) ) {
			$this->checkUserRightsAny( 'codereview-use' );
		} else {
			if ( !$this->getUser()->isAllowed( 'codereview-use' ) ) {
				$this->dieUsage( 'You don\'t have permission to view code diffs', 'permissiondenied' );
			}
		}
		$params = $this->extractRequestParams();

		$repo = CodeRepository::newFromName( $params['repo'] );
		if ( !$repo ) {
			if ( is_callable( array( $this, 'dieWithError' ) ) ) {
				$this->dieWithError( array( 'apierror-invalidrepo', wfEscapeWikiText( $params['repo'] ) ) );
			} else {
				$this->dieUsage( "Invalid repo ``{$params['repo']}''", 'invalidrepo' );
			}
		}

		$lastStoredRev = $repo->getLastStoredRev();

		if ( $params['rev'] > $lastStoredRev ) {
			if ( is_callable( array( $this, 'dieWithError' ) ) ) {
				$this->dieWithError( array( 'apierror-nosuchrevid', $params['rev'] ) );
			} else {
				$this->dieUsage( "There is no revision with ID {$params['rev']}", 'nosuchrev' );
			}
		}

		$diff = $repo->getDiff( $params['rev'] );

		if ( !is_string( $diff ) ) {
			// FIXME: Are we sure we don't want to throw an error here?
			$html = 'Failed to load diff. Error message: ' . CodeRepository::getDiffErrorMessage( $diff );
		} elseif ( strlen( $diff ) > $wgCodeReviewMaxDiffSize ) {
			$html = 'Diff too large.';
		} else {
			$hilite = new CodeDiffHighlighter();
			$html = $hilite->render( $diff );
		}

		$data = array(
			'repo' => $params['repo'],
			'id' => $params['rev'],
			'diff' => $html
		);
		$this->getResult()->addValue( 'code', 'rev', $data );
	}

	public function getAllowedParams() {
		return array(
			'repo' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'rev' => array(
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_REQUIRED => true,
			)
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return array(
			'action=codediff&repo=MediaWiki&rev=42080'
				=> 'apihelp-codediff-example-1',
		);
	}
}
