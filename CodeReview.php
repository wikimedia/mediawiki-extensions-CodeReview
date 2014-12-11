<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die();
}
/**
 * @file
 * @author Brion Vibber
 * @author Aaron Schulz
 * @author Alexandre Emsenhuber
 * @author Chad Horohoe
 * @copyright Copyright © 2008 Brion Vibber <brion@pobox.com>
 * @copyright Copyright © 2008 Chad Horohoe <innocentkiller@gmail.com>
 * @copyright Copyright © 2008 Aaron Schulz <JSchulz_4587@msn.com>
 * @copyright Copyright © 2008 Alexandre Emsenhuber <alex.emsenhuber@bluewin.ch>
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

/*

What do I need out of SVN?

1) Find out what revisions exist
2) Get id/author/timestamp/notice basics
3) base path helps if available
4) get list of affected files
5) get diffs

http://pecl.php.net/package/svn

*/

/******** EXTENSION CREDITS ********/

$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'CodeReview',
	'version' => '1.18',
	'url' => 'https://www.mediawiki.org/wiki/Extension:CodeReview',
	'author' => array( 'Brion Vibber', 'Aaron Schulz', 'Alexandre Emsenhuber', 'Chad Horohoe', 'Sam Reed', 'Roan Kattouw' ),
	'descriptionmsg' => 'codereview-desc',
);

/******** CLASS DEFINITIONS ********/

$wgAutoloadClasses['ApiCodeUpdate'] = __DIR__ . '/api/ApiCodeUpdate.php';
$wgAutoloadClasses['ApiCodeDiff'] = __DIR__ . '/api/ApiCodeDiff.php';
$wgAutoloadClasses['ApiRevisionUpdate'] = __DIR__ . '/api/ApiRevisionUpdate.php';
$wgAutoloadClasses['ApiQueryCodeComments'] = __DIR__ . '/api/ApiQueryCodeComments.php';
$wgAutoloadClasses['ApiQueryCodePaths'] = __DIR__ . '/api/ApiQueryCodePaths.php';
$wgAutoloadClasses['ApiQueryCodeRevisions'] = __DIR__ . '/api/ApiQueryCodeRevisions.php';
$wgAutoloadClasses['ApiQueryCodeTags'] = __DIR__ . '/api/ApiQueryCodeTags.php';
$wgAutoloadClasses['CodeRevisionCommitterApi'] = __DIR__ . '/api/CodeRevisionCommitterApi.php';

$wgAutoloadClasses['SubversionAdaptor'] = __DIR__ . '/backend/Subversion.php';
$wgAutoloadClasses['CodeDiffHighlighter'] = __DIR__ . '/backend/DiffHighlighter.php';

$wgAutoloadClasses['CodeRepository'] = __DIR__ . '/backend/CodeRepository.php';
$wgAutoloadClasses['CodeRevision'] = __DIR__ . '/backend/CodeRevision.php';
$wgAutoloadClasses['CodeComment'] = __DIR__ . '/backend/CodeComment.php';
$wgAutoloadClasses['CodeCommentLinker'] = __DIR__ . '/backend/CodeCommentLinker.php';
$wgAutoloadClasses['CodeCommentLinkerHtml'] = __DIR__ . '/backend/CodeCommentLinker.php';
$wgAutoloadClasses['CodeCommentLinkerWiki'] = __DIR__ . '/backend/CodeCommentLinker.php';
$wgAutoloadClasses['CodePropChange'] = __DIR__ . '/backend/CodePropChange.php';
$wgAutoloadClasses['CodeSignoff'] = __DIR__ . '/backend/CodeSignoff.php';
$wgAutoloadClasses['RepoStats'] = __DIR__ . '/backend/RepoStats.php';

