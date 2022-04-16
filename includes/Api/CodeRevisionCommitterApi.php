<?php

namespace MediaWiki\Extension\CodeReview\Api;

use MediaWiki\Extension\CodeReview\UI\CodeRevisionCommitter;
use MediaWiki\Permissions\Authority;

/**
 * Variation of CodeRevisionCommiter for use in the API. Removes the post and token checking from
 * validPost API can/will do both the POST and token
 */
class CodeRevisionCommitterApi extends CodeRevisionCommitter {
	/**
	 * Check whether the user has the correct permissions for the action
	 *
	 * @param string $permission
	 * @param Authority $performer
	 * @return bool
	 */
	public function validPost( $permission, Authority $performer ) {
		return $performer->isAllowed( $permission );
	}
}
