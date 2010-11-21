<?php
class CodeSignoff {
	public $rev, $user, $flag, $timestamp, $userText;
	
	public function __construct( $rev, $user, $userText, $flag, $timestamp ) {
		$this->rev = $rev;
		$this->user = $user;
		$this->userText = $userText;
		$this->flag = $flag;
		$this->timestamp = $timestamp;
	}
	
	public static function newFromRow( $rev, $row ) {
		return self::newFromData( $rev, get_object_vars( $row ) );
	}
	
	public static function newFromData( $rev, $data ) {
		return new self( $rev, $data['cs_user'], $data['cs_user_text'], $data['cs_flag'],
			wfTimestamp( TS_MW, $data['cs_timestamp'] )
		);
	}
}
