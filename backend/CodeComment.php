<?php

/**
 * Represents a comment made to a revision.
 */
class CodeComment {
	public $id, $text, $user, $userText, $timestamp, $review, $sortkey, $attrib, $removed, $added, $patchLine;

	/**
	 * @var CodeRevision
	 */
	public $rev;

	/**
	 * @param $rev CodeRevision
	 */
	function __construct( $rev ) {
		$this->rev = $rev;
	}

	/**
	 * @param $rev Revision
	 * @param $row
	 * @return CodeComment
	 */
	static function newFromRow( $rev, $row ) {
		return self::newFromData( $rev, get_object_vars( $row ) );
	}

	/**
	 * @param $rev Revision
	 * @param $data array
	 * @return CodeComment
	 */
	static function newFromData( $rev, $data ) {
		$comment = new CodeComment( $rev );
		$comment->id = intval( $data['cc_id'] );
		$comment->text = $data['cc_text']; // fixme
		$comment->user = $data['cc_user'];
		$comment->userText = $data['cc_user_text'];
		$comment->timestamp = wfTimestamp( TS_MW, $data['cc_timestamp'] );
		$comment->patchLine = $data['cc_patch_line'];
		$comment->review = $data['cc_review'];
		$comment->sortkey = $data['cc_sortkey'];
		return $comment;
	}

	/**
	 * @return int
	 */
	function threadDepth() {
		$timestamps = explode( ",", $this->sortkey );
		return count( $timestamps );
	}
}
