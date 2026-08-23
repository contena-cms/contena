<?php declare(strict_types=1);

namespace Contena\Core\System\Tenant\Resolver;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Resolves tenants by the subdomain convention: the first host label is the
 * tenant code, e.g. "ac.contena.cn" resolves tenant code "ac".
 *
 * @internal
 */
final class SubdomainTenantResolver implements TenantResolverInterface
{
    public const string ID = 'subdomain';

    private const string CODE_PATTERN = '/^[a-z0-9-]{2,64}$/';

    public function __construct(
        private readonly Connection $connection,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function resolve(Request $request): ?TenantResolution
    {
        $labels = \explode('.', $request->getHost());
        if (\count($labels) < 2) {
            return null;
        }

        $code = $labels[0];

        if (\count($this->validator->validate($code, [new Regex(self::CODE_PATTERN)])) > 0) {
            return null;
        }

        $tenantId = $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `tenant` WHERE `code` = :code AND `status` = 1',
            ['code' => $code],
        );

        if (!\is_string($tenantId) || $tenantId === '') {
            return null;
        }

        return new TenantResolution($tenantId, self::ID);
    }
}
