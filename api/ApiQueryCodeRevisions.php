<?php
/**
 * Created on July 06, 2010
 *
 * Copyright © 2010 Sam Reed
 * Copyright © 2008 Bryan Tong Minh <Bryan.TongMinh@Gmail.com>
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

class ApiQueryCodeRevisions extends ApiQueryBase {
	private $props;

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'cr' );
	}

	public function execute() {
		$this->getMain()->setCacheMode( 'anon-public-user-private' );
		// Before doing anything at all, let's check permissions
		if ( is_callable( [ $this, 'checkUserRightsAny' ] ) ) {
			$this->checkUserRightsAny( 'codereview-use' );
		} else {
			if ( !$this->getUser()->isAllowed( 'codereview-use' ) ) {
				$this->dieUsage( 'You don\'t have permission to view code revisions', 'permissiondenied' );
			}
		}
		$params = $this->extractRequestParams();

		$this->props = array_flip( $params['prop'] );

		$repo = CodeRepository::newFromName( $params['repo'] );

		if ( !$repo ) {
			if ( is_callable( [ $this, 'dieWithError' ] ) ) {
				$this->dieWithError( [ 'apierror-invalidrepo', wfEscapeWikiText( $params['repo'] ) ] );
			} else {
				$this->dieUsage( "Invalid repo ``{$params['repo']}''", 'invalidrepo' );
			}
		}

		$data = [];

		$listview = new CodeRevisionListView( $repo );
		if ( isset( $params['path'] ) && $params['path'] !== '' ) {
			$listview->mPath = CodeRevisionListView::pathsToArray( $params['path'] );
		}

		$pager = $listview->getPager();

		$revsSet = count( $params['revs'] );

		if ( $revsSet ) {
			$db = wfGetDB( DB_SLAVE );

			$query = $pager->getQueryInfo();

			$query['conds']['cr_id'] = $params['revs'];

			$revisions = $db->select( $query['tables'], $query['fields'], $query['conds'],
				__METHOD__, $query['options'], $query['join_conds'] );

		} else {
			if ( !is_null( $params['start'] ) ) {
				$pager->setOffset( $params['start'] );
			}

			$limit = $params['limit'];
			$pager->setLimit( $limit );

			$pager->doQuery();

			$revisions = $pager->getResult();
		}

		$count = 0;
		$start = 0;
		$defaultSort = $pager->getDefaultSort();
		$result = $this->getResult();

		foreach ( $revisions as $row ) {
			if ( !$revsSet && $count == $limit ) {
				$this->setContinueEnumParameter( 'start', $start );
				break;
			}

			$data[] = $this->formatRow( $row, $repo, $result );
			$start = $row->$defaultSort;
			$count++;
		}

		$result->setIndexedTagName( $data, 'revision' );
		$result->addValue( 'query', $this->getModuleName(), $data );
	}

	/**
	 * @param stdClass $row
	 * @param CodeRepository $repo
	 * @param ApiResult $result
	 * @return array
	 */
	private function formatRow( $row, $repo, $result ) {
		$item = [];
		if ( isset( $this->props['revid'] ) ) {
			$item['revid'] = intval( $row->cr_id );
		}
		if ( isset( $this->props['status'] ) ) {
			$item['status'] = $row->cr_status;
		}
		if ( isset( $this->props['commentcount'] ) ) {
			$item['commentcount'] = $row->comments;
		}
		if ( isset( $this->props['path'] ) ) {
			$item['path'] = $row->cr_path;
		}
		if ( isset( $this->props['message'] ) ) {
			ApiResult::setContentValue( $item, 'message', $row->cr_message );
		}
		if ( isset( $this->props['author'] ) ) {
			$item['author'] = $row->cr_author;
		}
		if ( isset( $this->props['timestamp'] ) ) {
			$item['timestamp'] = wfTimestamp( TS_ISO_8601, $row->cr_timestamp );
		}
		$rev = null;
		if ( isset( $this->props['tags'] ) ) {
			$rev = CodeRevision::newFromRow( $repo, $row );
			$item['tags'] = $rev->getTags();
			$result->setIndexedTagName( $item['tags'], 'tags' );
		}
		if ( isset( $this->props['followups'] ) ) {
			if ( $rev === null ) {
				$rev = CodeRevision::newFromRow( $repo, $row );
			}
			$item['followsup'] = $this->addReferenced( $rev );
			$result->setIndexedTagName( $item['followsup'], 'followsup' );
		}

		if ( isset( $this->props['followedup'] ) ) {
			if ( $rev === null ) {
				$rev = CodeRevision::newFromRow( $repo, $row );
			}
			$item['followedup'] = $this->addReferenced( $rev );
			$result->setIndexedTagName( $item['followedup'], 'followedup' );
		}
		return $item;
	}

	/**
	 * @param CodeRevision $rev
	 * @return array
	 */
	protected function addReferenced( $rev ) {
		$items = [];
		foreach ( $rev->getFollowedUpRevisions() as $ref ) {
			$refItem = [
				'revid' => $ref->cr_id,
				'status' => $ref->cr_status,
				'timestamp' => wfTimestamp( TS_ISO_8601, $ref->cr_timestamp ),
				'author' => $ref->cr_author ,
			];
			ApiResult::setContentValue( $refItem, 'message', $ref->cr_message );

			$items[] = $refItem;
		}
		return $items;
	}

	public function getAllowedParams() {
		return [
			'repo' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'limit' => [
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'path' => null,
			'start' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
			'revs' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_MIN => 1,
			],
			'prop' => [
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_DFLT => 'revid|status|author|timestamp',
				ApiBase::PARAM_TYPE => [
					'revid',
					'status',
					'commentcount',
					'path',
					'message',
					'author',
					'tags',
					'timestamp',
					'followups',
					'followedup',
				],
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&list=coderevisions&crrepo=MediaWiki'
				=> 'apihelp-query+coderevisions-example-1',
			'action=query&list=coderevisions&crrepo=MediaWiki&crprop=revid|author|status|timestamp|tags'
				=> 'apihelp-query+coderevisions-example-2',
		];
	}
}
