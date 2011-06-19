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
    
    public function setPageSize($pageSize) {
        if ($pageSize < 1) {
            $pageSize = Leaderboard::DEFAULT_PAGE_SIZE;
        }
        
        $this->_page_size = $pageSize;
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
        return $this->rankForIn($this->_leaderboard_name, $member, $useZeroIndexForRank);
    }
    
    public function rankForIn($leaderboardName, $member, $useZeroIndexForRank = false) {
        $rank = $this->_redis_connection->zRevRank($leaderboardName, $member);
        if ($useZeroIndexForRank == false) {
            $rank += 1;
        }
        
        return $rank;
    }

    public function scoreFor($member) {
        return $this->scoreForIn($this->_leaderboard_name, $member);
    }
    
    public function scoreForIn($leaderboardName, $member) {
        return $this->_redis_connection->zScore($leaderboardName, $member);
    }
    
    public function checkMember($member) {
        return $this->checkMemberIn($this->_leaderboard_name, $member);      
    }

    public function checkMemberIn($leaderboardName, $member) {
        return !($this->_redis_connection->zScore($leaderboardName, $member) == NULL);        
    }

    public function scoreAndRankFor($member, $useZeroIndexForRank = false) {
        return $this->scoreAndRankForIn($this->_leaderboard_name, $member, $useZeroIndexForRank);
    }
    
    public function scoreAndRankForIn($leaderboardName, $member, $useZeroIndexForRank = false) {
        $memberData = array();
        $memberData['member'] = $member;
        $memberData['score'] = $this->scoreForIn($leaderboardName, $member);
        $memberData['rank'] = $this->rankForIn($leaderboardName, $member, $useZeroIndexForRank);
        
        return $memberData;
    }
    
    public function removeMembersInScoreRange($minScore, $maxScore) {
        return $this->removeMembersInScoreRangeIn($this->_leaderboard_name, $minScore, $maxScore);
    }
    
    public function removeMembersInScoreRangeIn($leaderboardName, $minScore, $maxScore) {
        return $this->_redis_connection->zRemRangeByScore($leaderboardName, $minScore, $maxScore);
    }
    
    public function leaders($currentPage, $withScores = true, $withRank = true, $useZeroIndexForRank = false) {
        return $this->leadersIn($this->_leaderboard_name, $currentPage, $withScores, $withRank, $useZeroIndexForRank);
    }
    
    public function leadersIn($leaderboardName, $currentPage, $withScores = true, $withRank = true, $useZeroIndexForRank = false) {
        if ($currentPage < 1) {
            $currentPage = 1;
        }
        
        if ($currentPage > $this->totalPagesIn($leaderboardName)) {
            $currentPage = $this->totalPagesIn($leaderboardName);
        }
        
        $indexForRedis = $currentPage - 1;
        
        $startingOffset = ($indexForRedis * $this->_page_size);
        if ($startingOffset < 0) {
            $startingOffset = 0;
        }
        
        $endingOffset = ($startingOffset + $this->_page_size) - 1;
        
        $leaderData = $this->_redis_connection->zRevRange($leaderboardName, $startingOffset, $endingOffset, $withScores);
        if (!is_null($leaderData)) {
            return $this->massageLeaderData($leaderboardName, $leaderData, $withScores, $withRank, $useZeroIndexForRank);
        } else {
            return NULL;
        }
    }
    
    public function aroundMe($member, $withScores = true, $withRank = true, $useZeroIndexForRank = false) {
        return $this->aroundMeIn($this->_leaderboard_name, $member, $withScores, $withRank, $useZeroIndexForRank);
    }
    
    public function aroundMeIn($leaderboardName, $member, $withScores = true, $withRank = true, $useZeroIndexForRank = false) {
        $reverseRankForMember = $this->_redis_connection->zRevRank($leaderboardName, $member);
        
        $startingOffset = $reverseRankForMember - ($this->_page_size / 2);
        if ($startingOffset < 0) {
            $startingOffset = 0;
        }
        
        $endingOffset = ($startingOffset + $this->_page_size) - 1;
        
        $leaderData = $this->_redis_connection->zRevRange($leaderboardName, $startingOffset, $endingOffset, $withScores);
        if (!is_null($leaderData)) {
            return $this->massageLeaderData($leaderboardName, $leaderData, $withScores, $withRank, $useZeroIndexForRank);
        } else {
            return NULL;
        }
    }
    
    public function rankedInList($members, $withScores = true, $useZeroIndexForRank = false) {
        return $this->rankedInListIn($this->_leaderboard_name, $members, $withScores, $useZeroIndexForRank);
    }
    
    public function rankedInListIn($leaderboardName, $members, $withScores = true, $useZeroIndexForRank = false) {
        $leaderData = array();
        
        foreach ($members as $member) {
            $memberData = array();
            $memberData['member'] = $member;
            if ($withScores) {
                $memberData['score'] = $this->scoreForIn($leaderboardName, $member);
            }
            $memberData['rank'] = $this->rankForIn($leaderboardName, $member, $useZeroIndexForRank);
            
            array_push($leaderData, $memberData);
        }
        
        return $leaderData;
    }
        
    private function massageLeaderData($leaderboardName, $leaders, $withScores, $withRank, $useZeroIndexForRank) {
        $memberAttribute = true;
        $leaderData = array();
        
        $memberData = array();
        foreach ($leaders as $key => $value) {

            $memberData['member'] = $key;
            $memberData['score'] = $value;

            if ($withRank) {
                $memberData['rank'] = $this->rankForIn($leaderboardName, $key, $useZeroIndexForRank);
            }

            array_push($leaderData, $memberData);
            $memberData = array();
        }
        
        return $leaderData;
    }   
}

?>