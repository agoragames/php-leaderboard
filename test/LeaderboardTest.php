<?php

require "{dirname(__FILE__)}/../lib/Leaderboard.php";

class LeaderboardTestSuite extends PHPUnit_Framework_TestCase {
    public $redis;

    protected function setUp() {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
        $this->redis->flushDB();
    }

    protected function tearDown() { 
        $this->redis->close();
    }
    
    function testVersion() {
        $this->assertEquals('1.0.0', Leaderboard::VERSION);
    }
    
    function testConstructLeaderboardClassWithName() {
        $leaderboard = new Leaderboard('leaderboard');
        $this->assertEquals('leaderboard', $leaderboard->getLeaderboardName());
    }
    
    function testCloseLeaderboardConnection() {
        $leaderboard = new Leaderboard('leaderboard');
        $this->assertTrue($leaderboard->close());
    }
    
    function testAddMember() {
        $leaderboard = new Leaderboard('leaderboard');
        $this->assertEquals(1, $leaderboard->addMember('david', 69));
        $this->assertEquals(1, $this->redis->zSize('leaderboard'));
    }

    function testRemoveMember() {
        $leaderboard = new Leaderboard('leaderboard');
        $this->assertEquals(1, $leaderboard->addMember('david', 69));
        $this->assertEquals(1, $leaderboard->removeMember('david'));
        $this->assertEquals(0, $this->redis->zSize('leaderboard'));
    }

    function testTotalMembers() {
        $leaderboard = new Leaderboard('leaderboard');
        $this->assertEquals(1, $leaderboard->addMember('david', 69));
        $this->assertEquals(1, $leaderboard->totalMembers());
    }
    
    function testTotalPages() {
        $leaderboard = new Leaderboard('leaderboard');
        for ($i = 1; $i <= Leaderboard::DEFAULT_PAGE_SIZE + 1; $i++) {
            $leaderboard->addMember("member_{$i}", $i);
        }
        
        $this->assertEquals(2, $leaderboard->totalPages());
    }
    
    function testSetPageSize() {
        $leaderboard = new Leaderboard('leaderboard');
        for ($i = 1; $i <= Leaderboard::DEFAULT_PAGE_SIZE + 5; $i++) {
            $leaderboard->addMember("member_{$i}", $i);
        }
        
        $this->assertEquals(2, $leaderboard->totalPages());
        $leaderboard->setPageSize(10);
        $this->assertEquals(3, $leaderboard->totalPages());
    }
    
    
    function testTotalMembersInScoreRange() {
        $leaderboard = new Leaderboard('leaderboard');
        for ($i = 1; $i <= Leaderboard::DEFAULT_PAGE_SIZE + 1; $i++) {
            $leaderboard->addMember("member_{$i}", $i);
        }

        $this->assertEquals(3, $leaderboard->totalMembersInScoreRange(2, 4));
    }
    
    function testChangeScoreFor() {
        $leaderboard = new Leaderboard('leaderboard');
        
        $leaderboard->changeScoreFor('member_1', 5);
        $this->assertEquals(5, $leaderboard->scoreFor('member_1'));
        
        $leaderboard->changeScoreFor('member_1', 5);
        $this->assertEquals(10, $leaderboard->scoreFor('member_1'));

        $leaderboard->changeScoreFor('member_1', -5);
        $this->assertEquals(5, $leaderboard->scoreFor('member_1'));
    }
    
    function testCheckMember() {
        $leaderboard = new Leaderboard('leaderboard');
        
        $leaderboard->addMember('member_1', 10);
        $this->assertTrue($leaderboard->checkMember('member_1'));
        $this->assertFalse($leaderboard->checkMember('member_2'));
    }
    
    function testRankFor() {
        $leaderboard = new Leaderboard('leaderboard');
        for ($i = 1; $i <= Leaderboard::DEFAULT_PAGE_SIZE + 1; $i++) {
            $leaderboard->addMember("member_{$i}", $i);
        }
        
        $this->assertEquals(26, $leaderboard->rankFor('member_1'));
        $this->assertEquals(25, $leaderboard->rankFor('member_1', true));
    }

    function testScoreFor() {
        $leaderboard = new Leaderboard('leaderboard');
        for ($i = 1; $i <= Leaderboard::DEFAULT_PAGE_SIZE + 1; $i++) {
            $leaderboard->addMember("member_{$i}", $i);
        }
        
        $this->assertEquals(14, $leaderboard->scoreFor('member_14'));
    }
    
