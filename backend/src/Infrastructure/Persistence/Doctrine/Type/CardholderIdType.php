<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Card\CardholderId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

final class CardholderIdType extends Type
{
    public const NAME = 'cardholder_id';

    public function convertToPHPValue($value, AbstractPlatform $platform): ?CardholderId
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof CardholderId) {
            return $value;
        }

        return CardholderId::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof CardholderId) {
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
