<?php

namespace Lkt\Factory\Instantiator\Encrypt;

use Lkt\Factory\Schemas\Fields\EncryptField;
use Lkt\Factory\Schemas\Schema;

class EncryptFieldHelper
{
    public static function autoEncryptSchemaFieldValue(string $component, string $fieldName, string $value): string
    {
        $schema = Schema::get($component);
        $field = $schema->getField($fieldName);

        if ($field instanceof EncryptField) {
            if ($field->hasAlgorithmSHA256()) {
                $secureSeed = $field->getSecureSeed();

                if ($field->isHashMode()) {
                    $value = hash_hmac('sha256', $value . $secureSeed, $secureSeed);
                } else {
                    $value = static::encryptSHA256($value, $secureSeed);
                }
            }
        }

        return $value;
    }

    public static function encryptSHA256(string $value, string $secureSeed, int $iv_size = 32): string
    {
        return openssl_encrypt(static::pkcs7_pad($value, $iv_size), 'AES-256-CBC', $secureSeed);
    }

    public static function decryptSHA256(string $value, string $secureSeed, int $iv_size = 32): string
    {
        return openssl_decrypt(static::pkcs7_pad($value, $iv_size), 'AES-256-CBC', $secureSeed);
    }

    private static function pkcs7_pad($data, $size): string
    {
        $length = $size - strlen($data) % $size;
        return $data . str_repeat(chr($length), $length);
    }
}