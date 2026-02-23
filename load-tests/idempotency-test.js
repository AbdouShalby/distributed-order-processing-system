import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

// ──────────────────────────────────────────────
// Idempotency Test
// 50 VUs send the SAME idempotency key.
// Exactly 1 should get 201 (created),
// the rest should get 200 (existing).
// ──────────────────────────────────────────────

const BASE_URL = __ENV.BASE_URL || 'http://localhost:80';
const IDEM_KEY = 'idempotency-stress-test-key';

const created = new Counter('orders_created_total');
const existing = new Counter('orders_existing_total');
const unexpected = new Counter('orders_unexpected_total');

export const options = {
    scenarios: {
        idempotency_burst: {
            executor: 'shared-iterations',
            vus: 50,
            iterations: 50,
            maxDuration: '30s',
        },
    },
    thresholds: {
        orders_created_total: ['count==1'],
        orders_unexpected_total: ['count==0'],
    },
};

export default function () {
    const payload = JSON.stringify({
        user_id: 1,
        idempotency_key: IDEM_KEY,
        items: [{ product_id: 1, quantity: 1 }],
    });

    const params = {
        headers: { 'Content-Type': 'application/json' },
    };

    const res = http.post(`${BASE_URL}/api/orders`, payload, params);

    check(res, {
        'status is 201 or 200': (r) => r.status === 201 || r.status === 200,
    });

    if (res.status === 201) {
        created.add(1);
    } else if (res.status === 200) {
        existing.add(1);
    } else {
        unexpected.add(1);
        console.log(`VU ${__VU}: UNEXPECTED ${res.status} - ${res.body}`);
    }
}

export function handleSummary(data) {
    const createdCount = data.metrics.orders_created_total
        ? data.metrics.orders_created_total.values.count
        : 0;
    const existingCount = data.metrics.orders_existing_total
        ? data.metrics.orders_existing_total.values.count
        : 0;
    const unexpectedCount = data.metrics.orders_unexpected_total
        ? data.metrics.orders_unexpected_total.values.count
        : 0;

    console.log('\n══════════════════════════════════════');
    console.log('  IDEMPOTENCY TEST RESULTS');
    console.log(`  Created (201):    ${createdCount} (expected: 1)`);
    console.log(`  Existing (200):   ${existingCount} (expected: 49)`);
    console.log(`  Unexpected:       ${unexpectedCount} (expected: 0)`);
    console.log(`  Verdict: ${createdCount === 1 && unexpectedCount === 0 ? 'PASS ✓' : 'FAIL ✗'}`);
    console.log('══════════════════════════════════════\n');

    return {};
}
