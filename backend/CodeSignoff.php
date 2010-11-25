<?php
class CodeSignoff {
	public $rev, $user, $flag, $timestamp, $userText;
	private $timestampStruck;
	
	public function __construct( $rev, $user, $userText, $flag, $timestamp, $timestampStruck ) {
		$this->rev = $rev;
		$this->user = $user;
		$this->userText = $userText;
		$this->flag = $flag;
		$this->timestamp = $timestamp;
		$this->timestampStruck = $timestampStruck;
	}
	
	public function isStruck() {
		return $this->timestampStruck !== Block::infinity();
	}
	
	public function getTimestampStruck() {
		return wfTimestamp( TS_MW, $this->timestampStruck );
	}
	
	public function strike() {
		if ( $this->isStruck() ) {
			return;
		}
		$dbw = wfGetDB( DB_MASTER );
		$dbw->update( 'code_signoffs', array( 'cs_timestamp_struck' => $dbw->timestamp() ),
			array(
				'cs_repo_id' => $this->rev->getRepoId(),
				'cs_rev_id' => $this->rev->getId(),
				'cs_flag' => $this->flag,
				'cs_user_text' => $this->userText,
				'cs_timestamp_struck' => $this->timestampStruck
			), __METHOD__
		);
	}
	
	public function getID() {
		return implode( '|', array( $this->flag, $this->timestampStruck, $this->userText ) );
	}
	
	public static function newFromRow( $rev, $row ) {
		return self::newFromData( $rev, get_object_vars( $row ) );
	}
	
	public static function newFromData( $rev, $data ) {
		return new self( $rev, $data['cs_user'], $data['cs_user_text'], $data['cs_flag'],
			wfTimestamp( TS_MW, $data['cs_timestamp'] ), $data['cs_timestamp_struck']
		);
	}
	
	public static function newFromID( $rev, $id ) {
		$parts = explode( '|', $id, 3 );
		if ( count( $parts ) != 3 ) {
			return null;
		}
		$dbr = wfGetDB( DB_SLAVE );
		$row = $dbr->selectRow( 'code_signoffs',
			array( 'cs_user', 'cs_user_text', 'cs_flag', 'cs_timestamp', 'cs_timestamp_struck' ),
			array(
				'cs_repo_id' => $rev->getRepoId(),
				'cs_rev_id' => $rev->getId(),
				'cs_flag' => $parts[0],
				'cs_timestamp_struck' => $parts[1],
				'cs_user_text' => $parts[2]
			), __METHOD__
		);
		if ( !$row ) {
			return null;
		}
		return self::newFromRow( $rev, $row );
	}
}
