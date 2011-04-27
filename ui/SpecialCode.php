<?php

/**
 * Main UI entry point. This calls the appropriate CodeView subclass and runs it
 */
class SpecialCode extends SpecialPage {
	public function __construct() {
		parent::__construct( 'Code' , 'codereview-use' );
	}

	public function execute( $subpage ) {
		global $wgOut, $wgUser;

		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return;
		}

		$this->setHeaders();
		// Base styles used for all code review UI actions.
		$wgOut->addModules( 'ext.codereview' );
		$wgOut->addModules( 'ext.codereview.tooltips' );
		$wgOut->addModuleStyles( 'ext.codereview.styles' );

		$view = $this->getViewFrom( $subpage );
		if( $view ) {
			$view->execute();
		} else {
			$wgOut->addWikiText( wfMsg( 'nosuchactiontext' ) );
			$wgOut->returnToMain( null, $this->getTitle() );
			return;
		}

		// Add subtitle for easy navigation
		if ( $view instanceof CodeView ) {
			$repo = $view->getRepo();

			if ( $repo ) {
				$wgOut->setSubtitle(
					wfMsgExt( 'codereview-subtitle', 'parse', CodeRepoListView::getNavItem( $repo ) )
				);
			}
		}
	}

	/**
	 * Get a view object from a sub page path.
	 * @return CodeView object or null if no valid action could be found
	 */
	private function getViewFrom( $subpage ) {
		global $wgRequest;

		# Remove stray slashes
		$subpage = preg_replace( '/\/$/', '', $subpage );
		if ( $subpage == '' ) {
			$view = new CodeRepoListView();
		} else {
			$params = explode( '/', $subpage );

			$repo = CodeRepository::newFromName( $params[0] );
			// If a repository was specified, but it does not exist, redirect to the
			// repository list with an appropriate message.
			if ( !$repo ) {
				$view = new CodeRepoListView();
				global $wgOut;
				$wgOut->addWikiMsg( 'code-repo-not-found', wfEscapeWikiText( $params[0] ) );
				return $view;
			}

			switch( count( $params ) ) {
			case 1:
				$view = new CodeRevisionListView( $repo );
				break;
			case 2:
				if ( $params[1] === 'tag' ) {
					$view = new CodeTagListView( $repo );
					break;
				} elseif ( $params[1] === 'author' ) {
					$view = new CodeAuthorListView( $repo );
					break;
				} elseif ( $params[1] === 'stats' ) {
					$view = new CodeRepoStatsView( $repo );
					break;
				} elseif ( $params[1] === 'status' ) {
					$view = new CodeStatusListView( $repo );
					break;
				} elseif ( $params[1] === 'comments' ) {
					$view = new CodeCommentsListView( $repo );
					break;
				} elseif ( $params[1] === 'statuschanges' ) {
					$view = new CodeStatusChangeListView( $repo );
					break;
				} elseif ( $params[1] === 'releasenotes' ) {
					$view = new CodeReleaseNotes( $repo );
					break;
				} else if ( $wgRequest->wasPosted() && !$wgRequest->getCheck( 'wpPreview' ) ) {
					# This is not really a view, but we return it nonetheless.
					# Add any tags, Set status, Adds comments
					$view = new CodeRevisionCommitter( $repo, $params[1] );
					break;
				} else { // revision details
					$view = new CodeRevisionView( $repo, $params[1] );
					break;
				}
			case 3:
				if ( $params[1] === 'tag' ) {
					$view = new CodeRevisionTagView( $repo, $params[2] );
					break;
				} elseif ( $params[1] === 'author' ) {
					$view = new CodeRevisionAuthorView( $repo, $params[2] );
					break;
				} elseif ( $params[1] === 'status' ) {
					$view = new CodeRevisionStatusView( $repo, $params[2] );
					break;
				} elseif ( $params[1] === 'comments' ) {
					$view = new CodeCommentsListView( $repo );
					break;
				} else {
					# Nonsense parameters, back out
					if ( empty( $params[1] ) ) {
						$view = new CodeRevisionListView( $repo );
					} else {
						$view = new CodeRevisionView( $repo, $params[1] );
					}
					break;
				}
			case 4:
				if ( $params[1] === 'author' && $params[3] === 'link' ) {
					$view = new CodeRevisionAuthorLink( $repo, $params[2] );
					break;
				} elseif ( $params[1] === 'comments' ) {
					$view = new CodeCommentsAuthorListView( $repo, $params[3]  );
					break;
				} elseif ( $params[1] === 'statuschanges' ) {
					$view = new CodeStatusChangeAuthorListView( $repo, $params[3] );
					break;
				}
			default:
				if ( $params[2] == 'reply' ) {
					$view = new CodeRevisionView( $repo, $params[1], $params[3] );
					break;
				}
				return null;
			}
		}
		return $view;
	}
}

