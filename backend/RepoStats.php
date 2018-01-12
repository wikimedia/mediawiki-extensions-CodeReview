<?php

class RepoStats {

	/**
	 * @var CodeRepository
	 */
	private $repo;

	public $time;

	// Statistics
	public $revisions,
		$authors,
		$states,
		$fixmes,
		$new;

	public $fixmesPerPath, $newPerPath;

	/**
	 * @param CodeRepository $repo
	 * @return RepoStats
	 */
	public static function newFromRepo( CodeRepository $repo ) {
		global $wgMemc, $wgCodeReviewRepoStatsCacheTime;

		$key = $wgMemc->makeKey( 'codereview1', 'stats', $repo->getName() );
		$stats = $wgMemc->get( $key );
		wfDebug( "{$repo->getName()} repo stats: cache " );
		if ( $stats ) {
			wfDebug( "hit\n" );
			return $stats;
		}
		wfDebug( "miss\n" );
		$stats = new RepoStats( $repo );
		$stats->generate();
		$wgMemc->set( $key, $stats, $wgCodeReviewRepoStatsCacheTime );
		return $stats;
	}

	/**
	 * @param CodeRepository $repo
	 */
	public function __construct( CodeRepository $repo ) {
		$this->repo = $repo;
		$this->time = wfTimestamp( TS_MW );
	}

	private function generate() {
		$dbr = wfGetDB( DB_REPLICA );

		$this->revisions = $dbr->selectField( 'code_rev',
			'COUNT(*)',
			[ 'cr_repo_id' => $this->repo->getId() ],
			__METHOD__
		);

		$this->authors = $dbr->selectField( 'code_rev',
			'COUNT(DISTINCT cr_author)',
			[ 'cr_repo_id' => $this->repo->getId() ],
			__METHOD__
		);

		$this->states = [];
		$res = $dbr->select( 'code_rev',
			[ 'cr_status', 'COUNT(*) AS revs' ],
			[ 'cr_repo_id' => $this->repo->getId() ],
			__METHOD__,
			[ 'GROUP BY' => 'cr_status' ]
		);
		foreach ( $res as $row ) {
			$this->states[$row->cr_status] = $row->revs;
		}

		$repoName = $this->repo->getName();

		$this->fixmes = $this->getAuthorStatusCounts( 'fixme' );
		$this->new = $this->getAuthorStatusCounts( 'new' );

		$this->fixmesPerPath = [];
		global $wgCodeReviewFixmePerPath;
		if ( isset( $wgCodeReviewFixmePerPath[$repoName] ) ) {
			foreach ( $wgCodeReviewFixmePerPath[$repoName] as $path ) {
				$this->fixmesPerPath[$path] = $this->getPathFixmes( $path );
			}
		}

		$this->newPerPath = [];
		global $wgCodeReviewNewPerPath;
		if ( isset( $wgCodeReviewNewPerPath[$repoName] ) ) {
			foreach ( $wgCodeReviewNewPerPath[$repoName] as $path ) {
				$this->newPerPath[$path] = $this->getPathNews( $path );
			}
		}
	}

	/**
	 * @param string $status
	 *
	 * @return array
	 */
	private function getAuthorStatusCounts( $status ) {
		$array = [];
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select( 'code_rev',
			[ 'COUNT(*) AS revs', 'cr_author' ],
			[ 'cr_repo_id' => $this->repo->getId(), 'cr_status' => $status ],
			__METHOD__,
			[
				'GROUP BY' => 'cr_author',
				'ORDER BY' => 'revs DESC',
				'LIMIT' => 500,
			]
		);
		foreach ( $res as $row ) {
			$array[$row->cr_author] = $row->revs;
		}
		return $array;
	}

	/**
	 * @param array|string $path path to get fixmes for
	 * @return array
	 */
	private function getPathFixmes( $path ) {
		return $this->getStatusPath( $path, 'fixme' );
	}

	/**
	 * @param array|string $path path to get fixmes for
	 * @return array
	 */
	private function getPathNews( $path ) {
		return $this->getStatusPath( $path, 'new' );
	}

	/**
	 * @param array|string $path
	 * @param string $status
	 * @return array
	 */
	private function getStatusPath( $path, $status ) {
		$array = [];
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			[ 'code_paths', 'code_rev' ],
			[ 'COUNT(*) AS revs', 'cr_author' ],
			[
				'cr_repo_id' => $this->repo->getId(),
				'cp_path' => $path,
				'cr_status' => $status,
			],
			__METHOD__,
			[
				'GROUP BY' => 'cr_author',
				'ORDER BY' => 'revs DESC',
				'LIMIT' => 500,
			],
			[
				'code_rev' => [ 'INNER JOIN', 'cr_repo_id = cp_repo_id AND cr_id = cp_rev_id' ]
			]
		);
		foreach ( $res as $row ) {
			$array[$row->cr_author] = $row->revs;
		}
		return $array;
	}
}
