<?php

namespace App\Services\Code;

use App\Actions\Code\CodeCreateAction;
use App\Actions\Code\CodeDeleteAction;
use App\Actions\Code\CodeReadAction;
use App\Actions\Code\CodeSearchByIdAction;
use App\Actions\Code\CodeUpdateAction;
use App\Models\Code;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class CodeService
{
    public function create(array $data): Code
    {
        return (new CodeCreateAction())->handle($data);
    }

    public function read(array $filters = []): LengthAwarePaginator
    {
        $user = Auth::user();

        if (! $user?->hasRole(['super-admin', 'admin'])) {
            $membership = $user?->memberships()->where('is_active', true)->first();
            $filters['tenant_id'] = $membership?->tenant_id;
            $filters['organization_id'] = $membership?->organization_id;
        }

        return (new CodeReadAction())->handle($filters);
    }

    public function searchById(int $id): Code
    {
        return (new CodeSearchByIdAction())->handle($id);
    }

    public function update(Code $code, array $data): Code
    {
        return (new CodeUpdateAction())->handle($code, $data);
    }

    public function delete(Code $code): bool
    {
        return (new CodeDeleteAction())->handle($code);
    }
}