$wgAutoloadClasses['CodeRepoListView'] = __DIR__ . '/ui/CodeRepoListView.php';
$wgAutoloadClasses['CodeRepoStatsView'] = __DIR__ . '/ui/CodeRepoStatsView.php';
$wgAutoloadClasses['CodeRevisionAuthorView'] = __DIR__ . '/ui/CodeRevisionAuthorView.php';
$wgAutoloadClasses['CodeRevisionAuthorLink'] = __DIR__ . '/ui/CodeRevisionAuthorLink.php';
$wgAutoloadClasses['CodeRevisionCommitter'] = __DIR__ . '/ui/CodeRevisionCommitter.php';
$wgAutoloadClasses['CodeRevisionListView'] = __DIR__ . '/ui/CodeRevisionListView.php';
$wgAutoloadClasses['CodeRevisionStatusView'] = __DIR__ . '/ui/CodeRevisionStatusView.php';
$wgAutoloadClasses['CodeRevisionTagView'] = __DIR__ . '/ui/CodeRevisionTagView.php';
$wgAutoloadClasses['CodeRevisionView'] = __DIR__ . '/ui/CodeRevisionView.php';
$wgAutoloadClasses['CodeAuthorListView'] = __DIR__ . '/ui/CodeAuthorListView.php';
$wgAutoloadClasses['CodeStatusListView'] = __DIR__ . '/ui/CodeStatusListView.php';
$wgAutoloadClasses['CodeTagListView'] = __DIR__ . '/ui/CodeTagListView.php';
$wgAutoloadClasses['CodeCommentsListView'] = __DIR__ . '/ui/CodeCommentsListView.php';
$wgAutoloadClasses['CodeCommentsAuthorListView'] = __DIR__ . '/ui/CodeCommentsAuthorListView.php';
$wgAutoloadClasses['CodeReleaseNotes'] = __DIR__ . '/ui/CodeReleaseNotes.php';
$wgAutoloadClasses['CodeStatusChangeListView'] = __DIR__ . '/ui/CodeStatusChangeListView.php';
$wgAutoloadClasses['CodeStatusChangeAuthorListView'] = __DIR__ . '/ui/CodeStatusChangeAuthorListView.php';
$wgAutoloadClasses['SpecialCode'] = __DIR__ . '/ui/SpecialCode.php';
$wgAutoloadClasses['CodeView'] = __DIR__ . '/ui/CodeView.php';
$wgAutoloadClasses['SpecialRepoAdmin'] = __DIR__ . '/ui/SpecialRepoAdmin.php';
$wgAutoloadClasses['WordCloud'] = __DIR__ . '/ui/WordCloud.php';

$wgAutoloadClasses['SvnRevTablePager'] = __DIR__ . '/ui/CodeRevisionListView.php';
$wgAutoloadClasses['CodeCommentsTablePager'] = __DIR__ . '/ui/CodeCommentsListView.php';
$wgAutoloadClasses['SvnRevAuthorTablePager'] = __DIR__ . '/ui/CodeRevisionAuthorView.php';
$wgAutoloadClasses['SvnRevStatusTablePager'] = __DIR__ . '/ui/CodeRevisionStatusView.php';
$wgAutoloadClasses['SvnRevTagTablePager'] = __DIR__ . '/ui/CodeRevisionTagView.php';
$wgAutoloadClasses['CodeStatusChangeTablePager'] = __DIR__ . '/ui/CodeRevisionStatusView.php';

$wgAutoloadClasses['CodeReviewHooks'] = __DIR__ . '/CodeReviewHooks.php';

// MediaWiki:CodeReview.css (bug #16049)
$wgAutoloadClasses['ResourceLoaderCodeReviewModule'] = __DIR__ . '/ResourceLoaderCodeReviewModule.php';

/******** SPECIAL PAGE SETUP ********/

$wgSpecialPages['Code'] = 'SpecialCode';
$wgSpecialPageGroups['Code'] = 'developer';
$wgSpecialPages['RepoAdmin'] = 'SpecialRepoAdmin';
$wgSpecialPageGroups['RepoAdmin'] = 'developer';

/******** API SETUP ********/

$wgAPIModules['codeupdate'] = 'ApiCodeUpdate';
$wgAPIModules['codediff'] = 'ApiCodeDiff';
$wgAPIModules['coderevisionupdate'] ='ApiRevisionUpdate';
$wgAPIListModules['codecomments'] = 'ApiQueryCodeComments';
$wgAPIListModules['codepaths'] = 'ApiQueryCodePaths';
$wgAPIListModules['coderevisions'] = 'ApiQueryCodeRevisions';
$wgAPIListModules['codetags'] = 'ApiQueryCodeTags';

/******** I18N MESSAGES ********/

