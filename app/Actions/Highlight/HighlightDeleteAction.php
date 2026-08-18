<?php

namespace App\Actions\Highlight;

use App\Models\Highlight;

class HighlightDeleteAction
{
    public function handle(Highlight $highlight): void
    {
        $highlight->delete();
    }
}
