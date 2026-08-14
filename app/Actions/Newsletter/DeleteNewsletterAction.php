<?php

namespace App\Actions\Newsletter;

use App\Models\Newsletter;
use App\Enums\NewsletterStatusEnum;

class DeleteNewsletterAction
{
    public function execute(Newsletter $newsletter): bool
    {
        // Only allow deletion of drafts or unsent newsletters
        // Compare with enum cases (status is cast to NewsletterStatusEnum)
        if (!in_array($newsletter->status, [NewsletterStatusEnum::DRAFT, NewsletterStatusEnum::SCHEDULED])) {
            throw new \Exception('Cannot delete sent or published newsletters.');
        }

        return $newsletter->delete();
    }
}
