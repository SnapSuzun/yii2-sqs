<?php

namespace snapsuzun\sqs\watcher;

use snapsuzun\sqs\SqsClient;

/**
 * Interface HandlerInterface
 * @package snapsuzun\sqs\watcher
 */
interface HandlerInterface
{
    /**
     * @param array $messages
     * @param array $receiptHandlers
     * @param SqsClient $client
     */
    public function handleMessages(array $messages, array &$receiptHandlers, SqsClient $client);
}