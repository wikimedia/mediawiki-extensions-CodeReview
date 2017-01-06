<?php
/**
 * Class containing hooked functions used by the CodeReview extension.
 * All functions are public and static.
 *
 * @file
 */
class CodeReviewHooks {

	/**
	 * Add state messages to RL
	 * @todo this should use a custom ResourceLoaderModule class instead
	 */
	public static function onExtensionFunctions() {
		global $wgResourceModules;

		$wgResourceModules['ext.codereview.tooltips']['messages'] = array_merge(
			CodeRevision::getPossibleStateMessageKeys(),
			array( 'code-tooltip-withsummary', 'code-tooltip-withoutsummary' )
		);
	}

	/**
	 * Performs database updates (initial table creation on first install,
	 * addition of new tables/fields/indexes for old installs that are being
	 * upgraded) when the user runs the core MediaWiki updater script,
	 * /maintenance/update.php.
	 *
	 * Only MySQL(/MariaDB) and SQLite are supported at the moment.
	 *
	 * @param $updater DatabaseUpdater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$base = __DIR__;
		switch ( $updater->getDB()->getType() ) {
		case 'mysql':
			$updater->addExtensionTable( 'code_rev', "$base/codereview.sql" ); // Initial install tables
			$updater->addExtensionField( 'code_rev', 'cr_diff',
				"$base/archives/codereview-cr_diff.sql" );
			$updater->addExtensionIndex( 'code_relations', 'repo_to_from',
				"$base/archives/code_relations_index.sql" );

			if ( !$updater->updateRowExists( 'make cr_status varchar' ) ) {
				$updater->addExtensionUpdate( array( 'modifyField', 'code_rev', 'cr_status',
					"$base/archives/codereview-cr_status_varchar.sql", true ) );
			}

			$updater->addExtensionTable( 'code_bugs', "$base/archives/code_bugs.sql" );

			$updater->addExtensionTable( 'code_signoffs', "$base/archives/code_signoffs.sql" );

			$updater->addExtensionField( 'code_signoffs', 'cs_user',
				"$base/archives/code_signoffs_userid.sql" );
			$updater->addExtensionField( 'code_signoffs', 'cs_timestamp_struck',
				"$base/archives/code_signoffs_timestamp_struck.sql" );

			$updater->addExtensionIndex( 'code_comment', 'cc_author',
				"$base/archives/code_comment_author-index.sql" );

			$updater->addExtensionIndex( 'code_prop_changes', 'cpc_author',
				"$base/archives/code_prop_changes_author-index.sql" );

			if ( !$updater->updateRowExists( 'make cp_action char' ) ) {
				$updater->addExtensionUpdate( array( 'modifyField', 'code_paths', 'cp_action',
					"$base/archives/codereview-cp_action_char.sql", true ) );
			}

			if ( !$updater->updateRowExists( 'make cpc_attrib varchar' ) ) {
				$updater->addExtensionUpdate( array( 'modifyField', 'code_prop_changes', 'cpc_attrib',
					"$base/archives/codereview-cpc_attrib_varchar.sql", true ) );
			}

			$updater->addExtensionIndex( 'code_paths', 'repo_path',
				"$base/archives/codereview-repopath.sql" );

			$updater->addExtensionIndex( 'code_rev', 'cr_repo_status_author',
				"$base/archives/code_revs_status_author-index.sql" );

			$updater->addExtensionUpdate( array( 'dropField', 'code_comment', 'cc_review',
				"$base/archives/code_drop_cc_review.sql", true ) );

			$updater->addExtensionUpdate( array( 'dropTable', 'code_test_suite', "$base/archives/code_drop_test.sql", true ) );

			$updater->addExtensionUpdate( array( 'addField', 'code_authors', 'ca_user',
				"$base/archives/code_authors_add_ca_user.sql", true ) );

			$updater->addExtensionUpdate( array( 'addIndex', 'code_authors', 'ca_repo_author',
				"$base/archives/code_authors_repo-index.sql", true ) );
			break;
		case 'sqlite':
			$updater->addExtensionTable( 'code_rev', "$base/codereview.sql" );
			$updater->addExtensionTable( 'code_signoffs', "$base/archives/code_signoffs.sql" );
			$updater->addExtensionUpdate( array( 'addField', 'code_signoffs', 'cs_user',
				"$base/archives/code_signoffs_userid-sqlite.sql", true ) );
			$updater->addExtensionUpdate( array( 'addField', 'code_signoffs', 'cs_timestamp_struck',
				"$base/archives/code_signoffs_timestamp_struck.sql", true ) );
			$updater->addExtensionUpdate( array( 'addIndex', 'code_paths', 'repo_path',
				"$base/archives/codereview-repopath.sql", true ) );
			$updater->addExtensionUpdate( array( 'addField', 'code_authors', 'ca_user',
				"$base/archives/code_authors_add_ca_user.sql", true ) );
			$updater->addExtensionUpdate( array( 'addIndex', 'code_authors', 'ca_repo_author',
				"$base/archives/code_authors_repo-index.sql", true ) );
			break;
		case 'postgres':
			// TODO
			break;
		}
		return true;
	}

	/**
	 * Sets the wgCodeReviewRepository JavaScript variable to the name of the
	 * current repository when we're on Special:Code, or to be more specific,
	 * a subpage of a repository on Special:Code.
	 *
	 * @param $values array
	 * @param $out OutputPage
	 * @return bool
	 */
	public static function onMakeGlobalVariablesScript( &$values, $out ) {
		# Bleugh, this is horrible
		$title = $out->getTitle();
		if ( $title->isSpecial( 'Code' ) ) {
			$bits = explode( '/', $title->getText() );
			if ( isset( $bits[1] ) ) {
				$values['wgCodeReviewRepository'] = $bits[1];
			}
		}
		return true;
	}

	/**
	 * For integration with the Renameuser extension.
	 *
	 * @param $renameUserSQL RenameuserSQL
	 * @return bool
	 */
	public static function onRenameUserSQL( $renameUserSQL ) {
		foreach ( self::$userTables as $table => $fields ) {
			$renameUserSQL->tables[$table] = $fields;
		}
		return true;
	}

	private static $userTables = array(
		'code_authors' => array( 'ca_user_text', 'ca_user' ),
		'code_comment' => array( 'cc_user_text', 'cc_user' ),
		'code_prop_changes' => array( 'cpc_user_text', 'cpc_user' ),
		'code_signoffs' => array( 'cs_user_text', 'cs_user' )
	);

	/**
	 * For integration with the UserMerge extension.
	 *
	 * @param array $updateFields
	 * @return bool
	 */
	public static function onUserMergeAccountFields( &$updateFields ) {
		// array( tableName, idField, textField )
		foreach ( self::$userTables as $table => $fields ) {
			$updateFields[] = array( $table, $fields[1], $fields[0] );
		}
		return true;
	}
}
