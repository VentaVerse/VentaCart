<?php

namespace App\Exceptions;

/**
 * An uploaded theme or extension that will not be installed. The message is
 * written for the admin who uploaded it: it names what is wrong with the
 * archive and never carries paths of the server or driver errors.
 */
class PackageRefused extends \RuntimeException
{
}
