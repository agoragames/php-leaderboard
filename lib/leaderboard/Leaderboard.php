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
	
	public function totalPages() {
		return ceil($this->totalMembers() / Leaderboard::DEFAULT_PAGE_SIZE);
	}
	
	public function rankFor($member, $useZeroIndexForRank = false) {
	 	$rank = $this->_redis_connection->zRevRank($this->_leaderboard_name, $member);
		if ($useZeroIndexForRank == false) {
			$rank += 1;
		}
		
		return $rank;
	}
	
	public function scoreFor($member) {
		return $this->_redis_connection->zScore($this->_leaderboard_name, $member);
	}
	
	public function leaders($currentPage, $withScores = true, $withRank = true, $useZeroIndexForRank = false) {
		if ($currentPage < 1) {
			$currentPage = 1;
		}
		
		if ($currentPage > $this->totalPages()) {
			$currentPage = $this->totalPages();
		}
		
		$indexForRedis = $currentPage - 1;
		
		$startingOffset = ($indexForRedis * Leaderboard::DEFAULT_PAGE_SIZE);
		if ($startingOffset < 0) {
			$startingOffset = 0;
		}
		
		$endingOffset = ($startingOffset + Leaderboard::DEFAULT_PAGE_SIZE) - 1;
		
		$leaderData = $this->_redis_connection->zRevRange($this->_leaderboard_name, $startingOffset, $endingOffset, $withScores);
		if (!is_null($leaderData)) {
			return $this->massageLeaderData($leaderData, $withScores, $withRank, $useZeroIndexForRank);
		} else {
			return NULL;
		}
	}
	
	private function massageLeaderData($leaders, $withScores, $withRank, $useZeroIndexForRank) {
		$memberAttribute = true;
		$leaderData = array();
		
		$memberData = array();
		foreach ($leaders as $key => $value) {

			$memberData['member'] = $key;
			$memberData['score'] = $value;

			if ($withRank) {
				$memberData['rank'] = $this->rankFor($key, $useZeroIndexForRank);
			}

			array_push($leaderData, $memberData);
			$memberData = array();
		}
		
		return $leaderData;
	}	
}

?>