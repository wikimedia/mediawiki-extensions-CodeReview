<?php

/**
 * Core class for interacting with a repository of code.
 */
class CodeRepository {
	const DIFFRESULT_BadRevision = 0;
	const DIFFRESULT_NothingToCompare = 1;
	const DIFFRESULT_TooManyPaths = 2;
	const DIFFRESULT_NoDataReturned = 3;
	const DIFFRESULT_NotInCache = 4;

	/**
	 * Local cache of Wiki user -> SVN user mappings
	 * @var array
	 */
	private static $userLinks = [];

	/**
	 * Sort of the same, but looking it up for the other direction
	 * @var array
	 */
	private static $authorLinks = [];

	/**
	 * Various data about the repo
	 */
	private $id, $name, $path, $viewVc, $bugzilla;

	/**
	 * Constructor, can't use it. Call one of the static newFrom* methods
	 * @param int $id Database ID for the repo
	 * @param string $name User-defined name for the repository
	 * @param string $path Path to SVN
	 * @param string $viewvc Base path to ViewVC URLs
	 * @param string $bugzilla Base path to Bugzilla
	 */
	public function __construct( $id, $name, $path, $viewvc, $bugzilla ) {
		$this->id = $id;
		$this->name = $name;
		$this->path = $path;
		$this->viewVc = $viewvc;
		$this->bugzilla = $bugzilla;
	}

	/**
	 * @param string $name
	 * @return CodeRepository|null
	 */
	public static function newFromName( $name ) {
		$dbw = wfGetDB( DB_REPLICA );
		$row = $dbw->selectRow(
			'code_repo',
			[
				'repo_id',
				'repo_name',
				'repo_path',
				'repo_viewvc',
				'repo_bugzilla'
			],
			[ 'repo_name' => $name ],
			__METHOD__ );

		if ( $row ) {
			return self::newFromRow( $row );
		} else {
			return null;
		}
	}

	/**
	 * @param int $id
	 * @return CodeRepository|null
	 */
	public static function newFromId( $id ) {
		$dbw = wfGetDB( DB_REPLICA );
		$row = $dbw->selectRow(
			'code_repo',
			[
				'repo_id',
				'repo_name',
				'repo_path',
				'repo_viewvc',
				'repo_bugzilla' ],
			[ 'repo_id' => intval( $id ) ],
			__METHOD__ );

		if ( $row ) {
			return self::newFromRow( $row );
		} else {
			return null;
		}
	}

	/**
	 * @param stdClass $row
	 * @return CodeRepository
	 */
	static function newFromRow( $row ) {
		return new CodeRepository(
			intval( $row->repo_id ),
			$row->repo_name,
			$row->repo_path,
			$row->repo_viewvc,
			$row->repo_bugzilla
		);
	}

