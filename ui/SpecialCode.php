<?php

/**
 * Main UI entry point. This calls the appropriate CodeView subclass and runs it
 */
class SpecialCode extends SpecialPage {
	public function __construct() {
		parent::__construct( 'Code' , 'codereview-use' );
	}

	public function execute( $subpage ) {
		global $wgOut, $wgUser, $wgExtensionAssetsPath, $wgCodeReviewStyleVersion;

		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return;
		}

		$this->setHeaders();
		$wgOut->addStyle( "$wgExtensionAssetsPath/CodeReview/codereview.css?$wgCodeReviewStyleVersion" );

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
			switch( count( $params ) ) {
			case 1:
				$view = new CodeRevisionListView( $params[0] );
				break;
			case 2:
				if ( $params[1] === 'tag' ) {
					$view = new CodeTagListView( $params[0] );
					break;
				} elseif ( $params[1] === 'author' ) {
					$view = new CodeAuthorListView( $params[0] );
					break;
				} elseif ( $params[1] === 'stats' ) {
					$view = new CodeRepoStatsView( $params[0] );
					break;
				} elseif ( $params[1] === 'status' ) {
					$view = new CodeStatusListView( $params[0] );
					break;
				} elseif ( $params[1] === 'comments' ) {
					$view = new CodeCommentsListView( $params[0] );
					break;
				} elseif ( $params[1] === 'statuschanges' ) {
					$view = new CodeStatusChangeListView( $params[0] );
					break;
				} elseif ( $params[1] === 'releasenotes' ) {
					$view = new CodeReleaseNotes( $params[0] );
					break;
				} else if ( $wgRequest->wasPosted() && !$wgRequest->getCheck( 'wpPreview' ) ) {
					# This is not really a view, but we return it nonetheless.
					# Add any tags, Set status, Adds comments
					$view = new CodeRevisionCommitter( $params[0], $params[1] );
					break;
				} else { // revision details
					$view = new CodeRevisionView( $params[0], $params[1] );
					break;
				}
			case 3:
				if ( $params[1] === 'tag' ) {
					$view = new CodeRevisionTagView( $params[0], $params[2] );
					break;
				} elseif ( $params[1] === 'author' ) {
					$view = new CodeRevisionAuthorView( $params[0], $params[2] );
					break;
				} elseif ( $params[1] === 'status' ) {
					$view = new CodeRevisionStatusView( $params[0], $params[2] );
					break;
				} elseif ( $params[1] === 'comments' ) {
					$view = new CodeCommentAuthorListView( $params[0], $params[2]  );
					break;
				} else {
					# Nonsense parameters, back out
					if ( empty( $params[1] ) ) {
						$view = new CodeRevisionListView( $params[0] );
					} else {
						$view = new CodeRevisionView( $params[0], $params[1] );
					}
					break;
				}
			case 4:
				if ( $params[1] == 'author' && $params[3] == 'link' ) {
					$view = new CodeRevisionAuthorLink( $params[0], $params[2] );
					break;
				}
			default:
				if ( $params[2] == 'reply' ) {
					$view = new CodeRevisionView( $params[0], $params[1], $params[3] );
					break;
				}
				return null;
			}

			// If a repository was specified, but it does not exist, redirect to the
			// repository list with an appropriate message.
			if ( !$view->mRepo ) {
				$view = new CodeRepoListView();
				global $wgOut;
				$wgOut->addWikiMsg( 'code-repo-not-found', wfEscapeWikiText( $params[0] ) );
			}
		}
		return $view;
	}
}

/**
 * Extended by CodeRevisionListView and CodeRevisionView
 */
abstract class CodeView {
	/**
	 * @var CodeRepository
	 */
	var $mRepo;

	function __construct() {
		global $wgUser;
		$this->skin = $wgUser->getSkin();
	}

	function validPost( $permission ) {
		global $wgRequest, $wgUser;
		return $wgRequest->wasPosted()
			&& $wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) )
			&& $wgUser->isAllowed( $permission );
	}

	abstract function execute();

	function authorLink( $author, $extraParams = array() ) {
		$repo = $this->mRepo->getName();
		$special = SpecialPage::getTitleFor( 'Code', "$repo/author/$author" );
		return $this->skin->link( $special, htmlspecialchars( $author ), array(), $extraParams );
	}

	function statusDesc( $status ) {
		return wfMsg( "code-status-$status" );
	}

	function formatMessage( $text ) {
		$text = nl2br( htmlspecialchars( $text ) );
		$linker = new CodeCommentLinkerHtml( $this->mRepo );
		return $linker->link( $text );
	}

	function messageFragment( $value ) {
		global $wgLang;
		$message = trim( $value );
		$lines = explode( "\n", $message, 2 );
		$first = $lines[0];

		$html = $this->formatMessage( $first );
		$truncated = $wgLang->truncateHtml( $html, 80 );

		if ( count( $lines ) > 1  ) { // If multiline, we might want to add an ellipse
			$ellipse = wfMsgExt( 'ellipsis', array() );

			$len = strlen( $truncated );
			if ( substr( $truncated, $len ) !== $ellipse ) { // Don't add if the end is already an ellipse
				$truncated .= $ellipse;
			}
		}

	    return $truncated;
	}
	/*
	 * Formatted HTML array for properties display
	 * @param array fields : 'propname' => HTML data
	 */
	function formatMetaData( $fields ) {
		$html = '<table class="mw-codereview-meta">';
		foreach ( $fields as $label => $data ) {
			$html .= "<tr><td>" . wfMsgHtml( $label ) . "</td><td>$data</td></tr>\n";
		}
		return $html . "</table>\n";
	}

	function getRepo() {
		if ( $this->mRepo ) {
			return $this->mRepo;
		}
		return false;
	}
}

abstract class SvnTablePager extends TablePager {

	/**
	 * @var CodeRepository
	 */
	protected $mRepo;

	/**
	 * @var CodeView
	 */
	protected $mView;

	/**
	 * @param  $view CodeView
	 *
	 */
	function __construct( $view ) {
		global $IP;
		$this->mView = $view;
		$this->mRepo = $view->mRepo;
		$this->mDefaultDirection = true;
		$this->mCurSVN = SpecialVersion::getSvnRevision( $IP );
		parent::__construct();
	}

	function isFieldSortable( $field ) {
		return $field == $this->getDefaultSort();
	}

	function formatRevValue( $name, $value, $row ) {
		return $this->formatValue( $name, $value );
	}

	// Note: this function is poorly factored in the parent class
	function formatRow( $row ) {
		$css = "mw-codereview-status-{$row->cr_status}";
		$s = "<tr class=\"$css\">\n";
		// Some of this stolen from Pager.php...sigh
		$fieldNames = $this->getFieldNames();
		$this->mCurrentRow = $row;  # In case formatValue needs to know
		foreach ( $fieldNames as $field => $name ) {
			$value = isset( $row->$field ) ? $row->$field : null;
			$formatted = strval( $this->formatRevValue( $field, $value, $row ) );
			if ( $formatted == '' ) {
				$formatted = '&#160;';
			}
			$class = 'TablePager_col_' . htmlspecialchars( $field );
			$s .= "<td class=\"$class\">$formatted</td>\n";
		}
		$s .= "</tr>\n";
		return $s;
	}
}
