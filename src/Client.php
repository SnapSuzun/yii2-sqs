<?php

namespace snapsuzun\sqs;


use Aws\Sqs\SqsClient as BaseSqsClient;

/**
 * Class Client
 * @package snapsuzun\sqs
 */
class Client extends BaseSqsClient
{
    /**
     * @var null|string
     */
    public $accountId = null;

    /**
     * @var array
     */
    public $queueNameAliases = [];

    /**
     * @var array
     */
    protected $_knownQueueUrls = [];

    /**
     * @var null|array
     */
    protected $_listQueues = null;

    /**
     * AmazonSQS constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->load($config);
        parent::__construct($config);
    }

    /**
     * @param array|null $params
     */
    public function load(array $params)
    {
        foreach ($params as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * Получение URL очереди по ее названию
     *
     * @param string $queueName Алиас из sqsQueueAliases или название очереди
     * @return null|string
     */
    public function getSqsQueueUrl(string $queueName): ?string
    {
        $queueName = $this->getRealQueueName($queueName);
        if (empty($this->_knownQueueUrls[$queueName])) {
            $args = [
                'QueueName' => $queueName
            ];
            if ($this->accountId) {
                $args['QueueOwnerAWSAccountId'] = $this->accountId;
            }
            $result = $this->getQueueUrl($args);
            $this->_knownQueueUrls[$queueName] = $result->get('QueueUrl');
        }
        return $this->_knownQueueUrls[$queueName];
    }

    /**
     * Проверка на существование очереди
     *
     * @param string $queueName
     * @return bool|null
     */
    public function existsQueue(string $queueName): ?bool
    {
        return isset($this->getQueueUrlList()[$this->getQueueName($this->getRealQueueName($queueName))]);
    }

    /**
     * Получение именованного списка URL очередей
     *
     * @return array|mixed|null
     */
    public function getQueueUrlList()
    {
        if (is_null($this->_listQueues)) {
            $this->_listQueues = $this->listQueues()->get('QueueUrls');
            $buffer = [];
            foreach (array_keys($this->_listQueues) as $key) {
                $split = explode('/', $this->_listQueues[$key]);
                $buffer[array_pop($split)] = $this->_listQueues[$key];
            }
            $this->_listQueues = $buffer;
        }
        return $this->_listQueues;
    }

    /**
     * Получение реального названия очереди, с проверкой на существование алиаса имени в параметрах
     *
     * @param string $queueName
     * @return string
     */
    public function getRealQueueName(string $queueName): string
    {
        return $this->queueNameAliases[$queueName] ?? $queueName;
    }

    /**
     * Получение названия очереди с учетом флага
     *
     * @param string $queueName
     * @return string
     */
    public function getQueueName(string $queueName): string
    {
        if (YII_ENV_DEV) {
            return 'dev_' . $queueName;
        }
        return $queueName;
    }

    /**
     * @param array $args
     * @return \Aws\Result
     */
    public function createQueue(array $args = [])
    {
        $this->_listQueues = null;
        if (isset($args['QueueName'])) {
            $args['QueueName'] = $this->getQueueName($args['QueueName']);
        }
        return parent::createQueue($args);
    }

    /**
     * @param array $args
     * @return \Aws\Result
     */
    public function getQueueUrl(array $args = [])
    {
        if (isset($args['QueueName'])) {
            $args['QueueName'] = $this->getQueueName($args['QueueName']);
        }
        return parent::getQueueUrl($args);
    }

    /**
     * @param array $args
     * @return \Aws\Result
     */
    public function deleteQueue(array $args = [])
    {
        $this->_listQueues = null;
        return parent::deleteQueue($args);
    }
}