<?php

require '/Users/dczarnecki/projects/php-leaderboard/lib/Leaderboard.php';

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
        $this->assertEquals(1, $leaderboard->addMember(69, 'david'));
        $this->assertEquals(1, $this->redis->zSize('leaderboard'));
    }

    function testRemoveMember() {
        $leaderboard = new Leaderboard('leaderboard');
        $this->assertEquals(1, $leaderboard->addMember(69, 'david'));
        $this->assertEquals(1, $leaderboard->removeMember('david'));
        $this->assertEquals(0, $this->redis->zSize('leaderboard'));
    }

    function testTotalMembers() {
        $leaderboard = new Leaderboard('leaderboard');
        $this->assertEquals(1, $leaderboard->addMember(69, 'david'));
        $this->assertEquals(1, $leaderboard->totalMembers());
    }
    
    function testTotalPages() {
        $leaderboard = new Leaderboard('leaderboard');
        for ($i = 1; $i <= Leaderboard::DEFAULT_PAGE_SIZE + 1; $i++) {
            $leaderboard->addMember($i, "member_{$i}");
        }
        
        $this->assertEquals(2, $leaderboard->totalPages());
    }
    
    function testTotalMembersInScoreRange() {
        $leaderboard = new Leaderboard('leaderboard');
        for ($i = 1; $i <= Leaderboard::DEFAULT_PAGE_SIZE + 1; $i++) {
            $leaderboard->addMember($i, "member_{$i}");
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
    
    function testRankFor() {
        $leaderboard = new Leaderboard('leaderboard');
        for ($i = 1; $i <= Leaderboard::DEFAULT_PAGE_SIZE + 1; $i++) {
            $leaderboard->addMember($i, "member_{$i}");
        }
        
        $this->assertEquals(26, $leaderboard->rankFor('member_1'));
        $this->assertEquals(25, $leaderboard->rankFor('member_1', true));
    }

    function testScoreFor() {
        $leaderboard = new Leaderboard('leaderboard');
        for ($i = 1; $i <= Leaderboard::DEFAULT_PAGE_SIZE + 1; $i++) {
            $leaderboard->addMember($i, "member_{$i}");
        }
        
        $this->assertEquals(14, $leaderboard->scoreFor('member_14'));
    }
    
    function testLeaders() {
        $leaderboard = new Leaderboard('leaderboard');
        for ($i = 1; $i <= Leaderboard::DEFAULT_PAGE_SIZE + 1; $i++) {
            $leaderboard->addMember($i, "member_{$i}");
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
            $leaderboard->addMember($i, "member_{$i}");
        }
        
        $this->assertEquals(Leaderboard::DEFAULT_PAGE_SIZE * 3 + 1, $leaderboard->totalMembers());
        
        $leadersAroundMe = $leaderboard->aroundMe('member_30');
        $this->assertEquals(Leaderboard::DEFAULT_PAGE_SIZE / 2, count($leadersAroundMe) / 2);

        $leadersAroundMe = $leaderboard->aroundMe('member_1');
        $this->assertEquals(ceil(Leaderboard::DEFAULT_PAGE_SIZE / 2 + 1), count($leadersAroundMe));

        $leadersAroundMe = $leaderboard->aroundMe('member_76');
        $this->assertEquals(Leaderboard::DEFAULT_PAGE_SIZE / 2, count($leadersAroundMe) / 2);
    }
}

?>