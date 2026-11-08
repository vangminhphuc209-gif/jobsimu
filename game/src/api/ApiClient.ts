/** Same-origin PHP API adapter. Local training does not imply server sync. */
export class ApiError extends Error {
  constructor(message: string, public readonly status: number, public readonly code: string) {
    super(message);
    this.name = 'ApiError';
  }
}

export interface ApiResponse<T = any> {
  success: true;
  data: T;
}

/** Resolve the PHP root for /game/, /game/dist/ and subdirectory installs. */
export function resolveApiRoot(pageUrl: string): string {
  const page = new URL(pageUrl);
  const gameSegment = page.pathname.indexOf('/game/');
  const prefix = gameSegment >= 0 ? page.pathname.slice(0, gameSegment) : '';
  return `${page.origin}${prefix}`;
}

export class ApiClient {
  private readonly baseUrl: string;
  private csrfToken: string | null = null;

  constructor(baseUrl?: string) {
    const page = typeof location === 'undefined' ? 'http://localhost/' : location.href;
    const resolved = baseUrl ? new URL(baseUrl, page) : new URL(resolveApiRoot(page));
    if (resolved.origin !== new URL(page).origin) {
      throw new ApiError('Game API phải chạy cùng origin với website.', 0, 'CROSS_ORIGIN');
    }
    this.baseUrl = resolved.href.replace(/\/$/, '');
    // A PHP host can inject this token after authenticating the user.
    if (typeof document !== 'undefined') {
      this.csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content || null;
    }
  }

  public setCsrfToken(token: string): void {
    this.csrfToken = token.trim() || null;
  }

  public async startRun(): Promise<ApiResponse> {
    // start.php itself requires CSRF: never manufacture a token or local run ID.
    const res = await this.request('/api/game/start.php', 'POST', {});
    if (typeof res.data?.csrf_token === 'string') this.setCsrfToken(res.data.csrf_token);
    return res;
  }

  public async getState(runId: string): Promise<ApiResponse> {
    this.validateRunId(runId);
    const res = await this.request(`/api/game/state.php?run_id=${encodeURIComponent(runId)}`, 'GET');
    if (typeof res.data?.csrf_token === 'string') this.setCsrfToken(res.data.csrf_token);
    return res;
  }

  public async logEvent(runId: string, eventSeq: number, eventId: string, actionId: string,
    payload: Record<string, unknown>): Promise<ApiResponse> {
    this.validateRunId(runId);
    return this.request('/api/game/events.php', 'POST', {
      run_id: runId, event_seq: eventSeq, event_id: eventId, action_id: actionId, payload
    });
  }

  public async saveSnapshot(runId: string, revision: number, snapshot: Record<string, unknown>,
    idempotencyKey?: string): Promise<ApiResponse> {
    this.validateRunId(runId);
    return this.request('/api/game/save.php', 'POST', {
      run_id: runId, revision,
      // Retries of one immutable revision must reuse the same identity.
      idempotency_key: idempotencyKey || `save_${runId}_${revision}`,
      snapshot
    });
  }

  public async finishRun(runId: string, reflection: Record<string, unknown>): Promise<ApiResponse> {
    this.validateRunId(runId);
    return this.request('/api/game/finish.php', 'POST', { run_id: runId, reflection });
  }

  private validateRunId(runId: string): void {
    if (!/^run_[a-f0-9]{32}$/.test(runId)) {
      throw new ApiError('Chưa có phiên chơi được máy chủ xác nhận.', 0, 'INVALID_RUN');
    }
  }

  private async request(url: string, method: 'GET' | 'POST', body?: unknown): Promise<ApiResponse> {
    if (method === 'POST' && !this.csrfToken) {
      throw new ApiError('Chưa có mã CSRF từ phiên đăng nhập PHP; tiến độ chưa đồng bộ.', 403, 'CSRF_REQUIRED');
    }
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (this.csrfToken) headers['X-CSRF-Token'] = this.csrfToken;
    if (method === 'POST') headers['Content-Type'] = 'application/json';

    let response: Response;
    try {
      response = await fetch(`${this.baseUrl}${url}`, {
        method, headers, credentials: 'same-origin',
        body: method === 'POST' ? JSON.stringify(body ?? {}) : undefined,
        signal: AbortSignal.timeout(15000)
      });
    } catch {
      throw new ApiError('Không kết nối được máy chủ; tiến độ chưa đồng bộ.', 0, 'NETWORK_ERROR');
    }
    let data: any;
    try {
      data = await response.json();
    } catch {
      throw new ApiError('Máy chủ chưa cung cấp Game API JSON hợp lệ.', response.status, 'INVALID_RESPONSE');
    }
    if (!response.ok || data?.success !== true) {
      throw new ApiError(data?.error?.message || `Game API HTTP ${response.status}`,
        response.status, data?.error?.code || 'API_ERROR');
    }
    return data;
  }
}
