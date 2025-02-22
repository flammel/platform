<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\RecoverPassword;

use Shopware\Storefront\Page\Page;

/**
 * @package customer-order
 */
class AccountRecoverPasswordPage extends Page
{
    protected ?string $hash = null;

    protected bool $hashExpired;

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): void
    {
        $this->hash = $hash;
    }

    public function isHashExpired(): bool
    {
        return $this->hashExpired;
    }

    public function setHashExpired(bool $hashExpired): void
    {
        $this->hashExpired = $hashExpired;
    }
}
