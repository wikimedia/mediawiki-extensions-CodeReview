<?php

namespace MediaWiki\Extension\CodeReview\Api;

use ApiBase;
use ApiQueryBase;
use ApiResult;
use MediaWiki\Extension\CodeReview\UI\CodeCommentsListView;
use stdClass;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * Created on Oct 29, 2008
 *
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

class ApiQueryCodeComments extends ApiQueryBase {
	/**
	 * @var array
	 */
	private $props;

	/** @inheritDoc */
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'cc' );
	}

	public function execute() {
		$this->checkUserRightsAny( 'codereview-use' );

		$params = $this->extractRequestParams();

		$this->props = array_flip( $params['prop'] );
		if ( isset( $this->props['revision'] ) ) {
			$this->addDeprecation(
				[ 'apiwarn-deprecation-withreplacement', 'ccprop=revision', 'ccprop=status' ],
				'action=query&list=codecomments&ccprop=revision'
			);
		}

		$listview = new CodeCommentsListView( $params['repo'] );
		if ( $listview->getRepo() === null ) {
			$this->dieWithError( [ 'apierror-invalidrepo', wfEscapeWikiText( $params['repo'] ) ] );
		}
		$pager = $listview->getPager();

		if ( $params['start'] !== null ) {
			$pager->setOffset( $this->getDB()->timestamp( $params['start'] ) );
		}
		$limit = $params['limit'];
		$pager->setLimit( $limit );

		$pager->doQuery();

		$comments = $pager->getResult();
		$data = [];

		$count = 0;
		$lastTimestamp = 0;
		foreach ( $comments as $row ) {
			if ( $count == $limit ) {
				$this->setContinueEnumParameter( 'start',
					wfTimestamp( TS_ISO_8601, $lastTimestamp ) );
				break;
			}

			$data[] = $this->formatRow( $row );
			$lastTimestamp = $row->cc_timestamp;
			$count++;
		}
		$comments->free();

		$result = $this->getResult();
		ApiResult::setIndexedTagName( $data, 'comment' );
		$result->addValue( 'query', $this->getModuleName(), $data );
	}

	/**
	 * @param stdClass $row
	 * @return string
	 */
	private function formatRow( $row ) {
		$item = [];
		if ( isset( $this->props['revid'] ) ) {
			$item['revid'] = $row->cc_rev_id;
		}
		if ( isset( $this->props['timestamp'] ) ) {
			$item['timestamp'] = wfTimestamp( TS_ISO_8601, $row->cc_timestamp );
		}
		if ( isset( $this->props['user'] ) ) {
			$item['user'] = $row->cc_user_text;
		}
		if ( isset( $this->props['revision'] ) || isset( $this->props['status'] ) ) {
			$item['status'] = $row->cr_status;
		}
		if ( isset( $this->props['text'] ) ) {
			ApiResult::setContentValue( $item, 'text', $row->cc_text );
		}
		return $item;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'repo' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'start' => [
				ParamValidator::PARAM_TYPE => 'timestamp'
			],
			'prop' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_DEFAULT => 'timestamp|user|status|revid',
				ParamValidator::PARAM_TYPE => [
					'timestamp',
					'user',
					'status',
					'text',
					'revid',
					'revision',
				],
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=query&list=codecomments&ccrepo=MediaWiki'
				=> 'apihelp-query+codecomments-example-1',
			'action=query&list=codecomments&ccrepo=MediaWiki&ccprop=timestamp|user|status|text'
				=> 'apihelp-query+codecomments-example-2',
		];
	}
}
