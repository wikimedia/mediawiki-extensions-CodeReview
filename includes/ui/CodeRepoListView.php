<?php

/**
 * Class for showing the list of repositories, if none was specified
 */
class CodeRepoListView {
	public function execute() {
		global $wgOut;
		$repos = CodeRepository::getRepoList();
		if ( !count( $repos ) ) {
			global $wgUser;
			$wgOut->addWikiMsg( 'code-no-repo' );

			if ( $wgUser->isAllowed( 'repoadmin' ) ) {
				$wgOut->addWikiMsg( 'code-create-repo' );
			} else {
				$wgOut->addWikiMsg( 'code-need-repoadmin-rights' );

				if ( !count( User::getGroupsWithPermission( 'repoadmin' ) ) ) {
					$wgOut->addWikiMsg( 'code-need-group-with-rights' );
				}
			}
			return;
		}
		$text = '';
		foreach ( $repos as $repo ) {
			$text .= '* ' . self::getNavItem( $repo ) . "\n";
		}
		$wgOut->addWikiText( $text );
	}

	/**
	 * @param CodeRepository $repo
	 * @return string
	 */
	public static function getNavItem( $repo ) {
		global $wgLang, $wgUser;
		$name = $repo->getName();

		$code = SpecialPage::getTitleFor( 'Code', $name );
		$links[] = "[[$code/comments|" . wfMessage( 'code-notes' )->escaped() . ']]';
		$links[] = "[[$code/statuschanges|" . wfMessage( 'code-statuschanges' )->escaped() . ']]';
		if ( $wgUser->getId() ) {
			$author = $repo->wikiUserAuthor( $wgUser->getName() );
			if ( $author !== false ) {
				$links[] = "[[$code/author/$author|" . wfMessage( 'code-mycommits' )->escaped() . ']]';
			}
		}

		if ( $wgUser->isAllowed( 'codereview-post-comment' ) ) {
			$userName = $wgUser->getName();
			$links[] = "[[$code/comments/author/$userName|" . wfMessage( 'code-mycomments' )->escaped() .
				']]';
		}

		$links[] = "[[$code/tag|" . wfMessage( 'code-tags' )->escaped() . ']]';
		$links[] = "[[$code/author|" . wfMessage( 'code-authors' )->escaped() . ']]';
		$links[] = "[[$code/status|" . wfMessage( 'code-status' )->escaped() . ']]';
		$links[] = "[[$code/releasenotes|" . wfMessage( 'code-releasenotes' )->escaped() . ']]';
		$links[] = "[[$code/stats|" . wfMessage( 'code-stats' )->escaped() . ']]';
		if ( $wgUser->isAllowed( 'repoadmin' ) ) {
			$links[] = "[[Special:RepoAdmin/$name|" . wfMessage( 'repoadmin-nav' )->escaped() . ']]';
		}
		$text = "'''[[$code|$name]]''' " .
			wfMessage( 'parentheses', $wgLang->pipeList( $links ) )->text();
		return $text;
	}
}
