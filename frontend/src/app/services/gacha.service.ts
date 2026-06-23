// frontend/src/app/services/gacha.service.ts — the API service.
// A service is a shared class that handles data fetching.
// Components don't call the API directly — they call this service.
// This keeps API logic in one place: if the URL changes, you change it here only.

import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

// @Injectable marks this class as something Angular can inject into components.
// providedIn: 'root' means one shared instance exists for the whole app —
// every component that asks for GachaService gets the SAME instance.
@Injectable({
  providedIn: 'root'
})
export class GachaService {

  // Base URL for all API calls.
  // /api/ routes through Nginx to your PHP container.
  // No need for http://localhost — Nginx handles routing internally.
  private apiUrl = '/api';

  // HttpClient is Angular's built-in HTTP library.
  // We "inject" it via the constructor — Angular provides it automatically.
  // Think of it like PHP's PDO — you don't create it manually, you receive it.
  constructor(private http: HttpClient) {}

  // ── pull() — POST /api/pull.php ──────────────────────────────
  // Called when the user clicks "Pull x1".
  // Returns an Observable — the result arrives asynchronously.
  // Observable<any> means "will eventually return some data" —
  // the "any" type means we're not strictly defining the shape right now.
  pull(): Observable<any> {
    // http.post(url, body) sends a POST request.
    // We send an empty object {} as the body — pull.php doesn't need
    // any input from Angular, it uses user_id = 1 internally.
    return this.http.post(`${this.apiUrl}/pull.php`, {});
  }

  // ── getHistory() — GET /api/history.php ─────────────────────
  // Called after each pull and on page load to refresh history.
  getHistory(): Observable<any> {
    return this.http.get(`${this.apiUrl}/history.php`);
  }

  // ── getStats() — GET /api/stats.php ─────────────────────────
  // Called on page load to get the initial pity counts
  // so the bars show the correct state before the first pull.
  getStats(): Observable<any> {
    return this.http.get(`${this.apiUrl}/stats.php`);
  }
}
