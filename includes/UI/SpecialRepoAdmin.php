<?php

namespace MediaWiki\Extension\CodeReview\UI;

use MediaWiki\Extension\CodeReview\Backend\CodeRepository;
use SpecialPage;

/**
 * Repository administration
 */
class SpecialRepoAdmin extends SpecialPage {
	public function __construct() {
		parent::__construct( 'RepoAdmin', 'repoadmin' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Return an array of subpages that this special page will accept.
	 *
	 * @return string[] subpages
	 */
	public function getSubpagesForPrefixSearch() {
		$repos = CodeRepository::getRepoList();
		if ( count( $repos ) ) {
			$retVal = [];
			foreach ( $repos as $repo ) {
				$retVal[] = $repo->getName();
			}
			sort( $retVal );
			return $retVal;
		}
		return [];
	}

	/**
	 * @param string $subpage
	 */
	public function execute( $subpage ) {
		$this->setHeaders();

		$this->checkPermissions();

		$repo = $this->getRequest()->getVal( 'repo', $subpage );
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
