<?php

namespace PhantomInstaller;

class PhantomBinary
{
    const BIN = '/Users/daniele/projects/ricardoAnalytic/bin/phantomjs';
    const DIR = '/Users/daniele/projects/ricardoAnalytic/bin';

    public static function getBin() {
        return self::BIN;
    }

    public static function getDir() {
        return self::DIR;
    }
}
