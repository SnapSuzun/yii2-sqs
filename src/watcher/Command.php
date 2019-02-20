<?php

namespace snapsuzun\sqs\watcher;


use yii\console\Controller;

/**
 * Class Command
 * @package snapsuzun\sqs\watcher
 */
class Command extends Controller
{
    /**
     * @var Watcher
     */
    public $watcher;

    /**
     * @var bool
     */
    public $isolate = true;

    /**
     * @var string
     */
    public $defaultAction = 'run';

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        $options = parent::options($actionID);
        if ($this->canIsolate($actionID)) {
            $options[] = 'isolate';
        }
        return $options;
    }

    /**
     * @inheritdoc
     */
    protected function isWorkerAction($actionID)
    {
        return in_array($actionID, ['run', 'listen'], true);
    }

    /**
     * @param string $actionID
     * @return bool
     */
    protected function canIsolate($actionID)
    {
        return $this->isWorkerAction($actionID);
    }

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function actionRun()
    {
        return $this->watcher->run(false);
    }

    /**
     * @param int $timeout
     * @throws \yii\base\InvalidConfigException
     */
    public function actionListen($timeout = 3)
    {
        return $this->watcher->run(true, $timeout);
    }

    /**
     * @param array $messages
     * @param array $receiptHandlers
     * @throws \yii\base\InvalidConfigException
     * @throws \Exception
     */
    protected function handleMessages(array $messages, array &$receiptHandlers)
    {
        // Разделяемая память, в которую будет скидываться измененный массив $receiptHandlers
        $sharedMemory = shmop_open(0, 'c', 0644, (count($receiptHandlers) * 512) + 5);
        if (!$sharedMemory) {
            throw new \Exception("Unable to create shared memory.");
        }
        // Создаем форк процесса, в котором будет обрабатываться пачка сообщений
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new \Exception("Unable to create fork process.");
        } else if ($pid) {
            // Родительский процесс
            pcntl_waitpid($pid, $status);
        } else {
            // Дочерний процесс
            $this->watcher->commandHandler = null;
            $this->watcher->handleMessages($messages, $receiptHandlers);
            shmop_write($sharedMemory, json_encode($receiptHandlers), 0);
            exit(0);
        }
        $receiptHandlers = json_decode(trim(shmop_read($sharedMemory, 0, 0)), true);
        shmop_delete($sharedMemory);
    }
}