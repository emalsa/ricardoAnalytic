<?php

/**
 * @file
 * Contains \Drupal\Console\Core\Utils\StringConverter
 */

namespace Drupal\Console\Core\Utils;

/**
 * Class StringConverter
 *
 * @package Drupal\Console\Core\Utils
 */
class StringConverter
{
    // This REGEX captures all uppercase letters after the first character
    const REGEX_UPPER_CASE_LETTERS = '/(?<=\\w)(?=[A-Z])/';
    // This REGEX captures non alphanumeric characters and non underscores
    const REGEX_MACHINE_NAME_CHARS = '@[^a-z0-9_]+@';
    // This REGEX captures
    const REGEX_CAMEL_CASE_UNDER = '/([a-z])([A-Z])/';
    // This REGEX captures spaces around words
    const REGEX_SPACES = '/\s\s+/';
    // This REGEX forces max length to 32
    const MAX_MACHINE_NAME = 32;

    /**
     * Replaces non alphanumeric characters with underscores.
     *
     * @param String $name User input
     *
     * @return String $machine_name User input in machine-name format
     */
    public function createMachineName($name)
    {
        $machine_name = preg_replace(self::REGEX_MACHINE_NAME_CHARS, '_', strtolower($name));
        $machine_name = trim($machine_name, '_');

        return $this->trimMachineNameToMaxLength($machine_name);
    }

    /**
     *  Converts camel-case strings to machine-name format.
     *
     * @param String $name User input
     *
     * @return String $machine_name  User input in machine-name format
     */
    public function camelCaseToMachineName($name)
    {
        $machine_name = preg_replace(self::REGEX_UPPER_CASE_LETTERS, '_$1', $name);
        $machine_name = preg_replace(self::REGEX_MACHINE_NAME_CHARS, '_', strtolower($machine_name));
        $machine_name = trim($machine_name, '_');

        return $this->trimMachineNameToMaxLength($machine_name);
    }

    /**
     * Trim machine name if it exceed max number of symbols for machine names.
     *
     * @param string $machine_name
     *   Machine name.
     *
     * @return string
     *   Machine name.
     */
    public function trimMachineNameToMaxLength($machine_name) {
        if (strlen($machine_name) > self::MAX_MACHINE_NAME) {
            $machine_name = substr($machine_name, 0, self::MAX_MACHINE_NAME);
        }
        return $machine_name;
    }

    /**
     *  Converts camel-case strings to under-score format.
     *
     * @param String $camel_case User input
     *
     * @return String
     */
    public function camelCaseToUnderscore($camel_case)
    {
        return strtolower(preg_replace(self::REGEX_CAMEL_CASE_UNDER, '$1_$2', $camel_case));
    }

    /**
     * Converts camel-case strings to human readable format.
     *
     * @param String $camel_case User input
     *
     * @return String
     */
    public function camelCaseToHuman($camel_case)
    {
        return ucfirst(strtolower(preg_replace(self::REGEX_CAMEL_CASE_UNDER, '$1 $2', $camel_case)));
    }

    /**
     * @param $human
     * @return mixed
     */
    public function humanToCamelCase($human)
    {
        return str_replace(' ', '', ucwords($human));
    }

    /**
     * Converts My Name to my name. For permissions.
     *
     * @param String $permission User input
     *
     * @return String
     */
    public function camelCaseToLowerCase($permission)
    {
        return strtolower(preg_replace(self::REGEX_SPACES, ' ', $permission));
    }

    /**
     * Convert the first character of upper case. For permissions.
     *
     * @param String $permission_title User input
     *
     * @return String
     */
    public function anyCaseToUcFirst($permission_title)
    {
        return ucfirst(preg_replace(self::REGEX_SPACES, ' ', $permission_title));
    }

    /**
     * @param $className
     * @return string
     */
    public function removeSuffix($className)
    {
        $suffixes = [
            'Form',
            'Controller',
            'Service',
            'Command'
        ];

        if (strlen($className) == 0) {
            return $className;
        }

        foreach ($suffixes as $suffix) {
            $length = strlen($suffix);
            if (strlen($className) <= $length) {
                continue;
            }

            if (substr($className, -$length) === $suffix) {
                return substr($className, 0, -$length);
            }
        }

        return $className;
    }

    /**
     * @param $input
     * @return string
     */
    public function underscoreToCamelCase($input)
    {
        return lcfirst(str_replace('_', '', ucwords($input, '_')));
    }
}
