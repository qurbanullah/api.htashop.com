<?php

namespace App\Actions\Code;

use App\Models\Code;

class CodeSearchByIdAction
{
    public function handle(int $id): Code
    {
        return Code::query()->with(['tenant', 'organization', 'codeable'])->findOrFail($id);
    }
}
