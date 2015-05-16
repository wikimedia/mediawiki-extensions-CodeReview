<?php

/**
 * Repository administration
 */
class SpecialRepoAdmin extends SpecialPage {
	public function __construct() {
		parent::__construct( 'RepoAdmin', 'repoadmin' );
	}

	/**
	 * @param $subpage string
	 */
	public function execute( $subpage ) {
		global $wgRequest, $wgUser;

		$this->setHeaders();

		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return;
		}

		$repo = $wgRequest->getVal( 'repo', $subpage );
		if ( $repo == '' ) {
			$view = new RepoAdminListView( $this->getPageTitle() );
		} else {
			$view = new RepoAdminRepoView( $this->getPageTitle( $repo ), $repo );
		}
		$view->execute();
	}

	protected function getGroupName() {
		return 'developer';
	}
}

/**
 * View for viewing all of the repositories
 */
class RepoAdminListView {
	/**
	 * Reference to Special:RepoAdmin
	 * @var Title
	 */
	private $title;

	/**
	 * Constructor
	 * @param $t Title object referring to Special:RepoAdmin
	 */
	public function __construct( Title $t ) {
		$this->title = $t;
	}

	/**
	 * Get "create new repo" form
	 * @return String
	 */
	private function getForm() {
		global $wgScript;
		return Xml::fieldset( wfMessage( 'repoadmin-new-legend' )->text() ) .
			Xml::openElement( 'form', array( 'method' => 'get', 'action' => $wgScript ) ) .
			Html::hidden( 'title', $this->title->getPrefixedDBKey() ) .
			Xml::inputLabel( wfMessage( 'repoadmin-new-label' )->text(), 'repo', 'repo' ) .
			Xml::submitButton( wfMessage( 'repoadmin-new-button' )->text() ) .
			'</form></fieldset>';
	}

	public function execute() {
		global $wgOut;
		$wgOut->addHTML( $this->getForm() );
		$repos = CodeRepository::getRepoList();
		if ( !count( $repos ) ) {
			return;
		}
		$text = '';
		foreach ( $repos as $repo ) {
			$name = $repo->getName();
			$text .= "* [[Special:RepoAdmin/$name|$name]]\n";
		}
		$wgOut->addWikiText( $text );
	}
}

/**
 * View for editing a single repository
 */
class RepoAdminRepoView {
	/**
	 * Reference to Special:RepoAdmin
	 * @var Title
	 */
	private $title;

	/**
	 * Human-readable name of the repository
	 * @var String
	 */
	private $repoName;

	/**
	 * Actual repository object
	 */
	private $repo;

	/**
	 * @param $t Title Special page title (with repo subpage)
	 * @param $repo string
	 */
	public function __construct( Title $t, $repo ) {
		$this->title = $t;
		$this->repoName = $repo;
		$this->repo = CodeRepository::newFromName( $repo );
	}

	function execute() {
		global $wgOut, $wgRequest, $wgUser;
		$repoExists = (bool)$this->repo;
		$repoPath = $wgRequest->getVal( 'wpRepoPath', $repoExists ? $this->repo->getPath() : '' );
		$bugPath = $wgRequest->getVal( 'wpBugPath', $repoExists ? $this->repo->getBugzillaBase() : '' );
		$viewPath = $wgRequest->getVal( 'wpViewPath', $repoExists ? $this->repo->getViewVcBase() : '' );
		if ( $wgRequest->wasPosted() && $wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ), $this->repoName ) ) {
			// @todo log
			$dbw = wfGetDB( DB_MASTER );
			if ( $repoExists ) {
				$dbw->update(
					'code_repo',
					array(
						'repo_path' => $repoPath,
						'repo_viewvc' => $viewPath,
						'repo_bugzilla' => $bugPath
					),
					array( 'repo_id' => $this->repo->getId() ),
					__METHOD__
				);
			} else {
				$dbw->insert(
					'code_repo',
					array(
						'repo_name' => $this->repoName,
						'repo_path' => $repoPath,
						'repo_viewvc' => $viewPath,
						'repo_bugzilla' => $bugPath
					),
					__METHOD__
				);
			}
			$wgOut->wrapWikiMsg( '<div class="successbox">$1</div>', array( 'repoadmin-edit-sucess', $this->repoName ) );
			return;
		}
		$wgOut->addHTML(
			Xml::fieldset( wfMessage( 'repoadmin-edit-legend', $this->repoName )->text() ) .
			Xml::openElement( 'form', array( 'method' => 'post', 'action' => $this->title->getLocalURL() ) ) .
			Xml::buildForm(
				array(
					'repoadmin-edit-path' =>
						Xml::input( 'wpRepoPath', 60, $repoPath, array( 'dir' => 'ltr') ),
					'repoadmin-edit-bug' =>
						Xml::input( 'wpBugPath', 60, $bugPath, array( 'dir' => 'ltr') ),
					'repoadmin-edit-view' =>
						Xml::input( 'wpViewPath', 60, $viewPath, array( 'dir' => 'ltr') ) ) ) .
			Html::hidden( 'wpEditToken', $wgUser->getEditToken( $this->repoName ) ) .
			Xml::submitButton( wfMessage( 'repoadmin-edit-button' )->text() ) .
			'</form></fieldset>'
		);
	}
}