    function testScoreAndRankFor() {
        $leaderboard = new Leaderboard('leaderboard');
        for ($i = 1; $i <= 5; $i++) {
            $leaderboard->addMember("member_{$i}", $i);
        }
        
        $memberData = $leaderboard->scoreAndRankFor('member_1');
        $this->assertEquals('member_1', $memberData['member']);
        $this->assertEquals(1, $memberData['score']);
        $this->assertEquals(5, $memberData['rank']);
    }
    
    function testRemoveMembersInScoreRange() {
        $leaderboard = new Leaderboard('leaderboard');
        for ($i = 1; $i <= 5; $i++) {
            $leaderboard->addMember("member_{$i}", $i);
        }

        $this->assertEquals(5, $leaderboard->totalMembers());
        
        $leaderboard->addMember('cheater_1', 100);
        $leaderboard->addMember('cheater_2', 101);
        $leaderboard->addMember('cheater_3', 102);

        $this->assertEquals(8, $leaderboard->totalMembers());
        
        $leaderboard->removeMembersInScoreRange(100, 102);

        $this->assertEquals(5, $leaderboard->totalMembers());
        
        $leaders = $leaderboard->leaders(1);
        foreach ($leaders as $key => $value) {
            $this->assertLessThan(100, $value['score']);
        }
    }
    
    function testLeaders() {
        $leaderboard = new Leaderboard('leaderboard');
        for ($i = 1; $i <= Leaderboard::DEFAULT_PAGE_SIZE + 1; $i++) {
            $leaderboard->addMember("member_{$i}", $i);
        }

        $leaders = $leaderboard->leaders(1);
        $this->assertEquals(Leaderboard::DEFAULT_PAGE_SIZE, count($leaders));
        $this->assertEquals('member_26', $leaders[0]['member']);
        $this->assertEquals(26, $leaders[0]['score']);
        $this->assertEquals(1, $leaders[0]['rank']);
        
        $leaders = $leaderboard->leaders(2);
        $this->assertEquals(1, count($leaders));
        $this->assertEquals('member_1', $leaders[0]['member']);
        $this->assertEquals(1, $leaders[0]['score']);
        $this->assertEquals(26, $leaders[0]['rank']);
    }
    
    function testAroundMe() {
        $leaderboard = new Leaderboard('leaderboard');
        for ($i = 1; $i <= Leaderboard::DEFAULT_PAGE_SIZE * 3 + 1; $i++) {
            $leaderboard->addMember("member_{$i}", $i);
        }
        
        $this->assertEquals(Leaderboard::DEFAULT_PAGE_SIZE * 3 + 1, $leaderboard->totalMembers());
        
        $leadersAroundMe = $leaderboard->aroundMe('member_30');
        $this->assertEquals(Leaderboard::DEFAULT_PAGE_SIZE / 2, count($leadersAroundMe) / 2);

        $leadersAroundMe = $leaderboard->aroundMe('member_1');
        $this->assertEquals(ceil(Leaderboard::DEFAULT_PAGE_SIZE / 2 + 1), count($leadersAroundMe));

        $leadersAroundMe = $leaderboard->aroundMe('member_76');
        $this->assertEquals(Leaderboard::DEFAULT_PAGE_SIZE / 2, count($leadersAroundMe) / 2);
    }
    
    function testRankedInList() {
        $leaderboard = new Leaderboard('leaderboard');
        for ($i = 1; $i <= Leaderboard::DEFAULT_PAGE_SIZE; $i++) {
            $leaderboard->addMember("member_{$i}", $i);
        }
        
        $this->assertEquals(Leaderboard::DEFAULT_PAGE_SIZE, $leaderboard->totalMembers());
        $members = array('member_1', 'member_5', 'member_10');
        $rankedMembers = $leaderboard->rankedInList($members);
        
        $this->assertEquals(3, count($rankedMembers));
        
        $this->assertEquals(25, $rankedMembers[0]['rank']);
        $this->assertEquals(1, $rankedMembers[0]['score']);

        $this->assertEquals(21, $rankedMembers[1]['rank']);
        $this->assertEquals(5, $rankedMembers[1]['score']);

        $this->assertEquals(16, $rankedMembers[2]['rank']);
        $this->assertEquals(10, $rankedMembers[2]['score']);
    }
}

?>