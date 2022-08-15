<?php

namespace MediaWiki\Extension\CodeReview\UI;

use HTMLForm;
use MediaWiki\Extension\CodeReview\Backend\CodeRepository;
use Title;
use User;

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
	 * @var string
	 */
	private $repoName;

	/**
	 * Actual repository object
	 * @var CodeRepository
	 */
	private $repo;

	/**
	 * @var User
	 */
	private $user;

	/**
	 * @param Title $t Special page title (with repo subpage)
	 * @param string $repo
	 * @param User $user
	 */
	public function __construct( Title $t, $repo, $user ) {
		$this->title = $t;
		$this->repoName = $repo;
		$this->repo = CodeRepository::newFromName( $repo );
		$this->user = $user;
	}

	public function execute() {
		global $wgOut, $wgRequest;
		$repoExists = (bool)$this->repo;
		$repoPath = $wgRequest->getVal( 'wpRepoPath', $repoExists ? $this->repo->getPath() : '' );
		$bugPath = $wgRequest->getVal( 'wpBugPath',
			$repoExists ? $this->repo->getBugzillaBase() : '' );
		$viewPath = $wgRequest->getVal( 'wpViewPath',
			$repoExists ? $this->repo->getViewVcBase() : '' );
		if ( $wgRequest->wasPosted()
			&& $this->user->matchEditToken( $wgRequest->getVal( 'wpEditToken' ), $this->repoName )
		) {
			// @todo log
			$dbw = wfGetDB( DB_PRIMARY );
			if ( $repoExists ) {
				$dbw->update(
					'code_repo',
					[
						'repo_path' => $repoPath,
						'repo_viewvc' => $viewPath,
						'repo_bugzilla' => $bugPath
					],
					[ 'repo_id' => $this->repo->getId() ],
					__METHOD__
				);
			} else {
				$dbw->insert(
					'code_repo',
					[
						'repo_name' => $this->repoName,
						'repo_path' => $repoPath,
						'repo_viewvc' => $viewPath,
						'repo_bugzilla' => $bugPath
					],
					__METHOD__
				);
			}
			$wgOut->wrapWikiMsg( '<div class="successbox">$1</div>',
				[ 'repoadmin-edit-sucess', $this->repoName ] );
			return;
		}
		$formDescriptor = [
			'repoadmin-edit-path' => [
				'type' => 'text',
				'name' => 'wpRepoPath',
				'size' => 60,
				'default' => $repoPath,
				'dir' => 'ltr',
				'label-message' => 'repoadmin-edit-path'
			],
			'repoadmin-edit-bug' => [
				'type' => 'text',
				'name' => 'wpBugPath',
				'size' => 60,
				'default' => $bugPath,
				'dir' => 'ltr',
				'label-message' => 'repoadmin-edit-bug'
			],
			'repoadmin-edit-view' => [
				'type' => 'text',
				'name' => 'wpViewPath',
				'size' => 60,
				'default' => $viewPath,
				'dir' => 'ltr',
				'label-message' => 'repoadmin-edit-view'
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $wgOut->getContext() );
		$htmlForm
			->addHiddenField( 'wpEditToken', $this->user->getEditToken( $this->repoName ) )
			->setAction( $this->title->getLocalURL() )
			->setMethod( 'post' )
			->setSubmitTextMsg( 'repoadmin-edit-button' )
			->setWrapperLegend( wfMessage( 'repoadmin-edit-legend', $this->repoName )->text() )
			->prepareForm()
			->displayForm( false );
	}
}
