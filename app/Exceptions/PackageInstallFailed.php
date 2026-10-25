<?php

namespace App\Exceptions;

/**
 * An uploaded package whose install started and did not finish. What it
 * changed was undone as far as the message says; like PackageRefused, the
 * message is written for the admin and carries no server details.
 */
class PackageInstallFailed extends PackageRefused
{
}
