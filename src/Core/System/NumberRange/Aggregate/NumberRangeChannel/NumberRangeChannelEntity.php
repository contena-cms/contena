<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\Aggregate\NumberRangeChannel;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeEntity;
use Contena\Core\System\NumberRange\NumberRangeEntity;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @codeCoverageIgnore
 */
class NumberRangeChannelEntity extends Entity
{
    use EntityIdTrait;

    protected string $numberRangeId;

    protected string $channelId;

    protected string $numberRangeTypeId;

    protected ?NumberRangeEntity $numberRange = null;

    protected ?ChannelEntity $channel = null;

    protected ?NumberRangeTypeEntity $numberRangeType = null;

    public function getNumberRangeId(): string
    {
        return $this->numberRangeId;
    }

    public function setNumberRangeId(string $numberRangeId): void
    {
        $this->numberRangeId = $numberRangeId;
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function setChannelId(string $channelId): void
    {
        $this->channelId = $channelId;
    }

    public function getNumberRangeTypeId(): string
    {
        return $this->numberRangeTypeId;
    }

    public function setNumberRangeTypeId(string $numberRangeTypeId): void
    {
        $this->numberRangeTypeId = $numberRangeTypeId;
    }

    public function getNumberRange(): ?NumberRangeEntity
    {
        return $this->numberRange;
    }

    public function setNumberRange(NumberRangeEntity $numberRange): void
    {
        $this->numberRange = $numberRange;
    }

    public function getChannel(): ?ChannelEntity
    {
        return $this->channel;
    }

    public function setChannel(ChannelEntity $channel): void
    {
        $this->channel = $channel;
    }

    public function getNumberRangeType(): ?NumberRangeTypeEntity
    {
        return $this->numberRangeType;
    }

    public function setNumberRangeType(NumberRangeTypeEntity $numberRangeType): void
    {
        $this->numberRangeType = $numberRangeType;
    }
}
