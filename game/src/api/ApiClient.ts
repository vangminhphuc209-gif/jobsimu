/**
 * game/src/api/ApiClient.ts
 * Strongly-typed API Client for JOBSIMU 3D backend persistence.
 * Implements CSRF caching, revision tracking, and idempotency key generation.
 */

export class ApiClient {
  private baseUrl: string;
  private csrfToken: string | null = null;

  constructor(baseUrl: string = '') {
    this.baseUrl = baseUrl;
  }

  public setCsrfToken(token: string): void {
    this.csrfToken = token;
  }

  public async startRun(): Promise<any> {
    const res = await this.request('/api/game/start.php', 'POST', {});
    if (res.data?.csrf_token) {
      this.csrfToken = res.data.csrf_token;
    }
    return res;
  }

  public async getState(runId: string): Promise<any> {
    return this.request(`/api/game/state.php?run_id=${encodeURIComponent(runId)}`, 'GET');
  }

  public async logEvent(
    runId: string,
    eventSeq: number,
    eventId: string,
    actionId: string,
    payload: Record<string, any>
  ): Promise<any> {
    return this.request('/api/game/events.php', 'POST', {
      run_id: runId,
      event_seq: eventSeq,
      event_id: eventId,
      action_id: actionId,
      payload
    });
  }

  public async saveSnapshot(
    runId: string,
    revision: number,
    snapshot: Record<string, any>,
    idempotencyKey?: string
  ): Promise<any> {
    return this.request('/api/game/save.php', 'POST', {
      run_id: runId,
      revision,
      idempotency_key: idempotencyKey || `idem_${runId}_${revision}_${Date.now()}`,
      snapshot
    });
  }

  public async finishRun(runId: string, reflection: Record<string, any>): Promise<any> {
    return this.request('/api/game/finish.php', 'POST', {
      run_id: runId,
      reflection
    });
  }

  private async request(url: string, method: 'GET' | 'POST', body?: any): Promise<any> {
    const headers: Record<string, string> = {
      'Accept': 'application/json'
    };

    if (this.csrfToken) {
      headers['X-CSRF-Token'] = this.csrfToken;
    }

    const options: RequestInit = {
      method,
      headers
    };

    if (method === 'POST') {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(body || {});
    }

    const response = await fetch(`${this.baseUrl}${url}`, options);
    const data = await response.json();

    if (!response.ok) {
      const errMessage = data?.error?.message || `HTTP ${response.status}`;
      throw new Error(errMessage);
    }

    return data;
  }
}