$wgMessagesDirs['CodeReview'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['CodeReview'] = __DIR__ . '/CodeReview.i18n.php';
$wgExtensionMessagesFiles['CodeReviewAliases'] = __DIR__ . '/CodeReview.alias.php';

/******** NEW USER RIGHTS ********/

$wgAvailableRights[] = 'repoadmin';
$wgAvailableRights[] = 'codereview-use';
$wgAvailableRights[] = 'codereview-add-tag';
$wgAvailableRights[] = 'codereview-remove-tag';
$wgAvailableRights[] = 'codereview-post-comment';
$wgAvailableRights[] = 'codereview-set-status';
$wgAvailableRights[] = 'codereview-signoff';
$wgAvailableRights[] = 'codereview-associate';
$wgAvailableRights[] = 'codereview-link-user';
$wgAvailableRights[] = 'codereview-review-own';

$wgGroupPermissions['*']['codereview-use'] = true;

$wgGroupPermissions['user']['codereview-add-tag'] = true;
$wgGroupPermissions['user']['codereview-remove-tag'] = true;
$wgGroupPermissions['user']['codereview-post-comment'] = true;
$wgGroupPermissions['user']['codereview-set-status'] = true;
$wgGroupPermissions['user']['codereview-link-user'] = true;
$wgGroupPermissions['user']['codereview-signoff'] = true;
$wgGroupPermissions['user']['codereview-associate'] = true;

$wgGroupPermissions['svnadmins']['repoadmin'] = true;

/******** CONFIGURATION SETTINGS ********/

// If you can't directly access the remote SVN repo, you can set this
// to an offsite proxy running this fun little proxy tool:
// http://svn.wikimedia.org/viewvc/mediawiki/trunk/tools/codereview-proxy/
$wgSubversionProxy = false;
$wgSubversionProxyTimeout = 30; // default 3 secs is too short :)

// Command-line options to pass on SVN command line if SVN PECL extension
// isn't available and we're not using the proxy.
// Defaults here should allow working with both http: and https: repos
// as long as authentication isn't required.
$wgSubversionOptions = "--non-interactive --trust-server-cert";

// What is the default SVN import chunk size?
$wgCodeReviewImportBatchSize = 400;

// Shuffle the tag cloud
$wgCodeReviewShuffleTagCloud = false;

// If you are running a closed svn, fill the following two lines with the username and password
// of a user allowed to access it. Otherwise, leave it false.
// This is only necessary if using the shell method to access Subversion
$wgSubversionUser = false;
$wgSubversionPassword = false;

// Leave this off by default until it works right
$wgCodeReviewENotif = false;

// Set this to an email list to send all comments to
$wgCodeReviewCommentWatcherEmail = false;
// Name to use in the To: header of emails to the list. Ignored if $wgCodeReviewCommentWatcherEmail isn't set
$wgCodeReviewCommentWatcherName = 'CodeReview comments list';

// Set this flag to true if you want to disable the automatic notifications to all
// watchers of a specific revision, when a follow-up to that revision is committed.
// Useful for small repos when everyone is reading the commit emails anyway,
// or if you have multiple repositories which refer to one another, which result
// in an unreasonable level of false positives.
// Note that the new revision will still be marked as a follow-up - this setting
// just stops the email being sent.
$wgCodeReviewDisableFollowUpNotification = false;

// What images can be used for client-side side-by-side comparisons?
$wgCodeReviewImgRegex = '/\.(png|jpg|jpeg|gif)$/i';

/**
 * Maximum size of diff text before it is omitted from the revision view
 */
$wgCodeReviewMaxDiffSize = 500000;

/**
 * The maximum number of paths that we will perform a diff on.
 * If a revision contains more changed paths than this, we will skip getting the
 * diff altogether.
 * May be set to 0 to indicate no limit.
 */
$wgCodeReviewMaxDiffPaths = 20;

/**
 * Key is repository name. Value is an array of regexes
 *
 * Any base paths matching regular expressions in these arrays will have their
 * default status set to deferred instead of new. Helpful if you've got a part
 * of the repository you don't care about.
 *
 * $wgCodeReviewDeferredPaths = array( 'RepoName' => array( '/path/to/use', '/another/path/to/use' ) )
 */
$wgCodeReviewDeferredPaths = array();

/**
 * Key is repository name. Value is an array of key value pairs of the path and then tags
 *
 * An array (or string, for 1 tag) of tags to add to a revision upon commit
 *
 * $wgCodeReviewAutoTagPath = array( 'RepoName' => array( '%^/path/to/use%' => 'sometag', '%^/another/path/to/use%' => array( 'tag1', 'tag2' ) ) )
 */
$wgCodeReviewAutoTagPath = array();

/**
 * Key is repository name. Value is an array of key value pairs of the paths to get fixme list for
 *
 * $wgCodeReviewFixmePerPath = array( 'RepoName' => array( '/path/to/use', '/another/path/to/use' ) )
 */
$wgCodeReviewFixmePerPath = array();

/**
 * Key is repository name. Value is an array of key value pairs of the paths to get new list for
 *
 * $wgCodeReviewNewPerPath = array( 'RepoName' => array( '/path/to/use', '/another/path/to/use' ) )
 */
$wgCodeReviewNewPerPath = array();

/**
 * Comment and status changes notification
 */
$wgCodeReviewRC = array();

/**
 * How long to cache repository statistics in seconds
 * See http://www.mediawiki.org/wiki/Special:Code/MediaWiki/stats
 */
$wgCodeReviewRepoStatsCacheTime = 6 * 60 * 60; // 6 Hours

/**
 * Possible states a revision can be in
 *
 * A system message will still needed to be added as code-status-<state>
 */
$wgCodeReviewStates = array(
	'new',
	'fixme',
	'reverted',
	'resolved',
	'ok',
	'deferred',
	'old',
);

/**
 * Revisions states that a user cannot change to on their own revision
 */
$wgCodeReviewProtectedStates = array(
	'ok',
	'resolved',
);

/**
 * List of all flags a user can mark themself as having done to a revision
 *
 * A system message will still needed to be added as code-signoff-flag-<flag>
 */
$wgCodeReviewFlags = array(
	'inspected',
	'tested',
);

/******** RESOURCE CONFIGURATION ********/

$commonModuleInfo = array(
	'localBasePath' => __DIR__ . '/modules',
	'remoteExtPath' => 'CodeReview/modules',
);

// Styles and any code common to all Special:Code subviews:
$wgResourceModules['ext.codereview'] = array(
	'scripts' => 'ext.codereview.js',
	'dependencies' => 'jquery.suggestions',
) + $commonModuleInfo;

$wgResourceModules['ext.codereview.styles'] = array(
	'styles' => 'ext.codereview.styles.css',
) + $commonModuleInfo;

// On-demand diff loader for CodeRevisionView:
$wgResourceModules['ext.codereview.loaddiff'] = array(
	'scripts' => 'ext.codereview.loaddiff.js'
) + $commonModuleInfo;

// Revision tooltips CodeRevisionView:
$wgResourceModules['ext.codereview.tooltips'] = array(
	'scripts' => 'ext.codereview.tooltips.js',
	'dependencies' => 'jquery.tipsy',
) + $commonModuleInfo;

// Revision 'scapmap':
$wgResourceModules['ext.codereview.overview'] = array(
	'scripts' => 'ext.codereview.overview.js',
	'styles' => 'ext.codereview.overview.css',
	'dependencies' => 'jquery.tipsy',
	'messages' => array( 'codereview-overview-title', 'codereview-overview-desc' ),
) + $commonModuleInfo;

// MediaWiki:CodeReview.css (bug #16049)
$wgResourceModules['ext.codereview.local'] = array(
	'class' => 'ResourceLoaderCodeReviewModule'
);

# Add global JS vars
$wgHooks['MakeGlobalVariablesScript'][] = 'CodeReviewHooks::onMakeGlobalVariablesScript';

# Add state messages to RL
$wgExtensionFunctions[] = function() {
	global $wgResourceModules;

	$wgResourceModules['ext.codereview.tooltips']['messages'] = array_merge(
		CodeRevision::getPossibleStateMessageKeys(),
		array( 'code-tooltip-withsummary', 'code-tooltip-withoutsummary' )
	);
};

/******** DB UPDATING ********/

# Schema changes
$wgHooks['LoadExtensionSchemaUpdates'][] = 'CodeReviewHooks::onLoadExtensionSchemaUpdates';

# Renameuser support
$wgHooks['RenameUserSQL'][] = 'CodeReviewHooks::onRenameUserSQL';

# UserMerge support
$wgHooks['UserMergeAccountFields'][] = 'CodeReviewHooks::onUserMergeAccountFields';

/******** UNIT TESTS ********/

$wgHooks['UnitTestsList'][] = 'CodeReviewHooks::onUnitTestsList';
