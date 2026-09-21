<?php
namespace App\Service;

final class ErpUserPassword
{
    public static function hash(string $source, string $format, string $manual): string
    {
        if ($format === 'php_hash' && in_array(password_get_info($source)['algoName'], ['bcrypt', 'argon2i', 'argon2id'], true)) return $source;
        if ($format === 'plain' && $source !== '') return password_hash($source, PASSWORD_DEFAULT);
        if (mb_strlen($manual) < 8) throw new \InvalidArgumentException('Informe uma senha manual de pelo menos 8 caracteres para os usuários sem senha de origem compatível.');
        return password_hash($manual, PASSWORD_DEFAULT);
    }
}
