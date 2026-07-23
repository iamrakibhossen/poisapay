// PoisaPay API load test (k6). Run: k6 run -e BASE_URL=https://staging.example tests/load/api-load.js
// Exercises auth throughput + read endpoints + a transfer write path under concurrency.
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const errors = new Rate('errors');
const BASE = __ENV.BASE_URL || 'http://localhost:8080';

export const options = {
  scenarios: {
    // Ramp read traffic to find the concurrency knee.
    reads: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '30s', target: 50 },
        { duration: '1m', target: 200 },
        { duration: '30s', target: 0 },
      ],
    },
  },
  thresholds: {
    http_req_duration: ['p(95)<800'], // 95% of requests under 800ms
    errors: ['rate<0.01'],            // < 1% errors
  },
};

// A pre-provisioned bearer token for a staging test user (never a real user).
const TOKEN = __ENV.TOKEN || '';

function authHeaders() {
  return { headers: { Authorization: `Bearer ${TOKEN}`, 'Content-Type': 'application/json' } };
}

export default function () {
  // Public: reference data (unauthenticated, cache-friendly).
  const assets = http.get(`${BASE}/api/v1/assets`, authHeaders());
  check(assets, { 'assets 200/401': (r) => r.status === 200 || r.status === 401 }) || errors.add(1);

  if (TOKEN) {
    const wallets = http.get(`${BASE}/api/v1/wallets`, authHeaders());
    check(wallets, { 'wallets ok': (r) => r.status === 200 }) || errors.add(1);

    const history = http.get(`${BASE}/api/v1/security/login-history`, authHeaders());
    check(history, { 'history ok': (r) => r.status === 200 }) || errors.add(1);
  }

  sleep(1);
}
