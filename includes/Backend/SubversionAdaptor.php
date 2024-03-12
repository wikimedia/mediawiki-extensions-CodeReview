<?php

namespace MediaWiki\Extension\CodeReview\Backend;

abstract class SubversionAdaptor {
	/**
	 * @var string
	 */
	protected $mRepoPath;

	/**
	 * @param string $repo
	 * @return SubversionAdaptor
	 */
	public static function newFromRepo( $repo ) {
		global $wgSubversionProxy, $wgSubversionProxyTimeout;
		if ( $wgSubversionProxy ) {
			return new SubversionProxy( $repo, $wgSubversionProxy, $wgSubversionProxyTimeout );
		}

		if ( function_exists( 'svn_log' ) ) {
			return new SubversionPecl( $repo );
		}

		return new SubversionShell( $repo );
	}

	/**
	 * @param string $repoPath Path to SVN Repo
	 */
	public function __construct( $repoPath ) {
		$this->mRepoPath = $repoPath;
	}

	abstract public function canConnect();

	/**
	 * @param string $path
	 * @param int|null $rev
	 */
	abstract public function getFile( $path, $rev = null );

	/**
	 * @param string $path
	 * @param int|null $rev1
	 * @param int|null $rev2
	 */
	abstract public function getDiff( $path, $rev1, $rev2 );

	/**
	 * @param string $path
	 * @param int|null $rev
	 */
	abstract public function getDirList( $path, $rev = null );

	/**
	 * @param string $path
	 * @param int|null $startRev
	 * @param int|null $endRev
	 */
	abstract public function getLog( $path, $startRev = null, $endRev = null );

	/**
	 * @param int $rev
	 * @param int $default
	 * @return int
	 */
	protected function _rev( $rev, $default ) {
		if ( $rev === null ) {
			return $default;
		}

		return intval( $rev );
	}
}
