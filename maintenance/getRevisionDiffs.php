<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = dirname( __FILE__ ) . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

class GetRevisionDiffs extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Populates the cr_diff column (where possible) for all rows in a repo";
		$this->addArg( 'repo', 'The name of the repo. Cannot be all.' );
	}

	public function execute() {
		$repoName = $this->getArg( 0 );

		if ( $repoName == "all" ) {
			$this->error( "Cannot use the 'all' repo", true );
		}

		$repo = CodeRepository::newFromName( $repoName );
		if ( !$repo ) {
			$this->error( "Repo '{$repoName}' is not a valid Repository", true );
		}

		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
			'code_rev',
			'cr_id',
			array( 'cr_repo_id' => $repo->getId(), 'cr_diff IS null',  ),
			__METHOD__
		);

		$count = 0;
		foreach ( $res as $row ) {
			$id = $row->cr_id;
			try {
				$diff = $repo->getDiff( $row->cr_id , '' );
			} catch ( MWException $mwe ) {
				// Suppress errors
				$this->output( "$id - error {$mwe->getMessage()}\n" );
				continue;
			}
			if ( is_int( $diff ) ) {
				$error = CodeRepository::getDiffErrorMessage( $diff );
				$this->output( "$id - $error\n" );
			} else {
				$this->output( "$id\n" );
			}

			if ( ++$count % 100 == 0 ) {
				wfWaitForSlaves();
			}
		}
		$this->output( "Done!\n" );
	}
}

$maintClass = "GetRevisionDiffs";
require_once( DO_MAINTENANCE );