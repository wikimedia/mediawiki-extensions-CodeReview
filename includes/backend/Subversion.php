<?php


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
		} elseif ( function_exists( 'svn_log' ) ) {
			return new SubversionPecl( $repo );
		} else {
			return new SubversionShell( $repo );
		}
	}

	/**
	 * @param string $repoPath Path to SVN Repo
	 */
	public function __construct( $repoPath ) {
		$this->mRepoPath = $repoPath;
	}

	abstract public function canConnect();

	abstract public function getFile( $path, $rev = null );

	abstract public function getDiff( $path, $rev1, $rev2 );

	abstract public function getDirList( $path, $rev = null );

	abstract public function getLog( $path, $startRev = null, $endRev = null );

	protected function _rev( $rev, $default ) {
		if ( $rev === null ) {
			return $default;
		} else {
			return intval( $rev );
		}
	}
}

/**
 * Using the thingy-bobber
 */
class SubversionShell extends SubversionAdaptor {
	const MIN_MEMORY = 204800;

	public function __construct( $repo ) {
		parent::__construct( $repo );
		global $wgMaxShellMemory;
		if ( $wgMaxShellMemory < self::MIN_MEMORY ) {
			$wgMaxShellMemory = self::MIN_MEMORY;
			wfDebug( __METHOD__ . " raised wgMaxShellMemory to $wgMaxShellMemory\n" );
		}
	}

	public function canConnect() {
		$command = sprintf(
			'svn info %s %s',
			$this->getExtraArgs(),
			wfEscapeShellArg( $this->mRepoPath )
		);

		$result = wfShellExec( $command );
		if ( $result == '' ) {
			return false;
		} elseif ( strpos( $result, 'No repository found' ) !== false ) {
			return false;
		} else {
			return true;
		}
	}

	public function getFile( $path, $rev = null ) {
		if ( $rev ) {
			$path .= "@$rev";
		}
		$command = sprintf(
			'svn cat %s %s',
			$this->getExtraArgs(),
			wfEscapeShellArg( $this->mRepoPath . $path ) );

		return wfShellExec( $command );
	}

	public function getDiff( $path, $rev1, $rev2 ) {
		$command = sprintf(
			'svn diff -r%d:%d %s %s',
			intval( $rev1 ),
			intval( $rev2 ),
			$this->getExtraArgs(),
			wfEscapeShellArg( $this->mRepoPath . $path )
		);

		return wfShellExec( $command );
	}

	public function getLog( $path, $startRev = null, $endRev = null ) {
		$lang = wfIsWindows() ? '' : 'LC_ALL=en_US.utf-8 ';
		$command = sprintf(
			"{$lang}svn log -v -r%s:%s %s %s",
			wfEscapeShellArg( $this->_rev( $startRev, 'BASE' ) ),
			wfEscapeShellArg( $this->_rev( $endRev, 'HEAD' ) ),
			$this->getExtraArgs(),
			wfEscapeShellArg( $this->mRepoPath . $path ) );

		$lines = explode( "\n", wfShellExec( $command ) );
		$out = [];

		$divider = str_repeat( '-', 72 );
		$formats = [
			'rev' => '/^r(\d+)$/',
			'author' => '/^(.*)$/',
			'date' => '/^(?:(.*?) )?\(.*\)$/', // account for '(no date)'
			'lines' => '/^(\d+) lines?$/',
		];
		$state = "start";
		foreach ( $lines as $line ) {
			$line = rtrim( $line );

			switch ( $state ) {
			case 'start':
				if ( $line == $divider ) {
					$state = 'revdata';
					break;
				} else {
					return $out;
					# throw new Exception( "Unexpected start line: $line" );
				}
			case 'revdata':
				if ( $line == '' ) {
					$state = 'done';
					break;
				}
				$data = [];
				$bits = explode( ' | ', $line );
				$i = 0;
				foreach ( $formats as $key => $regex ) {
					$text = $bits[$i++];
					$matches = [];
					if ( preg_match( $regex, $text, $matches ) ) {
						$data[$key] = $matches[1];
					} else {
						throw new Exception(
							"Unexpected format for $key in '$text'" );
					}
				}
				$data['msg'] = '';
				$data['paths'] = [];
				$state = 'changedpaths';
				break;
			case 'changedpaths':
				if ( $line == 'Changed paths:' ) { // broken when svn messages are not in English
					$state = 'path';
				} elseif ( $line == '' ) {
					// No changed paths?
					$state = 'msg';
				} else {
					throw new Exception(
						"Expected 'Changed paths:' or '', got '$line'" );
				}
				break;
			case 'path':
				if ( $line == '' ) {
					// Out of paths. Move on to the message...
					$state = 'msg';
				} else {
					$matches = [];
					if ( preg_match( '/^   (.) (.*)$/', $line, $matches ) ) {
						$data['paths'][] = [
							'action' => $matches[1],
							'path' => $matches[2]
						];
					}
				}
				break;
			case 'msg':
				$data['msg'] .= $line;
				if ( --$data['lines'] ) {
					$data['msg'] .= "\n";
				} else {
					unset( $data['lines'] );
					$out[] = $data;
					$state = 'start';
				}
				break;
			case 'done':
				throw new Exception( "Unexpected input after end: $line" );
			default:
				throw new Exception( "Invalid state '$state'" );
			}
		}

		return $out;
	}

