<?php

namespace MediaWiki\Extension\CodeReview\Backend;

use Exception;
use MediaWiki\MediaWikiServices;

/**
 * Using a remote JSON proxy
 */
class SubversionProxy extends SubversionAdaptor {

	private string $mProxy;

	private int $mTimeout;

	/**
	 * @param CodeRepository|string $repo
	 * @param string $proxy
	 * @param int $timeout
	 */
	public function __construct( $repo, $proxy, $timeout = 30 ) {
		parent::__construct( $repo );
		$this->mProxy = $proxy;
		$this->mTimeout = $timeout;
	}

	public function canConnect() {
		// TODO!
		return true;
	}

	/**
	 * @param string $path
	 * @param null $rev
	 *
	 * @return never
	 * @throws Exception
	 */
	public function getFile( $path, $rev = null ) {
		throw new Exception( 'NYI' );
	}

	/** @inheritDoc */
	public function getDiff( $path, $rev1, $rev2 ) {
		return $this->_proxy( [
			'action' => 'diff',
			'base' => $this->mRepoPath,
			'path' => $path,
			'rev1' => $rev1,
			'rev2' => $rev2
		] );
	}

	/** @inheritDoc */
	public function getLog( $path, $startRev = null, $endRev = null ) {
		return $this->_proxy( [
			'action' => 'log',
			'base' => $this->mRepoPath,
			'path' => $path,
			'start' => $startRev,
			'end' => $endRev
		] );
	}

	/** @inheritDoc */
	public function getDirList( $path, $rev = null ) {
		return $this->_proxy( [
			'action' => 'list',
			'base' => $this->mRepoPath,
			'path' => $path,
			'rev' => $rev
		] );
	}

	/**
	 * @param array $params
	 * @return mixed
	 */
	protected function _proxy( $params ) {
		foreach ( $params as $key => $val ) {
			if ( $val === null ) {
				// Don't pass nulls to remote
				unset( $params[$key] );
			}
		}
		$target = $this->mProxy . '?' . wfArrayToCgi( $params );
		$blob = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->get( $target, [ 'timeout' => $this->mTimeout ], __METHOD__ );
		if ( $blob === null ) {
			throw new Exception( 'SVN proxy error' );
		}
		return unserialize( $blob );
	}
}
