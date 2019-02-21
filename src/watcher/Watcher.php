<?php

namespace snapsuzun\sqs\watcher;

use snapsuzun\sqs\SqsClient;
use snapsuzun\sqs\watcher\events\DeleteErrorEvent;
use Yii;
use yii\base\Application;
use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\console\Application as ConsoleApp;
use yii\di\Instance;
use yii\helpers\Inflector;

/**
 * Class Watcher
 * @package snapsuzun\sqs\watcher
 */
class Watcher extends Component implements BootstrapInterface
{
    const EVENT_DELETE_ERROR = 'deleteError';

    /**
     * @var string
     */
    public $queueName;

    /**
     * @var int
     */
    public $maxNumberOfMessages = 10;

    /**
     * @var string command class name
     */
    public $commandClass = Command::class;

    /**
     * @var array of additional options of command
     */
    public $commandOptions = [];

    /**
     * @var string|array|SqsClient|callable
     */
    public $sqsClient = 'sqsClient';

    /**
     * @var callable|string|array|HandlerInterface
     */
    public $handler;

    /**
     * Only for set from SqsCommand
     * @var null|callable
     */
    public $commandHandler = null;

    /**
     * @throws InvalidConfigException
     */
    public function init()
    {
        parent::init();
        if (empty($this->queueName)) {
            throw new InvalidConfigException("queueName should not be empty!");
        }
        if (!is_a($this->commandClass, Command::class, true)) {
            throw new InvalidConfigException('commandClass should be instance of ' . Command::class);
        }
        if (is_callable($this->sqsClient)) {
            $this->sqsClient = call_user_func($this->sqsClient);
        }
        $this->sqsClient = Instance::ensure($this->sqsClient, SqsClient::class);
    }

    /**
     * @param Application $app
     * @throws InvalidConfigException
     */
    public function bootstrap($app)
    {
        if ($app instanceof ConsoleApp) {
            $app->controllerMap[$this->getCommandId()] = [
                    'class' => $this->commandClass,
                    'watcher' => $this,
                ] + $this->commandOptions;
        }
    }

    /**
     * @return string command id
     * @throws
     */
    protected function getCommandId()
    {
        foreach (Yii::$app->getComponents(false) as $id => $component) {
            if ($component === $this) {
                return Inflector::camel2id($id);
            }
        }
        throw new InvalidConfigException('Queue must be an application component.');
    }

    /**
     * @param $repeat
     * @param int $timeout
     * @throws InvalidConfigException
     */
    public function run(bool $repeat, $timeout = 0)
    {
        while (true) {
            if (!empty(($messages = $this->getMessages()))) {
                list($messageBodies, $receiptHandlers) = $this->prepareMessages($messages);
                $this->handleMessages($messageBodies, $receiptHandlers);
                $this->deleteMessages($receiptHandlers, $messageBodies);
            } elseif ($repeat) {
                sleep($timeout);
            } else {
                break;
            }
        }
    }

    /**
     * @return array|null
     */
    protected function getMessages()
    {
        $response = $this->sqsClient->receiveMessage([
            'MaxNumberOfMessages' => $this->maxNumberOfMessages,
            'MessageAttributeNames' => ['All'],
            'QueueUrl' => $this->sqsClient->getSqsQueueUrl($this->queueName),
        ]);

        return $response->get('Messages');
    }

    /**
     * @param array $messages
     * @return array
     */
    protected function prepareMessages(array $messages): array
    {
        $messageBodies = [];
        $receiptHandlers = [];

        foreach ($messages as $message) {
            $messageBodies[$message['MessageId']] = json_decode($message['Body'], true);
            $receiptHandlers[$message['MessageId']] = $message['ReceiptHandle'];
        }

        return [$messageBodies, $receiptHandlers];
    }

    /**
     * @param array $messages
     * @param array $receiptHandlers
     * @throws InvalidConfigException
     */
    public function handleMessages(array $messages, array &$receiptHandlers)
    {
        call_user_func_array(is_callable($this->commandHandler) ? $this->commandHandler : $this->prepareHandleFunction(), [
            $messages,
            &$receiptHandlers,
            $this->sqsClient
        ]);
    }

    /**
     * @return HandlerInterface|array|callable|string
     * @throws InvalidConfigException
     */
    protected function prepareHandleFunction()
    {
        if (is_callable($this->handler)) {
            return $this->handler;
        }
        if (is_string($this->handler)) {
            $this->handler = new $this->handler();
        } elseif (is_array($this->handler) && isset($this->handler['class'])) {
            $class = $this->handler['class'];
            unset($this->handler['class']);
            $this->handler = new $class($this->handler);
        }

        if ($this->handler instanceof HandlerInterface) {
            return [$this->handler, 'handleMessages'];
        } else {
            throw new InvalidConfigException("Unable to identify handle function.");
        }
    }

    /**
     * @param array $receiptHandlers
     * @param array $messages
     */
    protected function deleteMessages(array $receiptHandlers, array $messages)
    {
        if (!empty($receiptHandlers)) {
            $deletingEntries = [];
            foreach ($receiptHandlers as $messageId => $handlerId) {
                $deletingEntries[] = [
                    'Id' => $messageId,
                    'ReceiptHandle' => $handlerId,
                ];
            }
            $result = $this->sqsClient->deleteMessageBatch([
                'QueueUrl' => $this->sqsClient->getSqsQueueUrl($this->queueName),
                'Entries' => $deletingEntries,
            ]);
            $failedRequests = $result->get('Failed');
            if (is_array($failedRequests)) {
                foreach ($failedRequests as $request) {
                    $errorEvent = new DeleteErrorEvent([
                        'message' => $request['Message'],
                        'messageBody' => $messages[$request['Id']],
                        'messageId' => $request['Id']
                    ]);
                    $this->trigger(static::EVENT_DELETE_ERROR, $errorEvent);
                }
            }
        }
    }
}