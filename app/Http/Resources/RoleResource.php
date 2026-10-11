<?php

namespace App\Http\Resources;

use App\Services\RoleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * Needs permissions loaded and users counted.
 *
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => ucfirst($this->name),
            'locked' => $this->name === RoleService::ADMIN,
            'users_count' => $this->users_count,
            'permissions' => $this->permissions->pluck('name')->sort()->values(),
        ];
    }
}
