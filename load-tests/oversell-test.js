import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';

// ──────────────────────────────────────────────
// Oversell Test
// 50 VUs race to buy the same product with stock = 1
// Only 1 order should succeed; stock must never go negative.
// ──────────────────────────────────────────────

const BASE_URL = __ENV.BASE_URL || 'http://localhost:80';
const PRODUCT_ID = 5; // Headphones — seeded with stock = 1

const ordersCreated = new Counter('orders_created_total');
const ordersRejected = new Counter('orders_rejected_total');

export const options = {
    scenarios: {
        oversell_burst: {
            executor: 'shared-iterations',
            vus: 50,
            iterations: 50,
            maxDuration: '30s',
        },
    },
    thresholds: {
        orders_created_total: ['count==1'],   // Exactly 1 should succeed
        orders_rejected_total: ['count==49'], // Rest should be rejected
    },
};

export default function () {
    const payload = JSON.stringify({
        user_id: Math.floor(Math.random() * 3) + 1,
        idempotency_key: `oversell-${__VU}-${__ITER}-${Date.now()}`,
        items: [{ product_id: PRODUCT_ID, quantity: 1 }],
    });

    const params = {
        headers: { 'Content-Type': 'application/json' },
    };

    const res = http.post(`${BASE_URL}/api/orders`, payload, params);

    check(res, {
        'status is 201 or 409': (r) => r.status === 201 || r.status === 409,
    });

    if (res.status === 201) {
        ordersCreated.add(1);
        console.log(`VU ${__VU}: ORDER CREATED ✓`);
    } else if (res.status === 409) {
        ordersRejected.add(1);
    } else {
        console.log(`VU ${__VU}: UNEXPECTED ${res.status} - ${res.body}`);
    }
}

export function handleSummary(data) {
    const created = data.metrics.orders_created_total
        ? data.metrics.orders_created_total.values.count
        : 0;
    const rejected = data.metrics.orders_rejected_total
        ? data.metrics.orders_rejected_total.values.count
        : 0;

    console.log('\n══════════════════════════════════════');
    console.log(`  OVERSELL TEST RESULTS`);
    console.log(`  Orders Created:  ${created} (expected: 1)`);
    console.log(`  Orders Rejected: ${rejected} (expected: 49)`);
    console.log(`  Verdict: ${created === 1 ? 'PASS ✓' : 'FAIL ✗'}`);
    console.log('══════════════════════════════════════\n');

    return {};
}
