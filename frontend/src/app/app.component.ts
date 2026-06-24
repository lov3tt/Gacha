// frontend/src/app/app.component.ts — root component.
// This is the top-level component that holds everything together.
// It owns the shared state (pity data, history) and passes it
// DOWN to child components via @Input() properties.

import { Component, OnInit } from '@angular/core';
import { GachaService } from './services/gacha.service';

// @Component is the decorator that marks this class as a component.
// selector    → the HTML tag: <app-root> in index.html
// templateUrl → the HTML template file for this component
// styleUrls   → CSS scoped to this component only
@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrls: ['./app.component.css']
})
export class AppComponent implements OnInit {

  // Shared state — owned by AppComponent, passed down to children.
  // When these change, Angular automatically re-renders any component
  // that receives them as @Input() — no manual DOM manipulation needed.
  pityData: any = null;    // current pity counts + rates for PityBarComponent
  history: any = null;     // pull history for HistoryComponent

  // GachaService is injected automatically by Angular.
  // We don't write "new GachaService()" — Angular creates it and passes it in.
  constructor(private gachaService: GachaService) {}

  // ngOnInit runs once when the component first loads — like a constructor
  // but specifically for setup that needs the component to be ready.
  // Here we load the initial pity state and history from the API.
  ngOnInit() {
    this.loadStats();
    this.loadHistory();
  }

  // ── loadStats() ───────────────────────────────────────────────
  // Fetches pity counts from PHP so pity bars show correctly on load.
  loadStats() {
    this.gachaService.getStats().subscribe({
      next: (data) => {
        // data is the parsed JSON from stats.php
        // e.g. { pity: { count_5star: 23, count_4star: 7, ... } }
        this.pityData = data.pity;
      },
      error: (err) => console.error('Failed to load stats:', err)
    });
  }

  // ── loadHistory() ─────────────────────────────────────────────
  // Fetches pull history from PHP for the history table.
  loadHistory() {
    this.gachaService.getHistory().subscribe({
      next: (data) => {
        this.history = data;
      },
      error: (err) => console.error('Failed to load history:', err)
    });
  }

  // ── onPullComplete() ──────────────────────────────────────────
  // Called by PullComponent (child) after a successful pull.
  // Instead of reloading history from the API (which causes flicker),
  // we update the history arrays directly in memory — instant, no reload.
  onPullComplete(result: any) {
    // Update pity bars immediately from the pull result
    this.pityData = result.pity;

    // Build a new history entry from the pull result
    // so we don't need a second API call to history.php
    const newEntry = {
      name:      result.item.name,
      rarity:    result.item.rarity,
      pulled_at: new Date().toISOString().replace('T', ' ').substring(0, 19)
    };

    if (this.history) {
      // Prepend the new pull to the front of both lists
      // (most recent first, matching ORDER BY pulled_at DESC in PHP)
      const newRecent = [newEntry, ...this.history.recent].slice(0, 10);
      const newFull   = [newEntry, ...this.history.full].slice(0, 100);

      // Recount the summary totals
      const count5 = newFull.filter((r: any) => r.rarity == 5).length;
      const count4 = newFull.filter((r: any) => r.rarity == 4).length;
      const count3 = newFull.filter((r: any) => r.rarity == 3).length;

      // Spread operator creates a NEW object so Angular detects the change
      // and re-renders — mutating the existing object directly wouldn't
      // trigger Angular's change detection.
      this.history = {
        recent:  newRecent,
        full:    newFull,
        summary: { '5star': count5, '4star': count4, '3star': count3, total: newFull.length }
      };
    }
  }
}