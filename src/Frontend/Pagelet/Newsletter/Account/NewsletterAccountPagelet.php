<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Newsletter\Account;

use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Pagelet\Pagelet;

class NewsletterAccountPagelet extends Pagelet
{
    protected UserEntity $user;

    protected ?bool $success = null;

    /**
     * @var array<array<string, mixed>>|null
     */
    protected ?array $messages = null;

    protected ?bool $newsletterDoi = null;

    protected ?string $newsletterStatus = null;

    public function getUser(): UserEntity
    {
        return $this->user;
    }

    public function setUser(UserEntity $user): void
    {
        $this->user = $user;
    }

    public function isSuccess(): ?bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    /**
     * @return array<array<string, mixed>>|null
     */
    public function getMessages(): ?array
    {
        return $this->messages;
    }

    /**
     * @param array<array<string, mixed>> $messages
     */
    public function setMessages(array $messages): void
    {
        $this->messages = $messages;
    }

    /**
     * @param array<array<string, mixed>> $messages
     */
    public function addMessages(array $messages): void
    {
        if (!\is_array($this->messages)) {
            $this->messages = $messages;
        } else {
            $this->messages = array_merge($this->messages, $messages);
        }
    }

    public function isNewsletterDoi(): ?bool
    {
        return $this->newsletterDoi;
    }

    public function setNewsletterDoi(bool $newsletterDoi): void
    {
        $this->newsletterDoi = $newsletterDoi;
    }

    public function getNewsletterStatus(): ?string
    {
        return $this->newsletterStatus;
    }

    public function setNewsletterStatus(?string $newsletterStatus): void
    {
        $this->newsletterStatus = $newsletterStatus;
    }
}
