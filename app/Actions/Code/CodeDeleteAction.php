<?php

namespace App\Actions\Code;

use App\Models\Code;

class CodeDeleteAction
{
    public function handle(Code $code): bool
    {
        return (bool) $code->delete();
    }
}
