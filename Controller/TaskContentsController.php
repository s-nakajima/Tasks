<?php
/**
 * TaskContents Controller
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author Yuto Kitatsuji <kitatsuji.yuto@wihtone.co.jp>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */

App::uses('TasksAppController', 'Tasks.Controller');

/**
 * TaskContents Controller
 *
 * @author Yuto Kitatsuji <kitatsuji.yuto@wihtone.co.jp>
 * @package NetCommons\Tasks\Controller
 * @property TaskContent $TaskContent
 * @property TaskCharge $TaskCharge
 */
class TaskContentsController extends TasksAppController {

/**
 * use models
 *
 * @var array
 */
	public $uses = array(
		'Tasks.TaskContent',
		'Tasks.TaskCharge',
		'User' => 'Users.User',
		'Mails.MailSetting',
	);

/**
 * use components
 *
 * @var array
 */
	public $components = array(
		'NetCommons.Permission' => array(
			//アクセスの権限
			'allow' => array(
				'add,edit,delete' => 'content_creatable',
			),
		),
		'Categories.Categories',
		'ContentComments.ContentComments' => array(
			'viewVarsKey' => array(
				'contentKey' => 'taskContent.TaskContent.key',
				'useComment' => 'taskSetting.use_comment'
			),
			'allow' => array('view')
		),
		'NetCommons.NetCommonsTime',
	);

/**
 * beforeFilters
 *
 * @return void
 */
	public function beforeFilter() {
		parent::beforeFilter();
	}

/**
 * use helpers
 *
 * @var array
 */
	public $helpers = array(
		'Workflow.Workflow',
		'NetCommons.NetCommonsForm',
		'ContentComments.ContentComment' => array(
			'viewVarsKey' => array(
				'contentKey' => 'taskContent.TaskContent.key',
				'contentTitleForMail' => 'taskContent.TaskContent.title',
				'useComment' => 'taskSetting.use_comment',
				'useCommentApproval' => 'taskSetting.use_comment_approval'
			)
		),
		'Categories.Category',
		'Users.DisplayUser',
		'Users.UserSearch',
		'Groups.GroupUserList',
	);

/**
 * index action
 *
 * @return void
 */
	public function index() {
		if (! Current::read('Block.id')) {
			$this->autoRender = false;
			return;
		}

		$this->_prepare();
		$this->set('listTitle', $this->_taskTitle);

		$conditions = $this->request->params['named'];

		$this->_list($conditions);
	}

/**
 * index add
 *
 * @return void
 */
	public function add() {
		$this->_prepare();

		if ($this->request->is('post')) {
			$data = $this->request->data;

			$data['TaskContent']['task_key'] = $this->_taskSetting['TaskSetting']['task_key'];

			// set status
			$status = $this->Workflow->parseStatus();
			$data['TaskContent']['status'] = $status;

			// set block_id
			$data['TaskContent']['block_id'] = Current::read('Block.id');
			// set language_id
			$data['TaskContent']['language_id'] = Current::read('Language.id');

			// set task_end_date
			if ($data['TaskContent']['is_date_set']) {
				$data = $this->setTaskEndDateTime($data);
			}

			if (($result = $this->TaskContent->saveContent($data))) {
				$url = NetCommonsUrl::actionUrl(
					array(
						'controller' => 'task_contents',
						'action' => 'view',
						'frame_id' => Current::read('Frame.id'),
						'block_id' => Current::read('Block.id'),
						'key' => $result['TaskContent']['key'])
				);

				return $this->redirect($url);
			} else {
				// ToDo担当者ユーザー保持
				$this->request->data = $this->TaskCharge->getSelectUsers($this->request->data);
			}

			$this->NetCommons->handleValidationError($this->TaskContent->validationErrors);

		} else {
			$this->request->data = Hash::merge($this->request->data, $this->TaskContent->create());
		}
		$this->request->data = $this->NetCommonsTime->toUserDatetimeArray(
			$this->request->data,
			array(
				'TaskContent.task_start_date',
				'TaskContent.task_end_date',
			)
		);

		$mailSetting = $this->getMailSetting();
		$this->set('mailSetting', $mailSetting);

		$this->view = 'edit';
	}

/**
 * index edit
 *
 * @return void
 * @throws BadRequestException
 */
	public function edit() {
		$key = $this->params['key'];
		$taskContent = $this->TaskContent->getTask($key);

		// 実施期間設定フラグを持たせる
		if ($taskContent['TaskContent']['task_start_date'] === null) {
			$taskContent['TaskContent']['date_set_flag'] = 0;
		} else {
			$taskContent['TaskContent']['date_set_flag'] = 1;
		}

		// ToDo担当者ユーザー保持
		$taskContent = $this->TaskCharge->getSelectUsers($taskContent);

		if (empty($taskContent)) {
			return $this->throwBadRequest();
		}

		if ($this->TaskContent->canEditWorkflowContent($taskContent) === false) {
			return $this->throwBadRequest();
		}
		$this->_prepare();

		if ($this->request->is('put')) {

			$this->TaskContent->create();
			$this->request->data['TaskContent']['task_key'] =
				$this->_taskSetting['TaskSetting']['task_key'];
			$this->request->data['TaskContent']['key'] = $key;

			// set status
			$status = $this->Workflow->parseStatus();
			$this->request->data['TaskContent']['status'] = $status;
			// set block_id
			$this->request->data['TaskContent']['block_id'] = Current::read('Block.id');
			// set language_id
			$this->request->data['TaskContent']['language_id'] = Current::read('Language.id');

			$data = $this->request->data;

			// set task_end_date
			if ($data['TaskContent']['is_date_set']) {
				$data = $this->setTaskEndDateTime($data);
			}

			unset($data['TaskContent']['id']); // 常に新規保存

			if ($this->TaskContent->saveContent($data)) {
				$url = NetCommonsUrl::actionUrl(
					array(
						'controller' => 'task_contents',
						'action' => 'view',
						'frame_id' => Current::read('Frame.id'),
						'block_id' => Current::read('Block.id'),
						'key' => $data['TaskContent']['key']
					)
				);

				return $this->redirect($url);
			}

			// ToDo担当者ユーザー保持
			$this->request->data = $this->TaskCharge->getSelectUsers($this->request->data);
			// 入力値を保持する
			$taskContent = $this->request->data;

			$this->NetCommons->handleValidationError($this->TaskContent->validationErrors);
		} else {
			$this->request->data = $taskContent;
		}

		$taskContent = $this->NetCommonsTime->toUserDatetimeArray(
			$taskContent,
			array(
				'TaskContent.task_start_date',
				'TaskContent.task_end_date',
			));

		$mailSetting = $this->getMailSetting();
		$this->set('mailSetting', $mailSetting);
		$this->set('taskContent', $taskContent);
		$this->set('isDeletable', $this->TaskContent->canDeleteWorkflowContent($taskContent));

		$comments = $this->TaskContent->getCommentsByContentKey($taskContent['TaskContent']['key']);
		$this->set('comments', $comments);
	}

/**
 * index view
 *
 * @return void
 */
	public function view() {
		if (! Current::read('Block.id')) {
			$this->autoRender = false;
			return;
		}
		$key = $this->params['key'];

		$this->_prepare();
		$this->set('listTitle', $this->_taskTitle);

		$taskContent = $this->TaskContent->getTask($key);

		if ($taskContent) {
			$this->set('taskContent', $taskContent);

			$selectUsers = Hash::extract($taskContent['TaskCharge'], '{n}.user_id');

			$this->request->data['selectUsers'] = array();
			foreach ($selectUsers as $userId) {
				$this->request->data['selectUsers'][] = $this->User->getUser($userId);
			}

			// コメントを利用する
			if ($this->_taskSetting['TaskSetting']['use_comment']) {
				if ($this->request->is('post')) {
					// コメントする

					$taskContentKey = $taskContent['TaskContent']['key'];
					$useCommentApproval = $this->_taskSetting['TaskSetting']['use_comment_approval'];
					if (! $this->ContentComments->comment('tasks', $taskContentKey,
						$useCommentApproval)
					) {
						return $this->throwBadRequest();
					}
				}
			}

		} else {
			// 表示できないToDoへのアクセスならBadRequest
			return $this->throwBadRequest();
		}
	}

/**
 * delete method
 *
 * @throws InternalErrorException
 * @return void
 */
	public function delete() {
		$this->request->allowMethod('post', 'delete');

		$key = $this->request->data['TaskContent']['key'];
		$taskContent = $this->TaskContent->findByKeyAndIsLatest($key, 1);

		// 権限チェック
		if ($this->TaskContent->canDeleteWorkflowContent($taskContent) === false) {
			return $this->throwBadRequest();
		}

		if ($this->TaskContent->deleteContentByKey($key) === false) {
			throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
		}
		$this->redirect(NetCommonsUrl::backToPageUrl());
	}

/**
 * 権限の取得
 *
 * @return array
 */
	protected function _getPermission() {
		$permissionNames = array(
			'content_readable',
			'content_creatable',
			'content_editable',
			'content_publishable',
		);
		$permission = array();
		foreach ($permissionNames as $key) {
			$permission[$key] = Current::permission($key);
		}
		return $permission;
	}

/**
 * 一覧
 *
 * @param array $conditions ソート絞り込み条件
 * @return void
 */
	protected function _list($conditions) {
		$this->TaskContent->recursive = 0;
		$this->TaskContent->Behaviors->load('ContentComments.ContentComment');

		$params = array();
		$defaultOrder = array('TaskContent.modified' => 'desc');
		$userParam = array();

		// カテゴリ絞り込み
		if (isset($conditions['category_id'])) {
			$params[] = array('TaskContent.category_id' => $conditions['category_id']);
		}

		$currentUserId = '';
		// 担当者絞り込み
		if (isset($conditions['user_id'])) {
			if (! empty($conditions['user_id'])
					&& $this->TaskContent->searchChargeUser($conditions['user_id'])) {
				$userParam = array(
					'TaskCharge.user_id' => $conditions['user_id']
				);
			}
			$currentUserId = $conditions['user_id'];
		} else {
			$userParam = array(
				'TaskCharge.user_id' => Current::read('User.id')
			);
		}

		// 完了未完了option取得
		$isCompletionOptions = $this->getSelectOptions('is_completion');
		// 完了未完了絞り込み
		$currentIsCompletion = '';
		if (isset($conditions['is_completion'])) {
			if ($conditions['is_completion'] !== 'all'
				&& isset($isCompletionOptions['TaskContents.is_completion.' . $conditions['is_completion']])
			) {
				$params[] = array(
					'TaskContent.is_completion' => $conditions['is_completion']
				);
			}
			$currentIsCompletion = $conditions['is_completion'];
		} else {
			$params[] = array(
				'TaskContent.is_completion' => TaskContent::TASK_CONTENT_INCOMPLETE_TASK
			);
		}

		// 並べ替えoption取得
		$sortOptions = $this->getSelectOptions('sort');
		// 並べ替え絞り込み
		$sort = $this->getSortParam($conditions, $sortOptions);

		// order情報を整理
		$order = array_merge($sort['order'], $defaultOrder);

		$taskContents = $this->TaskContent->getList($params, $order, $userParam);

		// 期限間近のToDo一覧を分けて取得
		$deadLineTasks = Hash::extract($taskContents, '{n}.TaskContents.{n}[isDeadLine=' . true . ']');
		$this->set('deadLineTasks', $deadLineTasks);

		// 通常のToDo一覧
		$this->set('taskContents', $taskContents);

		// 自身のユーザーデータを取得
		$myUser = array(Current::read('User'));
		// 担当者絞り込みデフォルト値
		$options = array(
			'TaskContents.charge_user_id_all' => array(
				'label' => __d('tasks', 'No person in charge'),
				'user_id' => 'all',
			),
			'TaskContents.charge_user_id_' . $myUser[0]['id'] => array(
				'label' => $myUser[0]['handlename'],
				'user_id' => $myUser[0]['id'],
			),
		);
		$selectChargeUsers = $this->TaskCharge->getSelectChargeUsers($taskContents);

		// 担当者絞り込み条件をマージする
		$userOptions = array_merge($options, $selectChargeUsers);
		$this->set('currentUserId', $currentUserId);
		$this->set('userOptions', $userOptions);
		$this->set('currentIsCompletion', $currentIsCompletion);
		$this->set('currentSort', $sort['currentSort']);

		$this->TaskContent->Behaviors->unload('ContentComments.ContentComment');
	}

/**
 * getMailSetting
 *
 * メール設定情報の取得
 *
 * @return array メール設定情報の配列
 */
	public function getMailSetting() {
		$mailSetting = $this->MailSetting->find('first', array(
				'conditions' => array(
					$this->MailSetting->alias . '.plugin_key' => 'tasks',
					$this->MailSetting->alias . '.block_key' => Current::read('Block.key'),
				),
				'recursive' => -1,
			)
		);
		return $mailSetting;
	}

/**
 * getMailSetting
 *
 * 絞り込み及びソートのoption取得
 *
 * @param void $selectTarget 絞り込み及びソート対象名
 * @return array selectOptions
 */
	public function getSelectOptions($selectTarget = '') {
		$selectOptions = array();

		if ($selectTarget === 'is_completion') {
			$selectOptions = array(
				'TaskContents.is_completion.' . TaskContent::TASK_CONTENT_INCOMPLETE_TASK => array(
					'label' => __d('tasks', 'Incomplete task'),
					'is_completion' => TaskContent::TASK_CONTENT_INCOMPLETE_TASK,
				),
				'TaskContents.is_completion.' . TaskContent::TASK_CONTENT_IS_COMPLETION => array(
					'label' => __d('tasks', 'Completed task'),
					'is_completion' => TaskContent::TASK_CONTENT_IS_COMPLETION,
				),
				'TaskContents.is_completion.' . 'all' => array(
					'label' => __d('tasks', 'All task'),
					'is_completion' => 'all',
				),
			);
			$this->set('isCompletionOptions', $selectOptions);
		}

		if ($selectTarget === 'sort') {
			$selectOptions = array(
				'TaskContent.task_end_date.asc' => array(
					'label' => __d('tasks', 'Close of the deadline order'),
					'sort' => 'TaskContent.task_end_date',
					'direction' => 'asc'
				),
				'TaskContent.priority.desc' => array(
					'label' => __d('tasks', 'Priority order'),
					'sort' => 'TaskContent.priority',
					'direction' => 'desc'
				),
				'TaskContent.progress_rate.desc' => array(
					'label' => __d('tasks', 'High progress rate order'),
					'sort' => 'TaskContent.progress_rate',
					'direction' => 'desc'
				),
				'TaskContent.progress_rate.asc' => array(
					'label' => __d('tasks', 'Low progress rate order'),
					'sort' => 'TaskContent.progress_rate',
					'direction' => 'asc'
				),
			);
			$this->set('sortOptions', $selectOptions);
		}

		return $selectOptions;
	}

/**
 * getSortParam
 *
 * 並び替えのorderパラメーターとcurrentSortの値を取得
 *
 * @param array $conditions POSTされた絞り込み及びソートデータ
 * @param array $sortOptions 並び替え選択肢
 * @return array sortパラメーター
 */
	public function getSortParam($conditions = array(), $sortOptions = array()) {
		$sortPram = '';
		$currentSort = '';
		if (isset($conditions['sort']) && isset($conditions['direction'])) {
			$sortPram = $conditions['sort'] . '.' . $conditions['direction'];
		}
		if (isset($sortOptions[$sortPram])) {
			$order = array($conditions['sort'] => $conditions['direction']);
			$currentSort = $conditions['sort'] . '.' . $conditions['direction'];
		} else {
			$order = array('TaskContent.task_end_date' => 'asc');
		}

		$sort = array(
			'order' => $order,
			'currentSort' => $currentSort
		);

		return $sort;
	}

/**
 * setTaskEndDateTime
 *
 * ToDoの実施日終了日の時刻を23:59:59に設定
 *
 * @param array $data POSTされたToDoデータ
 * @return array
 */
	public function setTaskEndDateTime($data) {
		$endDate = $data['TaskContent']['task_end_date'];
		$data['TaskContent']['task_end_date'] = date(
			'Y-m-d H:i:s', strtotime($endDate . '+1 days -1 second')
		);
		return $data;
	}
}
