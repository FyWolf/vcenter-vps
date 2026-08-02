# v2 — decoupling from the in-panel Billing plugin

Billing used to be a plugin in this same panel. This plugin depended on it
directly: `PackProvisionerContract`, `ProvisionerRegistry`, and Eloquent reads
across `Order`, `Pack` and `Customer`, plus foreign keys into `billing_orders`
and `billing_packs`.

Billing is a separate app now (`FyWolf/HexalabsStorefront`), so none of that
exists in the panel database. This plugin stays where it is — the panel is a
server control panel, a VPS is a server, and the console proxy has to terminate
somewhere with a session — and only the seam to billing changed.

## What replaced what

| Was | Now |
| --- | --- |
| `implements PackProvisionerContract` | plain class, called from `VpsController` |
| `ProvisionerRegistry->register(...)` | `/api/application/billing/vps/*` routes |
| `Fywolf\Billing\Models\Order` | `Billing\Data\OrderData` (request payload) |
| `Fywolf\Billing\Models\Pack` | `BillingClient::packs()` over HTTP |
| `Fywolf\Billing\Models\Customer` | `vps_instances.user_id` (panel user id) |
| `Fywolf\Billing\Models\AuditLog` | `Billing\AuditLogger` → panel log + billing push |
| `Fywolf\Billing\Enums\OrderStatus` | local `Billing\Enums\OrderStatus` |
| `order.customer_id` authorization | `VpsInstance::scopeOwnedBy(user_id)` |
| `order->packPrice->{cores,memory,disk}` | `spec_cores` / `spec_memory_mb` / `spec_disk_gb` |

Billing facts are **pushed and cached**, never read live. Reading them live would
put billing on the critical path of every console open and every VPS list render
— a billing outage would become a datacenter outage. Customers can still reach
their machines when billing is down.

## Authentication

Same scheme as the `billing-bridge` plugin, deliberately: routes sit under
`/api/application/billing/vps/*` behind `auth:sanctum`, and every request class
extends `ApplicationApiRequest` gated on a custom ACL resource.

This plugin registers its own `vps` resource rather than reusing the bridge's
`billing`, for the reason the bridge gives for not reusing `server`: these routes
destroy virtual machines, and that should be grantable separately from
"provision game servers".

**The billing service's `papp_` key therefore needs `billing: write` *and*
`vps: write`.** Add the second scope to the existing key — a `billing`-only key
gets 403 from every endpoint here.

Never issue it a root-admin `pacc_` key; those bypass the application ACL
entirely.

The plugin's own outbound direction (pack lookups for the admin form, audit
events) is separate and configured on the panel:

```
VCENTER_BILLING_URL=https://billing.hexalabshosting.fr
VCENTER_BILLING_TOKEN=<plugin → billing>
```

Settable from Settings → vCenter VPS → Billing App.

## Endpoints

| Route | When |
| --- | --- |
| `POST /` | order paid |
| `POST /sync` | order status, expiry, pack or spec changed |
| `GET /{order}` | current instance — id, ip, state, management url |
| `POST /{order}/suspend` | dunning / non-payment |
| `POST /{order}/unsuspend` | payment recovered |
| `PATCH /{order}/plan` | plan change |
| `DELETE /{order}` | order ended |

Everything is addressed by **billing's order id**, never the local instance id.
Provisioning is asynchronous, so the order id is the only identifier both sides
have from the start.

`POST /` and `POST /sync` take the same body:

```json
{
  "order_id": 123,
  "pack_id": 7,
  "user_id": 42,
  "pack_name": "VPS Medium",
  "customer_label": "alice@example.com",
  "status": "active",
  "expires_at": "2026-09-01T00:00:00Z",
  "cores": 4,
  "memory_mb": 8192,
  "disk_gb": 80
}
```

`user_id` is the **panel** user id, not billing's customer id. Billing resolves
it from `customers.panel_user_id`; this plugin cannot derive it, and all
customer-facing authorization keys off it.

`POST /` returns **202**, not 201 — cloning a template takes minutes, so it is
queued and no VM exists when the call returns. Treat 202 as "accepted" and read
`instance_id` from a later `GET /{order}`. Retries are safe: an order that
already has an instance returns 200 with `status: already_provisioned`.

### Plan changes are not automatic

`PATCH /{order}/plan` returns what it could apply and what it could not:

```json
{ "applied": [], "requires_manual_action": ["cores","memory_mb"],
  "reason": "vcenter_resize_not_automated" }
```

`VCenterService` has no reconfigure call. CPU and memory need hot-add enabled or
a power cycle, and a disk can be grown but never shrunk — and growing one still
leaves the guest filesystem to whoever runs it. So the plugin records an audit
event and reports the gap rather than pretending. It deliberately does **not**
move the cached `spec_*` columns: those describe what the machine actually is,
and advancing them would show a customer an upgrade that had not happened.

Billing records this as `vps_plan_partially_applied`. Watch for it — a plan
change that silently never reaches the machine is a bug this codebase already
fixed once for game servers.

## Deploying

1. Add `vps: write` to the billing service's existing panel API key.
2. Set `VCENTER_BILLING_URL` and `VCENTER_BILLING_TOKEN` on the panel.
3. `php artisan migrate` — migration `005` drops the dead foreign keys, renames
   `order_id` → `billing_order_id` and `pack_id` → `billing_pack_id`, and adds
   the cached columns.
4. On billing, `php artisan migrate` for `orders.vps_instance_id`.
5. **Backfill.** The new columns are null for existing VMs, so those customers
   will not see their machines until filled. There is no separate script: have
   billing call `POST /sync` for every order on a vCenter pack. The rename
   preserved the order ids, so each sync matches its existing row.
6. Confirm Admin → VCenter VPS → Pack Settings shows a pack name against each
   row; re-link any created before the rename.

Do step 5 in a maintenance window, or customers will briefly see an empty VPS
list. It is idempotent; run it again if unsure.

Verify the routes registered:

```bash
php artisan route:list | grep billing/vps
```
