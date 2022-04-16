<?php

use MediaWiki\Extension\CodeReview\Backend\CodeRepository;
use MediaWiki\Extension\CodeReview\Backend\CodeRevision;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class PopulateFollowupRevisions extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populates followup revisions. Useful for setting them on old ' .
			'revisions, without reimporting' );
		$this->addArg( 'repo', 'The name of the repo. Cannot be all.' );
		$this->addArg( 'revisions',
			'The revisions to set followups revisions for. Format: start:end' );
		$this->addOption( 'dry-run', 'Perform a dry run' );

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
			$this->fatalError( "Invalid revision range" );
		}

		$start = intval( $revisionVals[0] );
		$end = intval( $revisionVals[1] );

		$revisions = range( $start, $end );

		$dryrun = $this->hasOption( 'dry-run' );

		$dbr = wfGetDB( DB_REPLICA );

		$res = $dbr->select(
			'code_rev',
			'*',
			[ 'cr_id' => $revisions, 'cr_repo_id' => $repo->getId() ],
			__METHOD__
		);

		foreach ( $res as $row ) {
			$rev = CodeRevision::newFromRow( $repo, $row );

			$affectedRevs = $rev->getUniqueAffectedRevs();

			$this->output( "r{$row->cr_id}: " );

			if ( count( $affectedRevs ) ) {
				$this->output( 'associating revs ' . implode( ',', $affectedRevs ) . "\n" );

				if ( !$dryrun ) {
					$rev->addReferencesTo( $affectedRevs );
				}
			} else {
				$this->output( "no revisions followed up\n" );
			}
		}
		$this->output( "Done!\n" );
	}
}

$maintClass = PopulateFollowupRevisions::class;
require_once RUN_MAINTENANCE_IF_MAIN;
