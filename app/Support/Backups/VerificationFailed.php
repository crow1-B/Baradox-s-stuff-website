<?php

namespace App\Support\Backups;

use RuntimeException;

//The code is the exit code backup:verify and backup:restore return
class VerificationFailed extends RuntimeException
{
    public const MISSING = 3;

    public const CONFIG = 2;

    public const SIGNATURE = 6;

    public const CONTENTS = 7;
}
