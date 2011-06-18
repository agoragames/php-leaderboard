<?php

class Leaderboard {
	const VERSION = '1.0.0';
	const DEFAULT_PAGE_SIZE = 25;
	const DEFAULT_HOST = 'localhost';
	const DEFAULT_PORT = 6379;
	
	private $_leaderboard_name;
	private $_redis_connection;
	
	public function __construct($name) {
		$this->_leaderboard_name = $name;
		$this->_redis_connection = new Redis();
		$this->_redis_connection->connect(Leaderboard::DEFAULT_HOST, Leaderboard::DEFAULT_PORT);
	}
	
	public function getLeaderboardName() {
		return $this->_leaderboard_name;
	}

	public function close() {
		return $this->_redis_connection->close();
	}
	
	public function addMember($score, $member) {
		return $this->_redis_connection->zAdd($this->_leaderboard_name, $score, $member);
	}
	
	public function removeMember($member) {
		return $this->_redis_connection->zRem($this->_leaderboard_name, $member);
	}
	
	public function totalMembers() {
		return $this->_redis_connection->zCard($this->_leaderboard_name);
	}
}

?>