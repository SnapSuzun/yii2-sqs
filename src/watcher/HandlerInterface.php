<?php

namespace snapsuzun\sqs\watcher;

use snapsuzun\sqs\Client;

/**
 * Interface HandlerInterface
 * @package snapsuzun\sqs\watcher
 */
interface HandlerInterface
{
    /**
     * @param array $messages
     * @param array $receiptHandlers
     * @param Client $client
     */
    public function handleMessages(array $messages, array &$receiptHandlers, Client $client);
}