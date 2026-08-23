<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

enum FieldType: string
{
    public const string UUID = 'uuid';
    public const string STRING = 'string';
    public const string TEXT = 'text';
    public const string INT = 'int';
    public const string FLOAT = 'float';
    public const string BOOL = 'bool';
    public const string ENUM = 'enum';
    public const string JSON = 'json';
    public const string DATETIME = 'datetime';
    public const string DATE = 'date';
    public const string DATE_INTERVAL = 'date-interval';
    public const string TIME_ZONE = 'time-zone';
    public const string EMAIL = 'email';
}
