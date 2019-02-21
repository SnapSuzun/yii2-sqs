Amazon SQS Watcher for Yii2
===========================

An extension for receiving and handling messages from Amazon SQS.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist snapsuzun/yii2-sqs
```

or add

```
"snapsuzun/yii2-sqs": "dev-master"
```

to the require section of your `composer.json` file.

Configuration
-----------

To use this extension, simply add the following code in your application configuration:

```php
return [
    'bootstrap' => ['sqsWatcher']
    //....
    'components' => [
        'sqsClient' => function () {
            return new \snapsuzun\sqs\SqsClient([
                'cridentials => [
                    'key' => 'Api key of Amazon AWS',
                    'secret' => 'Secret key of Amazon AWS'
                ],
                'region' => 'Region of Amazon SQS',
                'version' => 'latest',
                'accountId' => 'ID of account in Amazon AWS',
                'queueNameAliases' => [
                    'aliasForQueue' => 'Real name of queue in Amazon SQS',
                    'test_queue' => 'TestQueue.fifo'
                ]
            ]);
        },
        'sqsWatcher' => [
            'class' => '\snapsuzun\sqs\watcher\Watcher',
            'queueName' => 'queue',
            'handler' => function (array $messages, array &$receiptHandlers, \snapsuzun\sqs\SqsClient $client) {
                // handle messages                
            }
        ],
    ],
];
```

Message Handler
---------------

Handling messages from SQS queue may be by callback function or object which implements `\snapsuzun\sqs\watcher\HandlerInterface`.

Example of object what implements `\snapsuzun\sqs\watcher\HandlerInterface`:

```php
class ExampleHandler implements \snapsuzun\sqs\watcher\HandlerInterface 
{
        public $db = 'db';
   
        /**
         * @param array $messages
         * @param array $receiptHandlers
         * @param \snapsuzun\sqs\SqsClient $client
         */
        public function handleMessages(array $messages, array &$receiptHandlers, \snapsuzun\sqs\SqsClient $client)
        {
            // handle messages
        }
}
```

Usage `ExampleHandler`:

```php
return [
    //.......
    'components' => [
        'sqsWatcher' => [
            //.....
            'handler' => ExampleHandler::class
        ]
    ]
];
```

Or:

```php
return [
    //.......
    'components' => [
        'sqsWatcher' => [
            //.....
            'handler' => [
                'class' => ExampleHandler::class,
                'db' => 'db2'
            ]
        ]
    ]
];
```

When messages handling you can remove receipt handler from $receiptHandlers and message will not removed from SQS queue.

Console commands:
------

To listen queue you can use command:

```
./yii sqs-watcher/listen --isolate=1
``` 

If you want only handle messages in queue and not want to wait a new messages, you can use command:

```
./yii sqs-watcher/run --isolate=1
```

Option ``isolate`` enable creating a fork process for each batch of messages.