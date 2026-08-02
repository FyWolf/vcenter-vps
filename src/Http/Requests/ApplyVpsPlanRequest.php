<?php

namespace Fywolf\VcenterVps\Http\Requests;

/**
 * A plan change on an existing VM. Every field is optional — a plan may move
 * only memory, and sending a null must not be read as "resize to nothing".
 */
class ApplyVpsPlanRequest extends VpsApiRequest
{
    /**
     * @return array<string, string[]>
     */
    public function rules(): array
    {
        return [
            'cores'     => ['nullable', 'integer', 'min:1'],
            'memory_mb' => ['nullable', 'integer', 'min:1'],
            'disk_gb'   => ['nullable', 'integer', 'min:1'],
        ];
    }
}
