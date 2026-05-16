<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Authorization\AuthorizationId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class AuthorizationIdType extends Type
{
    public const NAME = 'authorization_id';

    public function convertToPHPValue($value, AbstractPlatform $platform): ?AuthorizationId
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof AuthorizationId) {
            return $value;
        }

        return AuthorizationId::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof AuthorizationId) {
            return $value->toString();
        }

        return (string) $value;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
