<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Type;

use App\Domain\Merchant\MerchantCategoryCode;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * Maps the Card's allowed-categories collection (list<MerchantCategoryCode>)
 * to a JSONB array of code strings. The descriptions are reconstructed via
 * MerchantCategoryCode::ofCode() on read so the column stays compact.
 */
final class MerchantCategoryCodeListType extends Type
{
    public const NAME = 'merchant_category_code_list';

    /**
     * @return list<MerchantCategoryCode>
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        $codes = is_array($value) ? $value : json_decode((string) $value, true);
        if (!is_array($codes)) {
            throw new ConversionException(sprintf('Cannot decode MCC list payload: %s', (string) $value));
        }

        return array_values(array_map(
            static fn ($code): MerchantCategoryCode => MerchantCategoryCode::ofCode((string) $code),
            $codes,
        ));
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): string
    {
        if (!is_array($value)) {
            throw new ConversionException('Expected a list of MerchantCategoryCode value objects.');
        }

        $codes = array_map(
            static function ($mcc): string {
                if (!$mcc instanceof MerchantCategoryCode) {
                    throw new ConversionException('Every list entry must be a MerchantCategoryCode.');
                }

                return $mcc->code;
            },
            $value,
        );

        return json_encode($codes, JSON_THROW_ON_ERROR);
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
