<?php

namespace Fywolf\VcenterVps\Http\Requests;

/**
 * Suspend / unsuspend / terminate / show. The order id is a route segment and
 * there is no body, so this exists purely to carry the ACL gate.
 */
class VpsLifecycleRequest extends VpsApiRequest
{
    /**
     * @return array<string, string[]>
     */
    public function rules(): array
    {
        return [];
    }
}
