<?php

/**
 * Created on 20 April 2011
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

class ApiQueryCodeTags extends ApiQueryBase {
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'ct' );
	}

	public function execute() {
		// Before doing anything at all, let's check permissions
		if ( is_callable( [ $this, 'checkUserRightsAny' ] ) ) {
			$this->checkUserRightsAny( 'codereview-use' );
		} else {
			if ( !$this->getUser()->isAllowed( 'codereview-use' ) ) {
				$this->dieUsage( 'You don\'t have permission to view code tags', 'permissiondenied' );
			}
		}
		$params = $this->extractRequestParams();

		$repo = CodeRepository::newFromName( $params['repo'] );
		if ( !$repo instanceof CodeRepository ) {
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError( [ 'apierror-invalidrepo', wfEscapeWikiText( $params['repo'] ) ] );
			} else {
				$this->dieUsage( "Invalid repo ``{$params['repo']}''", 'invalidrepo' );
			}
		}

		$data = [];
		foreach ( $repo->getTagList( true ) as $tag => $count ) {
			$data[] = [
				'name' => $tag,
				'revcount' => $count,
			];
		}

		$result = $this->getResult();
		$result->setIndexedTagName( $data, 'tag' );
		$result->addValue( 'query', $this->getModuleName(), $data );
	}

	public function getAllowedParams() {
		return [
			'repo' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&list=codetags&ctrepo=MediaWiki'
				=> 'apihelp-query+codetags-example-1',
		];
	}
}
