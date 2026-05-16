<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Card\CardId;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

/**
 * Maps the CardId value object to a PostgreSQL UUID column. Keeps the
 * domain free of any Doctrine knowledge — the type lives here in
 * infrastructure and is registered via doctrine.yaml.
 */
final class CardIdType extends Type
{
    public const NAME = 'card_id';

    public function convertToPHPValue($value, AbstractPlatform $platform): ?CardId
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof CardId) {
            return $value;
        }

        return CardId::fromString((string) $value);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof CardId) {
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
