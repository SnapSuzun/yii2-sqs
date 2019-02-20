<?php

namespace snapsuzun\sqs\watcher\events;


use yii\base\Event;

/**
 * Class DeleteErrorEvent
 * @package snapsuzun\sqs\watcher\events
 */
class DeleteErrorEvent extends Event
{
    /**
     * @var string
     */
    public $message;

    /**
     * @var array
     */
    public $messageBody;

    /**
     * @var string
     */
    public $messageId;
}