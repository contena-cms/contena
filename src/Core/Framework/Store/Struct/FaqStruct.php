<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\Struct;

use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class FaqStruct extends StoreStruct
{
    protected string $question;

    protected string $answer;

    /**
     * @return FaqStruct
     */
    public static function fromArray(array $data): StoreStruct
    {
        return (new self())->assign($data);
    }

    public function getQuestion(): string
    {
        return $this->question;
    }

    public function getAnswer(): string
    {
        return $this->answer;
    }
}
