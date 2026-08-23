<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue;

use Contena\Core\Framework\DependencyInjection\CompilerPass\ScheduledTaskExecutorCompilerPass;
use Contena\Core\Framework\HttpException;
use Contena\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskExecutor;
use Symfony\Component\HttpFoundation\Response;

class MessageQueueException extends HttpException
{
    public const string NO_VALID_RECEIVER_NAME_PROVIDED = 'FRAMEWORK__NO_VALID_RECEIVER_NAME_PROVIDED';
    public const string QUEUE_CANNOT_UNSERIALIZE_MESSAGE = 'FRAMEWORK__QUEUE_CANNOT_UNSERIALIZE_MESSAGE';
    public const string WORKER_IS_LOCKED = 'FRAMEWORK__WORKER_IS_LOCKED';
    public const string CANNOT_FIND_SCHEDULED_TASK = 'FRAMEWORK__CANNOT_FIND_SCHEDULED_TASK';
    public const string QUEUE_MESSAGE_SIZE_EXCEEDS = 'FRAMEWORK__QUEUE_MESSAGE_SIZE_EXCEEDS';
    public const string QUEUE_STATS_NOT_FOUND = 'FRAMEWORK__QUEUE_STATS_NOT_FOUND';
    public const string MISSING_EXTENDS_CODE = 'FRAMEWORK__SCHEDULED_TASK_MISSING_EXTENDS';
    public const string NOT_FOUND_CODE = 'FRAMEWORK__SCHEDULED_TASK_NOT_FOUND';
    public const string SCHEDULED_TASK_NOT_IMPLEMENTING_INTERFACE = 'FRAMEWORK__SCHEDULED_TASK_NOT_IMPLEMENTING_INTERFACE';
    public const string SCHEDULED_TASK_EXECUTOR_NOT_SET = 'FRAMEWORK__SCHEDULED_TASK_EXECUTOR_NOT_SET';

    public static function validReceiverNameNotProvided(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NO_VALID_RECEIVER_NAME_PROVIDED,
            'No receiver name provided.',
        );
    }

    public static function cannotUnserializeMessage(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::QUEUE_CANNOT_UNSERIALIZE_MESSAGE,
            'Cannot unserialize message {{ message }}',
            ['message' => $message]
        );
    }

    public static function workerIsLocked(string $receiver): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::WORKER_IS_LOCKED,
            'Another worker is already running for receiver: "{{ receiver }}"',
            ['receiver' => $receiver]
        );
    }

    public static function cannotFindTaskByName(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CANNOT_FIND_SCHEDULED_TASK,
            self::$couldNotFindMessage,
            ['entity' => 'scheduled task', 'field' => 'name', 'value' => $name]
        );
    }

    public static function maxQueueMessageSizeExceeded(string $messageName, float $size, int $maxSize): self
    {
        $message = 'The message "{{ message }}" exceeds the {{ maxSize }} KiB size limit with its size of {{ size }} KiB.';

        return new self(
            Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
            self::QUEUE_MESSAGE_SIZE_EXCEEDS,
            $message,
            [
                'message' => $messageName,
                'maxSize' => $maxSize,
                'size' => $size,
            ]
        );
    }

    public static function missingExtends(string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_EXTENDS_CODE,
            'Tried to register "{{ class }}" as scheduled task, but class does not extend ScheduledTask',
            ['class' => $class]
        );
    }

    public static function notFound(string $name): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::NOT_FOUND_CODE,
            'Tried to fetch "{{ name }}" scheduled task, but scheduled task does not exist',
            ['name' => $name]
        );
    }

    public static function scheduledTaskDoesNotImplementInterface(string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SCHEDULED_TASK_NOT_IMPLEMENTING_INTERFACE,
            'Tried to schedule "{{ class }}", but class does not extend ScheduledTask',
            ['class' => $class]
        );
    }

    public static function scheduledTaskExecutorNotSet(string $handler): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SCHEDULED_TASK_EXECUTOR_NOT_SET,
            'No "{{ executor }}" was set on the scheduled task handler "{{ handler }}". Register the handler as a "messenger.message_handler" service so the "{{ compilerPass }}" can inject the executor, or call "{{ handler }}::setScheduledTaskExecutor()" manually.',
            [
                'executor' => ScheduledTaskExecutor::class,
                'handler' => $handler,
                'compilerPass' => ScheduledTaskExecutorCompilerPass::class,
            ]
        );
    }
}
