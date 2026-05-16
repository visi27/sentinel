<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Merchant\GeoLocation;
use App\Domain\Merchant\Merchant;
use App\Domain\Merchant\MerchantCategoryCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Stores a Merchant value object as a single JSONB column. JSON keeps the
 * optional GeoLocation simple (no nullable-embeddable gymnastics) and we
 * never need to filter by merchant fields on the write side.
 */
final class MerchantType extends Type
{
    public const NAME = 'merchant';

    public function convertToPHPValue($value, AbstractPlatform $platform): ?Merchant
    {
        if (null === $value) {
            return null;
        }

        if ($value instanceof Merchant) {
            return $value;
        }

        $data = is_array($value) ? $value : json_decode((string) $value, true);
        if (!is_array($data)) {
            throw new ConversionException(sprintf('Cannot decode merchant payload: %s', (string) $value));
        }

        $location = null;
        if (isset($data['location']) && is_array($data['location'])) {
            $location = new GeoLocation(
                (string) $data['location']['city'],
                (string) $data['location']['region'],
                (string) $data['location']['country'],
            );
        }

        return new Merchant(
            (string) $data['name'],
            new MerchantCategoryCode(
                (string) $data['category_code'],
                (string) ($data['category_description'] ?? $data['category_code']),
            ),
            $location,
        );
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }

        if (!$value instanceof Merchant) {
            throw new ConversionException('Expected an App\\Domain\\Merchant\\Merchant instance.');
        }

        $payload = [
            'name' => $value->name,
            'category_code' => $value->categoryCode->code,
            'category_description' => $value->categoryCode->description,
            'location' => null === $value->location ? null : [
                'city' => $value->location->city,
                'region' => $value->location->region,
                'country' => $value->location->country,
            ],
        ];

        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

        return $encoded;
    }

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return 'JSONB';
    }

    public function getName(): string
    {
        return self::NAME;
    }
}
