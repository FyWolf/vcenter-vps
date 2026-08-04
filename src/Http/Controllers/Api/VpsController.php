<?php

namespace Fywolf\VcenterVps\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Fywolf\VcenterVps\Billing\Data\OrderData;
use Fywolf\VcenterVps\Http\Requests\ApplyVpsPlanRequest;
use Fywolf\VcenterVps\Http\Requests\ProvisionVpsRequest;
use Fywolf\VcenterVps\Http\Requests\StoreVpsCollaboratorRequest;
use Fywolf\VcenterVps\Http\Requests\VpsLifecycleRequest;
use Fywolf\VcenterVps\Jobs\ProvisionVpsJob;
use Fywolf\VcenterVps\Models\VpsInstance;
use Fywolf\VcenterVps\Models\VpsInstanceUser;
use Fywolf\VcenterVps\Provisioners\VcenterProvisioner;
use Illuminate\Http\JsonResponse;

/**
 * The endpoints the billing service calls, in the same shape and under the same
 * prefix as the billing-bridge plugin's — `/api/application/billing/vps/*`,
 * gated on an application API key.
 *
 * This replaces billing's old in-process ProvisionerRegistry lookup. Billing
 * used to resolve the provisioner out of a container it shared with this plugin;
 * it is a separate app now, so the same operations arrive over HTTP.
 *
 * Orders are addressed by *billing's* order id throughout, not by the local
 * instance id. It is the identifier both sides had before a VM existed, which
 * matters because provisioning is asynchronous.
 */
class VpsController extends Controller
{
    public function __construct(private readonly VcenterProvisioner $provisioner)
    {
    }

    /**
     * Accept an order for provisioning.
     *
     * 202, not 201 — cloning a template takes minutes, so the work is queued and
     * no VM exists when this responds. Billing should treat it as "accepted" and
     * read `instance_id` from a later `show()`, not as "provisioned".
     */
    public function store(ProvisionVpsRequest $request): JsonResponse
    {
        $order = OrderData::fromArray($request->validated());

        // Idempotent: billing retries a request it never got a response to, and
        // a timeout on one that actually succeeded must not build a second VM.
        if ($instance = $this->provisioner->findInstance($order->orderId)) {
            return response()->json($this->format($instance) + ['status' => 'already_provisioned']);
        }

        ProvisionVpsJob::dispatch($order);

        return response()->json(['status' => 'accepted'], 202);
    }

    public function show(VpsLifecycleRequest $request, int $order): JsonResponse
    {
        $instance = $this->provisioner->findInstance($order);

        if (!$instance) {
            return response()->json(['message' => 'No VPS instance for that order.'], 404);
        }

        return response()->json($this->format($instance));
    }

    public function suspend(VpsLifecycleRequest $request, int $order): JsonResponse
    {
        $this->provisioner->suspend($order);

        return response()->json(['status' => 'suspended']);
    }

    public function unsuspend(VpsLifecycleRequest $request, int $order): JsonResponse
    {
        $this->provisioner->unsuspend($order);

        return response()->json(['status' => 'unsuspended']);
    }

    public function destroy(VpsLifecycleRequest $request, int $order): JsonResponse
    {
        $this->provisioner->terminate($order);

        return response()->json(['status' => 'terminated']);
    }

    /**
     * Share this VPS with another panel user, or change what they can do.
     *
     * Billing owns the invitation — who was asked, by whom, with what, and when
     * it ends — so this is the only way a row appears in `vps_instance_users`.
     * The panel offers no screen for it, which is what keeps the two sides from
     * drifting.
     *
     * Idempotent per (instance, user): billing retries a request it never got a
     * response to, and re-sending must not fail on the unique key.
     */
    public function storeCollaborator(StoreVpsCollaboratorRequest $request, int $order): JsonResponse
    {
        $instance = $this->provisioner->findInstance($order);

        if (!$instance) {
            return response()->json(['message' => 'No VPS instance for that order.'], 404);
        }

        $userId = (int) $request->validated('user_id');

        // The owner already has everything. A row for them would be a second,
        // weaker statement about their own machine — and `userCan()` short
        // circuits on ownership anyway, so it could only ever mislead.
        if ($instance->user_id === $userId) {
            return response()->json(['message' => 'That user already owns this VPS.'], 422);
        }

        $collaborator = VpsInstanceUser::updateOrCreate(
            ['vps_instance_id' => $instance->id, 'user_id' => $userId],
            ['permissions' => VpsInstanceUser::clean($request->validated('permissions', []))],
        );

        return response()->json([
            'user_id'     => $userId,
            'permissions' => $collaborator->permissions,
        ], $collaborator->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Stop sharing. Already gone is a success — billing retries revocation on
     * termination and on account deletion, and both must be repeatable.
     */
    public function destroyCollaborator(VpsLifecycleRequest $request, int $order, int $user): JsonResponse
    {
        $instance = $this->provisioner->findInstance($order);

        if (!$instance) {
            return response()->json(['status' => 'removed']);
        }

        VpsInstanceUser::where('vps_instance_id', $instance->id)
            ->where('user_id', $user)
            ->delete();

        return response()->json(['status' => 'removed']);
    }

    /**
     * Apply a changed plan. Returns what was actually applied and what still
     * needs a human — see VcenterProvisioner::applyPlan() for why a VPS resize
     * is not unconditional.
     */
    public function applyPlan(ApplyVpsPlanRequest $request, int $order): JsonResponse
    {
        return response()->json(
            $this->provisioner->applyPlan($order, $request->validated())
        );
    }

    /**
     * Refresh the billing facts cached on the instance — status, expiry, pack
     * name, customer label, purchased spec. This is what keeps the customer's
     * VPS list and console authorization honest without the plugin ever calling
     * billing.
     */
    public function sync(ProvisionVpsRequest $request): JsonResponse
    {
        $this->provisioner->syncOrder(OrderData::fromArray($request->validated()));

        return response()->json(['status' => 'synced']);
    }

    /**
     * @return array<string, mixed>
     */
    private function format(VpsInstance $instance): array
    {
        return [
            'instance_id'    => $instance->id,
            'vm_id'          => $instance->vm_id,
            'vm_ip'          => $instance->vm_ip,
            'state'          => $instance->state_cache,
            'install_status' => $instance->install_status,
            'management_url' => $this->provisioner->getManagementUrl($instance->billing_order_id),
        ];
    }
}
