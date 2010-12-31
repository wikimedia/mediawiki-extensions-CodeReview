<?php
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = dirname( __FILE__ ) . '/../..';
}
require( "$IP/maintenance/Maintenance.php" );

class RepopulateCodePaths extends Maintenance {
	public function __construct() {
		parent::__construct();
	    $this->mDescription = "Rebuilds all code paths to support more efficient searching";
		$this->addArg( 'repo', 'The name of the repo. Cannot be all.' );
		$this->addArg( 'revisions', "The revisions to set status for. Format: start:end" );
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

		$revisions = $this->getArg( 1 );
		if ( strpos( $revisions, ':' ) !== false ) {
			$revisionVals = explode( ':', $revisions, 2 );
		} else {
			$this->error( "Invalid revision range", true );
		}

		$start = intval( $revisionVals[0] );
		$end = intval( $revisionVals[1] );

		$revisions = range( $start, $end );

		$dbr = wfGetDB( DB_SLAVE );

	    $res = $dbr->select( 'code_paths', '*', array( 'cp_rev_id' => $revisions, 'cp_repo_id' => $repo->getId() ),
			__METHOD__ );

	    $data = array();
		foreach ( $res as $row ) {
			$this->output( "r{$row->cp_rev_id}\n" );

			$data[] = array(
				'cp_repo_id' => $repo->getId(),
				'cp_rev_id'  => $row->cp_rev_id,
				'cp_path'    => CodeRevision::getPathFragments( array( $row->cp_path ) ),
				'cp_action'  => $row->cp_action
			);
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->begin();
		$this->output( "Commiting paths...\n" );
	    $this->insertChunks( $dbw, 'code_paths', $data, __METHOD__, array( 'IGNORE' ) );
	    $dbw->commit();

	    $this->output( "Done!\n" );
	}

	private static function insertChunks( $db, $table, $data, $method = __METHOD__, $options = array() ) {
		$chunkSize = 100;
		for ( $i = 0; $i < count( $data ); $i += $chunkSize ) {
			$db->insert( $table,
				array_slice( $data, $i, $chunkSize ),
				$method,
				$options
			);
		}
	}
}

$maintClass = "RepopulateCodePaths";
require_once( DO_MAINTENANCE );
