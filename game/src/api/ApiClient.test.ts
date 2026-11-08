import { afterEach, describe, expect, it, vi } from 'vitest';
import { ApiClient, ApiError, resolveApiRoot } from './ApiClient';

const runId = 'run_0123456789abcdef0123456789abcdef';
afterEach(() => vi.unstubAllGlobals());

describe('PHP API adapter boundaries', () => {
  it('resolves nested static deployment without losing website prefix', () => {
    expect(resolveApiRoot('https://example.com/jobsimu/game/dist/index.html')).toBe('https://example.com/jobsimu');
    expect(resolveApiRoot('https://example.com/game/')).toBe('https://example.com');
    expect(resolveApiRoot('http://localhost:5173/')).toBe('http://localhost:5173');
  });
  it('does not POST before receiving a real PHP CSRF token', async () => {
    const fetch = vi.fn();
    vi.stubGlobal('fetch', fetch);
    await expect(new ApiClient().startRun()).rejects.toMatchObject({ code: 'CSRF_REQUIRED' });
    expect(fetch).not.toHaveBeenCalled();
  });
  it('does not synchronize a fabricated local run', async () => {
    await expect(new ApiClient().getState('local_dev_run')).rejects.toMatchObject({ code: 'INVALID_RUN' });
  });
  it('rejects cross-origin endpoints to avoid leaking the session token', () => {
    expect(() => new ApiClient('https://other.test')).toThrow(ApiError);
  });
  it('preserves idempotency identity across retries of a snapshot revision', async () => {
    const fetch = vi.fn().mockImplementation(async () => new Response(JSON.stringify({ success: true, data: {} })));
    vi.stubGlobal('fetch', fetch);
    const api = new ApiClient();
    api.setCsrfToken('test-session-token');
    await api.saveSnapshot(runId, 1, { checkpoint: 'CP-01' });
    await api.saveSnapshot(runId, 1, { checkpoint: 'CP-01' });
    const first = fetch.mock.calls[0][1];
    const second = fetch.mock.calls[1][1];
    expect(first.credentials).toBe('same-origin');
    expect(JSON.parse(first.body).idempotency_key).toBe(JSON.parse(second.body).idempotency_key);
  });
  it('surfaces an HTML host fallback as unavailable API, never successful sync', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response('<html>login</html>')));
    await expect(new ApiClient().getState(runId)).rejects.toMatchObject({ code: 'INVALID_RESPONSE' });
  });
  it('preserves server failure even when HTTP status is 200', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(new Response(JSON.stringify({ success: false, error: { code: 'NOT_SAVED', message: 'not saved' } }))));
    await expect(new ApiClient().getState(runId)).rejects.toMatchObject({ code: 'NOT_SAVED' });
  });
  it('surfaces a network failure without pretending to queue the request', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
    await expect(new ApiClient().getState(runId)).rejects.toMatchObject({ code: 'NETWORK_ERROR' });
  });
});
