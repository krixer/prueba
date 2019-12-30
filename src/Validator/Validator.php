<?php

namespace App\Validator;

use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;

use DateTime;

/**
 * @author Hector Escriche <krixer@gmail.com>
 */
final class Validator
{
    public static function validateNumber($number)
    {
        if (!$number) {
            return $number;
        }

        if (!is_numeric($number)) {
            throw new RuntimeCommandException(sprintf('Invalid number "%s".', $number));
        }

        return (int) $number;
    }

    public static function validateTime($string)
    {
        if (!$string) {
            return $string;
        }

        if(!preg_match("/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/", $string)){
            throw new RuntimeCommandException(sprintf('Invalid time "%s".', $string));
        }

        return new DateTime($string);
    }
}
