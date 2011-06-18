<?php

require '/Users/dczarnecki/projects/php-leaderboard/lib/leaderboard/Leaderboard.php';

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

	function testAbleToEstablishConnectionToRedis() {
		$this->assertEquals('+PONG', $this->redis->ping());
	}
	
	function testAbleToSetAKeyToAValue() {
		$this->redis->set('key', 'value');
		$this->assertEquals('value', $this->redis->get('key'));
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
	
	function testAddMemberToLeaderboard() {
		$leaderboard = new Leaderboard('leaderboard');
		$this->assertEquals(1, $leaderboard->addMember(69, 'david'));
		$this->assertEquals(1, $this->redis->zSize('leaderboard'));
	}

	function testRemoveMemberFromLeaderboard() {
		$leaderboard = new Leaderboard('leaderboard');
		$this->assertEquals(1, $leaderboard->addMember(69, 'david'));
		$this->assertEquals(1, $leaderboard->removeMember('david'));
		$this->assertEquals(0, $this->redis->zSize('leaderboard'));
	}

	function testTotalMembersInLeaderboard() {
		$leaderboard = new Leaderboard('leaderboard');
		$this->assertEquals(1, $leaderboard->addMember(69, 'david'));
		$this->assertEquals(1, $leaderboard->totalMembers());
	}
}

?>