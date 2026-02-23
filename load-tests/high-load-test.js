import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { uuidv4 } from 'https://jslib.k6.io/k6-utils/1.4.0/index.js';

// ──────────────────────────────────────────────
// Custom Metrics
// ──────────────────────────────────────────────
const orderCreated = new Counter('orders_created');
const orderRejected = new Counter('orders_rejected');
const successRate = new Rate('order_success_rate');
const orderDuration = new Trend('order_create_duration', true);

// ──────────────────────────────────────────────
// Config
// ──────────────────────────────────────────────
const BASE_URL = __ENV.BASE_URL || 'http://localhost:80';

export const options = {
    scenarios: {
        // Ramp up to 50 VUs over 2 minutes, sustain, then ramp down
        load_test: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '30s', target: 20 },
                { duration: '1m', target: 50 },
                { duration: '30s', target: 50 },
                { duration: '30s', target: 0 },
            ],
        },
    },
    thresholds: {
        http_req_duration: ['p(95)<500', 'p(99)<1000'],
        order_success_rate: ['rate>0.8'],
        http_req_failed: ['rate<0.1'],
    },
};

export default function () {
    const idempotencyKey = uuidv4();

    // Random product (1-4) with random quantity (1-3)
    const productId = Math.floor(Math.random() * 4) + 1;
    const quantity = Math.floor(Math.random() * 3) + 1;

    const payload = JSON.stringify({
        user_id: Math.floor(Math.random() * 3) + 1,
        idempotency_key: idempotencyKey,
        items: [{ product_id: productId, quantity: quantity }],
    });

    const params = {
        headers: { 'Content-Type': 'application/json' },
    };

    const res = http.post(`${BASE_URL}/api/orders`, payload, params);

    orderDuration.add(res.timings.duration);

    const created = check(res, {
        'status is 201 or 200': (r) => r.status === 201 || r.status === 200,
        'response has data.id': (r) => {
            try {
                return JSON.parse(r.body).data.id !== undefined;
            } catch {
                return false;
            }
        },
    });

    if (res.status === 201) {
        orderCreated.add(1);
        successRate.add(1);
    } else if (res.status === 409) {
        orderRejected.add(1);
        successRate.add(1); // 409 is expected behavior, not a failure
    } else {
        successRate.add(0);
    }

    sleep(Math.random() * 0.5);
}
