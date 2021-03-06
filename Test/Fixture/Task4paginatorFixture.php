<?php
/**
 * TaskFixture
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author Tomoyoshi Nakata <nakata.tomoyoshi@withone.co.jp>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */

App::uses('TaskFixture', 'Tasks.Test/Fixture');

/**
 * TaskFixture
 *
 * @author Tomoyoshi Nakata <nakata.tomoyoshi@withone.co.jp>
 * @package NetCommons\Tasks\Test\Fixture
 * @codeCoverageIgnore
 */
class Task4paginatorFixture extends TaskFixture {

/**
 * Model name
 *
 * @var string
 */
	public $name = 'Task';

/**
 * Records
 *
 * @var array
 */
	public $records = array(
		//Task 1
		array(
			'id' => '1',
			'block_id' => '1',
			'key' => 'Task_1',
			'name' => 'Task name 1',
		),
		array(
			'id' => '2',
			'block_id' => '2',
			'key' => 'Task_1',
			'name' => 'Task name 1',
		),
		//Task 2
		array(
			'id' => '3',
			'block_id' => '4',
			'key' => 'Task_2',
			'name' => 'Task name 2',
		),
		//Task 3
		array(
			'id' => '4',
			'block_id' => '6',
			'key' => 'Task_3',
			'name' => 'Task name 2',
		),

		//101-200まで、ページ遷移のためのテスト
	);

/**
 * Initialize the fixture.
 *
 * @return void
 */
	public function init() {
		for ($i = 101; $i <= 200; $i++) {
			$this->records[$i] = array(
				'id' => $i,
				'block_id' => $i,
				'key' => 'Task_' . $i,
				'name' => 'Task_name_' . $i,
			);
		}
		parent::init();
	}

}
