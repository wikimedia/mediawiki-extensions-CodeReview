<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['suppress_issue_types'][] = 'PhanTypeMismatchArgument';
$cfg['suppress_issue_types'][] = 'PhanTypeMismatchArgumentProbablyReal';
$cfg['suppress_issue_types'][] = 'PhanTypeMismatchReturn';
$cfg['suppress_issue_types'][] = 'PhanTypeMismatchReturnProbablyReal';
$cfg['suppress_issue_types'][] = 'MediaWikiNoEmptyIfDefined';
$cfg['suppress_issue_types'][] = 'MediaWikiNoBaseException';

return $cfg;
