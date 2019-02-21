<?php

namespace snapsuzun\sqs\watcher;


use yii\console\Controller;
use yii\console\ExitCode;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

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
    public function beforeAction($action)
    {
        if ($this->canIsolate($action->id) && $this->isolate) {
            $this->watcher->commandHandler = [$this, 'handleMessages'];
        } else {
            $this->watcher->commandHandler = null;
        }

        return parent::beforeAction($action);
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
     * @return int
     * @throws \yii\base\InvalidConfigException
     */
    public function actionExec()
    {
        $data = json_decode(file_get_contents('php://stdin'), true);
        if ($data) {
            ob_start();
            $this->watcher->handleMessages($data['messages'], $data['receiptHandlers']);
            ob_end_clean();
            file_put_contents('php://stdout', json_encode($data['receiptHandlers']));
            return ExitCode::OK;
        } else {
            return ExitCode::DATAERR;
        }
    }

    /**
     * @param array $messages
     * @param array $receiptHandlers
     * @throws \yii\base\InvalidConfigException
     * @throws \Exception
     */
    public function handleMessages(array $messages, array &$receiptHandlers)
    {
        if (extension_loaded('pcntl') && extension_loaded('shmop')) {
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
        } else {
            // Executes child process
            $cmd = strtr('php yii watcher/exec "id"', [
                'php' => PHP_BINARY,
                'yii' => $_SERVER['SCRIPT_FILENAME'],
                'watcher' => $this->uniqueId
            ]);
            foreach ($this->getPassedOptions() as $name) {
                if (in_array($name, $this->options('exec'), true)) {
                    $cmd .= ' --' . $name . '=' . $this->$name;
                }
            }
            if (!in_array('color', $this->getPassedOptions(), true)) {
                $cmd .= ' --color=' . $this->isColorEnabled();
            }

            $process = new Process($cmd, null, null, json_encode(['messages' => $messages, 'receiptHandlers' => $receiptHandlers]), null);
            try {
                $process->mustRun();
                $receiptHandlers = json_decode($process->getOutput(), true) ?? null;
            } catch (ProcessTimedOutException $error) {
                return false;
            }
        }
    }
}