<?php

class ApiRevisionUpdate extends ApiBase {

	public function execute() {
		global $wgUser;
		// Before doing anything at all, let's check permissions
		if ( !$wgUser->isAllowed( 'codereview-use' ) ) {
			$this->dieUsage( 'You don\'t have permission to update code', 'permissiondenied' );
		}

		$params = $this->extractRequestParams();

		$repo = CodeRepository::newFromName( $params['repo'] );
		if ( !$repo ) {
			$this->dieUsage( "Invalid repo ``{$params['repo']}''", 'invalidrepo' );
		}

		$rev = $repo->getRevision( $params['rev'] );

		if ( !$rev ) {
			$this->dieUsage( "There is no revision with ID {$params['rev']}", 'nosuchrev' );
		}

		$revisionCommitter = new CodeRevisionCommitterApi( $repo, $rev );

		$revisionCommitter->doRevisionUpdate(
			$params['status'],
			$params['addtags'],
			$params['removeTags'],
			array(), //Signoff Flags to be done/exposed at later date
	    	$params['comment']
		);

		$r = array( 'result' => 'Success' );
		$this->getResult()->addValue( null, $this->getModuleName(), $r );
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
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
			),
			'comment' => null,
			'status' => array(
				ApiBase::PARAM_TYPE => CodeRevision::getPossibleStates()
			),
			'addtags' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			),
			'removetags' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			),
		);
	}

	public function getParamDescription() {
		return array(
			'repo' => 'Name of repository',
			'rev' => 'Revision ID number',
			'comment' => 'Comment to add to the revision',
			'status' => 'Status to set the revision to',
			'addtags' => 'Tags to be added to the revision',
			'removetags' => 'Tags to be removed from the revision',
		);
	}

	public function getDescription() {
		return array(
			'Submit comments, new status and tags to a revision'
		);
	}

	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => 'permissiondenied', 'info' => 'You don\'t have permission to update code' ),
			array( 'code' => 'invalidrepo', 'info' => "Invalid repo ``repo''" ),
			array( 'code' => 'nosuchrev', 'info' => 'There is no revision with ID \'rev\'' ),
			array( 'missingparam', 'repo' ),
			array( 'missingparam', 'rev' ),
		) );
	}

	public function getExamples() {
		return array(
			'api.php?action=updaterev&repo=MediaWiki&rev=1&status=fixme',
		);
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}
}
