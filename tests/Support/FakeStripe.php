<?php

namespace Tests\Support;

use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;

/**
 * Offline, in-memory stand-in for the Stripe API, plugged in through
 * stripe-php's own ApiRequestor::setHttpClient() hook. The real SDK still
 * builds every request (URL, params, Stripe-Account / Idempotency-Key
 * headers) - only the network hop is replaced - so tests can assert on
 * exactly what would have been sent, and nothing ever calls Stripe.
 *
 * Emulates only what this app and its tests use: customers, payment
 * methods, subscriptions (create/retrieve/update/cancel/list) and Checkout
 * Sessions (create/retrieve with expand/expire). Prices are resolved from
 * the central plan_prices table (the same catalog SyncPlanPrices pushes to
 * Stripe) or from inline price_data.
 *
 * Usage: $stripe = FakeStripe::install(); ... FakeStripe::uninstall();
 */
class FakeStripe implements ClientInterface
{
    /** Every request: ['method', 'path', 'headers' => [name => value], 'params'] */
    public array $requests = [];

    /** When set, overrides the status returned when retrieving a Checkout Session. */
    public ?string $remoteStatus = null;

    /** Connected accounts returned by GET /v1/accounts/{id}, keyed by id (Step 6C). */
    public array $accounts = [];

    private array $objects = [];
    private int $sequence = 0;

    public static function install(): self
    {
        $fake = new self();
        ApiRequestor::setHttpClient($fake);

        return $fake;
    }

