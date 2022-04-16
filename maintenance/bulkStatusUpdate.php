<?php

use MediaWiki\Extension\CodeReview\Backend\CodeRepository;
use MediaWiki\Extension\CodeReview\Backend\CodeRevision;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class BulkStatusUpdate extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Updates a range of revisions to a specific status' );
		$this->addArg( 'repo', 'The name of the repo. Cannot be all.' );
		$this->addArg( 'revisions', 'The revisions to set status for. Format: start:end' );
		$this->addArg( 'status', "Code States: 'new', 'fixme', 'reverted', "
			. "'resolved', 'ok', 'deferred', 'old'" );
		$this->addArg( 'user', 'Username for whom to accredit the state changes to.' .
			"The User needs to have the 'codereview-set-status' right" );

		$this->requireExtension( 'CodeReview' );
	}

	public function execute() {
		$repoName = $this->getArg( 0 );

		if ( $repoName == 'all' ) {
			$this->fatalError( "Cannot use the 'all' repo" );
		}

		$repo = CodeRepository::newFromName( $repoName );
		if ( !$repo ) {
			$this->fatalError( "Repo '{$repoName}' is not a valid Repository" );
		}

		$revisions = $this->getArg( 1 );
		if ( strpos( $revisions, ':' ) !== false ) {
			$revisionVals = explode( ':', $revisions, 2 );
		} else {
			$this->fatalError( 'Invalid revision range' );
		}

		$start = intval( $revisionVals[0] );
		$end = intval( $revisionVals[1] );

		$revisions = range( $start, $end );

		$status = $this->getArg( 2 );

		if ( !CodeRevision::isValidStatus( $status ) ) {
			$this->fatalError( "'{$status}' is not a valid status" );
		}

		$username = $this->getArg( 3 );
		$user = User::newFromName( $username );

		if ( !$user ) {
			$this->fatalError( "'{$username}' is not a valid username" );
		}

		if ( !$user->isAllowed( 'codereview-set-status' ) ) {
			$this->fatalError( "'{$username}' does not have the 'codereview-set-status' right" );
		}

		$dbr = wfGetDB( DB_REPLICA );

		$res = $dbr->select(
			'code_rev',
			'*',
			[ 'cr_id' => $revisions, 'cr_repo_id' => $repo->getId() ],
			__METHOD__
		);

		foreach ( $res as $row ) {
			$rev = CodeRevision::newFromRow( $repo, $row );

			if ( $rev && $rev->setStatus( $status, $user ) ) {
				$this->output( "r{$row->cr_id} updated\n" );
			} else {
				$this->output( "r{$row->cr_id} not updated\n" );
			}
		}

		$this->output( "Done!\n" );
	}
}

$maintClass = BulkStatusUpdate::class;
require_once RUN_MAINTENANCE_IF_MAIN;
