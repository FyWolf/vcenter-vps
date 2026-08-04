<?php

namespace Fywolf\VcenterVps\Http\Requests;

class StoreVpsCollaboratorRequest extends VpsApiRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            // Validated as strings only; the controller filters them through
            // `VpsInstanceUser::clean()`. Listing the cases here would reject
            // the whole request when billing is one release ahead, rather than
            // dropping the single permission this plugin does not know yet.
            'permissions'   => ['present', 'array'],
            'permissions.*' => ['string', 'max:32'],
        ];
    }
}