	/**
	 * @return array
	 */
	static function getRepoList() {
		$dbr = wfGetDB( DB_REPLICA );
		$options = [ 'ORDER BY' => 'repo_name' ];
		$res = $dbr->select( 'code_repo', '*', [], __METHOD__, $options );
		$repos = [];
		foreach ( $res as $row ) {
			$repos[] = self::newFromRow( $row );
		}
		return $repos;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return intval( $this->id );
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return string
	 */
	public function getPath() {
		return $this->path;
	}

	/**
	 * @return string
	 */
	public function getViewVcBase() {
		return $this->viewVc;
	}

	/**
	 * @return string
	 */
	public function getBugzillaBase() {
		return $this->bugzilla;
	}

	/**
	 * Return a bug URL or false
	 *
	 * @param int|string $bugId
	 * @return string|bool
	 */
	public function getBugPath( $bugId ) {
		if ( $this->bugzilla ) {
			return str_replace( '$1',
				urlencode( $bugId ), $this->bugzilla );
		}
		return false;
	}

	/**
	 * @return int
	 */
	public function getLastStoredRev() {
		$dbr = wfGetDB( DB_REPLICA );
		$row = $dbr->selectField(
			'code_rev',
			'MAX(cr_id)',
			[ 'cr_repo_id' => $this->getId() ],
			__METHOD__
		);
		return intval( $row );
	}

	/**
	 * @return array
	 */
	public function getAuthorList() {
		global $wgMemc;

		$key = $wgMemc->makeKey( 'codereview', 'authors', $this->getId() );
		$authors = $wgMemc->get( $key );
		if ( is_array( $authors ) ) {
			return $authors;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'code_rev',
			[ 'cr_author', 'MAX(cr_timestamp) AS time' ],
			[ 'cr_repo_id' => $this->getId() ],
			__METHOD__,
			[
				'GROUP BY' => 'cr_author',
				'ORDER BY' => 'cr_author',
				'LIMIT' => 500
			]
		);

		$authors = [];
		foreach ( $res as $row ) {
			if ( $row->cr_author !== null ) {
				$authors[] = [
					'author' => $row->cr_author,
					'lastcommit' => $row->time
				];
			}
		}

		$wgMemc->set( $key, $authors, 3600 * 24 );

		return $authors;
	}

	/**
	 * @return int
	 */
	public function getAuthorCount() {
		return count( $this->getAuthorList() );
	}

	/**
	 * Get a list of all tags in use in the repository
	 * @param bool $recache whether to get clean data
	 * @return array
	 */
	public function getTagList( $recache = false ) {
		global $wgMemc;

		$key = $wgMemc->makeKey( 'codereview', 'tags', $this->getId() );
		$tags = $wgMemc->get( $key );
		if ( is_array( $tags ) && !$recache ) {
			return $tags;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'code_tags',
			[ 'ct_tag', 'COUNT(*) AS revs' ],
			[ 'ct_repo_id' => $this->getId() ],
			__METHOD__,
			[
				'GROUP BY' => 'ct_tag',
				'ORDER BY' => 'revs DESC',
				'LIMIT' => 500
			]
		);

		$tags = [];
		foreach ( $res as $row ) {
			$tags[$row->ct_tag] = $row->revs;
		}

		$wgMemc->set( $key, $tags, 3600 * 3 );

		return $tags;
	}

	/**
	 * Load a particular revision out of the DB
	 * @param int|string $id
	 * @throws Exception
	 * @return CodeRevision
	 */
	public function getRevision( $id ) {
		if ( !$this->isValidRev( $id ) ) {
			return null;
		}
		$dbr = wfGetDB( DB_REPLICA );
		$row = $dbr->selectRow(
			'code_rev',
			'*',
			[
				'cr_id' => $id,
				'cr_repo_id' => $this->getId(),
			],
			__METHOD__
		);
		if ( !$row ) {
			throw new Exception( 'Failed to load expected revision data' );
		}
		return CodeRevision::newFromRow( $this, $row );
	}

	/**
	 * Returns the supplied revision ID as a string ready for output, including the
	 * appropriate (localisable) prefix (e.g. "r123" instead of 123).
	 *
	 * @param string $id
	 * @return string
	 */
	public function getRevIdString( $id ) {
		return wfMessage( 'code-rev-id', $id )->text();
	}

	/**
	 * Like getRevIdString(), but if more than one repository is defined
	 * on the wiki then it includes the repo name as a prefix to the revision ID
	 * (separated with a period).
	 * This ensures you get a unique reference, as the revision ID alone can be
	 * confusing (e.g. in emails, page titles etc.). If only one repository is
	 * defined then this returns the same as getRevIdString() as there
	 * is no ambiguity.
	 *
	 * @param string $id
	 * @return string
	 */
	public function getRevIdStringUnique( $id ) {
		$id = wfMessage( 'code-rev-id', $id )->text();

		// If there is more than one repo, use the repo name as well.
		$repos = self::getRepoList();
		if ( count( $repos ) > 1 ) {
			$id = $this->getName() . '.' . $id;
		}

		return $id;
	}

	/**
	 * @param int $rev Revision ID
	 * @param string $useCache 'skipcache' to avoid caching
	 *                   'cached' to *only* fetch if cached
	 * @return string|int The diff text on success, a DIFFRESULT_* constant on failure.
	 */
	public function getDiff( $rev, $useCache = '' ) {
		global $wgMemc, $wgCodeReviewMaxDiffPaths;

		$data = null;

		$rev1 = $rev - 1;
		$rev2 = $rev;

		// Check that a valid revision was specified.
		$revision = $this->getRevision( $rev );
		if ( $revision == null ) {
			$data = self::DIFFRESULT_BadRevision;
		} else {
			// Check that there is at least one, and at most $wgCodeReviewMaxDiffPaths
			// paths changed in this revision.

			$paths = $revision->getModifiedPaths();
			if ( !$paths->numRows() ) {
				$data = self::DIFFRESULT_NothingToCompare;
			} elseif ( $wgCodeReviewMaxDiffPaths > 0 && $paths->numRows() > $wgCodeReviewMaxDiffPaths ) {
				$data = self::DIFFRESULT_TooManyPaths;
			}
		}

		// If an error has occurred, return it.
		if ( $data !== null ) {
			return $data;
		}

		// Set up the cache key, which will be used both to check if already in the
		// cache, and to write the final result to the cache.
		$key = $wgMemc->makeKey( 'svn', md5( $this->path ), 'diff', $rev1, $rev2 );

		// If not set to explicitly skip the cache, get the current diff from memcached
		// directly.
		if ( $useCache === 'skipcache' ) {
			$data = null;
		} else {
			$data = $wgMemc->get( $key );
		}

		// If the diff hasn't already been retrieved from the cache, see if we can get
		// it from the DB.
		if ( !$data && $useCache != 'skipcache' ) {
			$dbr = wfGetDB( DB_REPLICA );
			$row = $dbr->selectRow(
				'code_rev',
				[ 'cr_diff', 'cr_flags' ],
				[ 'cr_repo_id' => $this->id, 'cr_id' => $rev, 'cr_diff IS NOT NULL' ],
				__METHOD__
			);
			if ( $row ) {
				$flags = explode( ',', $row->cr_flags );
				$data = $row->cr_diff;
				// If the text was fetched without an error, convert it
				if ( $data !== false && in_array( 'gzip', $flags ) ) {
					# Deal with optional compression of archived pages.
					# This can be done periodically via maintenance/compressOld.php, and
					# as pages are saved if $wgCompressRevisions is set.
					$data = gzinflate( $data );
				}
			}
		}

		// If the data was not already in the cache or in the DB, we need to retrieve
		// it from SVN.
		if ( !$data ) {
			// If the calling code is forcing a cache check, report that it wasn't
			// in the cache.
			if ( $useCache === 'cached' ) {
				$data = self::DIFFRESULT_NotInCache;

			// Otherwise, retrieve the diff using SubversionAdaptor.
			} else {
				$svn = SubversionAdaptor::newFromRepo( $this->path );
				$data = $svn->getDiff( '', $rev1, $rev2 );

				// If $data is blank, report the error that no data was returned.
				// TODO: Currently we can't tell the difference between an SVN/connection
				// failure and an empty diff. See if we can remedy this!
				if ( $data == '' ) {
					$data = self::DIFFRESULT_NoDataReturned;
				} else {
					// Otherwise, store the resulting diff to both the temporary cache and
					// permanent DB storage.
					// Store to cache
					$wgMemc->set( $key, $data, 3600 * 24 * 3 );

					// Permanent DB storage
					$storedData = $data;
					$flags = Revision::compressRevisionText( $storedData );
					$dbw = wfGetDB( DB_MASTER );
					$dbw->update(
						'code_rev',
						[ 'cr_diff' => $storedData, 'cr_flags' => $flags ],
						[ 'cr_repo_id' => $this->id, 'cr_id' => $rev ],
						__METHOD__
					);
				}
			}
		}

		return $data;
	}

	/**
	 * Set diff cache (for import operations)
	 * @param CodeRevision $codeRev
	 */
	public function setDiffCache( CodeRevision $codeRev ) {
		global $wgMemc;

		$rev1 = $codeRev->getId() - 1;
		$rev2 = $codeRev->getId();

		$svn = SubversionAdaptor::newFromRepo( $this->path );
		$data = $svn->getDiff( '', $rev1, $rev2 );
		// Store to cache
		$key = $wgMemc->makeKey( 'svn', md5( $this->path ), 'diff', $rev1, $rev2 );
		$wgMemc->set( $key, $data, 3600 * 24 * 3 );
		// Permanent DB storage
		$storedData = $data;
		$flags = Revision::compressRevisionText( $storedData );
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update(
			'code_rev',
			[ 'cr_diff' => $storedData, 'cr_flags' => $flags ],
			[ 'cr_repo_id' => $this->id, 'cr_id' => $codeRev->getId() ],
			__METHOD__
		);
	}

	/**
	 * Is the requested revid a valid revision to show?
	 * @return bool
	 * @param int $rev Rev ID to check
	 */
	public function isValidRev( $rev ) {
		$rev = intval( $rev );
		return ( $rev > 0 && $rev <= $this->getLastStoredRev() );
	}

	/**
	 * Link the $author to the wikiuser $user
	 * @param string $author
	 * @param User $user
	 * @return bool Success
	 */
	public function linkUser( $author, User $user ) {
		$userId = $user->getId();
		// We must link to an existing user
		if ( !$userId ) {
			return false;
		}
		$dbw = wfGetDB( DB_MASTER );
		// Insert in the auther -> user link row.
		// Skip existing rows.
		$dbw->insert(
			'code_authors',
			[
				'ca_repo_id'   => $this->getId(),
				'ca_author'    => $author,
				'ca_user'      => $userId,
				'ca_user_text' => $user->getName()
			],
			__METHOD__,
			[ 'IGNORE' ]
		);
		// If the last query already found a row, then update it.
		if ( !$dbw->affectedRows() ) {
			$dbw->update(
				'code_authors',
				[
					'ca_user'      => $userId,
					'ca_user_text' => $user->getName()
				],
				[
					'ca_repo_id'  => $this->getId(),
					'ca_author'   => $author,
				],
				__METHOD__
			);
		}
		self::$userLinks[$author] = $user;
		return ( $dbw->affectedRows() > 0 );
	}

	/**
	 * Remove local user links for $author
	 * @param string $author
	 * @return bool success
	 */
	public function unlinkUser( $author ) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'code_authors',
			[
				'ca_repo_id' => $this->getId(),
				'ca_author'  => $author,
			],
			__METHOD__
		);
		self::$userLinks[$author] = false;
		return ( $dbw->affectedRows() > 0 );
	}

	/**
	 * returns a User object if $author has a wikiuser associated,
	 * or false
	 *
	 * @param string $author
	 * @return User|bool
	 */
	public function authorWikiUser( $author ) {
		if ( isset( self::$userLinks[$author] ) ) {
			return self::$userLinks[$author];
		}

		$dbr = wfGetDB( DB_REPLICA );
		$wikiUser = $dbr->selectField(
			'code_authors',
			'ca_user_text',
			[
				'ca_repo_id' => $this->getId(),
				'ca_author'  => $author,
			],
			__METHOD__
		);
		$user = null;
		if ( $wikiUser !== false ) {
			$user = User::newFromName( $wikiUser );
		}
		if ( $user instanceof User ) {
			self::$userLinks[$author] = $user;
		} else {
			self::$userLinks[$author] = false;
		}
		return self::$userLinks[$author];
	}

	/**
	 * returns an author name if $name wikiuser has an author associated,
	 * or false
	 *
	 * @param string $name
	 * @return string|bool
	 */
	public function wikiUserAuthor( $name ) {
		if ( isset( self::$authorLinks[$name] ) ) {
			return self::$authorLinks[$name];
		}

		$dbr = wfGetDB( DB_REPLICA );
		self::$authorLinks[$name] = $dbr->selectField(
			'code_authors',
			'ca_author',
			[
				'ca_repo_id'   => $this->getId(),
				'ca_user_text' => $name,
			],
			__METHOD__
		);
		return self::$authorLinks[$name];
	}

	/**
	 * @param int|string $diff Error code (int) or diff text (string), as returned from getDiff()
	 * @return string (error message, or empty string if valid diff)
	 */
	public static function getDiffErrorMessage( $diff ) {
		global $wgCodeReviewMaxDiffPaths;

		if ( is_int( $diff ) ) {
			switch ( $diff ) {
				case self::DIFFRESULT_BadRevision:
					return 'Bad revision';
				case self::DIFFRESULT_NothingToCompare:
					return 'Nothing to compare';
				case self::DIFFRESULT_TooManyPaths:
					return 'Too many paths ($wgCodeReviewMaxDiffPaths = '
							. $wgCodeReviewMaxDiffPaths . ')';
				case self::DIFFRESULT_NoDataReturned:
					return 'No data returned - no diff data, or connection lost';
				case self::DIFFRESULT_NotInCache:
					return 'Not in cache';
				default:
					return 'Unknown reason!';
			}
		}

		// TODO: Should this return "", $diff or a message string, e.g. "OK"?
		return '';
	}
}