	public function getDirList( $path, $rev = null ) {
		$command = sprintf(
			'svn list --xml -r%s %s %s',
			wfEscapeShellArg( $this->_rev( $rev, 'HEAD' ) ),
			$this->getExtraArgs(),
			wfEscapeShellArg( $this->mRepoPath . $path )
		);
		$document = new DOMDocument();

		$listXml = wfShellExec( $command );
		if ( !$listXml || !$document->loadXML( $listXml ) ) {
			// svn list --xml returns invalid XML if the file does not exist
			// FIXME: report bug upstream
			return false;
		}

		$entries = $document->getElementsByTagName( 'entry' );
		$result = [];
		foreach ( $entries as $entry ) {
			$item = [];
			$item['type'] = $entry->getAttribute( 'kind' );
			foreach ( $entry->childNodes as $child ) {
				switch ( $child->nodeName ) {
				case 'name':
					$item['name'] = $child->textContent;
					break;
				case 'size':
					$item['size'] = intval( $child->textContent );
					break;
				case 'commit':
					$item['created_rev'] = intval( $child->getAttribute( 'revision' ) );
					foreach ( $child->childNodes as $commitEntry ) {
						switch ( $commitEntry->nodeName ) {
						case 'author':
							$item['last_author'] = $commitEntry->textContent;
							break;
						case 'date':
							$item['time_t'] = wfTimestamp( TS_UNIX, $commitEntry->textContent );
							break;
						}
					}
					break;
				}
			}
			$result[] = $item;
		}
		return $result;
	}

	/**
	 * Returns a string of extra arguments to be passed into the shell commands
	 * @return string
	 */
	private function getExtraArgs() {
		global $wgSubversionOptions, $wgSubversionUser, $wgSubversionPassword;
		$args = $wgSubversionOptions;
		if ( $wgSubversionUser ) {
			$args .= ' --username ' . wfEscapeShellArg( $wgSubversionUser )
				. ' --password ' . wfEscapeShellArg( $wgSubversionPassword );
		}
		return $args;
	}
}

/**
 * Using a remote JSON proxy
 */
class SubversionProxy extends SubversionAdaptor {
	public function __construct( $repo, $proxy, $timeout = 30 ) {
		parent::__construct( $repo );
		$this->mProxy = $proxy;
		$this->mTimeout = $timeout;
	}

	public function canConnect() {
		// TODO!
		return true;
	}

	public function getFile( $path, $rev = null ) {
		throw new Exception( 'NYI' );
	}

	public function getDiff( $path, $rev1, $rev2 ) {
		return $this->_proxy( [
			'action' => 'diff',
			'base' => $this->mRepoPath,
			'path' => $path,
			'rev1' => $rev1,
			'rev2' => $rev2
		] );
	}

	public function getLog( $path, $startRev = null, $endRev = null ) {
		return $this->_proxy( [
			'action' => 'log',
			'base' => $this->mRepoPath,
			'path' => $path,
			'start' => $startRev,
			'end' => $endRev
		] );
	}

	public function getDirList( $path, $rev = null ) {
		return $this->_proxy( [
			'action' => 'list',
			'base' => $this->mRepoPath,
			'path' => $path,
			'rev' => $rev
		] );
	}

	protected function _proxy( $params ) {
		foreach ( $params as $key => $val ) {
			if ( $val === null ) {
				// Don't pass nulls to remote
				unset( $params[$key] );
			}
		}
		$target = $this->mProxy . '?' . wfArrayToCgi( $params );
		$blob = Http::get( $target, $this->mTimeout );
		if ( $blob === false ) {
			throw new Exception( 'SVN proxy error' );
		}
		$data = unserialize( $blob );
		return $data;
	}
}
