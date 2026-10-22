<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['filename', 'sha256', 'status', 'duration_ms', 'error'])]
class BackupRestore extends Model
{
}
