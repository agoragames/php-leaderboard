<?php

class Leaderboard {
    const VERSION = '1.0.0';
    const DEFAULT_PAGE_SIZE = 25;
    const DEFAULT_HOST = 'localhost';
    const DEFAULT_PORT = 6379;
    
    private $_leaderboard_name;
    private $_redis_connection;
    private $_page_size;
    
    public function __construct($name, $host = Leaderboard::DEFAULT_HOST, $port = Leaderboard::DEFAULT_PORT, $pageSize = Leaderboard::DEFAULT_PAGE_SIZE) {
        $this->_leaderboard_name = $name;
        $this->_redis_connection = new Redis();
        $this->_redis_connection->connect($host, $port);
        
        if ($pageSize < 1) {
            $pageSize = Leaderboard::DEFAULT_PAGE_SIZE;
        }
        
        $this->_page_size = $pageSize;
    }
    
    public function getLeaderboardName() {
        return $this->_leaderboard_name;
    }

    public function close() {
        return $this->_redis_connection->close();
    }
    
    public function addMember($member, $score) {
        return $this->addMemberTo($this->_leaderboard_name, $member, $score);
    }
    
    public function addMemberTo($leaderboardName, $member, $score) {
        return $this->_redis_connection->zAdd($leaderboardName, $score, $member);
    }
    
    public function removeMember($member) {
        return $this->removeMemberFrom($this->_leaderboard_name, $member);
    }

    public function removeMemberFrom($leaderboardName, $member) {
        return $this->_redis_connection->zRem($leaderboardName, $member);
    }

    public function totalMembers() {
        return $this->totalMembersIn($this->_leaderboard_name);
    }
    
    public function totalMembersIn($leaderboardName) {
        return $this->_redis_connection->zCard($leaderboardName);
    }
    
    public function totalPages() {
        return $this->totalPagesIn($this->_leaderboard_name);
    }

    public function totalPagesIn($leaderboardName) {
        return ceil($this->totalMembersIn($leaderboardName) / $this->_page_size);
    }
    
    public function totalMembersInScoreRange($minScore, $maxScore) {
        return $this->totalMembersInScoreRangeIn($this->_leaderboard_name, $minScore, $maxScore);
    }
    
    public function totalMembersInScoreRangeIn($leaderboardName, $minScore, $maxScore) {
        return $this->_redis_connection->zCount($leaderboardName, $minScore, $maxScore);        
    }
    
    public function changeScoreFor($member, $delta) {
        return $this->changeScoreForIn($this->_leaderboard_name, $member, $delta);
    }

    public function changeScoreForIn($leaderboardName, $member, $delta) {
        return $this->_redis_connection->zIncrBy($leaderboardName, $delta, $member);
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
    
    public function checkMember($member) {
        return !($this->_redis_connection->zScore($this->_leaderboard_name, $member) == NULL);        
    }
    
    public function scoreAndRankFor($member, $useZeroIndexForRank = false) {
        $memberData = array();
        $memberData['member'] = $member;
        $memberData['score'] = $this->scoreFor($member);
        $memberData['rank'] = $this->rankFor($member, $useZeroIndexForRank);
        
        return $memberData;
    }
    
    public function removeMembersInScoreRange($minScore, $maxScore) {
        return $this->_redis_connection->zRemRangeByScore($this->_leaderboard_name, $minScore, $maxScore);
    }
    
    public function leaders($currentPage, $withScores = true, $withRank = true, $useZeroIndexForRank = false) {
        if ($currentPage < 1) {
            $currentPage = 1;
        }
        
        if ($currentPage > $this->totalPages()) {
            $currentPage = $this->totalPages();
        }
        
        $indexForRedis = $currentPage - 1;
        
        $startingOffset = ($indexForRedis * $this->_page_size);
        if ($startingOffset < 0) {
            $startingOffset = 0;
        }
        
        $endingOffset = ($startingOffset + $this->_page_size) - 1;
        
        $leaderData = $this->_redis_connection->zRevRange($this->_leaderboard_name, $startingOffset, $endingOffset, $withScores);
        if (!is_null($leaderData)) {
            return $this->massageLeaderData($leaderData, $withScores, $withRank, $useZeroIndexForRank);
        } else {
            return NULL;
        }
    }
    
    public function aroundMe($member, $withScores = true, $withRank = true, $useZeroIndexForRank = false) {
        $reverseRankForMember = $this->_redis_connection->zRevRank($this->_leaderboard_name, $member);
        
        $startingOffset = $reverseRankForMember - ($this->_page_size / 2);
        if ($startingOffset < 0) {
            $startingOffset = 0;
        }
        
        $endingOffset = ($startingOffset + $this->_page_size) - 1;
        
        $leaderData = $this->_redis_connection->zRevRange($this->_leaderboard_name, $startingOffset, $endingOffset, $withScores);
        if (!is_null($leaderData)) {
            return $this->massageLeaderData($leaderData, $withScores, $withRank, $useZeroIndexForRank);
        } else {
            return NULL;
        }
    }
    
    public function rankedInList($members, $withScores = true, $useZeroIndexForRank = false) {
        $leaderData = array();
        
        foreach ($members as $member) {
            $memberData = array();
            $memberData['member'] = $member;
            if ($withScores) {
                $memberData['score'] = $this->scoreFor($member);
            }
            $memberData['rank'] = $this->rankFor($member, $useZeroIndexForRank);
            
            array_push($leaderData, $memberData);
        }
        
        return $leaderData;
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