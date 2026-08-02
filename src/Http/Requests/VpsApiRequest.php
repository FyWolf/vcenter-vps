<?php

namespace Fywolf\VcenterVps\Http\Requests;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Services\Acl\Api\AdminAcl;
use Fywolf\VcenterVps\Providers\VcenterVpsServiceProvider;

/**
 * Base request for every endpoint the billing service calls.
 *
 * Gated on this plugin's own `vps` resource rather than the bridge's `billing`
 * one. Same reasoning the bridge gives for not using `server: write` — these
 * routes can destroy virtual machines, so the capability is separable from
 * "provision game servers". A key can hold one without the other.
 *
 * Never issue the billing service a root-admin `pacc_` key; those bypass the
 * application ACL entirely.
 */
abstract class VpsApiRequest extends ApplicationApiRequest
{
    protected ?string $resource = VcenterVpsServiceProvider::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;
}
