<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/Renameuser',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/Renameuser',
	]
);

$cfg['suppress_issue_types'][] = 'PhanPossiblyUndeclaredVariable';
$cfg['suppress_issue_types'][] = 'PhanTypeArraySuspiciousNullable';
$cfg['suppress_issue_types'][] = 'PhanTypeMismatchArgument';
$cfg['suppress_issue_types'][] = 'PhanTypeMismatchArgumentNullableInternal';
$cfg['suppress_issue_types'][] = 'PhanTypeMismatchArgumentInternal';
$cfg['suppress_issue_types'][] = 'PhanTypeMismatchArgumentProbablyReal';
$cfg['suppress_issue_types'][] = 'PhanTypeMismatchReturn';
$cfg['suppress_issue_types'][] = 'PhanTypeMismatchReturnProbablyReal';
// Needs stubs for svn constants
$cfg['suppress_issue_types'][] = 'PhanUndeclaredConstant';
$cfg['suppress_issue_types'][] = 'PhanUndeclaredMethod';
$cfg['suppress_issue_types'][] = 'PhanUndeclaredProperty';
$cfg['suppress_issue_types'][] = 'PhanUndeclaredVariable';

return $cfg;