    public static function uninstall(): void
    {
        ApiRequestor::setHttpClient(null);
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $parsedHeaders = [];
        foreach ($headers as $line) {
            [$name, $value] = array_map('trim', explode(':', $line, 2)) + [1 => ''];
            $parsedHeaders[$name] = $value;
        }

        $path   = parse_url($absUrl, PHP_URL_PATH);
        $params = $params ?? [];
        $this->requests[] = ['method' => $method, 'path' => $path, 'headers' => $parsedHeaders, 'params' => $params];

        try {
            return [json_encode($this->route($method, $path, $params)), 200, []];
        } catch (FakeStripeNotFound $e) {
            return [json_encode(['error' => ['type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => $e->getMessage()]]), 404, []];
        }
    }

    // ── Assertions helpers ──────────────────────────────────────────────

    public function creates(): array
    {
        return $this->matching('post', '#^/v1/checkout/sessions$#');
    }

    public function expires(): array
    {
        return $this->matching('post', '#^/v1/checkout/sessions/[^/]+/expire$#');
    }

    public function matching(string $method, string $pathPattern): array
    {
        return array_values(array_filter($this->requests, fn ($r) => $r['method'] === $method && preg_match($pathPattern, $r['path'])));
    }

    public function object(string $id): ?array
    {
        return $this->objects[$id] ?? null;
    }

    // ── Routing ─────────────────────────────────────────────────────────

    private function route(string $method, string $path, array $params): array
    {
        $segments = explode('/', trim(substr($path, strlen('/v1/')), '/'));
        [$resource, $id, $action] = $segments + [null, null, null];

        return match (true) {
            $resource === 'customers' && $method === 'post' && !$id         => $this->store('cus', 'customer', $params + ['invoice_settings' => []]),
            $resource === 'customers' && $method === 'post'                  => $this->update($id, $params),
            $resource === 'customers' && $method === 'get'                   => $this->find($id),

            $resource === 'payment_methods' && !$id                          => $this->store('pm', 'payment_method', ['type' => $params['type'] ?? 'card', 'customer' => null]),
            $resource === 'payment_methods' && $action === 'attach'          => $this->update($id, ['customer' => $params['customer'] ?? null]),

            $resource === 'subscriptions' && $method === 'post' && !$id     => $this->createSubscription($params),
            $resource === 'subscriptions' && $method === 'get' && !$id      => $this->listSubscriptions($params),
            $resource === 'subscriptions' && $method === 'get'              => $this->find($id),
            $resource === 'subscriptions' && $method === 'post'             => $this->update($id, $params),
            $resource === 'subscriptions' && $method === 'delete'           => $this->update($id, ['status' => 'canceled', 'canceled_at' => time()]),

            $resource === 'checkout' && $id === 'sessions'                   => $this->checkout($method, $segments[2] ?? null, $segments[3] ?? null, $params),

            $resource === 'accounts' && $method === 'get' && isset($this->accounts[$id]) => ['id' => $id, 'object' => 'account'] + $this->accounts[$id],

            default => throw new FakeStripeNotFound("FakeStripe: unsupported call {$method} {$path}"),
        };
    }

    private function checkout(string $method, ?string $id, ?string $action, array $params): array
    {
        if ($method === 'post' && !$id) {
            $session = $this->store('cs_test', 'checkout.session', [
                'mode'           => $params['mode'] ?? 'payment',
                'status'         => 'open',
                'payment_status' => 'unpaid',
                'customer'       => $params['customer'] ?? null,
                'payment_intent' => null,
                'metadata'       => $params['metadata'] ?? [],
                'success_url'    => $params['success_url'] ?? null,
                'cancel_url'     => $params['cancel_url'] ?? null,
                'expires_at'     => (int) ($params['expires_at'] ?? time() + 86400),
                '_line_items'    => array_map(fn ($item) => [
                    'object'   => 'item',
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'price'    => $this->resolvePrice($item),
                ], $params['line_items'] ?? []),
            ]);
            $session['amount_total'] = array_sum(array_map(fn ($i) => $i['price']['unit_amount'] * $i['quantity'], $session['_line_items']));
            $session['currency']     = $session['_line_items'][0]['price']['currency'] ?? null;
            $session['url']          = 'https://checkout.stripe.com/c/pay/' . $session['id'] . '#fake';
            $this->objects[$session['id']] = $session;

            return $this->present($session);
        }

        if ($action === 'expire') {
            return $this->present($this->update($id, ['status' => 'expired']));
        }

        $this->find($id); // 404s for an unknown id
        $session = $this->objects[$id];
        if ($this->remoteStatus !== null) {
            $session['status'] = $this->remoteStatus;
        }

        return $this->present($session, in_array('line_items', (array) ($params['expand'] ?? []), true)
            || in_array('line_items.data.price', (array) ($params['expand'] ?? []), true));
    }

    private function createSubscription(array $params): array
    {
        $trialDays = (int) ($params['trial_period_days'] ?? 0);
        $now       = time();

        return $this->store('sub', 'subscription', [
            'customer'             => $params['customer'] ?? null,
            'status'               => $trialDays > 0 ? 'trialing' : 'active',
            'trial_end'            => $trialDays > 0 ? $now + $trialDays * 86400 : null,
            'cancel_at_period_end' => false,
            'metadata'             => $params['metadata'] ?? [],
            'items'                => ['object' => 'list', 'data' => array_map(fn ($item) => [
                'object'               => 'subscription_item',
                'id'                   => 'si_' . (++$this->sequence),
                'price'                => $this->resolvePrice($item),
                'quantity'             => 1,
                // Current API versions: period lives on the item, not the subscription.
                'current_period_start' => $now,
                'current_period_end'   => $now + 30 * 86400,
            ], $params['items'] ?? [])],
        ]);
    }

    private function listSubscriptions(array $params): array
    {
        $data = array_values(array_filter($this->objects, fn ($o) =>
            $o['object'] === 'subscription'
            && (!isset($params['customer']) || $o['customer'] === $params['customer'])
            && (!isset($params['status']) || $o['status'] === $params['status'])
        ));

        return ['object' => 'list', 'url' => '/v1/subscriptions', 'has_more' => false, 'data' => $data];
    }

    private function resolvePrice(array $item): array
    {
        if (isset($item['price_data'])) {
            return [
                'object'      => 'price',
                'id'          => 'price_inline_' . (++$this->sequence),
                'currency'    => strtolower($item['price_data']['currency']),
                'unit_amount' => (int) $item['price_data']['unit_amount'],
            ];
        }

        $planPrice = \App\Models\PlanPrice::on('mysql')->where('stripe_price_id', $item['price'] ?? '')->first();

        if (!$planPrice) {
            throw new FakeStripeNotFound('FakeStripe: no such price: ' . ($item['price'] ?? 'null'));
        }

        return [
            'object'      => 'price',
            'id'          => $planPrice->stripe_price_id,
            'currency'    => strtolower($planPrice->currency),
            'unit_amount' => (int) $planPrice->amount,
        ];
    }

    // ── Storage ─────────────────────────────────────────────────────────

    private function store(string $prefix, string $object, array $attributes): array
    {
        $id = $prefix . '_fake_' . (++$this->sequence) . '_' . substr(md5(uniqid('', true)), 0, 8);

        return $this->objects[$id] = ['id' => $id, 'object' => $object, 'created' => time(), 'livemode' => false] + $attributes;
    }

    private function find(?string $id): array
    {
        if (!$id || !isset($this->objects[$id])) {
            throw new FakeStripeNotFound("FakeStripe: no such object: {$id}");
        }

        return $this->present($this->objects[$id]);
    }

    private function update(?string $id, array $changes): array
    {
        $this->find($id);
        $this->objects[$id] = array_replace_recursive($this->objects[$id], $changes);

        return $this->present($this->objects[$id]);
    }

    private function present(array $object, bool $withLineItems = false): array
    {
        $lineItems = $object['_line_items'] ?? null;
        unset($object['_line_items']);

        if ($withLineItems && $lineItems !== null) {
            $object['line_items'] = ['object' => 'list', 'has_more' => false, 'data' => $lineItems];
        }

        return $object;
    }
}

class FakeStripeNotFound extends \RuntimeException
{
}
