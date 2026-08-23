<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField;

final class CustomFieldTypes
{
    public const string BOOL = 'bool';
    public const string CHECKBOX = 'checkbox';
    public const string COLORPICKER = 'colorpicker';
    public const string DATE = 'date';
    public const string DATETIME = 'datetime';
    public const string ENTITY = 'entity';
    public const string FLOAT = 'float';
    public const string INT = 'int';
    public const string JSON = 'json';
    public const string NUMBER = 'number';
    public const string HTML = 'html';
    public const string MEDIA = 'media';
    public const string SELECT = 'select';
    public const string SWITCH = 'switch';
    public const string TEXT = 'text';

    private function __construct()
    {
    }
}
